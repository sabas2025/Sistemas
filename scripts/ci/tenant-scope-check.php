<?php
declare(strict_types=1);
/**
 * Melhoria 1 da seção 8 (relatório V104.49.3-R6): verificação estática do isolamento multiempresa.
 *
 * O isolamento não pode depender de disciplina. A lacuna original (colunas empresa_id existindo e
 * nenhuma consulta filtrando por elas) sobreviveu tanto tempo justamente porque nada cobrava.
 *
 * Esta checagem varre app/ e workers/, encontra toda consulta às tabelas do catálogo de
 * TenantScopeService e exige que cada uma:
 *   (a) aplique o predicado do serviço (TenantScopeService::where/whereStrict/stamp/assertRow), ou
 *   (b) cite empresa_id explicitamente na própria consulta, ou
 *   (c) esteja na lista de EXCEÇÕES abaixo, com justificativa escrita.
 *
 * Uma consulta nova a tabela com escopo reprova a CI até que alguém decida a qual caso ela pertence.
 */
if (PHP_SAPI !== 'cli') { fwrite(STDERR, "[FALHA] Somente CLI.\n"); exit(1); }

$root = dirname(__DIR__, 2);
require_once $root.'/app/Services/TenantScopeService.php';

/**
 * Exceções: consulta a tabela com escopo que é legitimamente GLOBAL à instalação.
 * Formato: 'caminho/relativo.php' => 'motivo'. Vale para o arquivo inteiro.
 *
 * Regra para acrescentar: só entra aqui o que opera sobre a INSTALAÇÃO, não sobre os dados de uma
 * empresa — worker que drena a fila de todos, diagnóstico de schema, métrica agregada de operação,
 * verificação de integridade. Tela que um usuário abre para ver SEUS dados nunca entra aqui.
 */
const EXCECOES = [
    // --- Infraestrutura de fila: processam trabalho de todas as empresas, por definição. ---
    'workers/worker_fila.php'                        => 'Worker de fila: drena a fila da instalação inteira; escopo por empresa tornaria itens de outras empresas eternos.',
    'workers/worker_estoque.php'                     => 'Worker de estoque: processamento em lote da instalação.',
    'workers/worker_fiscal.php'                      => 'Worker fiscal: processamento em lote da instalação.',
    'workers/worker_reconciliacao.php'               => 'Worker de reconciliação: varredura da instalação.',
    'workers/worker_notificacoes.php'                => 'Worker de notificações: varre pendências da instalação.',
    'workers/worker_xml_nfe.php'                     => 'Worker de XML: processamento em lote da instalação.',
    'workers/worker_consulta_estoque_vsm.php'        => 'Worker de consulta VSM: execução agendada da instalação.',
    'workers/worker_backup.php'                      => 'Worker de backup: opera sobre o banco inteiro.',
    'workers/worker_homologacao.php'                 => 'Worker de homologação: cenário de teste da instalação.',
    'workers/worker_selftest.php'                    => 'Autoteste: verifica a instalação, não dados de cliente.',
    'app/Services/QueueService.php'                  => 'Motor da fila: reserva e leasing são da instalação; o escopo é carimbado no item (stamp) e aplicado na leitura pelas telas.',
    'app/Services/DeadLetterQueueService.php'        => 'Fila morta: operação de infraestrutura sobre a instalação.',
    'app/Services/QueueBackpressureService.php'      => 'Contrapressão: mede a PROFUNDIDADE da fila da instalação para alarmar o operador. Recortar por empresa esconderia justamente o acúmulo global, que é o que importa aqui — não lê dado de cliente, só conta linhas por status.',

    // --- Diagnóstico, integridade e métrica agregada da instalação. ---
    'app/Services/SchemaMigrationService.php'        => 'Migrações: opera sobre o schema, não sobre dados de empresa.',
    'app/Services/SelfTestService.php'               => 'Autoteste da instalação.',
    'app/Services/DashboardIntegrityService.php'     => 'Verificação de integridade do banco da instalação.',
    'app/Services/RealtimeHealthService.php'         => 'Saúde operacional da instalação (contagens agregadas).',
    'app/Services/QueueAnalyticsService.php'         => 'Métrica agregada de fila da instalação.',
    'app/Services/QueueV24AnalyticsService.php'      => 'Métrica agregada de fila da instalação.',
    'app/Services/HeavyQueryOptimizerService.php'    => 'Diagnóstico de desempenho de consulta sobre o banco da instalação.',
    'app/Services/DataRetentionService.php'          => 'Retenção de dados: expurgo por idade em toda a instalação.',
    'app/Services/RetentionService.php'              => 'Limpeza operacional por idade: opera sobre a instalação inteira; recortar por empresa deixaria lixo eterno das demais.',
    'app/Services/EnterpriseIdempotencyGuardService.php' => 'Guarda de idempotência: chave global da instalação, por definição não pode ser por empresa.',
    'app/Services/EnterpriseObservabilityService.php'=> 'Observabilidade agregada da instalação.',
    'app/Services/BackupService.php'                 => 'Backup e restauração operam sobre o banco inteiro.',
    'app/Services/BackupSchemaService.php'           => 'Manutenção de schema de backup.',
    'app/Services/DatabaseValidationService.php'     => 'Validação de banco da instalação.',
    'app/Services/ProductionReadinessV24Service.php' => 'Prontidão da instalação.',

    // --- Manutenção estrutural: iteram sobre nomes de tabela para verificar/limpar a instalação. ---
    'app/Services/InstallationRecoveryService.php'   => 'Recuperação de instalação: sonda a EXISTÊNCIA de tabelas (SELECT 1 ... LIMIT 1), não lê dado de empresa.',
    'app/Services/RetentionCleanupService.php'       => 'Expurgo por retenção: apaga por idade em toda a instalação; recortar por empresa deixaria lixo eterno das demais.',
    'app/Services/SafeSqlUpgradeService.php'         => 'Atualização assistida de schema: opera sobre estrutura, não sobre dados de empresa.',

    // --- Legado desligado (melhoria 10): não é alcançável pelo roteamento. ---
    'app/Controllers/LegacyDatabaseUpgradeController.php' => 'Controller depreciado e desligado (melhoria 10); recusa execução antes de qualquer consulta.',
];

$alvos = TenantScopeService::scopedTables();
$padraoTabelas = implode('|', array_map('preg_quote', $alvos));

$arquivos = [];
foreach ([$root.'/app', $root.'/workers'] as $dir) {
    if (!is_dir($dir)) continue;
    $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($dir, FilesystemIterator::SKIP_DOTS));
    foreach ($it as $f) { if ($f->isFile() && $f->getExtension() === 'php') $arquivos[] = $f->getPathname(); }
}
sort($arquivos);

$violacoes = [];
$cobertas = 0;
$isentas = 0;

foreach ($arquivos as $arquivo) {
    $rel = ltrim(str_replace($root, '', $arquivo), '/');
    $conteudo = (string)file_get_contents($arquivo);
    $linhas = explode("\n", $conteudo);

    foreach ($linhas as $n => $linha) {
        if (!preg_match('/\b(?:FROM|JOIN|INTO|UPDATE)\s+`?('.$padraoTabelas.')`?\b/i', $linha, $m)) continue;
        // Comentário puro não é consulta.
        $semEspaco = ltrim($linha);
        if (str_starts_with($semEspaco, '//') || str_starts_with($semEspaco, '*') || str_starts_with($semEspaco, '#')) continue;

        $tabela = strtolower($m[1]);
        if (isset(EXCECOES[$rel])) { $isentas++; continue; }

        // Janela de contexto: a aplicação do escopo costuma estar na linha da consulta ou perto
        // dela (montagem do WHERE, concatenação do predicado, carimbo do INSERT).
        $ini = max(0, $n - 6);
        $fim = min(count($linhas) - 1, $n + 6);
        $janela = implode("\n", array_slice($linhas, $ini, $fim - $ini + 1));

        if (str_contains($janela, 'TenantScopeService')) { $cobertas++; continue; }
        if (preg_match('/\bempresa_id\b/i', $janela)) { $cobertas++; continue; }

        $violacoes[] = ['arquivo' => $rel, 'linha' => $n + 1, 'tabela' => $tabela, 'trecho' => trim(substr($semEspaco, 0, 130))];
    }

    // SQL com o NOME DA TABELA INTERPOLADO escapa da varredura acima, que procura a tabela
    // literal. Foi assim que DashboardController::count()/tableRows() — que servem nove tabelas
    // com escopo através de SAFE_TABLES — passaram despercebidos na primeira passagem. Aqui
    // exigimos que toda consulta desse formato também demonstre tratamento de escopo.
    foreach ($linhas as $n => $linha) {
        if (!preg_match('/\b(?:FROM|JOIN|INTO|UPDATE)\s+`?\{?\$[A-Za-z_]/', $linha)) continue;
        $semEspaco = ltrim($linha);
        if (str_starts_with($semEspaco, '//') || str_starts_with($semEspaco, '*') || str_starts_with($semEspaco, '#')) continue;
        if (isset(EXCECOES[$rel])) { $isentas++; continue; }
        $ini = max(0, $n - 8);
        $fim = min(count($linhas) - 1, $n + 8);
        $janela = implode("\n", array_slice($linhas, $ini, $fim - $ini + 1));
        if (str_contains($janela, 'TenantScopeService')) { $cobertas++; continue; }
        if (preg_match('/\bempresa_id\b/i', $janela)) { $cobertas++; continue; }
        $violacoes[] = ['arquivo' => $rel, 'linha' => $n + 1, 'tabela' => '(tabela interpolada)', 'trecho' => trim(substr($semEspaco, 0, 130))];
    }
}

/**
 * Passagem ESTRITA para GRAVAÇÃO (achado I-02, 2026-09-15).
 *
 * A passagem acima aceita a evidência em qualquer lugar de uma janela de ±6 linhas. Em arquivo
 * denso — FilaController tem métodos inteiros numa linha só — essa janela alcança OUTRO método, e
 * o portão dava por coberta uma gravação crua. Medido: `FilaController.php:7` gravava em
 * `fila_integracao` com `$pdo->prepare(...)` e passava, porque a linha 5 (outro método) cita
 * TenantScopeService; `EstoqueVsmSchedulerService.php:210` passava pela linha 216, que trata de
 * OUTRA tabela. Duas linhas nasciam sem empresa com o portão verde.
 *
 * Aqui a régua é outra, e só vale para INSERT/REPLACE/UPDATE:
 *   - a evidência tem de estar na PRÓPRIA sentença (mesma linha, ±400 caracteres) ou nas 4 linhas
 *     SEGUINTES — que é o padrão real de `$sql = "INSERT ..."` seguido de `applyToInsert($sql)`;
 *   - para trás não vale nada: é de onde vinha o falso negativo;
 *   - quando a evidência é uma chamada ao serviço, a TABELA citada tem de ser a mesma.
 */
$evidencia = static function (string $trecho, string $tabela): bool {
    // Chamada ao serviço citando a MESMA tabela (ou uma variável, caso tratado na passagem 2).
    if (preg_match('/TenantScopeService::[A-Za-z]+\s*\(\s*(?:[\'"]'.preg_quote($tabela, '/').'[\'"]|\$)/i', $trecho)) return true;
    // empresa_id escrito na própria consulta.
    return (bool)preg_match('/\bempresa_id\b/i', $trecho);
};

$violacoesEscrita = [];
$escritasCobertas = 0;
foreach ($arquivos as $arquivo) {
    $rel = ltrim(str_replace($root, '', $arquivo), '/');
    if (isset(EXCECOES[$rel])) continue;
    $linhas = explode("\n", (string)file_get_contents($arquivo));
    foreach ($linhas as $n => $linha) {
        $semEspaco = ltrim($linha);
        if (str_starts_with($semEspaco, '//') || str_starts_with($semEspaco, '*') || str_starts_with($semEspaco, '#')) continue;
        if (!preg_match_all('/\b(?:INSERT\s+(?:IGNORE\s+)?INTO|REPLACE\s+INTO|UPDATE)\s+`?('.$padraoTabelas.')`?\b/i', $linha, $ms, PREG_OFFSET_CAPTURE | PREG_SET_ORDER)) continue;
        $adiante = implode("\n", array_slice($linhas, $n + 1, 4));
        foreach ($ms as $m) {
            $tabela = strtolower($m[1][0]);
            $off = (int)$m[0][1];
            $janela = substr($linha, max(0, $off - 400), 800)."\n".$adiante;
            if ($evidencia($janela, $tabela)) { $escritasCobertas++; continue; }
            $violacoesEscrita[] = ['arquivo' => $rel, 'linha' => $n + 1, 'tabela' => $tabela,
                'trecho' => trim(substr(ltrim(substr($linha, max(0, $off - 60))), 0, 150))];
        }
    }
}

/**
 * Passagem 4: JOIN sem ALIAS (achado I-08, 2026-09-15).
 *
 * `where()` monta o predicado com o prefixo que recebe em `$alias`. Sem alias ele injeta
 * `empresa_id = ?` puro — e quando a consulta faz JOIN com OUTRA tabela que também tem a coluna, o
 * banco recusa a consulta inteira: "Column 'empresa_id' in WHERE is ambiguous". A tela não mostra
 * dado errado, ela para de funcionar; e só quando alguém tem empresa atribuída, porque sem empresa
 * na sessão o predicado nem entra. Medido: a tela Fiscal quebrava assim, e eram QUATRO consultas,
 * não uma — duas em Fiscal, duas em FiscalEnterpriseService.
 *
 * A checagem só cobra alias quando há conflito real: JOIN com tabela que TAMBÉM está no catálogo.
 */
$violacoesJoin = [];
$comEscopo = array_flip($alvos);
foreach ($arquivos as $arquivo) {
    $rel = ltrim(str_replace($root, '', $arquivo), '/');
    if (isset(EXCECOES[$rel])) continue;
    foreach (explode("\n", (string)file_get_contents($arquivo)) as $n => $linha) {
        if (!preg_match_all('/TenantScopeService::(?:run|applyToSelect)\s*\(\s*([\'"])([a-z_]+)\1\s*,\s*([\'"])/i', $linha, $ms, PREG_OFFSET_CAPTURE | PREG_SET_ORDER)) continue;
        foreach ($ms as $m) {
            $aspa = $m[3][0];
            $ini = (int)$m[3][1];
            $sql = '';
            for ($k = $ini + 1, $len = strlen($linha); $k < $len; $k++) {
                $c = $linha[$k];
                if ($c === '\\') { $k++; continue; }
                if ($c === $aspa) break;
                $sql .= $c;
            }
            if (!preg_match('/\bJOIN\b/i', $sql)) continue;
            preg_match_all('/\bJOIN\s+`?([a-z_]+)`?/i', $sql, $js);
            $conflito = array_values(array_filter($js[1], static fn(string $t): bool => isset($comEscopo[strtolower($t)])));
            if ($conflito === []) continue;
            $depois = substr($linha, $ini + strlen($sql) + 2, 200);
            if (preg_match('/^\s*,\s*(\[[^\]]*\]|\$[A-Za-z_]+)\s*,\s*[\'"][A-Za-z_]+[\'"]/', $depois)) continue;
            $violacoesJoin[] = ['arquivo' => $rel, 'linha' => $n + 1, 'tabela' => $m[2][0],
                'conflito' => implode(', ', $conflito), 'trecho' => trim(substr($sql, 0, 120))];
        }
    }
}

echo "Tabelas com escopo no catálogo: ".count($alvos)."\n";
echo "Consultas cobertas pelo escopo: {$cobertas}\n";
echo "Consultas em arquivos isentos:  {$isentas}\n";

echo "Gravações cobertas (régua estrita):  {$escritasCobertas}\n";

if ($violacoes === [] && $violacoesEscrita === [] && $violacoesJoin === []) {
    echo "[OK] Nenhuma consulta a tabela com escopo ficou sem isolamento nem justificativa.\n";
    exit(0);
}

if ($violacoesJoin !== []) {
    fwrite(STDERR, "[FALHA] ".count($violacoesJoin)." consulta(s) com JOIN passam pelo escopo SEM alias:\n\n");
    foreach ($violacoesJoin as $v) {
        fwrite(STDERR, sprintf("  %s:%d  (%s, JOIN com %s)\n      %s\n", $v['arquivo'], $v['linha'], $v['tabela'], $v['conflito'], $v['trecho']));
    }
    fwrite(STDERR, "\nSem alias o predicado vira 'empresa_id = ?' puro e o banco recusa a consulta\n");
    fwrite(STDERR, "(\"Column 'empresa_id' in WHERE is ambiguous\") — a tela quebra para quem tem empresa.\n");
    fwrite(STDERR, "Passe o alias da tabela principal: TenantScopeService::run('tabela', \$sql, \$params, 'i').\n\n");
}

if ($violacoesEscrita !== []) {
    fwrite(STDERR, "[FALHA] ".count($violacoesEscrita)." gravação(ões) em tabela com escopo sem isolamento NA PRÓPRIA SENTENÇA:\n\n");
    foreach ($violacoesEscrita as $v) {
        fwrite(STDERR, sprintf("  %s:%d  (%s)\n      %s\n", $v['arquivo'], $v['linha'], $v['tabela'], $v['trecho']));
    }
    fwrite(STDERR, "\nGravação sem escopo cria linha com empresa_id NULL, visível a todas as empresas.\n");
    fwrite(STDERR, "A evidência precisa estar na própria sentença ou nas 4 linhas seguintes, e citar a MESMA tabela.\n\n");
}

if ($violacoes === []) exit(1);

fwrite(STDERR, "[FALHA] ".count($violacoes)." consulta(s) a tabela com escopo de empresa sem isolamento:\n\n");
foreach ($violacoes as $v) {
    fwrite(STDERR, sprintf("  %s:%d  (%s)\n      %s\n", $v['arquivo'], $v['linha'], $v['tabela'], $v['trecho']));
}
fwrite(STDERR, "\nCada uma precisa de uma destas três coisas:\n");
fwrite(STDERR, "  1. aplicar TenantScopeService::where()/whereStrict() no WHERE, ou stamp() no INSERT;\n");
fwrite(STDERR, "  2. citar empresa_id explicitamente na consulta; ou\n");
fwrite(STDERR, "  3. entrar em EXCECOES neste arquivo, COM justificativa — só para operação da\n");
fwrite(STDERR, "     instalação inteira (worker, diagnóstico, métrica agregada), nunca para tela de usuário.\n");
exit(1);
