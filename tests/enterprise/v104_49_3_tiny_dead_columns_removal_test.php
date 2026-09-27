<?php
declare(strict_types=1);
require __DIR__.'/_helpers.php';
$checks = [];

/**
 * Fase 5 (auditoria Tiny ERP, 2026-09-27) — achados F5-01 e F5-02.
 *
 * F5-01: tiny_v3_manual_access_token / tiny_v3_manual_refresh_token eram colunas mortas em
 * configuracoes_integracao (TEXT NULL, semeadas vazias, NUNCA gravadas; só lidas como !empty()
 * em três serviços, sempre em OR com o token vivo). Removidas as leituras E as colunas (decisão de
 * produto: dropar). DROP em três caminhos no mesmo commit — módulo core.sql, consolidado e
 * migration 20260927_019 —, além de tirar do mapa de upgrade legado que as recriaria (lição I-18).
 *
 * F5-02: TinyV2Service::logEndpoint() sondava `SHOW TABLES LIKE 'tiny_v2_endpoint_logs'` a cada
 * chamada. O V3 nunca sondou (tenta o INSERT e deixa o catch tratar). Alinhado ao V3.
 *
 * Reprova sobre o código antigo. Asserções negativas guardadas por `!== ''` (lição I-20: sobre
 * arquivo ausente hub_read() devolve '' e a negativa passaria por engano).
 */
$mortas = ['tiny_v3_manual_access_token','tiny_v3_manual_refresh_token'];

// ---- F5-01: leituras removidas
$icfg = hub_read('app/Services/IntegrationConfig.php');
hub_check($checks, 'IntegrationConfig lido (N>0)', $icfg !== '');
foreach ($mortas as $c) {
  hub_check($checks, "IntegrationConfig não decifra mais {$c}", $icfg !== '' && !str_contains($icfg, $c));
}
// os segredos vivos continuam na lista de decifra (não removi de menos)
foreach (['tiny_v2_token','tiny_v3_token','tiny_v3_client_secret','webhook_secret','tiny_webhook_secret'] as $vivo) {
  hub_check($checks, "IntegrationConfig ainda decifra o segredo vivo {$vivo}", str_contains($icfg, "'{$vivo}'"));
}

$servicos = ['app/Services/ProductionReadinessV50Service.php','app/Services/OperationCenterService.php','app/Services/ProductionGoLiveService.php'];
foreach ($servicos as $rel) {
  $src = hub_read($rel);
  hub_check($checks, "{$rel} lido (N>0)", $src !== '');
  hub_check($checks, "{$rel} não lê mais tiny_v3_manual_access_token", $src !== '' && !str_contains($src, 'tiny_v3_manual_access_token'));
}
// preserva o sinal vivo que o termo morto acompanhava
hub_check($checks, 'ProductionReadiness ainda considera tiny_v3_token', str_contains(hub_read('app/Services/ProductionReadinessV50Service.php'), "tiny_v3_token"));

// ---- F5-01: schema (os dois caminhos) sem as colunas, e o upgrade legado não as recria
$core = hub_read('database/modules/core.sql');
$cons = hub_read('database/install_final_current.sql');
$legacy = hub_read('app/Controllers/LegacyDatabaseUpgradeController.php');
hub_check($checks, 'core.sql lido (N>0)', $core !== '');
hub_check($checks, 'install_final_current.sql lido (N>0)', $cons !== '');
hub_check($checks, 'LegacyDatabaseUpgradeController lido (N>0)', $legacy !== '');
foreach ($mortas as $c) {
  hub_check($checks, "core.sql não declara mais {$c}", $core !== '' && !str_contains($core, $c));
  hub_check($checks, "consolidado não declara mais {$c}", $cons !== '' && !str_contains($cons, $c));
  hub_check($checks, "upgrade legado V17 não recria mais {$c}", $legacy !== '' && !str_contains($legacy, "'{$c}'"));
}

// ---- F5-01: migration idempotente e portável (DROP condicional por information_schema, sem IF EXISTS)
$mig = hub_read('database/migrations/20260927_019_drop_tiny_v3_manual_token_columns.sql');
hub_check($checks, 'Migration 019 existe (N>0)', $mig !== '');
foreach ($mortas as $c) {
  hub_check($checks, "Migration 019 dropa {$c}", str_contains($mig, "DROP COLUMN {$c}"));
  hub_check($checks, "Migration 019 guarda o DROP de {$c} por COLUMN_NAME", (bool)preg_match('/COLUMN_NAME = \''.preg_quote($c,'/').'\'/', $mig));
}
// Ancora no SQL executável, não no comentário que explica a portabilidade (que cita "IF EXISTS").
$migSql = (string)preg_replace('/^\s*--[^\n]*$/m', '', $mig);
hub_check($checks, 'Migration 019 é portável (não usa DROP COLUMN IF EXISTS)', $mig !== '' && stripos($migSql, 'IF EXISTS') === false);
hub_check($checks, 'Migration 019 dropa só se a coluna existir (@col = 1)', str_contains($mig, '@col = 1'));

// ---- F5-02: TinyV2Service::logEndpoint alinhado ao V3 (sem sonda SHOW TABLES por chamada)
$v2 = hub_read('app/Services/TinyV2Service.php');
hub_check($checks, 'TinyV2Service lido (N>0)', $v2 !== '');
// Ancora no rabo executável da sonda (if(!$st->fetch()) return;), não na string SHOW TABLES —
// que o próprio comentário do F5-02 cita em backticks (armadilha da varredura acusar a doc).
hub_check($checks, 'TinyV2Service::logEndpoint não sonda mais SHOW TABLES por chamada', $v2 !== '' && !str_contains($v2, 'if(!$st->fetch()) return;'));
hub_check($checks, 'TinyV2Service ainda grava em tiny_v2_endpoint_logs (INSERT preservado)', str_contains($v2, 'INSERT INTO tiny_v2_endpoint_logs'));
hub_check($checks, 'TinyV2Service mantém o best-effort no catch (como o V3)', str_contains($v2, 'BestEffortLogService::warning'));

hub_finish($checks);
