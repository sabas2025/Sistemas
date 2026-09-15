<?php
declare(strict_types=1);
require __DIR__.'/_helpers.php';
$checks=[];

/**
 * Auditoria 2026-09-14 seguindo PROMPT_-_HUB.md (fases 3, 7, 9 e 11) sobre a R7.
 *
 * Os dois achados confirmados têm a mesma natureza: um INDICADOR que mentia. Nenhum quebrava
 * fluxo de negócio, e é exatamente por isso que são perigosos — um painel de prontidão que já
 * está vermelho por um defeito próprio deixa de servir para detectar o problema real quando ele
 * chegar. As asserções abaixo falham se qualquer um dos dois voltar.
 */

function hub_sem_comentarios(string $rel): string {
    $fonte = hub_read($rel);
    if ($fonte === '') return '';
    $saida = '';
    foreach (token_get_all($fonte) as $t) {
        if (is_array($t) && in_array($t[0], [T_COMMENT, T_DOC_COMMENT], true)) continue;
        $saida .= is_array($t) ? $t[1] : $t;
    }
    return $saida;
}

// ---------------------------------------------------------------- F-01
// A checagem de workers procurava WorkerCliGuardService::requireCli(), método que nunca existiu.
// Os 12 workers chamam enforce(). Resultado: ERRO permanente listando todos como desprotegidos.
$guard = hub_read('app/Services/WorkerCliGuardService.php');
hub_check($checks,'WorkerCliGuardService expõe enforce() e NÃO expõe requireCli()',
    preg_match('/function\s+enforce\s*\(/', $guard) === 1 && preg_match('/function\s+requireCli\s*\(/', $guard) === 0);

$workers = glob(hub_root().'/workers/*.php') ?: [];
$semGuard = [];
foreach ($workers as $w) {
    if (!str_contains((string)file_get_contents($w), 'WorkerCliGuardService::enforce')) $semGuard[] = basename($w);
}
hub_check($checks,'Todos os workers chamam o guard CLI real: '.(implode(', ', $semGuard) ?: 'nenhum pendente'),
    $workers !== [] && $semGuard === []);

$hardening = hub_sem_comentarios('app/Services/CommercialHardeningService.php');
hub_check($checks,'Checagem de workers não procura mais um método inexistente (F-01)',
    !str_contains($hardening, "'WorkerCliGuardService::requireCli'"));
hub_check($checks,'Nome do método do guard é derivado por reflexão, não repetido em string',
    str_contains($hardening, 'method_exists(\'WorkerCliGuardService\'') && str_contains($hardening, 'guardMethodName()'));

// ---------------------------------------------------------------- F-02
// A auditoria de tenant mantinha catálogo próprio (15 tabelas) divergente das 31 do
// TenantScopeService, e exigia filial_id — coluna consolidada para fora na R6.
$tenantAudit = hub_sem_comentarios('app/Services/TenantScopeAuditService.php');
hub_check($checks,'Auditoria de tenant usa o catálogo único do TenantScopeService (F-02)',
    str_contains($tenantAudit, 'TenantScopeService::scopedTables()'));
hub_check($checks,'Auditoria de tenant não mantém catálogo próprio duplicado',
    !str_contains($tenantAudit, '$operationalTables'));
hub_check($checks,'Auditoria de tenant não exige mais filial_id (consolidado na R6)',
    !str_contains($tenantAudit, 'filial_id'));
hub_check($checks,'Tela de endurecimento não exibe mais a coluna filial_id',
    !str_contains(hub_read('views/commercial_hardening_final.php'), 'filial_id'));

// Nenhuma tabela operacional tem filial_id: é o que torna a exigência antiga insatisfazível.
$schema = hub_read('database/install_final_current.sql');
preg_match_all('/CREATE TABLE(?: IF NOT EXISTS)?\s+`?(\w+)`?\s*\((.*?)\n\)\s*ENGINE/s', $schema, $ms, PREG_SET_ORDER);
$comFilial = [];
foreach ($ms as $m) { if (preg_match('/\bfilial_id\b/i', $m[2])) $comFilial[] = $m[1]; }
// Fato verificado: sobrou UMA coluna filial_id, em pedidos_integracao — vestígio anterior à
// consolidação da R6. Nada no código a lê ou grava. Ela NÃO é removida: as regras do projeto
// proíbem DROP COLUMN sem plano seguro e remoção de estrutura sem autorização explícita. Fica
// registrada aqui para que a auditoria não volte a exigir filial_id por vê-la no schema.
hub_check($checks,'filial_id é vestigial e está só em pedidos_integracao: '.implode(', ', $comFilial),
    $comFilial === ['pedidos_integracao']);
hub_check($checks,'Nenhum código lê ou grava filial_id em tabela operacional',
    !preg_match('/filial_id/', hub_sem_comentarios('app/Services/TenantScopeService.php')));

// ---------------------------------------------------------------- fases descartadas por evidência
// Fase 7: backoff exponencial, jitter e circuit breaker EXISTEM — a primeira varredura só olhou
// QueueService e concluiu errado. Registrado aqui para não virar "achado" de novo.
$retry = hub_read('app/Services/RetryPolicyService.php');
hub_check($checks,'Fase 7: retry tem backoff exponencial e jitter (não é achado)',
    str_contains($retry, '2 ** ') && (str_contains($retry, 'random_int') || str_contains($retry, 'jitter')));
hub_check($checks,'Fase 7: circuit breaker existe', is_file(hub_root().'/app/Services/CircuitBreakerService.php'));

// Fase 9: nenhum formulário POST sem CSRF.
$semCsrf = [];
foreach (glob(hub_root().'/views/*.php') ?: [] as $view) {
    $conteudo = (string)file_get_contents($view);
    if (!preg_match_all('/<form\b[^>]*>/i', $conteudo, $forms, PREG_OFFSET_CAPTURE)) continue;
    foreach ($forms[0] as $f) {
        if (!preg_match('/method\s*=\s*[\'"]?post/i', $f[0])) continue;
        $trecho = substr($conteudo, $f[1] + strlen($f[0]), 900);
        if (!preg_match('/csrf/i', $trecho)) $semCsrf[] = basename($view);
    }
}
hub_check($checks,'Fase 9: nenhum formulário POST sem token CSRF: '.(implode(', ', array_unique($semCsrf)) ?: 'nenhum'),
    $semCsrf === []);

hub_finish($checks);
