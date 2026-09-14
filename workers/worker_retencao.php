<?php
if (PHP_SAPI !== 'cli') {
  http_response_code(403);
  header('Content-Type: text/plain; charset=utf-8');
  echo "Worker bloqueado para execução via navegador. Execute pelo terminal/Agendador usando php ".basename(__FILE__).".";
  exit;
}
/**
 * Worker de retenção — auditoria de capacidade 2026-09-14 (achado C-04).
 *
 * Até a R7 o expurgo rodava DENTRO da requisição do usuário: RouteRateLimiterService chamava
 * DataRetentionService::maybeRun(), que em 1 de cada 100 requisições executava até seis
 * DELETE ... LIMIT 5000, mais um DELETE probabilístico em rate_limit_hits. A 30 req/s isso
 * disparava a cada ~3 segundos dentro de uma requisição sorteada, que pagava a latência de até
 * 30 mil deleções sem nenhuma relação com o que o usuário pediu.
 *
 * Agora a manutenção é deste worker. Agende no cron, a cada 10 minutos:
 *
 *     0,10,20,30,40,50 * * * * php /caminho/para/workers/worker_retencao.php >> /var/log/hub-retencao.log 2>&1
 *
 * (a forma com barra é equivalente, mas a sequência fecharia este bloco de comentário)
 *
 * Dez minutos é folgado: cada regra apaga em lotes de 5.000 linhas, e o volume alvo
 * (500 pedidos/min) gera bem menos que isso no intervalo.
 */
require_once __DIR__.'/../app/Core/Autoload.php';
WorkerCliGuardService::enforce(basename(__FILE__));
require_once __DIR__.'/../app/Core/Helpers.php';
App::setupErrors();

$inicio = microtime(true);
$total = 0;
$falhou = false;

try {
    // Expurgo das tabelas com regra de idade declarada (inclui fila_integracao em estado
    // terminal, acrescentada pelo achado C-06).
    $itens = DataRetentionService::cleanup();
    foreach ($itens as $item) {
        $apagadas = (int)($item['deleted'] ?? 0);
        $total += $apagadas;
        printf("  %-34s %6d linha(s)  [%s]\n", (string)($item['table'] ?? '?'), $apagadas, (string)($item['rule'] ?? ''));
    }

    // Baldes de rate limit vencidos: saiu do caminho quente junto com o resto (C-04).
    try {
        if (Database::tableExists('rate_limit_hits')) {
            $pdo = Database::forTable('rate_limit_hits');
            $st = $pdo->prepare('DELETE FROM rate_limit_hits WHERE janela_inicio < DATE_SUB(NOW(), INTERVAL 2 HOUR) LIMIT 20000');
            $st->execute();
            $apagadas = (int)$st->rowCount();
            $total += $apagadas;
            printf("  %-34s %6d linha(s)  [2 HOUR]\n", 'rate_limit_hits', $apagadas);
        }
    } catch (Throwable $e) {
        $falhou = true;
        fwrite(STDERR, "  [ERRO] rate_limit_hits: ".$e->getMessage()."\n");
    }

    // Contadores atômicos ociosos em disco (rate limit e cache de bloqueio de IP).
    $dir = __DIR__.'/../storage/cache/security';
    if (is_dir($dir)) {
        $removidos = 0;
        foreach (glob($dir.'/hub_*.json') ?: [] as $arquivo) {
            $mtime = @filemtime($arquivo);
            if ($mtime !== false && $mtime < time() - 7200) { if (@unlink($arquivo)) $removidos++; }
        }
        printf("  %-34s %6d arquivo(s) [2 HOUR]\n", 'storage/cache/security', $removidos);
    }
} catch (Throwable $e) {
    $falhou = true;
    fwrite(STDERR, "[FALHA] Retenção: ".$e->getMessage()."\n");
}

printf("[%s] Retenção concluída: %d linha(s) em %.2fs\n", $falhou ? 'PARCIAL' : 'OK', $total, microtime(true) - $inicio);
exit($falhou ? 1 : 0);
