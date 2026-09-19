<?php
declare(strict_types=1);
require __DIR__.'/_helpers.php';
$checks = [];

/**
 * Chamada a método de serviço que não existe — achado I-11 (auditoria de 2026-09-15).
 *
 * `DashboardController::atualizadorSeguroExecutar()` fazia
 * `(new UniversalUpgradeService($this->pdo))->executarTodos()`. Dois erros na MESMA linha: o
 * construtor declara `string $root` e recebia um PDO, e `executarTodos()` nunca existiu — a classe
 * só tem `run()`. O botão *Executar todos os updates com segurança* devolvia 500 desde sempre.
 *
 * O portão `controller-route-check.php` confere método ausente apenas para as rotas declaradas em
 * `FastRouteDispatcherService::$directActions`. Chamada a método de SERVIÇO escapa dele — por isso
 * esta verificação existe.
 *
 * O QUE ELA NÃO PROVA: não confere TIPO de argumento, só existência do método e número de
 * argumentos do construtor. O `PDO` passado onde se esperava `string` continua fora do alcance
 * estático — quem pega isso é exercitar a rota, como foi feito nas 78 rotas de mutação.
 */
$raiz = hub_root();

/** Mapa classe => [métodos, classe-pai], por varredura de texto — mesmo desenho do portão de rotas. */
$classes = [];
$arquivos = [];
$it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($raiz.'/app', FilesystemIterator::SKIP_DOTS));
foreach ($it as $f) { if ($f->isFile() && $f->getExtension() === 'php') $arquivos[] = $f->getPathname(); }
sort($arquivos);
foreach ($arquivos as $arquivo) {
    $codigo = (string)file_get_contents($arquivo);
    if (!preg_match_all('/(?<!::)\bclass\s+([A-Za-z_][A-Za-z0-9_]*)(?:\s+extends\s+([A-Za-z_][A-Za-z0-9_]*))?/', $codigo, $cm, PREG_SET_ORDER)) continue;
    $metodos = [];
    if (preg_match_all('/\bfunction\s+([a-zA-Z_][A-Za-z0-9_]*)\s*\(([^)]*)\)/', $codigo, $mm, PREG_SET_ORDER)) {
        foreach ($mm as $m) $metodos[strtolower($m[1])] = $m[2];
    }
    foreach ($cm as $c) $classes[$c[1]] = ['metodos' => $metodos, 'pai' => $c[2] ?? null];
}

$temMetodo = static function (string $classe, string $metodo) use ($classes): bool {
    $visto = [];
    while ($classe !== '' && isset($classes[$classe]) && !isset($visto[$classe])) {
        $visto[$classe] = true;
        if (isset($classes[$classe]['metodos'][strtolower($metodo)])) return true;
        $classe = (string)($classes[$classe]['pai'] ?? '');
    }
    return false;
};

$obrigatorios = static function (string $assinatura): int {
    $assinatura = trim($assinatura);
    if ($assinatura === '') return 0;
    $n = 0;
    foreach (explode(',', $assinatura) as $arg) {
        if (str_contains($arg, '=') || str_contains($arg, '...')) continue;
        if (trim($arg) !== '') $n++;
    }
    return $n;
};

$faltando = [];
$arity = [];
$conferidas = 0;
foreach ($arquivos as $arquivo) {
    $rel = ltrim(str_replace($raiz, '', $arquivo), '/');
    foreach (explode("\n", (string)file_get_contents($arquivo)) as $n => $linha) {
        $t = ltrim($linha);
        if (str_starts_with($t, '//') || str_starts_with($t, '*') || str_starts_with($t, '#')) continue;
        if (!preg_match_all('/\(new\s+([A-Z][A-Za-z0-9_]*)\s*\(([^()]*)\)\s*\)\s*->\s*([a-zA-Z_][A-Za-z0-9_]*)\s*\(/', $linha, $ms, PREG_SET_ORDER)) continue;
        foreach ($ms as $m) {
            [$todo, $classe, $args, $metodo] = $m;
            if (!isset($classes[$classe])) continue; // classe fora de app/ (ex.: PDO, DateTime)
            $conferidas++;
            if (!$temMetodo($classe, $metodo)) { $faltando[] = "{$rel}:".($n + 1)."  {$classe}::{$metodo}()"; continue; }
            $ctor = $classes[$classe]['metodos']['__construct'] ?? null;
            if ($ctor === null) continue;
            $passados = trim($args) === '' ? 0 : count(array_filter(array_map('trim', explode(',', $args)), static fn($a) => $a !== ''));
            $exigidos = $obrigatorios($ctor);
            if ($passados < $exigidos) $arity[] = "{$rel}:".($n + 1)."  {$classe}::__construct() exige {$exigidos}, recebeu {$passados}";
        }
    }
}

hub_check($checks, "Chamadas (new Classe(...))->metodo() conferidas: {$conferidas}", $conferidas >= 30);
hub_check($checks, 'Nenhum método de serviço inexistente: '.(implode(' | ', $faltando) ?: 'nenhum'), $faltando === []);
hub_check($checks, 'Nenhum construtor chamado com argumentos de menos: '.(implode(' | ', $arity) ?: 'nenhum'), $arity === []);

// O ponto exato do I-11, travado por nome.
$controller = hub_read('app/Controllers/DashboardController.php');
hub_check($checks, 'atualizadorSeguroExecutar chama run(), não o inexistente executarTodos()',
    !str_contains($controller, 'executarTodos') && str_contains($controller, 'UniversalUpgradeService(dirname(__DIR__,2)))->run()'));
hub_check($checks, 'O serviço devolve Trace ID, que a tela exibe',
    str_contains(hub_read('app/Services/UniversalUpgradeService.php'), "'trace_id'")
    && str_contains(hub_read('views/atualizador_seguro.php'), "\$r['trace_id']"));
// Casa com a LEITURA, e(...) em volta da chave, e não com o texto do comentário que explica o
// achado — a tag de fechamento PHP dentro de um comentário encerra o bloco e o arquivo passa a
// imprimir a si mesmo, com o lint verde.
hub_check($checks, 'A tela não lê mais chaves que o serviço nunca devolveu',
    !str_contains(hub_read('views/atualizador_seguro.php'), "e(\$r['ok'])")
    && !str_contains(hub_read('views/atualizador_seguro.php'), "e(\$r['ignorados'])"));

// Achado I-12: id sem guarda virava 500 e sujava a trilha com sistema.erro_fatal.
$fila = hub_read('app/Controllers/FilaController.php');
hub_check($checks, 'fila-morta-reprocessar mora no FilaController, como o RouteModuleRegistry declara',
    str_contains($fila, 'fila-morta-reprocessar') && str_contains($fila, 'mortaReprocessar'));
hub_check($checks, 'O id da fila morta é validado antes de chegar ao serviço',
    str_contains($fila, "if(\$id<1) redirect") && str_contains($fila, 'catch(RuntimeException'));
hub_check($checks, 'A tela da fila morta responde ao operador nos três casos',
    str_contains(hub_read('views/fila_morta.php'), 'reprocessado')
    && str_contains(hub_read('views/fila_morta.php'), 'nao_encontrado'));

// Achado I-13: a empresa se perdia na ida para a fila morta e na volta.
$dlq = hub_read('app/Services/DeadLetterQueueService.php');
hub_check($checks, 'A fila morta herda a empresa do item que falhou', str_contains($dlq, '$empresaDoItem'));
hub_check($checks, 'O reprocessamento devolve a linha à fila com a empresa de origem', str_contains($dlq, '$herdaEmpresa'));

// ------------------- I-21: script CLI autônomo chamando classe que ele não carrega
/**
 * Achado I-21, e ele é MEU: corrigindo o I-10 eu troquei um `SHOW TABLES LIKE ?` cru em
 * `scripts/diagnose-http-500.php` por `Database::tableExistsOn()`. O script é **autônomo** — não
 * carrega o autoloader do Hub —, então a chamada morria com `Class "Database" not found` e
 * derrubava o diagnóstico inteiro no ramo de banco, justamente quando alguém precisa dele.
 * `php -l` passa, e nenhum portão executa os scripts: só apareceu porque eu rodei o script.
 *
 * A checagem varre `scripts/` e `workers/`: quem NÃO carrega o autoloader não pode chamar método
 * estático de classe definida em `app/`, a menos que exija o arquivo dela explicitamente.
 * `Classe::class` é permitido — resolve para string sem disparar autoload.
 *
 * Comentários são removidos antes da varredura: sem isso ela acusa a própria documentação da
 * correção, o que já aconteceu três vezes nesta sessão.
 */
$classesDoApp = [];
$itApp = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($raiz.'/app', FilesystemIterator::SKIP_DOTS));
foreach ($itApp as $f) {
    if (!$f->isFile() || $f->getExtension() !== 'php') continue;
    if (preg_match_all('/^\s*(?:abstract\s+|final\s+)?class\s+([A-Za-z_]\w*)/m', (string)file_get_contents($f->getPathname()), $cm)) {
        foreach ($cm[1] as $c) $classesDoApp[$c] = 1;
    }
}
$semCarregar = [];
$scripts = array_merge(glob($raiz.'/scripts/*.php') ?: [], glob($raiz.'/scripts/ci/*.php') ?: [], glob($raiz.'/workers/*.php') ?: []);
foreach ($scripts as $script) {
    $src = (string)file_get_contents($script);
    if (preg_match('/require(?:_once)?[^;]*(?:Autoload|autoload)\.php/i', $src)) continue;
    preg_match_all('/require(?:_once)?[^;]*\/([A-Za-z_]\w*)\.php/', $src, $rm);
    $exigidas = array_flip($rm[1]);
    // tira comentários de linha e de bloco antes de procurar chamadas
    $limpo = (string)preg_replace(['/\/\*.*?\*\//s', '/\/\/[^\n]*/', '/^\s*#[^\n]*/m'], '', $src);
    if (!preg_match_all('/\b([A-Z][A-Za-z0-9_]*)::(?!class\b)([a-zA-Z_]\w*)/', $limpo, $um, PREG_SET_ORDER)) continue;
    foreach ($um as $u) {
        if (!isset($classesDoApp[$u[1]]) || isset($exigidas[$u[1]])) continue;
        $semCarregar[] = ltrim(str_replace($raiz, '', $script), '/')." chama {$u[1]}::{$u[2]}()";
    }
}
hub_check($checks, 'Scripts varridos: '.count($scripts), count($scripts) > 15);
hub_check($checks, 'Nenhum script autônomo chama classe do app/ sem carregá-la: '.(implode(' | ', array_unique($semCarregar)) ?: 'nenhum'), $semCarregar === []);
hub_check($checks, 'O diagnóstico de HTTP 500 confere tabela sem depender de classe do app/',
    str_contains(hub_read('scripts/diagnose-http-500.php'), 'information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ?'));

hub_finish($checks);
