<?php
declare(strict_types=1);
require __DIR__.'/_helpers.php';
$checks = [];

/**
 * EH-1 (auditoria de escala horizontal, 2026-09-28) — lock nomeado para jobs de tempo.
 *
 * SchedulerLockService centraliza o padrão GET_LOCK + fallback de arquivo (já provado em
 * TinyV3TokenService/VsmTokenService) e é aplicado aos três workers de tempo, que passam a PULAR
 * limpo (exit 0) quando já há execução concorrente, em vez de duplicar trabalho / logar falha.
 * Reprova sobre o código antigo (serviço ausente; workers sem o lock). Asserções negativas
 * guardadas por `!== ''` (lição I-20).
 */
$svc = hub_read('app/Services/SchedulerLockService.php');
hub_check($checks, 'SchedulerLockService existe (N>0)', $svc !== '');
hub_check($checks, 'Tem acquire(), release() e run()',
    str_contains($svc, 'function acquire(') && str_contains($svc, 'function release(') && str_contains($svc, 'function run('));
hub_check($checks, 'Usa GET_LOCK do MySQL', str_contains($svc, 'GET_LOCK'));
hub_check($checks, 'Tem fallback de arquivo (flock) para hospedagem sem GET_LOCK', str_contains($svc, 'LOCK_EX | LOCK_NB') || str_contains($svc, 'LOCK_EX|LOCK_NB'));
hub_check($checks, 'Timeout padrão 0 (scheduler não espera)', (bool)preg_match('/function acquire\(string \$name, int \$timeoutSeconds = 0\)/', $svc));

// Os três workers de tempo pulam limpo quando já há execução.
$workers = ['worker_retencao','worker_tiny_v3_refresh','worker_vsm_token_refresh'];
foreach ($workers as $w) {
    $src = hub_read("workers/{$w}.php");
    hub_check($checks, "{$w} lido (N>0)", $src !== '');
    hub_check($checks, "{$w} adquire o lock com o próprio nome", $src !== '' && str_contains($src, "SchedulerLockService::acquire('{$w}')"));
    hub_check($checks, "{$w} pula limpo (exit 0) se já houver execução", $src !== '' && (bool)preg_match('/já está em andamento — pulando.*\n\s*exit\(0\)/', $src));
    hub_check($checks, "{$w} libera o lock no shutdown", $src !== '' && str_contains($src, "SchedulerLockService::release('{$w}')"));
}

hub_finish($checks);
