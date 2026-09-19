<?php
declare(strict_types=1);
require __DIR__.'/_helpers.php';
$checks = [];

/**
 * Fase 7 (filas e resiliência) e retenção — auditoria de 2026-09-15.
 *
 * Achado I-15: `RetentionService::limparOperacional()` usava uma variável `$pdo` que **nunca era
 * definida**. As seis deleções morriam com `Call to a member function prepare() on null`, o
 * `catch(Throwable)` virava o erro numa string dentro do resultado, e `Audit::event()` registrava
 * o conjunto como **'sucesso'**. Efeito medido: `logs_integracao`, `auditoria_eventos`,
 * `tiny_webhooks`, `metricas_api`, `diagnostico_api` e `selftest_relatorios` nunca foram
 * expurgadas em instalação nenhuma — e o indicador dizia o contrário. Elas NÃO são cobertas pelo
 * `DataRetentionService` (que trata `fila_integracao` e `sessoes`): os dois não se sobrepõem.
 */

// ------------------------------------------------- a classe do defeito, generalizada
$raiz = hub_root();
$arquivos = [];
foreach (['app', 'workers'] as $dir) {
    $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($raiz.'/'.$dir, FilesystemIterator::SKIP_DOTS));
    foreach ($it as $f) { if ($f->isFile() && $f->getExtension() === 'php') $arquivos[] = $f->getPathname(); }
}
sort($arquivos);

$semAtribuir = [];
foreach ($arquivos as $arquivo) {
    $rel = ltrim(str_replace($raiz, '', $arquivo), '/');
    $src = (string)file_get_contents($arquivo);
    if (!preg_match_all('/function\s+([a-zA-Z_]\w*)\s*\(([^)]*)\)([^{;]*)\{/', $src, $ms, PREG_OFFSET_CAPTURE | PREG_SET_ORDER)) continue;
    foreach ($ms as $m) {
        $nome = $m[1][0];
        $ini = (int)$m[0][1] + strlen($m[0][0]) - 1;
        // corpo por chaves balanceadas, ignorando chave dentro de string
        $n = 0; $i = $ini; $len = strlen($src); $aspas = '';
        for (; $i < $len; $i++) {
            $c = $src[$i];
            if ($aspas !== '') { if ($c === '\\') { $i++; continue; } if ($c === $aspas) $aspas = ''; continue; }
            if ($c === "'" || $c === '"') { $aspas = $c; continue; }
            if ($c === '{') $n++;
            elseif ($c === '}') { $n--; if ($n === 0) break; }
        }
        $corpo = substr($src, $ini, $i - $ini + 1);
        $disp = ['this' => 1];
        foreach ([$m[2][0], $m[3][0]] as $cabec) { preg_match_all('/\$(\w+)/', $cabec, $pm); foreach ($pm[1] as $v) $disp[$v] = 1; }
        preg_match_all('/\$(\w+)\s*(?:=[^=]|\[[^\]]*\]\s*=|\+\+|--)/', $corpo, $am); foreach ($am[1] as $v) $disp[$v] = 1;
        preg_match_all('/\bas\s+\$(\w+)(?:\s*=>\s*\$(\w+))?/', $corpo, $fm);
        foreach ($fm[1] as $v) $disp[$v] = 1; foreach ($fm[2] as $v) { if ($v !== '') $disp[$v] = 1; }
        preg_match_all('/\bglobal\s+\$(\w+)/', $corpo, $gm); foreach ($gm[1] as $v) $disp[$v] = 1;
        preg_match_all('/\[\s*(?:\$\w+\s*,\s*)*\$(\w+)[^\]]*\]\s*=/', $corpo, $dm); foreach ($dm[1] as $v) $disp[$v] = 1;
        preg_match_all('/catch\s*\([^)]*\$(\w+)\s*\)/', $corpo, $cm); foreach ($cm[1] as $v) $disp[$v] = 1;
        // Parâmetros de closures/arrow functions internas: sem isto o $c de
        // array_map(function(X $c){ $c->… }) parece nunca atribuído — foi o único falso positivo.
        preg_match_all('/\bfn\s*\(([^)]*)\)|\bfunction\s*\(([^)]*)\)/', $corpo, $im, PREG_SET_ORDER);
        foreach ($im as $g) { preg_match_all('/\$(\w+)/', ($g[1] ?? '').' '.($g[2] ?? ''), $iv); foreach ($iv[1] as $v) $disp[$v] = 1; }
        preg_match_all('/\bfunction\s*\([^)]*\)\s*use\s*\(([^)]*)\)/', $corpo, $um);
        foreach ($um[1] as $lst) { preg_match_all('/\$(\w+)/', $lst, $uv); foreach ($uv[1] as $v) $disp[$v] = 1; }

        if (!preg_match_all('/\$(\w+)\s*->/', $corpo, $om)) continue;
        foreach (array_unique($om[1]) as $v) {
            if (isset($disp[$v])) continue;
            if (in_array($v, ['GLOBALS','_SERVER','_GET','_POST','_SESSION','_COOKIE','_ENV','_FILES','_REQUEST'], true)) continue;
            $semAtribuir[] = $rel.':'.(substr_count(substr($src, 0, $ini), "\n") + 1)."  {$nome}() usa \${$v}-> sem atribuir";
        }
    }
}
// Achado I-20 (auto-auditoria): "nenhuma ocorrência" sobre ZERO arquivos lidos é verde vazio —
// medido, esta asserção passava numa árvore com app/ e workers/ sem nenhum .php. Provar o N do
// conjunto varrido é a mesma lição que este projeto já pagou comparando schemas.
hub_check($checks, 'A varredura leu arquivos de verdade: '.count($arquivos), count($arquivos) > 100);
hub_check($checks, 'Nenhuma variável usada como objeto sem nunca ser atribuída: '.(implode(' | ', $semAtribuir) ?: 'nenhuma'), $semAtribuir === []);

// ------------------------------------------------- o ponto exato do I-15
$ret = hub_read('app/Services/RetentionService.php');
hub_check($checks, 'A retenção resolve a conexão por tabela (elas vivem em módulos diferentes)',
    str_contains($ret, 'Database::forTable($tabela)'));
hub_check($checks, 'A retenção guarda existência de tabela e coluna antes do DELETE',
    str_contains($ret, 'Database::tableExists($tabela)') && str_contains($ret, "Database::columnExists(\$tabela, 'criado_em')"));
hub_check($checks, 'O expurgo tem teto por execução (primeira passada não prende a tabela)',
    str_contains($ret, "LIMIT '.\$limite"));
hub_check($checks, 'O evento de auditoria deixou de ser sucesso fixo',
    str_contains($ret, "\$erros === [] ? 'sucesso' : 'alerta'"));
// Casa com o nome ENTRE ASPAS, que é como a tabela aparece no mapa — não com a menção em
// backticks do comentário que explica a divisão. Segunda vez nesta auditoria que uma asserção
// falha contra a própria documentação da correção.
hub_check($checks, 'Os dois serviços de retenção não se sobrepõem',
    !str_contains($ret, "'fila_integracao'") && !str_contains($ret, "'sessoes'")
    && str_contains(hub_read('app/Services/DataRetentionService.php'), "'fila_integracao'"));

// ------------------------------------------------- fila: a API que os workers realmente usam
// Escrevendo a sonda desta auditoria eu chamei `QueueService::liberarPresos()`, que não existe — o
// nome real é `liberarTravados()`. Método inventado em teste falha como se fosse defeito do Hub.
$fila = hub_read('app/Services/QueueService.php');
foreach (['liberarTravados', 'heartbeat', 'pegarProximo', 'marcarResultado', 'reprocessar'] as $metodo) {
    hub_check($checks, "QueueService::{$metodo}() existe", (bool)preg_match('/public static function '.$metodo.'\s*\(/', $fila));
}
hub_check($checks, 'A finalização do item exige que ele esteja em processamento (não finaliza item alheio)',
    substr_count($fila, "status='processando'") >= 1);
hub_check($checks, 'Esgotar tentativas manda o item para a fila morta',
    str_contains($fila, "DeadLetterQueueService::enviar(\$itemAtual"));

// ------------------------------------------------- OAuth V3: o que dá para provar sem Tiny real
$oauth = hub_read('app/Services/OAuthStateService.php');
hub_check($checks, 'O state é assinado com HMAC e conferido com hash_equals',
    str_contains($oauth, "hash_hmac('sha256'") && str_contains($oauth, 'hash_equals($expectedMac, $mac)'));
hub_check($checks, 'O state é de uso único, pela proteção anti-replay',
    str_contains($oauth, 'IntegrationReplayGuardService::guard('));
hub_check($checks, 'PKCE usa S256 sobre o code_verifier',
    str_contains($oauth, "hash('sha256', \$verifier, true)"));
hub_check($checks, 'O cookie de correlação não vive na sessão principal (SameSite Strict não volta do provedor)',
    str_contains($oauth, 'COOKIE_PREFIX') && str_contains($oauth, 'TTL_SECONDS'));

hub_finish($checks);
