<?php
declare(strict_types=1);
require __DIR__.'/_helpers.php';
$checks = [];

/**
 * Tipo de notificação dentro do ENUM — achado D-03 (auditoria de vídeo-demo, 2026-10-05).
 *
 * NotificationService::criar($tipo, ...) grava $tipo em notificacoes.tipo, um ENUM FECHADO. Em
 * MySQL/MariaDB strict, um valor fora do ENUM faz o INSERT falhar, e o catch(Throwable) de criar()
 * engole o erro (retorna 0) — a notificação some e sobra só "Falha ao criar notificação" no log.
 * TinyWebhookService passava 'webhook_repetido', que não existe no ENUM: o alerta de webhook
 * reenviado nunca chegava ao operador em produção.
 *
 * Esta checagem ancora TODO caller do produto no catálogo real: lê o ENUM do schema e exige que
 * cada literal passado como 1º argumento de NotificationService::criar() pertença a ele. Pega a
 * classe inteira do defeito, não só o caso conhecido.
 *
 * O QUE ELA NÃO PROVA: tipos montados em variável/concatenação (só cobre literais em aspas) — e o
 * INSERT em runtime (medido com MariaDB strict no job E2E).
 */
// O ENUM é lido do consolidado (fonte única sempre presente; a tabela mora em
// database/modules/observabilidade.sql, mas o nome do módulo pode mudar).
$schema = hub_read('database/install_final_current.sql');

// Extrai a lista do ENUM de notificacoes.tipo a partir do CREATE TABLE.
$enum = [];
if (preg_match('/CREATE TABLE IF NOT EXISTS notificacoes\b.*?\btipo\s+ENUM\(([^)]*)\)/is', $schema, $m)) {
    preg_match_all("/'([^']+)'/", $m[1], $vals);
    $enum = $vals[1] ?? [];
}
hub_check($checks, 'ENUM de notificacoes.tipo foi localizado no schema', count($enum) >= 5, count($enum).' valor(es)');

// Varre os callers do produto (app/ + workers/) e coleta o 1º argumento literal de ::criar(.
$arquivos = array_merge(
    glob(hub_root().'/app/Services/*.php') ?: [],
    glob(hub_root().'/app/Controllers/*.php') ?: [],
    glob(hub_root().'/workers/*.php') ?: []
);
$callers = [];
foreach ($arquivos as $f) {
    $src = (string)@file_get_contents($f);
    if ($src === '' || !str_contains($src, 'NotificationService::criar(')) continue;
    if (preg_match_all("/NotificationService::criar\(\s*'([^']*)'/", $src, $mm)) {
        foreach ($mm[1] as $tipo) $callers[] = [basename($f), $tipo];
    }
}
// I-20: prove que varreu algo antes de afirmar o vazio.
hub_check($checks, 'Encontrou chamadas literais a NotificationService::criar no produto', count($callers) > 0, count($callers).' chamada(s)');

$invalidos = [];
foreach ($callers as [$arq, $tipo]) {
    if (!in_array($tipo, $enum, true)) $invalidos[] = "$arq: '$tipo'";
}
hub_check($checks, 'Todo tipo passado a criar() pertence ao ENUM notificacoes.tipo',
    $invalidos === [], $invalidos ? implode(' | ', $invalidos) : 'todos válidos');

// Regressão específica do D-03: o caller do webhook duplicado não volta a usar 'webhook_repetido'.
$tiny = hub_read('app/Services/TinyWebhookService.php');
hub_check($checks, "TinyWebhookService não usa mais o tipo inexistente 'webhook_repetido'",
    $tiny !== '' && !str_contains($tiny, "criar('webhook_repetido'"));

hub_finish($checks);
