<?php
declare(strict_types=1);
require __DIR__.'/_helpers.php';
$checks = [];

/**
 * Paridade de índices entre INSTALAÇÃO NOVA e ATUALIZAÇÃO — achado I-18 (2026-09-15).
 *
 * Medido num MariaDB real, aplicando os dois caminhos em bancos separados e comparando o schema
 * resultante: as 1.550 colunas batiam exatamente, e os índices divergiam em **um**:
 *
 *   idx_fila_ready                     (status, proxima_tentativa, prioridade, id)  ← só na atualização
 *   idx_fila_status_proxima_prioridade (status, proxima_tentativa, prioridade, id)  ← nos dois
 *
 * Mesmas colunas, mesma ordem, ambos NÃO-únicos: o mesmo índice com dois nomes. A migration
 * `20260710_001` criou o primeiro; dois dias depois a `20260712_001` criou o segundo, e foi este
 * que entrou nos módulos. Resultado: toda instalação que rodou a cadeia mantinha a MESMA árvore B
 * duas vezes em `fila_integracao` — a tabela de escrita mais quente do Hub.
 *
 * Esta verificação é ESTÁTICA e cobre a classe: índice criado por migration que repete, na mesma
 * tabela e na mesma ordem, colunas de um índice já declarado nos módulos. Ela conta os DROPs: um
 * índice criado e depois removido por migration posterior não é divergência.
 *
 * O QUE ELA NÃO PROVA: paridade de COLUNAS, nem índice duplicado dentro do próprio módulo. Para o
 * primeiro, aplique os dois caminhos e compare `information_schema` — foi assim que este achado
 * apareceu, e nenhum portão estático o encontraria.
 */
$raiz = hub_root();

/** @return array<string,array<string,string>> tabela => [nome do índice => colunas normalizadas] */
$indicesDosModulos = static function () use ($raiz): array {
    $mapa = [];
    foreach (glob($raiz.'/database/modules/*.sql') ?: [] as $arquivo) {
        $sql = (string)file_get_contents($arquivo);
        // corpo de cada CREATE TABLE
        if (!preg_match_all('/CREATE\s+TABLE\s+(?:IF\s+NOT\s+EXISTS\s+)?`?([a-z0-9_]+)`?\s*\((.*?)\n\)\s*ENGINE/is', $sql, $ms, PREG_SET_ORDER)) continue;
        foreach ($ms as $m) {
            $tabela = strtolower($m[1]);
            if (!preg_match_all('/\b(?:UNIQUE\s+)?(?:INDEX|KEY)\s+`?([a-z0-9_]+)`?\s*\(([^)]*)\)/i', $m[2], $idx, PREG_SET_ORDER)) continue;
            foreach ($idx as $i) {
                $cols = implode(',', array_map(
                    static fn(string $c): string => strtolower(trim($c, " `\t\r\n")),
                    explode(',', $i[2])
                ));
                $mapa[$tabela][strtolower($i[1])] = $cols;
            }
        }
    }
    return $mapa;
};

$modulos = $indicesDosModulos();
hub_check($checks, 'Índices lidos dos módulos: '.array_sum(array_map('count', $modulos)), array_sum(array_map('count', $modulos)) > 50);

// Índices que as migrations criam, e os que elas removem — em ordem de nome de arquivo.
$criados = [];
$removidos = [];
foreach (glob($raiz.'/database/migrations/*.sql') ?: [] as $arquivo) {
    $sql = (string)file_get_contents($arquivo);
    $base = basename($arquivo);
    if (preg_match_all('/CREATE\s+(?:UNIQUE\s+)?INDEX\s+(?:IF\s+NOT\s+EXISTS\s+)?`?([a-z0-9_]+)`?\s+ON\s+`?([a-z0-9_]+)`?\s*\(([^)]*)\)/i', $sql, $ms, PREG_SET_ORDER)) {
        foreach ($ms as $m) {
            $cols = implode(',', array_map(static fn(string $c): string => strtolower(trim($c, " `\t\r\n")), explode(',', $m[3])));
            $criados[strtolower($m[2]).'|'.strtolower($m[1])] = ['tabela'=>strtolower($m[2]), 'indice'=>strtolower($m[1]), 'cols'=>$cols, 'arquivo'=>$base];
        }
    }
    if (preg_match_all('/DROP\s+INDEX\s+`?([a-z0-9_]+)`?\s+ON\s+`?([a-z0-9_]+)`?/i', $sql, $md, PREG_SET_ORDER)) {
        foreach ($md as $m) $removidos[strtolower($m[2]).'|'.strtolower($m[1])] = $base;
    }
}
hub_check($checks, 'Índices criados por migration lidos: '.count($criados), count($criados) > 0);

$divergentes = [];
foreach ($criados as $chave => $c) {
    if (isset($removidos[$chave])) continue; // criado e depois removido: resolvido
    foreach ($modulos[$c['tabela']] ?? [] as $nomeModulo => $colsModulo) {
        if ($nomeModulo === $c['indice']) continue;      // mesmo índice, só declarado nos dois
        if ($colsModulo !== $c['cols']) continue;        // colunas diferentes: não é duplicata
        $divergentes[] = "{$c['arquivo']} cria {$c['tabela']}.{$c['indice']}({$c['cols']}), que repete {$nomeModulo} dos módulos";
    }
}
hub_check($checks, 'Nenhuma migration cria índice duplicado do que já está nos módulos: '.(implode(' | ', $divergentes) ?: 'nenhuma'), $divergentes === []);

// O ponto exato do I-18, travado por nome.
$dedup = hub_read('database/migrations/20260915_015_indice_fila_duplicado.sql');
hub_check($checks, 'A migration de desduplicação existe e é idempotente',
    str_contains($dedup, 'DROP INDEX idx_fila_ready ON fila_integracao') && str_contains($dedup, 'information_schema.STATISTICS'));
hub_check($checks, 'O DROP é condicionado ao índice canônico existir (nunca fica sem caminho de acesso)',
    str_contains($dedup, '@canonico') && str_contains($dedup, "INDEX_NAME = 'idx_fila_status_proxima_prioridade'"));
hub_check($checks, 'A migration se registra com as colunas REAIS de schema_migrations',
    str_contains($dedup, 'schema_migrations(migration,version,description,status)'));
hub_check($checks, 'O índice canônico continua declarado nos módulos',
    ($modulos['fila_integracao']['idx_fila_status_proxima_prioridade'] ?? '') === 'status,proxima_tentativa,prioridade,id');
hub_check($checks, 'O duplicado NÃO foi levado para os módulos',
    !isset($modulos['fila_integracao']['idx_fila_ready']));

// ------------------------------------------------- I-19: o único gargalo MEDIDO da auditoria
// `IntegrationEventService::onQueueFinished()` filtra integration_events por fila_id duas vezes
// (um UPDATE e um SELECT) e é chamada a CADA item de fila concluído. A coluna não tinha índice.
// Medido com 150 mil linhas: SELECT 43ms -> 9ms, UPDATE 87ms -> 9ms, plano de varredura para ref.
hub_check($checks, 'O índice de fila_id está nos MÓDULOS (senão a instalação nova nasce sem ele)',
    ($modulos['integration_events']['idx_ie_fila'] ?? '') === 'fila_id,id');
$mig = hub_read('database/migrations/20260916_016_indice_integration_events_fila.sql');
hub_check($checks, 'A migration correspondente existe, é idempotente e guardada',
    str_contains($mig, 'CREATE INDEX idx_ie_fila ON integration_events(fila_id, id)')
    && str_contains($mig, "INDEX_NAME = 'idx_ie_fila'"));
hub_check($checks, 'A migration registra o motivo medido, não a suspeita',
    str_contains($mig, '43 ms -> 9 ms') && str_contains($mig, '87 ms -> 9 ms'));
hub_check($checks, 'O consultante continua filtrando por fila_id (o índice segue justificado)',
    str_contains(hub_read('app/Services/IntegrationEventService.php'), 'WHERE fila_id=?'));
// O contra-exemplo: evento_correlacao varre igual, mas NENHUMA consulta do Hub a filtra por
// fila_id — indexá-la seria custo de escrita sem leitura que justifique.
hub_check($checks, 'evento_correlacao NÃO ganhou índice de fila_id (ninguém a consulta assim)',
    !isset($modulos['evento_correlacao']['idx_evento_correlacao_fila'])
    && !preg_match('/WHERE[^;\'"]*fila_id[^;\'"]*FROM evento_correlacao/i', hub_read('app/Services/IntegrationEventService.php')));

hub_finish($checks);
