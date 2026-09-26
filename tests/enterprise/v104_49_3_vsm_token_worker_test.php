<?php
declare(strict_types=1);
require __DIR__.'/_helpers.php';
$checks = [];

/**
 * F6-07 etapa 5 (2026-09-26) — worker de aquecimento do token VSM.
 *
 * Estrutural: garante que o worker segue o mesmo contrato defensivo do worker_tiny_v3_refresh
 * (guarda CLI, no-op guardado, chamada de refresh, saída 1 em erro) e usa VsmTokenService.
 * Reprova sobre o código antigo: o worker não existe em main.
 *
 * O comportamento em runtime foi medido contra MariaDB provisionado: sem credenciais VSM ->
 * "não configurada", exit 0; com credenciais e token expirado -> tenta /v1/auth/token e sai 1
 * quando a rede à VSM está bloqueada (correto). Isso não é reproduzível como teste estático.
 */
$w = hub_read('workers/worker_vsm_token_refresh.php');
hub_check($checks, 'worker existe (N>0)', strlen($w) > 500);
hub_check($checks, 'bloqueia execução via navegador (só CLI)', str_contains($w, "PHP_SAPI !== 'cli'"));
hub_check($checks, 'usa WorkerCliGuardService::enforce', str_contains($w, 'WorkerCliGuardService::enforce'));
hub_check($checks, 'consulta o estado via VsmTokenService::status', str_contains($w, 'VsmTokenService::status'));
hub_check($checks, 'no-op guardado quando credenciais ausentes (exit 0)',
    str_contains($w, 'client_token_configurado') && str_contains($w, 'exit(0)'));
hub_check($checks, 'renova forçando a emissão (VsmTokenService::refresh(true))', str_contains($w, 'VsmTokenService::refresh(true)'));
hub_check($checks, 'sai 1 quando a emissão falha', str_contains($w, 'exit(1)'));
hub_check($checks, 'documenta que a VSM NÃO usa refresh token (não é salva-vidas)',
    stripos($w, 'refresh_token') !== false || stripos($w, 'refresh token') !== false);

// O README documenta o cron e a natureza opcional.
$readme = hub_read('workers/README-WORKERS.md');
hub_check($checks, 'README documenta o worker VSM', $readme !== '' && str_contains($readme, 'worker_vsm_token_refresh.php'));

hub_finish($checks);
