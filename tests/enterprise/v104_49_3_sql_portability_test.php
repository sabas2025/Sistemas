<?php
declare(strict_types=1);
require __DIR__.'/_helpers.php';
$checks = [];

/**
 * Portabilidade de SQL preparado — achados I-09 e I-10 (auditoria de 2026-09-15).
 *
 * O Hub abre toda conexão com PDO::ATTR_EMULATE_PREPARES => false (app/Core/Database.php). Com
 * prepares NATIVOS o servidor recusa placeholder em sentença que não é consulta de dados:
 * `SHOW TABLES LIKE ?` e `SHOW COLUMNS FROM t LIKE ?` falham com "near '?'". Medido nas duas
 * configurações contra MariaDB 10.11: falha com nativos, passa com emulados.
 *
 * O estrago não era um erro visível, era o contrário. Quase todo ponto envolvia a chamada em
 * catch(Throwable) devolvendo false — ou seja, "a tabela não existe". Medido em runtime, quatro
 * serviços declaravam AUSENTE tabela que existia (QueueV24Analytics, TinyV2Observability,
 * AuditIntegrity, VsmFichaTecnica), e o teste pós-instalação reprovava as cinco tabelas centrais
 * de QUALQUER instalação. Indicador que mente na pior direção: diz que está quebrado o que está
 * inteiro.
 */

// --------------------------------------------------- I-10: nenhum placeholder em SHOW/DESCRIBE
$raiz = hub_root();
$arquivos = [];
foreach (['app', 'workers', 'public', 'scripts'] as $dir) {
    $caminho = $raiz.'/'.$dir;
    if (!is_dir($caminho)) continue;
    $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($caminho, FilesystemIterator::SKIP_DOTS));
    foreach ($it as $f) { if ($f->isFile() && $f->getExtension() === 'php') $arquivos[] = $f->getPathname(); }
}
sort($arquivos);

$ofensores = [];
foreach ($arquivos as $arquivo) {
    foreach (explode("\n", (string)file_get_contents($arquivo)) as $n => $linha) {
        $t = ltrim($linha);
        if (str_starts_with($t, '//') || str_starts_with($t, '*') || str_starts_with($t, '#')) continue;
        if (preg_match('/prepare\s*\(\s*["\'](?:SHOW|DESCRIBE|DESC|EXPLAIN)[^"\']*\?/i', $linha)) {
            $ofensores[] = ltrim(str_replace($raiz, '', $arquivo), '/').':'.($n + 1);
        }
    }
}
// Achado I-20 (auto-auditoria): ver a nota em v104_49_3_retencao_fila_test.php. Sem esta linha,
// a afirmação "nenhum placeholder" ficaria verde mesmo se a varredura não lesse arquivo nenhum.
hub_check($checks, 'A varredura leu arquivos de verdade: '.count($arquivos), count($arquivos) > 100);
hub_check($checks, 'Nenhum placeholder em SHOW/DESCRIBE/EXPLAIN: '.(implode(', ', $ofensores) ?: 'nenhum'), $ofensores === []);

hub_check($checks, 'O Hub realmente usa prepares nativos (é o que torna o padrão inválido)',
    str_contains(hub_read('app/Core/Database.php'), 'PDO::ATTR_EMULATE_PREPARES => false'));

// Os pontos corrigidos passaram a usar o helper central, e não uma cópia paralela.
foreach ([
    'app/Services/QueueV24AnalyticsService.php',
    'app/Services/TinyV2ObservabilityService.php',
    'app/Services/AuditIntegrityService.php',
    'app/Services/VsmFichaTecnicaService.php',
    'app/Services/OperationCenterService.php',
    'app/Services/PostInstallTestService.php',
    'app/Services/ProductionReadinessV24Service.php',
] as $arquivo) {
    hub_check($checks, "Existência de tabela via helper central em {$arquivo}",
        str_contains(hub_read($arquivo), 'Database::tableExists'));
}
foreach (['app/Services/SafeSqlUpgradeService.php', 'app/Services/AutoHomologationService.php'] as $arquivo) {
    hub_check($checks, "Variante com PDO explícito usa o helper central em {$arquivo}",
        str_contains(hub_read($arquivo), 'Database::tableExistsOn') || str_contains(hub_read($arquivo), 'Database::columnExistsOn'));
}

// --------------------------------------------- I-09: escopo não gruda WHERE em sentença que não é dado
require_once $raiz.'/app/Services/TenantScopeService.php';
if (!class_exists('TenantContextService')) {
    eval('class TenantContextService { public static function currentEmpresaId(): ?int { return 9; } }');
}
foreach ([
    "SHOW COLUMNS FROM fila_integracao LIKE 'status'",
    'DESCRIBE fila_integracao',
    'EXPLAIN SELECT * FROM fila_integracao',
] as $sentenca) {
    [$sql] = TenantScopeService::applyToSelect('fila_integracao', $sentenca, []);
    hub_check($checks, 'Escopo não altera sentença que não é consulta de dados: '.substr($sentenca, 0, 34), $sql === $sentenca);
}
foreach ([
    'SELECT * FROM fila_integracao',
    '  SELECT * FROM fila_integracao',
    '/* nota */ SELECT * FROM fila_integracao',
    'UPDATE fila_integracao SET status=? WHERE id=?',
    'DELETE FROM fila_integracao WHERE id=?',
] as $sentenca) {
    [$sql] = TenantScopeService::applyToSelect('fila_integracao', $sentenca, []);
    hub_check($checks, 'Escopo CONTINUA sendo aplicado a: '.substr(ltrim($sentenca), 0, 34), $sql !== $sentenca);
}

hub_finish($checks);
