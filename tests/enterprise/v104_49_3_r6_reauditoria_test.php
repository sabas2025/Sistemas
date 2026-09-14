<?php
declare(strict_types=1);
require __DIR__.'/_helpers.php';
$checks=[];

/**
 * Reauditoria 2026-09-14 (R6): regressão das correções que ainda não tinham teste próprio.
 *
 * O achado A-01 desta mesma reauditoria foi justamente que a suíte abortava no primeiro teste
 * com falha e mascarava dezenas de outros - ou seja, o gate existia mas não provava nada. Entregar
 * correções de segurança sem regressão automatizada repetiria o mesmo erro em outra forma, então
 * cada correção abaixo passa a ter uma asserção que falha se o defeito voltar.
 *
 * Onde o comportamento depende de banco ou de sessão HTTP, a asserção é estrutural (sobre o código
 * executável, com comentários removidos) em vez de fingir um teste de integração que não roda aqui.
 */

/** Código executável de um arquivo, sem comentários - evita casar com a documentação do próprio fix. */
function hub_codigo_sem_comentarios(string $relativo): string {
    $fonte = hub_read($relativo);
    if ($fonte === '') return '';
    $saida = '';
    foreach (token_get_all($fonte) as $token) {
        if (is_array($token) && in_array($token[0], [T_COMMENT, T_DOC_COMMENT], true)) continue;
        $saida .= is_array($token) ? $token[1] : $token;
    }
    return $saida;
}

// =====================================================================================
// A-06 / A-07 / B-01 - contador de janela deslizante atômico
// =====================================================================================
$dirContadores = hub_root().'/storage/cache/security';
$dirPreexistente = is_dir($dirContadores);
$arquivosAntes = $dirPreexistente ? (glob($dirContadores.'/*') ?: []) : [];

require_once hub_root().'/app/Services/AtomicRateCounterService.php';
$chave = 'regressao-r6-'.bin2hex(random_bytes(6));
$estados = [];
for ($i = 0; $i < 4; $i++) $estados[] = AtomicRateCounterService::hit($chave, 60, 3);

hub_check($checks,'Contador incrementa a cada chamada (leitura e escrita sob o mesmo lock)',
    array_column($estados,'count') === [1,2,3,4]);
hub_check($checks,'Contador não bloqueia dentro do limite', $estados[2]['limited'] === false);
hub_check($checks,'Contador bloqueia ao ultrapassar o limite', $estados[3]['limited'] === true);
hub_check($checks,'Armazenamento saudável não é reportado como degradado',
    array_column($estados,'degraded') === [false,false,false,false]);

// Janela curta: eventos antigos saem da contagem em vez de acumular para sempre.
$chaveJanela = 'regressao-r6-janela-'.bin2hex(random_bytes(6));
AtomicRateCounterService::hit($chaveJanela, 1, 5);
sleep(2);
$aposJanela = AtomicRateCounterService::hit($chaveJanela, 1, 5);
hub_check($checks,'Eventos fora da janela deslizante são descartados', $aposJanela['count'] === 1);

// B-01: falha REAL de armazenamento precisa ser explícita, não silenciosamente permissiva.
// Testar com chmod não serve: a suíte costuma rodar como root, que ignora a permissão. Criar um
// DIRETÓRIO exatamente onde o contador espera um arquivo faz fopen('c+') falhar de verdade,
// em qualquer usuário.
$refPath = new ReflectionMethod('AtomicRateCounterService','path');
$refPath->setAccessible(true);
$chaveQuebrada = 'regressao-r6-degradado-'.bin2hex(random_bytes(6));
$arquivoQuebrado = (string)$refPath->invoke(null, $chaveQuebrada);
$degradado = null;
if (@mkdir($arquivoQuebrado, 0700)) {
    $degradado = AtomicRateCounterService::hit($chaveQuebrada, 60, 3);
    @rmdir($arquivoQuebrado);
}
hub_check($checks,'Armazenamento inutilizável é reportado como degraded=true (B-01)',
    is_array($degradado) && $degradado['degraded'] === true);
hub_check($checks,'Retorno degradado não finge que o limite foi avaliado',
    is_array($degradado) && $degradado['limited'] === false && $degradado['count'] === 0);
hub_check($checks,'hit() devolve a chave "degraded" em todo retorno (contrato do B-01)',
    array_key_exists('degraded', $estados[0]) && array_key_exists('degraded', $aposJanela));
$codigoContador = hub_codigo_sem_comentarios('app/Services/AtomicRateCounterService.php');
hub_check($checks,'Toda saída de falha de armazenamento marca degraded=true (nunca limited=false silencioso)',
    substr_count($codigoContador, 'return $degraded;') === 3
    && str_contains($codigoContador, "'degraded' => true"));

// B-01: cada chamador declara a política explicitamente.
$codigoTinyWebhook = hub_codigo_sem_comentarios('app/Services/TinyWebhookSecurityService.php');
hub_check($checks,'Webhook Tiny falha FECHADO quando o contador está degradado',
    str_contains($codigoTinyWebhook, "\$tentativas['degraded']") && str_contains($codigoTinyWebhook, 'TINY_WEBHOOK_RATE_COUNTER_DEGRADED'));
$codigoTelemetria = hub_codigo_sem_comentarios('public/pwa_telemetry.php');
hub_check($checks,'Telemetria PWA falha FECHADA (503) quando o contador está degradado',
    str_contains($codigoTelemetria, "\$rateState['degraded']") && str_contains($codigoTelemetria, 'http_response_code(503)'));

// Melhoria 7: as políticas passaram a ser declaradas em UM lugar, e superfície não declarada é
// recusada em tempo de chamada - para que o próximo limitador não nasça fail-open como o A-06.
require_once hub_root().'/app/Services/AtomicRateCounterService.php';
require_once hub_root().'/app/Services/SecurityHealthService.php';
require_once hub_root().'/app/Services/RateLimitService.php';
$politicas = RateLimitService::policies();
hub_check($checks,'Toda superfície declara explicitamente o comportamento em falha de armazenamento',
    count($politicas) >= 8 && count(array_filter($politicas, static fn(array $p): bool =>
        in_array($p['on_storage_failure'] ?? '', [RateLimitService::FAIL_OPEN, RateLimitService::FAIL_CLOSED], true))) === count($politicas));
hub_check($checks,'Webhook Tiny e telemetria PWA são política FECHADA',
    $politicas['tiny_webhook']['on_storage_failure'] === RateLimitService::FAIL_CLOSED
    && $politicas['pwa_telemetry']['on_storage_failure'] === RateLimitService::FAIL_CLOSED);
hub_check($checks,'Rotas do painel são a única política ABERTA, e deliberada',
    $politicas['route']['on_storage_failure'] === RateLimitService::FAIL_OPEN
    && $politicas['route_sensitive']['on_storage_failure'] === RateLimitService::FAIL_OPEN);
$recusou = false;
try { RateLimitService::hit('superficie-inventada', 'x'); } catch (Throwable $e) { $recusou = true; }
hub_check($checks,'Superfície de rate limit não declarada é recusada em tempo de chamada', $recusou);
$codigoRota = hub_codigo_sem_comentarios('app/Services/RouteRateLimiterService.php');
// Melhoria 6: a degradação saiu de $GLOBALS['HUB_SECURITY_DEGRADED'] (que morria no fim da
// requisição) para SecurityHealthService, que persiste e aparece no painel e em api/status.
hub_check($checks,'Limitador de rotas registra a degradação em serviço consultável, não em variável global',
    str_contains($codigoRota, 'SecurityHealthService::degrade') && !str_contains($codigoRota, 'HUB_SECURITY_DEGRADED'));
hub_check($checks,'Nenhum controle usa mais a variável global de degradação',
    !str_contains(hub_codigo_sem_comentarios('app/Services/SecurityEventService.php'), 'HUB_SECURITY_DEGRADED')
    && !str_contains(hub_codigo_sem_comentarios('app/Services/IpBlockService.php'), 'HUB_SECURITY_DEGRADED'));
hub_check($checks,'Limitador de rotas declara a política na fachada única (melhoria 7)',
    str_contains($codigoRota, "RateLimitService::hit("));

// A-09: a contagem de tentativas do webhook Tiny precisa vir ANTES da autenticação.
// Melhoria 7: a contagem passou a ser feita pela fachada, com a superfície 'tiny_webhook'.
$posTentativa = strpos($codigoTinyWebhook, "RateLimitService::hit('tiny_webhook'");
$posSecret = strpos($codigoTinyWebhook, 'hash_equals');
hub_check($checks,'Tentativas de webhook Tiny são contadas antes da verificação do segredo (A-09)',
    $posTentativa !== false && ($posSecret === false || $posTentativa < $posSecret));

// A-07: Sec-Fetch-Site ausente não pode ser tratado como same-origin.
hub_check($checks,'Telemetria PWA trata Sec-Fetch-Site ausente como não confiável (A-07)',
    str_contains($codigoTelemetria, 'HTTP_SEC_FETCH_SITE')
    && str_contains($codigoTelemetria, "in_array(\$fetchSite, ['same-origin', 'same-site'], true)"));
hub_check($checks,'Telemetria PWA tem cota de tamanho e retenção do arquivo JSONL (A-07)',
    str_contains($codigoTelemetria, 'PWA_TELEMETRY_MAX_BYTES') && str_contains($codigoTelemetria, 'PWA_TELEMETRY_RETENTION_MONTHS'));

// Limpeza: remove apenas o que este teste criou.
foreach (glob($dirContadores.'/*') ?: [] as $arquivo) {
    if (!in_array($arquivo, $arquivosAntes, true)) { is_dir($arquivo) ? @rmdir($arquivo) : @unlink($arquivo); }
}
if (!$dirPreexistente) @rmdir($dirContadores);

// =====================================================================================
// A-08 - assinatura de webhook cobrindo método e rota
// =====================================================================================
$codigoWebhook = hub_codigo_sem_comentarios('app/Services/WebhookSecurityService.php');
hub_check($checks,'Assinatura v2 canoniza versão, método, rota, timestamp, nonce e hash do corpo',
    str_contains($codigoWebhook, "'v2:'.\$metodo.':'.\$rota.':'.\$timestamp.':'.\$nonce.':'.\$payloadHash"));
hub_check($checks,'v1 legado só é aceito enquanto webhook_signature_require_v2 estiver desligado',
    str_contains($codigoWebhook, 'webhook_signature_require_v2') && str_contains($codigoWebhook, '!$requireV2 &&'));
hub_check($checks,'Guarda de replay passa a considerar método e rota, não só o corpo',
    str_contains($codigoWebhook, "\$metodo.':'.\$rota.':'.\$raw"));
require_once hub_root().'/app/Services/WebhookSecurityService.php';
$refCanonical = new ReflectionMethod('WebhookSecurityService','canonicalPath');
$refCanonical->setAccessible(true);
$getOriginal = $_GET;
$_GET['page'] = 'api/webhook/vsm/pedido';
$rotaCanonica = (string)$refCanonical->invoke(null);
$_GET = $getOriginal;
hub_check($checks,'Caminho canônico usa a rota lógica (?page=) e não o script de entrada',
    $rotaCanonica === '/api/webhook/vsm/pedido');

// =====================================================================================
// A-02 / B-03 - callback OAuth fora do gate de sessão, mas autorizado
// =====================================================================================
$codigoDispatcher = hub_codigo_sem_comentarios('app/Services/FastRouteDispatcherService.php');
$posAllowlist = strpos($codigoDispatcher, 'AdminIpAllowlistService::enforceForRequest');
$posCallback  = strpos($codigoDispatcher, "\$page === 'tiny-v3-callback'");
$posLogin     = strpos($codigoDispatcher, "\$page === 'login'");
hub_check($checks,'Allowlist de IP é aplicada antes de login/logout (A-05)',
    $posAllowlist !== false && $posLogin !== false && $posAllowlist < $posLogin);
hub_check($checks,'Callback OAuth é despachado fora do gate de sessão, mas depois da allowlist (A-02)',
    $posCallback !== false && $posAllowlist < $posCallback && $posCallback < $posLogin);

$codigoDashboard = hub_codigo_sem_comentarios('app/Controllers/DashboardController.php');
hub_check($checks,'Callback OAuth revalida o usuário no banco em vez de confiar no instantâneo do state (B-03)',
    str_contains($codigoDashboard, "AuthRepository::userById((int)\$oauthTransaction['user_id'])"));
hub_check($checks,'Callback OAuth autoriza pelo perfil ATUAL, lido do banco (B-03)',
    str_contains($codigoDashboard, "\$oauthTransaction['perfil'] = (string)(\$usuarioAtual['perfil'] ?? '')"));
hub_check($checks,'Callback OAuth usa canForProfile (sem depender da sessão cross-site)',
    str_contains($codigoDashboard, "PermissionService::canForProfile(\$oauthTransaction['perfil'], 'configuracoes', 'editar')"));
$codigoOAuthState = hub_codigo_sem_comentarios('app/Services/OAuthStateService.php');
hub_check($checks,'State OAuth assinado carrega o usuário iniciador e recusa transação sem vínculo',
    str_contains($codigoOAuthState, "'user_id' =>") && str_contains($codigoOAuthState, '$userId <= 0'));

// =====================================================================================
// A-12 / A-13 - superfície removida e 404 real
// =====================================================================================
$codigoHeavy = hub_codigo_sem_comentarios('app/Services/HeavyQueryOptimizerService.php');
hub_check($checks,'selectLight() foi removido do código executável (A-12)',
    !str_contains($codigoHeavy, 'function selectLight'));
hub_check($checks,'Rota desconhecida devolve 404 controlado em vez de cair no dashboard (A-13)',
    str_contains($codigoDashboard, 'default: $this->naoEncontrado($page);')
    && str_contains($codigoDashboard, 'http_response_code(404)'));
// Nada da requisição pode ser refletido na página de erro. O casamento usa fronteira de palavra
// para não confundir $page com $pageTitle, que é um rótulo fixo e legítimo.
hub_check($checks,'Página 404 existe e não reflete rota, query nem URI da requisição',
    is_file(hub_root().'/views/nao_encontrado.php')
    && !preg_match('/\\$page\\b|\\$_GET|\\$_REQUEST|\\$_SERVER|REQUEST_URI/', hub_read('views/nao_encontrado.php')));

// =====================================================================================
// A-04 - redirecionamento HTTPS não decidido por cabeçalho do cliente
// =====================================================================================
$htaccess = hub_read('public/.htaccess');
hub_check($checks,'.htaccess não redireciona HTTPS com base em cabeçalho enviado pelo cliente (A-04)',
    !preg_match('/RewriteCond\s+%\{HTTP:X-Forwarded-Proto\}/i', $htaccess)
    && !preg_match('/RewriteRule.*https:\/\/%\{HTTP_HOST\}/i', $htaccess));
hub_check($checks,'HSTS permanece configurado no .htaccess', str_contains($htaccess, 'Strict-Transport-Security'));

// =====================================================================================
// B-05 - script destrutivo de CI não pode rodar por engano em instalação real
// =====================================================================================
$codigoProvision = hub_codigo_sem_comentarios('scripts/ci/provision-e2e-environment.php');
hub_check($checks,'Provisionamento E2E só roda em CLI', str_contains($codigoProvision, "PHP_SAPI !== 'cli'"));
hub_check($checks,'Provisionamento E2E exige confirmação explícita de ambiente descartável',
    str_contains($codigoProvision, "getenv('HUB_CI_E2E_CONFIRM') !== '1'"));
hub_check($checks,'Provisionamento E2E recusa árvore com install.lock (instalação real)',
    str_contains($codigoProvision, "storage/install.lock"));
hub_check($checks,'Provisionamento E2E recusa sobrescrever config.php que não foi gerado por ele',
    str_contains($codigoProvision, "str_contains(\$atual, 'provision-e2e-environment.php')"));
hub_check($checks,'Workflow de CI passa HUB_CI_E2E_CONFIRM ao script destrutivo',
    str_contains(hub_read('.github/workflows/hub-ci.yml'), 'HUB_CI_E2E_CONFIRM'));

// =====================================================================================
// A-01 / A-03 - o próprio gate precisa provar o que afirma
// =====================================================================================
$runner = hub_read('scripts/ci/enterprise-tests.sh');
hub_check($checks,'Suíte Enterprise executa todos os testes mesmo com falhas (A-01)',
    str_contains($runner, 'if php "$test_file"; then') && str_contains($runner, 'failed_files'));
$workflow = hub_read('.github/workflows/hub-ci.yml');
hub_check($checks,'E2E do CI provisiona ambiente próprio e define HUB_BASE_URL (A-03)',
    str_contains($workflow, 'provision-e2e-environment.php') && str_contains($workflow, 'HUB_BASE_URL: http://127.0.0.1:8000/public'));
hub_check($checks,'Teste E2E pulado reprova o gate em vez de passar em silêncio (A-03)',
    str_contains($workflow, 'stats.skipped'));
hub_check($checks,'Playwright está com versão travada e lockfile presente (A-03)',
    !str_contains(hub_read('tests/e2e/package.json'), '"latest"') && is_file(hub_root().'/tests/e2e/package-lock.json'));
hub_check($checks,'playwright.config.js não tem fallback para host remoto de produção (A-03)',
    !preg_match('/https?:\/\/(?!127\.0\.0\.1|localhost)[a-z0-9.-]+/i', hub_read('tests/e2e/playwright.config.js')));

hub_finish($checks);
