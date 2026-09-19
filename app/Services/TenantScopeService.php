<?php
/**
 * R7 build 20260917.1: escopo fechado. Sem contexto, leitura não retorna linhas e escrita falha.
 * Linhas NULL ficam fora do fluxo normal até classificação comprovada.
 * A instalação continua limitada a uma empresa; credenciais globais impedem liberar multicliente.
 */
class TenantScopeService {
  /**
   * Tabelas que guardam dados operacionais pertencentes a UMA empresa.
   *
   * Não entram aqui: catálogos globais (`empresas`, `usuarios`, `permissoes_perfil`), configuração
   * da instalação (`configuracoes_integracao`), infraestrutura de segurança (`security_events`,
   * `ips_bloqueados`, `rate_limit_hits`) e telemetria da instalação - todos legitimamente globais.
   * Acrescentar uma tabela aqui obriga toda consulta a ela a passar por este serviço, e a CI cobra.
   *
   * @var list<string>
   */
  private const SCOPED_TABLES = [
    'categorias_mapeamento',
    'estoque_alertas',
    'estoque_auditoria_sku',
    'estoque_divergencias',
    'estoque_movimentos',
    'estoque_reconciliacao',
    'estoque_saldos_cache',
    'fila_estoque',
    'fila_fiscal',
    'fila_integracao',
    'fila_morta',
    'logs_integracao',
    'nfe_integracao',
    'nfe_status_historico',
    'nfe_xml',
    'notas_fiscais',
    'notas_fiscais_eventos',
    'pedidos_hub',
    'pedidos_integracao',
    'pedidos_nfe_xml',
    'pedidos_payloads',
    'pedidos_status_historico',
    'pedidos_validacao',
    'pedidos_validacao_historico',
    'produto_pendencias',
    'produtos_aprovacao_historico',
    'produtos_mapeamento',
    'produtos_pendentes_integracao',
    'produtos_tiny',
    'produtos_vsm',
    'produtos_vsm_eventos',
    'reconciliacao_itens',
  ];

  public const COLUMN = 'empresa_id';

  /** @return list<string> */
  public static function scopedTables(): array { return self::SCOPED_TABLES; }

  public static function isScoped(string $table): bool {
    return in_array(strtolower(trim($table, '` ')), self::SCOPED_TABLES, true);
  }

  /** Empresa ativa, ou null quando a instalação é de empresa única / não há contexto. */
  public static function currentEmpresaId(): ?int {
    if (!class_exists('TenantContextService')) return null;
    try { return TenantContextService::currentEmpresaId(); }
    catch (Throwable $e) { return null; }
  }

  /** Não infere proprietário pelo número de empresas: o contexto externo vem do vínculo autenticado. */
  public static function empresaParaGravar(): ?int { return self::currentEmpresaId(); }

  /**
   * Predicado de isolamento para acrescentar a um WHERE já existente.
   *
   * @param string $alias prefixo da tabela na consulta ('p' vira 'p.empresa_id')
   * @return array{sql:string,params:list<int>}
   */
  public static function where(string $table, string $alias = ''): array {
    if (!self::isScoped($table)) return ['sql' => '', 'params' => []];
    $empresa = self::currentEmpresaId();
    if ($empresa === null) return ['sql' => ' AND 1=0', 'params' => []];
    $prefixo = $alias !== '' ? rtrim($alias, '.').'.' : '';
    return [
      'sql' => ' AND ('.$prefixo.self::COLUMN.' = ?)',
      'params' => [$empresa],
    ];
  }

  /**
   * Predicado estrito: exclui as linhas sem empresa. Use onde herdar o histórico legado seria pior
   * do que perder visibilidade dele - por exemplo, exportação entregue a um cliente.
   * @return array{sql:string,params:list<int>}
   */
  public static function whereStrict(string $table, string $alias = ''): array {
    if (!self::isScoped($table)) return ['sql' => '', 'params' => []];
    $empresa = self::currentEmpresaId();
    if ($empresa === null) return ['sql' => ' AND 1=0', 'params' => []];
    $prefixo = $alias !== '' ? rtrim($alias, '.').'.' : '';
    return ['sql' => ' AND '.$prefixo.self::COLUMN.' = ?', 'params' => [$empresa]];
  }

  /**
   * Carimba a empresa ativa num array de INSERT/UPDATE.
   * @param array<string,mixed> $data
   * @return array<string,mixed>
   */
  public static function stamp(string $table, array $data): array {
    if (!self::isScoped($table)) return $data;
    $empresa = self::empresaParaGravar();
    if ($empresa === null) throw new RuntimeException('TENANT_CONTEXT_REQUIRED: empresa não resolvida.');
    if (isset($data[self::COLUMN]) && (int)$data[self::COLUMN] !== $empresa) throw new RuntimeException('TENANT_SCOPE_VIOLATION');
    $data[self::COLUMN] = $empresa;
    return $data;
  }

  /**
   * Confere se uma linha já lida pertence à empresa ativa. Última linha de defesa para leitura por
   * chave primária, em que não existe WHERE onde acrescentar o predicado.
   *
   * @param array<string,mixed>|false|null $row
   */
  public static function assertRow(string $table, $row, string $contexto = ''): bool {
    if (!is_array($row) || !self::isScoped($table)) return true;
    $empresa = self::currentEmpresaId();
    if ($empresa === null) return false;
    if (!array_key_exists(self::COLUMN, $row)) return false;
    $linha = $row[self::COLUMN];
    if ($linha !== null && (int)$linha === $empresa) return true;
    if (class_exists('SecurityHealthService')) {
      SecurityHealthService::degrade('tenant_scope', 'Linha de outra empresa alcançada em '.$table.'.', ['tabela'=>$table,'contexto'=>$contexto]);
    }
    if (class_exists('Audit')) {
      try {
        Audit::event('tenant.vazamento_bloqueado','erro',[
          'codigo_erro'=>'TENANT_SCOPE_VIOLATION',
          'mensagem'=>'Leitura de linha pertencente a outra empresa foi bloqueada.',
          'entidade'=>$table,
          'contexto'=>['tabela'=>$table,'empresa_ativa'=>$empresa,'empresa_da_linha'=>(int)$linha,'origem'=>$contexto],
          'acao_recomendada'=>'Verifique a consulta de origem: ela deve usar TenantScopeService::where().'
        ]);
      } catch (Throwable $e) { if (class_exists('BestEffortLogService')) BestEffortLogService::warning(__METHOD__, $e); }
    }
    return false;
  }

  /** O isolamento está sendo efetivamente aplicado nesta requisição? */
  public static function ativo(): bool { return self::currentEmpresaId() !== null; }

  // ===================================================================================
  // Reescrita de consulta
  // ===================================================================================
  //
  // Aplicar o escopo à mão em ~160 pontos seria o caminho mais provável para introduzir defeito:
  // cada INSERT exigiria mexer na lista de colunas, na lista de VALUES e no array de parâmetros,
  // em ordem. Estes auxiliares fazem a transformação em UM lugar, testado, e o ponto de chamada
  // passa a ser uma linha só.
  //
  // O ponto delicado é a POSIÇÃO do parâmetro: PDO liga '?' por ordem, então inserir o predicado
  // no meio da consulta obriga a inserir o valor na posição correspondente do array — não no fim.
  // É o que countPlaceholders() resolve.

  /** Quantos '?' existem em $sql antes do offset $ate, ignorando os que estão dentro de string. */
  private static function countPlaceholders(string $sql, int $ate): int {
    $n = 0; $aspas = '';
    $limite = min($ate, strlen($sql));
    for ($i = 0; $i < $limite; $i++) {
      $c = $sql[$i];
      if ($aspas !== '') {
        if ($c === '\\') { $i++; continue; }
        if ($c === $aspas) $aspas = '';
        continue;
      }
      if ($c === "'" || $c === '"' || $c === '`') { $aspas = $c; continue; }
      if ($c === '?') $n++;
    }
    return $n;
  }

  /** Posição, fora de string/identificador, da primeira ocorrência de um dos padrões dados. */
  private static function findClauseOffset(string $sql, array $palavras): ?int {
    $aspas = ''; $len = strlen($sql); $nivel = 0;
    for ($i = 0; $i < $len; $i++) {
      $c = $sql[$i];
      if ($aspas !== '') {
        if ($c === '\\') { $i++; continue; }
        if ($c === $aspas) $aspas = '';
        continue;
      }
      if ($c === "'" || $c === '"' || $c === '`') { $aspas = $c; continue; }
      if ($c === '(') { $nivel++; continue; }
      if ($c === ')') { $nivel--; continue; }
      if ($nivel !== 0) continue;
      foreach ($palavras as $palavra) {
        $tam = strlen($palavra);
        if (strncasecmp(substr($sql, $i, $tam), $palavra, $tam) !== 0) continue;
        $antes = $i === 0 ? ' ' : $sql[$i - 1];
        $depois = $sql[$i + $tam] ?? ' ';
        // Precisa ser palavra isolada, senão "ORDERS" casaria com "ORDER".
        if (preg_match('/[A-Za-z0-9_]/', $antes) || preg_match('/[A-Za-z0-9_]/', $depois)) continue;
        return $i;
      }
    }
    return null;
  }

  /**
   * A sentença opera sobre LINHAS de dados, e portanto admite um predicado de empresa?
   *
   * Achado I-09 (2026-09-15): `applyToSelect()` acrescentava o predicado a qualquer coisa que não
   * fosse INSERT. `EnterpriseRegressionTestService` passa
   * `SHOW COLUMNS FROM fila_integracao LIKE 'status'` pelo serviço — introspecção de schema, que
   * não tem dono — e o resultado era
   * `SHOW COLUMNS ... LIKE 'status' WHERE (empresa_id = ? OR empresa_id IS NULL)`: **SQL inválido**.
   * As telas *Testes de Regressão Enterprise* e *Production Ready V25* exibiam o erro de sintaxe,
   * e só para quem tem empresa atribuída — sem empresa o predicado nem entra.
   *
   * Lista de PERMISSÃO, não de recusa: é mais seguro não tocar numa sentença desconhecida do que
   * grudar um WHERE nela. `SHOW`, `DESCRIBE`, `EXPLAIN` e DDL saem intactos.
   */
  private static function ehConsultaDeDados(string $sql): bool {
    // Pula espaços, parênteses de abertura e comentários antes do verbo.
    $limpo = $sql;
    do {
      $antes = $limpo;
      $limpo = ltrim($limpo, " \t\r\n(");
      $limpo = (string)preg_replace('/^(?:\/\*.*?\*\/|--[^\n]*\n|#[^\n]*\n)/s', '', $limpo, 1);
    } while ($limpo !== $antes);
    return (bool)preg_match('/^(SELECT|UPDATE|DELETE|WITH)\b/i', $limpo);
  }

  /**
   * Acrescenta o predicado de empresa a um SELECT/UPDATE/DELETE, inserindo o parâmetro na posição
   * correta. Consulta sem WHERE ganha um.
   *
   * @param list<mixed> $params
   * @return array{0:string,1:list<mixed>}
   */
  public static function applyToSelect(string $table, string $sql, array $params = [], string $alias = ''): array {
    if (!self::ehConsultaDeDados($sql)) return [$sql, $params];
    $escopo = self::where($table, $alias);
    if ($escopo['sql'] === '') return [$sql, $params];

    $fim = self::findClauseOffset($sql, ['GROUP BY', 'HAVING', 'ORDER BY', 'LIMIT', 'FOR UPDATE', 'ON DUPLICATE KEY']);
    $where = self::findClauseOffset($sql, ['WHERE']);

    if ($where === null) {
      // Sem WHERE: o predicado vira o WHERE, mas ainda antes de GROUP/ORDER/LIMIT.
      $prefixo = $alias !== '' ? rtrim($alias, '.').'.' : '';
      $clausula = ' WHERE '.substr($escopo['sql'], 5).' ';
      $corte = $fim ?? strlen($sql);
    } else {
      $clausula = $escopo['sql'].' ';
      $corte = $fim ?? strlen($sql);
      if ($fim !== null && $fim < $where) $corte = strlen($sql); // cláusula antes do WHERE: ignora
    }

    $posicao = self::countPlaceholders($sql, $corte);
    $inicio = rtrim(substr($sql, 0, $corte));
    if ($where !== null) {
      $condicao = trim(substr($sql,$where+5,$corte-$where-5));
      // SQL AND tem precedência sobre OR: sem parênteses o primeiro ramo escapava do tenant.
      if (self::findClauseOffset($condicao,['OR']) !== null) $inicio = substr($sql,0,$where+5).' ('.$condicao.')';
    }
    $novoSql = $inicio.$clausula.substr($sql, $corte);
    array_splice($params, $posicao, 0, $escopo['params']);
    // Não normalizar espaços dentro de strings SQL (JSON/texto podem depender deles).
    return [trim($novoSql), $params];
  }

  /**
   * Acrescenta empresa_id a um INSERT, na lista de colunas, na lista de VALUES e no array de
   * parâmetros — sempre na mesma posição relativa, e antes de qualquer '?' de
   * ON DUPLICATE KEY UPDATE.
   *
   * INSERT ... SELECT não é reescrito (a origem é outra consulta): devolve o SQL intacto, e a
   * verificação estática continua cobrando o tratamento explícito.
   *
   * @param list<mixed> $params
   * @return array{0:string,1:list<mixed>}
   */
  public static function applyToInsert(string $table, string $sql, array $params = []): array {
    // Antes isto perguntava a where(), que responde pela SESSÃO: fora dela o predicado vinha vazio
    // e o INSERT saía intacto — era assim que webhook e fila gravavam empresa_id NULL (H-01).
    if (!self::isScoped($table)) return [$sql, $params];
    $empresa = self::empresaParaGravar();
    if ($empresa === null) throw new RuntimeException('TENANT_CONTEXT_REQUIRED: escrita sem empresa bloqueada.');
    if (preg_match('/\b'.preg_quote(self::COLUMN, '/').'\b/i', $sql)) return [$sql, $params];

    // INSERT INTO tabela ( colunas ) VALUES ( ... )
    if (!preg_match('/\bINSERT\b[\s\S]*?\bINTO\b\s+`?'.preg_quote($table, '/').'`?\s*\(/i', $sql, $m, PREG_OFFSET_CAPTURE)) {
      return [$sql, $params];
    }
    $aberturaCols = (int)$m[0][1] + strlen($m[0][0]) - 1;
    $fimCols = self::matchParen($sql, $aberturaCols);
    if ($fimCols === null) return [$sql, $params];

    $posValues = stripos($sql, 'VALUES', $fimCols);
    if ($posValues === false) return [$sql, $params]; // INSERT ... SELECT
    $aberturaVals = strpos($sql, '(', $posValues);
    if ($aberturaVals === false) return [$sql, $params];
    $fimVals = self::matchParen($sql, $aberturaVals);
    if ($fimVals === null) return [$sql, $params];

    $posicao = self::countPlaceholders($sql, $fimVals);
    $novo = substr($sql, 0, $fimCols).','.self::COLUMN.substr($sql, $fimCols, $fimVals - $fimCols).',?'.substr($sql, $fimVals);
    array_splice($params, $posicao, 0, [$empresa]);
    return [$novo, $params];
  }

  /** Offset do ')' que fecha o '(' em $abertura, ignorando parênteses dentro de string. */
  private static function matchParen(string $sql, int $abertura): ?int {
    $nivel = 0; $aspas = ''; $len = strlen($sql);
    for ($i = $abertura; $i < $len; $i++) {
      $c = $sql[$i];
      if ($aspas !== '') {
        if ($c === '\\') { $i++; continue; }
        if ($c === $aspas) $aspas = '';
        continue;
      }
      if ($c === "'" || $c === '"' || $c === '`') { $aspas = $c; continue; }
      if ($c === '(') $nivel++;
      elseif ($c === ')') { $nivel--; if ($nivel === 0) return $i; }
    }
    return null;
  }

  /**
   * Prepara e executa uma consulta já com o escopo aplicado. É a forma preferida nos pontos de
   * chamada: substitui Database::forTable($t)->prepare($sql)->execute($params) por uma linha.
   *
   * @param list<mixed> $params
   */
  public static function run(string $table, string $sql, array $params = [], string $alias = ''): PDOStatement {
    $ehInsert = (bool)preg_match('/^\s*INSERT\b/i', $sql);
    [$sqlFinal, $paramsFinais] = $ehInsert
      ? self::applyToInsert($table, $sql, $params)
      : self::applyToSelect($table, $sql, $params, $alias);
    $st = Database::forTable($table)->prepare($sqlFinal);
    $st->execute($paramsFinais);
    return $st;
  }
}
