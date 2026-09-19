<?php
declare(strict_types=1);
/**
 * Portão 14 (2026-09-15) — EXECUTA os scripts de scripts/ e reprova quando morrem com erro fatal.
 *
 * Por que ele existe: o achado I-21 foi criado por mim ao corrigir o I-10. Troquei um
 * `SHOW TABLES LIKE ?` cru por `Database::tableExistsOn()` em scripts/diagnose-http-500.php, que é
 * AUTÔNOMO — não carrega o autoloader do Hub. A chamada morria com `Class "Database" not found` e
 * derrubava o diagnóstico inteiro no ramo de banco, justamente quando alguém recorre a ele.
 * `php -l` passava. Os 13 portões passavam. O teste que eu mesmo escrevi afirmava que o arquivo
 * CONTINHA a chamada — ou seja, travava o defeito no lugar. Só apareceu quando eu executei o
 * script. Nenhum portão executava nada de scripts/; este executa.
 *
 * O que ele prova: os scripts exercitados chegam ao fim sem erro fatal de PHP no modo em que são
 * chamados aqui. O que ele NÃO prova: que a saída está correta, nem que os ramos não alcançados
 * nesta invocação funcionam. O ramo de banco de diagnose-http-500.php, por exemplo, só roda quando
 * existe config/config.php — por isso o passo da CI aparece DUAS vezes: no job estático (sem
 * config) e no job com MariaDB provisionado (com config). Era no segundo caso que o I-21 vivia.
 *
 * Todo script de scripts/*.php precisa de uma decisão declarada no catálogo abaixo. Script novo sem
 * entrada REPROVA o portão: a decisão de rodar ou não rodar é de quem o escreve, não deste arquivo.
 */
if (PHP_SAPI !== 'cli') { fwrite(STDERR, "[FALHA] Somente CLI.\n"); exit(1); }

$root = dirname(__DIR__, 2);

/**
 * modo:
 *   direto   — roda no próprio repositório; só entram invocações comprovadamente SEM escrita
 *   isolado  — escreve, então roda numa raiz descartável (o script usa dirname(__DIR__) como raiz)
 *   excluido — não é exercitado; exige motivo
 */
$catalogo = [
    'upgrade-r7.php' => [
        'modo' => 'direto', 'args' => ['--help'],
        'nota' => 'help não acessa banco nem escreve; aplicação só com flags explícitas',
    ],
    'build-classmap.php' => [
        'modo' => 'direto',
        'args' => ['--check'],
        // --check chama ClassmapBuilderService::verify(), que compara e não grava.
        'nota' => 'somente leitura com --check; já é o portão 6',
    ],
    'diagnose-http-500.php' => [
        'modo' => 'direto',
        'args' => [],
        'nota' => 'somente leitura; é o script do I-21',
    ],
    'rotate-secrets.php' => [
        'modo' => 'direto',
        'args' => ['--audit'],
        // As duas escritas do script (backup do config e gravação do config) ficam depois do
        // `if ($rotateArg === null) { ... exit; }`, e $rotateArg só é definido por --rotate=.
        // Sem --rotate= nada é gravado.
        'nota' => 'sem --rotate= o script sai antes de qualquer escrita',
    ],
    'create-install-authorization.php' => [
        'modo' => 'isolado',
        'args' => [],
        'nota' => 'cria storage/install-authorization.json e imprime um código; roda em raiz descartável',
    ],
];

$arquivos = glob($root.'/scripts/*.php') ?: [];
sort($arquivos);
$total = count($arquivos);
if ($total === 0) {
    fwrite(STDERR, "[FALHA] Nenhum script encontrado em scripts/*.php. Varredura vazia não é aprovação.\n");
    exit(1);
}

$semDecisao = [];
foreach ($arquivos as $arquivo) {
    if (!isset($catalogo[basename($arquivo)])) $semDecisao[] = basename($arquivo);
}
if ($semDecisao) {
    fwrite(STDERR, "[FALHA] Script sem decisão declarada em cli-scripts-smoke.php: ".implode(', ', $semDecisao)."\n");
    fwrite(STDERR, "        Declare modo 'direto' (comprove que não escreve), 'isolado' ou 'excluido' com motivo.\n");
    exit(1);
}

/** Padrões de morte por erro fatal. Nenhum deles é saída legítima de script bem comportado. */
$fatais = [
    '/PHP Fatal error/i',
    '/^Fatal error:/m',
    '/PHP Parse error/i',
    '/^Parse error:/m',
    '/Uncaught\s+\w+/',
    '/Class "[^"]+" not found/',
    '/Call to undefined (method|function)/',
    '/Call to a member function [^ ]+ on (null|bool|int|string|array)/',
];

/** Não deixar código de autorização (hex longo) vazar para o log da CI. */
$redigir = static fn(string $s): string => (string)preg_replace('/\b[0-9a-f]{32,}\b/i', '[REDIGIDO]', $s);

$rmrf = static function (string $dir) use (&$rmrf): void {
    if (!is_dir($dir)) return;
    foreach (scandir($dir) ?: [] as $item) {
        if ($item === '.' || $item === '..') continue;
        $p = $dir.'/'.$item;
        is_dir($p) && !is_link($p) ? $rmrf($p) : @unlink($p);
    }
    @rmdir($dir);
};

$executados = 0;
$falhas = [];

foreach ($arquivos as $arquivo) {
    $nome = basename($arquivo);
    $entrada = $catalogo[$nome];
    $modo = $entrada['modo'];

    if ($modo === 'excluido') {
        printf("  %-38s  IGNORADO  %s\n", $nome, $entrada['nota'] ?? '');
        continue;
    }

    $alvo = $arquivo;
    $sandbox = null;
    if ($modo === 'isolado') {
        $sandbox = sys_get_temp_dir().'/hub-cli-smoke-'.bin2hex(random_bytes(6));
        if (!@mkdir($sandbox.'/scripts', 0700, true)) {
            $falhas[] = [$nome, 'não foi possível criar a raiz descartável'];
            continue;
        }
        $alvo = $sandbox.'/scripts/'.$nome;
        copy($arquivo, $alvo);
    }

    $cmd = escapeshellarg(PHP_BINARY).' '.escapeshellarg($alvo);
    foreach ($entrada['args'] as $a) $cmd .= ' '.escapeshellarg($a);
    $cmd .= ' 2>&1';

    $saida = [];
    $codigo = 0;
    exec($cmd, $saida, $codigo);
    $texto = implode("\n", $saida);
    $executados++;

    if ($sandbox !== null) $rmrf($sandbox);

    $motivos = [];
    foreach ($fatais as $padrao) {
        if (preg_match($padrao, $texto)) $motivos[] = 'saída casa '.$padrao;
    }
    // 255 é o código com que o PHP morre de erro fatal. Códigos 1 e 2 são saídas legítimas destes
    // scripts (config ausente, segredo fraco, autorização já ativa) e NÃO reprovam o portão.
    if ($codigo === 255) $motivos[] = 'exit 255 (morte por erro fatal)';

    if ($motivos) {
        $falhas[] = [$nome, implode('; ', $motivos)."\n".$redigir($texto)];
        printf("  %-38s  FALHA     exit %d\n", $nome, $codigo);
    } else {
        printf("  %-38s  OK        exit %d\n", $nome, $codigo);
    }
}

echo "\nScripts em scripts/*.php: {$total} · executados: {$executados}\n";
if ($executados === 0) {
    fwrite(STDERR, "[FALHA] Nenhum script foi executado. Portão que não exercita nada não aprova nada.\n");
    exit(1);
}
if ($falhas) {
    fwrite(STDERR, "\n[FALHA] ".count($falhas)." script(s) morreram com erro fatal ao serem executados:\n");
    foreach ($falhas as [$nome, $detalhe]) fwrite(STDERR, "\n--- {$nome} ---\n{$detalhe}\n");
    exit(1);
}
echo "[OK] Nenhum erro fatal ao executar os scripts de CLI.\n";
exit(0);
