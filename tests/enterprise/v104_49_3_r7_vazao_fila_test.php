<?php
declare(strict_types=1);
require __DIR__.'/_helpers.php';
$checks=[];

/**
 * Auditoria de vazão 2026-09-14 — achados E-01 a E-05.
 *
 * A fila protege a INGESTÃO corretamente (o webhook enfileira e responde). O risco está no
 * consumidor: ApiController::processarFila() retira UM item por chamada, e quem agendar o
 * worker de nome óbvio (worker_fila.php) drena 1 item por minuto contra 500 entrando.
 */

function hub_exec_vz(string $rel): string {
    $fonte = hub_read($rel); if ($fonte === '') return '';
    $o='';
    foreach (token_get_all($fonte) as $t) {
        if (is_array($t) && in_array($t[0],[T_COMMENT,T_DOC_COMMENT],true)) continue;
        $o .= is_array($t) ? $t[1] : $t;
    }
    return $o;
}

// ---------------------------------------------------------------- premissa da auditoria
$api = hub_exec_vz('app/Controllers/ApiController.php');
hub_check($checks,'processarFila() continua retirando UM item (premissa dos achados)',
    str_contains($api,'QueueService::pegarProximo()'));

$ent = hub_exec_vz('workers/worker_enterprise.php');

// E-01 — chave duplicada no array do heartbeat
hub_check($checks,'E-01: heartbeat sem chave duplicada max_seconds',
    substr_count($ent, "'max_seconds'=>\$maxSeconds,'max_seconds'") === 0);

// E-02 — $processed contava a iteração vazia
$posBreak = strpos($ent, "\$empty = true; break;");
$posInc   = strpos($ent, '$processed++');
hub_check($checks,'E-02: $processed só incrementa DEPOIS do teste de fila vazia',
    $posBreak !== false && $posInc !== false && $posBreak < $posInc);

// E-03 — padrão do lote compatível com a meta
preg_match('/\$limit\s*=\s*max\(1,\s*min\((\d+),\s*\(int\)\(\$argv\[1\]\s*\?\?\s*(\d+)\)\)\)/', $ent, $m);
$teto = (int)($m[1] ?? 0); $padrao = (int)($m[2] ?? 0);
hub_check($checks,'E-03: padrão do lote é '.$padrao.' (era 50, meta 500/min)', $padrao >= 500);
hub_check($checks,'E-03: teto e piso preservados', $teto === 500 && str_contains($ent,'max(1,'));
hub_check($checks,'E-03: laço ainda para em fila vazia e em maxSeconds',
    str_contains($ent,'$empty = true; break;') && str_contains($ent,'>= $maxSeconds) { break; }'));

// E-04 — aviso no worker de item único
$fila = hub_read('workers/worker_fila.php');
hub_check($checks,'E-04: worker_fila avisa que processa UM item por execução',
    str_contains($fila,'UM** ITEM POR EXECUÇÃO') || str_contains($fila,'UM ITEM POR EXECUÇÃO'));
hub_check($checks,'E-04: worker_fila aponta para o worker de lote',
    str_contains($fila,'worker_enterprise.php'));
hub_check($checks,'E-04: worker_fila segue CLI-only com guard',
    str_contains($fila,"PHP_SAPI !== 'cli'") && str_contains($fila,'WorkerCliGuardService::enforce'));

// E-05 — alarme de contrapressão
hub_check($checks,'E-05: serviço de contrapressão existe',
    is_file(hub_root().'/app/Services/QueueBackpressureService.php'));
$bp = hub_exec_vz('app/Services/QueueBackpressureService.php');
hub_check($checks,'E-05: alarma por crescimento SUSTENTADO, não por limite absoluto',
    str_contains($bp,'CICLOS_PARA_ALARME') && str_contains($bp,'ciclos'));
hub_check($checks,'E-05: também alarma por item pendente antigo (worker fora do ar)',
    str_contains($bp,'mais_antigo_min'));
hub_check($checks,'E-05: registra em auditoria e no estado de saúde',
    str_contains($bp,"'fila.contrapressao'") && str_contains($bp,"degrade('queue_backpressure'"));
hub_check($checks,'E-05: volta a saudável quando a fila drena',
    str_contains($bp,"healthy('queue_backpressure')"));
hub_check($checks,'E-05: controle declarado no catálogo de saúde',
    str_contains(hub_exec_vz('app/Services/SecurityHealthService.php'),"'queue_backpressure'"));
hub_check($checks,'E-05: ligado ao worker_selftest',
    str_contains(hub_read('workers/worker_selftest.php'),'QueueBackpressureService::avaliar()'));
hub_check($checks,'E-05: estado gravado sob lock exclusivo (não corrompe com workers paralelos)',
    str_contains($bp,'LOCK_EX') && str_contains($bp,"fopen(\$f, 'c+')"));

// ---------------------------------------------------------------- documentação
$readme = hub_read('workers/README-WORKERS.md');
hub_check($checks,'README distingue o worker de 1 item do worker de lote',
    str_contains($readme,'worker_fila.php') && str_contains($readme,'**1**') && str_contains($readme,'worker_enterprise.php'));
hub_check($checks,'README traz a conta de dimensionamento por latência',
    str_contains($readme,'Latência por item') && str_contains($readme,'Processos para 500'));
hub_check($checks,'README mostra exemplo de cron com processos paralelos',
    substr_count($readme,'worker_enterprise.php 500 55') >= 4);
hub_check($checks,'README diz que a concorrência é segura e por quê',
    str_contains($readme,'SKIP LOCKED'));
hub_check($checks,'README documenta o cron de retenção',
    str_contains($readme,'worker_retencao.php'));
hub_check($checks,'README registra a decisão sobre envio em lote e a razão',
    str_contains($readme,'lote') && str_contains($readme,'idempotência'));
hub_check($checks,'README não promete latência que não foi medida',
    str_contains($readme,'Não meça pela tabela'));

hub_finish($checks);
