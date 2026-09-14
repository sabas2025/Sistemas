<?php
declare(strict_types=1);
require __DIR__.'/_helpers.php';
$checks=[];

/**
 * Auditoria final 2026-09-14 — achado G-03.
 *
 * O reagendamento das filas (proxima_tentativa) era determinístico em QUATRO pontos. Quando a VSM
 * ou o Tiny falha rápido, muitos itens terminam no mesmo segundo, recebem o mesmo atraso e voltam
 * a ficar elegíveis no MESMO segundo - rajada sincronizada contra o provedor. O pior caso era o
 * guard de idempotência, com 300s fixos para todos os itens em voo.
 *
 * O jitter é ADITIVO: nunca antecipa. Antecipar seria pior que não ter jitter - a política de
 * rate_limit recua justamente para parar de bater no provedor.
 */

function hub_exec_bj(string $rel): string {
    $fonte = hub_read($rel); if ($fonte === '') return '';
    $o='';
    foreach (token_get_all($fonte) as $t) {
        if (is_array($t) && in_array($t[0],[T_COMMENT,T_DOC_COMMENT],true)) continue;
        $o .= is_array($t) ? $t[1] : $t;
    }
    return $o;
}

// ------------------------------------------------- os quatro pontos de reagendamento
$pontos = [
    'app/Services/QueueService.php'                      => 'fila_integracao (marcarResultado)',
    'app/Services/EnterpriseIdempotencyGuardService.php' => 'fila_integracao (guard de idempotência)',
    'app/Services/EstoqueEnterpriseService.php'          => 'fila_estoque',
    'app/Services/FiscalEnterpriseService.php'           => 'fila_fiscal',
];
foreach ($pontos as $arq => $rotulo) {
    $c = hub_exec_bj($arq);
    hub_check($checks,"G-03: $rotulo agenda por RetryPolicyService::proximaTentativaEm()",
        str_contains($c,'RetryPolicyService::proximaTentativaEm('));
    hub_check($checks,"G-03: $rotulo sem date('Y-m-d H:i:s', time()+...) determinístico",
        !preg_match("/date\('Y-m-d H:i:s'\s*,\s*time\(\)\s*\+/", $c));
}

// ------------------------------------------------------------------- comportamento real
require_once hub_root().'/app/Services/RetryPolicyService.php';

// limites: 10% do atraso, piso de 30s, teto de 300s
foreach ([2=>30, 5=>30, 15=>90, 60=>300, 180=>300] as $min => $teto) {
    $vistos=[];
    for ($i=0;$i<3000;$i++) $vistos[] = RetryPolicyService::jitterSegundos($min*60);
    hub_check($checks,"jitter de atraso de {$min} min fica em [0..{$teto}]",
        min($vistos) >= 0 && max($vistos) <= $teto, 'observado ['.min($vistos).'..'.max($vistos).']');
    hub_check($checks,"jitter de atraso de {$min} min não é constante",
        max($vistos) > (int)($teto*0.9), 'máximo observado '.max($vistos));
}
hub_check($checks,'base <= 0 devolve 0 (nunca negativo)',
    RetryPolicyService::jitterSegundos(0) === 0 && RetryPolicyService::jitterSegundos(-10) === 0);

// a propriedade que mais importa: NUNCA antecipar
$violacoes = 0;
foreach ([2,5,15,30,60,180] as $min) {
    for ($i=0;$i<1500;$i++) {
        if (strtotime(RetryPolicyService::proximaTentativaEm($min)) < time() + $min*60) $violacoes++;
    }
}
hub_check($checks,'9.000 agendamentos: nenhum antes do que a política manda (jitter é aditivo)',
    $violacoes === 0, "violações=$violacoes");

// dispersão de um lote que falha junto
$inst=[];
for ($i=0;$i<500;$i++) $inst[] = strtotime(RetryPolicyService::proximaTentativaEm(5));
hub_check($checks,'500 itens falhando juntos se espalham por mais de um segundo',
    count(array_unique($inst)) > 1, count(array_unique($inst)).' segundos distintos');
hub_check($checks,'nenhum segundo concentra mais de 10% do lote',
    max(array_count_values($inst)) <= 50, 'maior pico '.max(array_count_values($inst)).' itens/s');

// formato aceito pelo DATETIME do MySQL
hub_check($checks,'formato Y-m-d H:i:s preservado',
    (bool)preg_match('/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}$/', RetryPolicyService::proximaTentativaEm(5)));

// assinaturas públicas preservadas (a correção não pode mudar contrato)
foreach (['EstoqueEnterpriseService','FiscalEnterpriseService'] as $cls) {
    $fonte = hub_read("app/Services/$cls.php");
    hub_check($checks,"$cls::proximaTentativa(int \$tentativa): ?string preservado",
        str_contains($fonte,'public static function proximaTentativa(int $tentativa): ?string'));
}

hub_finish($checks);
