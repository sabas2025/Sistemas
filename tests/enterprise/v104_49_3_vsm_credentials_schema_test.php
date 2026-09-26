<?php
declare(strict_types=1);
require __DIR__.'/_helpers.php';
$checks = [];

/**
 * F6-07 etapa 1 (2026-09-26) — schema das credenciais reais da VSM Conecta Venda.
 *
 * O contrato oficial exige clientToken/clientSecret (auth /v1/auth/token) e clientTokenLoja
 * (query do pedido). Esta suíte trava as CINCO colunas novas nos DOIS caminhos de instalação
 * (módulo + migration + consolidado — lição I-18), o plumbing de IntegrationConfig e a gravação
 * por serviço (VsmCredentialsConfigService), fora do DashboardController (teto do controller).
 *
 * Reprova sobre o código antigo: nada disto existe em main.
 */

$colunas = ['vsm_client_token','vsm_client_secret','vsm_client_token_loja','vsm_access_token','vsm_access_token_expira_em'];

// --- 1) Módulo core.sql (instalação nova) ---
$core = hub_read('database/modules/core.sql');
hub_check($checks, 'core.sql foi lido (N>0)', strlen($core) > 1000);
foreach ($colunas as $c) {
    hub_check($checks, "core.sql declara a coluna {$c}", $core !== '' && str_contains($core, $c));
}
hub_check($checks, 'core.sql: vsm_access_token_expira_em é DATETIME', str_contains($core, 'vsm_access_token_expira_em DATETIME'));

// --- 2) Consolidado gerado (paridade com os módulos) ---
$consol = hub_read('database/install_final_current.sql');
hub_check($checks, 'consolidado foi lido (N>0)', strlen($consol) > 1000);
foreach ($colunas as $c) {
    hub_check($checks, "consolidado tem a coluna {$c}", $consol !== '' && str_contains($consol, $c));
}

// --- 3) Migration idempotente (instalação existente) ---
$mig = hub_read('database/migrations/20260926_018_vsm_credentials.sql');
hub_check($checks, 'migration 018 foi lida (N>0)', strlen($mig) > 500);
foreach ($colunas as $c) {
    hub_check($checks, "migration 018 acrescenta a coluna {$c}",
        $mig !== '' && str_contains($mig, 'ADD COLUMN '.$c));
}
hub_check($checks, 'migration 018 é idempotente (guarda em information_schema.COLUMNS)',
    str_contains($mig, 'information_schema.COLUMNS') && str_contains($mig, "COLUMN_NAME = 'vsm_client_token'"));

// --- 4) IntegrationConfig: descriptografa e tem defaults ---
$ic = hub_read('app/Services/IntegrationConfig.php');
foreach (['vsm_client_token','vsm_client_secret','vsm_client_token_loja','vsm_access_token'] as $c) {
    hub_check($checks, "IntegrationConfig descriptografa {$c}",
        $ic !== '' && preg_match('/'.$c.'/', $ic) === 1);
}
hub_check($checks, 'IntegrationConfig tem default para vsm_client_token', str_contains($ic, "'vsm_client_token'=>''"));

// --- 5) Gravação por SERVIÇO, fora do controller-deus ---
$svc = hub_read('app/Services/VsmCredentialsConfigService.php');
hub_check($checks, 'VsmCredentialsConfigService existe (N>0)', strlen($svc) > 300);
hub_check($checks, 'o serviço cifra as credenciais (CryptoService::encrypt)', str_contains($svc, 'CryptoService::encrypt'));
hub_check($checks, 'o serviço grava só colunas existentes (Database::columnExists)', str_contains($svc, 'Database::columnExists'));
hub_check($checks, 'o serviço descarta o JWT em cache ao salvar credenciais', str_contains($svc, 'vsm_access_token=NULL'));
hub_check($checks, 'o serviço usa keepIfMasked (não apaga segredo mascarado)', str_contains($svc, 'Secrets::keepIfMasked'));

$dash = hub_read('app/Controllers/DashboardController.php');
hub_check($checks, 'DashboardController delega a gravação ao serviço',
    str_contains($dash, 'VsmCredentialsConfigService::salvarDoFormulario'));

// --- 6) View oferece os campos ---
$view = hub_read('views/configuracoes.php');
foreach (['vsm_client_token','vsm_client_secret','vsm_client_token_loja'] as $c) {
    hub_check($checks, "a view de Configurações tem o campo {$c}", $view !== '' && str_contains($view, 'name="'.$c.'"'));
}

hub_finish($checks);
