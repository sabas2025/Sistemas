<?php
declare(strict_types=1);
require __DIR__.'/_helpers.php';
$checks=[];

/**
 * Auditoria de capacidade 2026-09-14 — meta declarada: 100 clientes, 500 pedidos por minuto.
 *
 * O achado C-01 é o tipo de defeito que só aparece quando alguém escreve o número alvo: nada no
 * código estava "errado" isoladamente, mas a composição recusaria a integração que o Hub existe
 * para servir. As asserções abaixo fixam a meta no código, para que uma mudança futura de limite
 * ou de classificação de rota falhe aqui em vez de falhar em produção às 500 req/min.
 */

const META_PEDIDOS_POR_MINUTO = 500;

function hub_exec(string $rel): string {
    $fonte = hub_read($rel); if ($fonte === '') return '';
    $out = '';
    foreach (token_get_all($fonte) as $t) {
        if (is_array($t) && in_array($t[0], [T_COMMENT, T_DOC_COMMENT], true)) continue;
        $out .= is_array($t) ? $t[1] : $t;
    }
    return $out;
}

require_once hub_root().'/app/Services/AtomicRateCounterService.php';
require_once hub_root().'/app/Services/SecurityHealthService.php';
require_once hub_root().'/app/Services/RateLimitService.php';
require_once hub_root().'/app/Services/RouteCatalogService.php';

// ---------------------------------------------------------------- C-01
$politicas = RateLimitService::policies();
hub_check($checks,'Existe superfície própria para webhook de entrada (C-01)',
    isset($politicas['webhook_inbound']));
hub_check($checks,'Orçamento do webhook comporta a meta de '.META_PEDIDOS_POR_MINUTO.' pedidos/min com folga',
    (int)($politicas['webhook_inbound']['default'] ?? 0) >= META_PEDIDOS_POR_MINUTO * 2);
hub_check($checks,'Orçamento do webhook é configurável',
    ($politicas['webhook_inbound']['config'] ?? null) === 'webhook_route_rate_limit_per_minute');
hub_check($checks,'Config de exemplo e instalador declaram a chave',
    str_contains(hub_read('config/config.example.php'),'webhook_route_rate_limit_per_minute')
    && str_contains(hub_read('public/install.php'),'webhook_route_rate_limit_per_minute'));

$limitador = hub_exec('app/Services/RouteRateLimiterService.php');
hub_check($checks,'Webhook de entrada é desviado do contador em banco antes de tocá-lo',
    str_contains($limitador,'RouteCatalogService::isInboundWebhook($rotaAtual)')
    && str_contains($limitador,'enforceWebhookInbound'));
// Ordem de EXECUÇÃO dentro de enforce(), não ordem de declaração no arquivo: o desvio precisa
// acontecer antes de ensureSchema(), senão o webhook ainda paga a verificação de schema e o
// caminho do balde em banco que a correção existe para evitar.
$corpoEnforce = '';
if (preg_match('/function\s+enforce\s*\([^)]*\)\s*:\s*void\s*\{(.*?)\n  \}/s', $limitador, $mEnf)) $corpoEnforce = $mEnf[1];
$posDesvio = strpos($corpoEnforce, 'enforceWebhookInbound');
$posSchema = strpos($corpoEnforce, 'ensureSchema');
hub_check($checks,'Dentro de enforce(), o desvio do webhook vem ANTES de ensureSchema/balde em banco',
    $corpoEnforce !== '' && $posDesvio !== false && $posSchema !== false && $posDesvio < $posSchema);

// A regressão concreta: os dez webhooks reais não podem cair no orçamento de painel.
$semOrcamento = [];
foreach (RouteCatalogService::inboundWebhooks() as $rota) {
    if (!RouteCatalogService::isInboundWebhook($rota)) $semOrcamento[] = $rota;
}
hub_check($checks,'Os 10 webhooks de entrada são reconhecidos pelo catálogo: '.(implode(', ',$semOrcamento) ?: 'todos'),
    $semOrcamento === [] && count(RouteCatalogService::inboundWebhooks()) === 10);
hub_check($checks,'Rota de painel api/* NÃO herda o orçamento de webhook',
    !RouteCatalogService::isInboundWebhook('api/processar-fila'));

// Simulação do volume alvo no contador atômico da superfície.
$chave = 'meta-'.bin2hex(random_bytes(5));
$dirContadores = hub_root().'/storage/cache/security';
$preexistente = is_dir($dirContadores);
$antes = $preexistente ? (glob($dirContadores.'/*') ?: []) : [];
$bloqueado = false;
for ($i = 0; $i < META_PEDIDOS_POR_MINUTO; $i++) {
    if (RateLimitService::hit('webhook_inbound', $chave)['limited']) { $bloqueado = true; break; }
}
hub_check($checks,'500 pedidos no mesmo minuto NÃO são bloqueados pela superfície de webhook', !$bloqueado);
foreach (glob($dirContadores.'/*') ?: [] as $f) { if (!in_array($f,$antes,true)) @unlink($f); }
if (!$preexistente) @rmdir($dirContadores);

// ---------------------------------------------------------------- C-03
$schema = hub_read('database/install_final_current.sql');
preg_match('/CREATE TABLE(?: IF NOT EXISTS)? `?pedidos_integracao`?\s*\((.*?)\n\)\s*ENGINE/s', $schema, $m);
$corpo = $m[1] ?? '';
hub_check($checks,'pedidos_integracao tem índice em empresa_id (C-03)',
    str_contains($corpo,'idx_pedidos_integracao_empresa'));
hub_check($checks,'pedidos_integracao tem índices compostos para os filtros reais das telas',
    str_contains($corpo,'idx_pedidos_empresa_status') && str_contains($corpo,'idx_pedidos_empresa_data'));

// Guarda geral: nenhuma tabela com escopo pode ficar sem índice em empresa_id.
require_once hub_root().'/app/Services/TenantScopeService.php';
$semIndice = [];
foreach (TenantScopeService::scopedTables() as $t) {
    if (!preg_match('/CREATE TABLE(?: IF NOT EXISTS)? `?'.preg_quote($t,'/').'`?\s*\((.*?)\n\)\s*ENGINE/s', $schema, $mm)) continue;
    if (!preg_match('/\bempresa_id\b/i', $mm[1])) continue;
    $idx = [];
    preg_match_all('/(?:UNIQUE KEY|INDEX|KEY)\s+\w+\s*\(([^)]*)\)/i', $mm[1], $ix);
    foreach ($ix[1] as $cols) { $idx[] = trim(explode(',', $cols)[0], " `"); }
    if (!in_array('empresa_id', $idx, true)) $semIndice[] = $t;
}
hub_check($checks,'Toda tabela com escopo tem índice em empresa_id: '.(implode(', ',$semIndice) ?: 'nenhuma pendente'),
    $semIndice === []);

hub_check($checks,'Migration de índices de capacidade existe e é idempotente',
    str_contains(hub_read('database/migrations/20260914_011_indices_capacidade.sql'),'information_schema.STATISTICS'));

// ---------------------------------------------------------------- C-02 (PK BIGINT)
$schemaTxt = hub_read('database/install_final_current.sql');
$aindaInt = [];
foreach (['fila_integracao','pedidos_integracao','pedidos_hub','logs_integracao'] as $t) {
    if (!preg_match('/CREATE TABLE(?: IF NOT EXISTS)? `?'.preg_quote($t,'/').'`?\s*\((.*?)\n\)\s*ENGINE/s', $schemaTxt, $mm)) continue;
    foreach (explode("\n", $mm[1]) as $l) {
        if (preg_match('/^\s*id\s+INT\b/i', $l)) $aindaInt[] = $t;
    }
}
hub_check($checks,'Chaves de alto volume são BIGINT, não INT (C-02): '.(implode(', ',$aindaInt) ?: 'todas migradas'),
    $aindaInt === []);
$refsInt = preg_match_all('/\b(?:fila_id|pedido_hub_id)\s+INT\b/i', $schemaTxt);
hub_check($checks,'Colunas que referenciam essas chaves acompanharam o tipo (C-02)', $refsInt === 0);
hub_check($checks,'Migration de PK avisa sobre bloqueio e janela de manutenção',
    str_contains(hub_read('database/migrations/20260914_012_pk_bigint_capacidade.sql'),'LEIA ANTES DE EXECUTAR'));

// ---------------------------------------------------------------- C-04 (expurgo fora do caminho quente)
$limitadorExec = hub_exec('app/Services/RouteRateLimiterService.php');
hub_check($checks,'Expurgo saiu do caminho da requisição (C-04)',
    !str_contains($limitadorExec,'DataRetentionService::maybeRun')
    && !str_contains($limitadorExec,'DELETE FROM rate_limit_hits'));
hub_check($checks,'Worker de retenção existe e é CLI-only com guard',
    is_file(hub_root().'/workers/worker_retencao.php')
    && str_contains(hub_read('workers/worker_retencao.php'),'WorkerCliGuardService::enforce')
    && str_contains(hub_read('workers/worker_retencao.php'),"PHP_SAPI !== 'cli'"));

// ---------------------------------------------------------------- C-06 (retenção da fila)
$retencao = hub_exec('app/Services/DataRetentionService.php');
hub_check($checks,'Fila tem retenção, apenas em estado TERMINAL (C-06)',
    str_contains($retencao,'cleanupFilaConcluida')
    && str_contains($retencao,"'concluido','processado','sucesso'"));
hub_check($checks,'Retenção da fila é configurável', str_contains($retencao,'queue_done_retention_days'));

// ---------------------------------------------------------------- C-07 (sessão compartilhada)
hub_check($checks,'Handler de sessão em banco existe e implementa a interface do PHP',
    is_file(hub_root().'/app/Services/DatabaseSessionHandler.php')
    && str_contains(hub_read('app/Services/DatabaseSessionHandler.php'),'implements SessionHandlerInterface'));
hub_check($checks,'Sessão em banco é OPT-IN; o padrão continua arquivo',
    str_contains(hub_read('config/config.example.php'),"'session_driver' => 'file'"));
hub_check($checks,'Autoloader sobe antes de session_start para permitir o handler',
    strpos(hub_read('public/index.php'),'DatabaseSessionHandler::registerIfEnabled') < strpos(hub_read('public/index.php'),'if (!session_start())'));
hub_check($checks,'Falha do handler não derruba o bootstrap (cai para arquivo)',
    str_contains(hub_read('public/index.php'),'usando arquivo'));

// ---------------------------------------------------------------- C-08 (cache do bloqueio de IP)
$ipBlock = hub_exec('app/Services/IpBlockService.php');
hub_check($checks,'Consulta de IP bloqueado tem cache do resultado negativo (C-08)',
    str_contains($ipBlock,'negativoEmCache') && str_contains($ipBlock,'marcarNegativo'));
hub_check($checks,'Bloquear um IP invalida o cache na hora',
    str_contains($ipBlock,'self::limparCache($ip)'));

// ---------------------------------------------------------------- D-01 / D-02
// Achados da validação independente, na própria correção C-07.
//
// D-01: DatabaseSessionHandler::gc() só é chamado pelo PHP de forma PROBABILÍSTICA, e
// session.gc_probability=0 é padrão de fábrica em Debian/Ubuntu (que limpam sessões de ARQUIVO
// por cron — cron que não sabe nada de tabela). Sem expurgo próprio, ligar session_driver
// ='database' faria a tabela crescer para sempre.
$retencao2 = hub_exec('app/Services/DataRetentionService.php');
hub_check($checks,'Tabela de sessão tem expurgo próprio, independente do gc do PHP (D-01)',
    str_contains($retencao2,'cleanupSessoes') && str_contains($retencao2,'ultimo_acesso'));
hub_check($checks,'Expurgo de sessão é chamado pelo cleanup() que o worker executa',
    preg_match('/cleanup\(\)[\s\S]{0,800}cleanupSessoes\(\)/', $retencao2) === 1);
hub_check($checks,'Expurgo de sessão respeita o timeout absoluto configurado',
    str_contains($retencao2,'session_absolute_timeout_seconds'));

// D-02: o handler reportava falha de sessão no controle 'event_log', que descreve a tabela
// security_events — indicador apontando para o lugar errado, mesma família do F-01/F-02.
$saude = hub_exec('app/Services/SecurityHealthService.php');
hub_check($checks,'Existe controle de saúde próprio para o armazenamento de sessão (D-02)',
    str_contains($saude,"'session_store'"));
hub_check($checks,'Handler de sessão usa o controle correto, não o de eventos de segurança',
    str_contains(hub_exec('app/Services/DatabaseSessionHandler.php'),"degrade('session_store'")
    && !str_contains(hub_exec('app/Services/DatabaseSessionHandler.php'),"degrade('event_log'"));

hub_finish($checks);
