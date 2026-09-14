<?php
declare(strict_types=1);

/**
 * Garante que o caminho realmente usado pelo instalador modular entrega o mesmo
 * contrato de tabelas do schema consolidado. Não executa DDL nem acessa banco.
 */
$root = dirname(__DIR__, 2);
$consolidatedPath = $root.'/database/install_final_current.sql';
$modulePaths = array_map(
    static fn(string $module): string => $root.'/database/modules/'.$module.'.sql',
    ['core','pedidos','produtos','estoque','fiscal','fila','observabilidade','backups']
);
$errors = [];
$consolidated = parseSchemaFile($consolidatedPath, $errors);
$modules = [];
$owners = [];
foreach ($modulePaths as $path) {
    $parsed = parseSchemaFile($path, $errors);
    foreach ($parsed as $table => $contract) {
        if (isset($modules[$table])) {
            $errors[] = 'Tabela modular declarada mais de uma vez: '.$table.' ('.basename((string)$owners[$table]).' e '.basename($path).').';
            continue;
        }
        $modules[$table] = $contract;
        $owners[$table] = $path;
    }
}

foreach (array_diff(array_keys($consolidated), array_keys($modules)) as $table) {
    $errors[] = 'Tabela do schema consolidado ausente nos módulos: '.$table.'.';
}
foreach (array_diff(array_keys($modules), array_keys($consolidated)) as $table) {
    $errors[] = 'Tabela modular ausente no schema consolidado: '.$table.'.';
}
foreach (array_intersect(array_keys($consolidated), array_keys($modules)) as $table) {
    $expected = $consolidated[$table];
    $actual = $modules[$table];
    foreach (array_diff(array_keys($expected['columns']), array_keys($actual['columns'])) as $column) {
        $errors[] = 'Coluna consolidada ausente no módulo: '.$table.'.'.$column.'.';
    }
    foreach (array_diff(array_keys($actual['columns']), array_keys($expected['columns'])) as $column) {
        $errors[] = 'Coluna modular ausente no consolidado: '.$table.'.'.$column.'.';
    }
    foreach (array_intersect(array_keys($expected['columns']), array_keys($actual['columns'])) as $column) {
        if ($expected['columns'][$column] !== $actual['columns'][$column]) {
            $errors[] = 'Contrato divergente em '.$table.'.'.$column.': consolidado ['.$expected['columns'][$column].'], módulo ['.$actual['columns'][$column].'].';
        }
    }
    foreach (array_diff(array_keys($expected['indexes']), array_keys($actual['indexes'])) as $index) {
        $errors[] = 'Índice consolidado ausente no módulo: '.$table.'.'.$index.'.';
    }
    foreach (array_diff(array_keys($actual['indexes']), array_keys($expected['indexes'])) as $index) {
        $errors[] = 'Índice modular ausente no consolidado: '.$table.'.'.$index.'.';
    }
    foreach (array_intersect(array_keys($expected['indexes']), array_keys($actual['indexes'])) as $index) {
        if ($expected['indexes'][$index] !== $actual['indexes'][$index]) {
            $errors[] = 'Índice divergente em '.$table.'.'.$index.': consolidado ['.$expected['indexes'][$index].'], módulo ['.$actual['indexes'][$index].'].';
        }
    }
    if ($expected['constraints'] !== $actual['constraints']) {
        $errors[] = 'Constraints/FKs/CHECKs divergentes em '.$table.'.';
    }
    foreach (['engine','charset','collation'] as $attribute) {
        if ($expected[$attribute] !== $actual[$attribute]) {
            $errors[] = ucfirst($attribute).' divergente em '.$table.': consolidado ['.$expected[$attribute].'], módulo ['.$actual[$attribute].']. ';
        }
    }
}

if ($errors !== []) {
    foreach (array_values(array_unique($errors)) as $error) fwrite(STDERR, '[FALHA] '.$error.PHP_EOL);
    exit(1);
}
echo '[OK] Paridade modular: '.count($modules).' tabelas com colunas, tipos, padrões, índices, constraints, engine e collation equivalentes ao schema consolidado.'.PHP_EOL;

/** @return array<string,array{columns:array<string,string>,indexes:array<string,string>,constraints:list<string>,engine:string,charset:string,collation:string}> */
function parseSchemaFile(string $path, array &$errors): array
{
    $sql = is_file($path) ? (string)file_get_contents($path) : '';
    if ($sql === '') {
        $errors[] = 'SQL ausente ou vazio: '.$path.'.';
        return [];
    }
    preg_match_all('/CREATE\s+TABLE\s+IF\s+NOT\s+EXISTS\s+`?([A-Za-z0-9_]+)`?\s*\((.*?)\)\s*ENGINE\s*=\s*([A-Za-z0-9_]+)([^;]*);/is', $sql, $matches, PREG_SET_ORDER);
    $tables = [];
    foreach ($matches as $match) {
        $table = strtolower((string)$match[1]);
        if (isset($tables[$table])) {
            $errors[] = 'Tabela duplicada em '.basename($path).': '.$table.'.';
            continue;
        }
        $contract=parseTableBody((string)$match[2]);
        $tail=(string)($match[4]??'');
        preg_match('/(?:DEFAULT\s+)?CHARSET\s*=\s*([A-Za-z0-9_]+)/i',$tail,$charset);
        preg_match('/COLLATE\s*=\s*([A-Za-z0-9_]+)/i',$tail,$collation);
        $contract['engine']=strtolower((string)$match[3]);
        $contract['charset']=strtolower((string)($charset[1]??''));
        $contract['collation']=strtolower((string)($collation[1]??''));
        $tables[$table] = $contract;
    }
    return $tables;
}

/** @return array{columns:array<string,string>,indexes:array<string,string>,constraints:list<string>} */
function parseTableBody(string $body): array
{
    $columns = [];
    $indexes = [];
    $constraints = [];
    foreach (splitTopLevel($body) as $definition) {
        $definition = trim($definition);
        if ($definition === '') continue;
        if (preg_match('/^(UNIQUE\s+)?(?:INDEX|KEY)\s+`?([A-Za-z0-9_]+)`?\s*\((.*?)\)/is', $definition, $index)) {
            $indexes[strtolower($index[2])] = (!empty($index[1]) ? 'unique:' : 'index:').normalizeSql($index[3]);
            continue;
        }
        if (preg_match('/^PRIMARY\s+KEY\s*\((.*?)\)/is', $definition, $primary)) {
            $indexes['primary'] = 'unique:'.normalizeSql($primary[1]);
            continue;
        }
        if (preg_match('/^(?:CONSTRAINT|FOREIGN\s+KEY|CHECK)\b/i', $definition)) {
            $constraints[]=normalizeSql($definition);
            continue;
        }
        if (!preg_match('/^`?([A-Za-z0-9_]+)`?\s+(.+)$/s', $definition, $column)) continue;
        $columns[strtolower($column[1])] = normalizeColumnContract($column[2]);
    }
    ksort($columns);
    ksort($indexes);
    sort($constraints);
    return ['columns'=>$columns, 'indexes'=>$indexes, 'constraints'=>$constraints];
}

/** @return list<string> */
function splitTopLevel(string $sql): array
{
    $parts = [];
    $buffer = '';
    $depth = 0;
    $quote = '';
    $length = strlen($sql);
    for ($i = 0; $i < $length; $i++) {
        $char = $sql[$i];
        if ($quote !== '') {
            $buffer .= $char;
            if ($char === $quote && ($i === 0 || $sql[$i - 1] !== '\\')) $quote = '';
            continue;
        }
        if ($char === "'" || $char === '"') {
            $quote = $char;
            $buffer .= $char;
            continue;
        }
        if ($char === '(') $depth++;
        if ($char === ')') $depth--;
        if ($char === ',' && $depth === 0) {
            $parts[] = $buffer;
            $buffer = '';
            continue;
        }
        $buffer .= $char;
    }
    if (trim($buffer) !== '') $parts[] = $buffer;
    return $parts;
}

function normalizeColumnContract(string $definition): string
{
    $normalized = normalizeSql($definition);
    $normalized = preg_replace('/\b(tinyint|smallint|mediumint|int|bigint)\(\d+\)/i', '$1', $normalized) ?? $normalized;
    return $normalized;
}

function normalizeSql(string $sql): string
{
    $sql = strtolower(trim($sql));
    $sql = str_replace('`', '', $sql);
    $sql = preg_replace('/\s+/', ' ', $sql) ?? $sql;
    $sql = preg_replace('/\s*,\s*/', ',', $sql) ?? $sql;
    $sql = preg_replace('/\s*\(\s*/', '(', $sql) ?? $sql;
    return preg_replace('/\s*\)\s*/', ')', $sql) ?? $sql;
}
