<?php
/**
 * Melhoria 1 da seção 8 (relatório V104.49.3-R6): isolamento de dados por empresa.
 *
 * Até a R6, `empresas` e `filiais` existiam no schema, `TenantContextService` guardava a empresa
 * ativa na sessão e `commercial.tenant_scope_required` BLOQUEAVA rotas operacionais sem empresa
 * selecionada - mas NENHUMA consulta filtrava por empresa. Ou seja: a tela sugeria um isolamento
 * que o banco não tinha. Era a maior lacuna estrutural do sistema, e silenciosa.
 *
 * Este serviço é a camada de isolamento. `TenantContextService` responde "qual empresa está
 * ativa"; este aqui responde "como aplicar isso a uma consulta".
 *
 * ## Contrato
 *
 * - `where()` devolve o predicado e os parâmetros a acrescentar numa consulta.
 * - `stamp()` carimba `empresa_id` num array de INSERT.
 * - Tabela fora do catálogo devolve predicado vazio: o catálogo é explícito, nunca adivinhado.
 * - **Sem empresa no contexto, o predicado é vazio** - a instalação de empresa única, que é o caso
 *   de hoje, continua funcionando exatamente como antes. O isolamento passa a valer quando há
 *   empresa selecionada, e `commercial.tenant_scope_required` garante que rotas operacionais não
 *   sejam alcançadas sem ela.
 *
 * ## Como isto é verificado
 *
 * `scripts/ci/tenant-scope-check.php` varre o código e reprova consulta a tabela do catálogo que
 * não passe por este serviço nem esteja numa exceção justificada. Sem essa checagem, a próxima
 * consulta escrita à mão voltaria a vazar entre empresas sem ninguém perceber - que foi exatamente
 * como a lacuna original se manteve por tanto tempo.
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

  /**
   * Empresa a CARIMBAR numa gravação. Deliberadamente diferente de `currentEmpresaId()`.
   *
   * A leitura continua valendo só pela sessão: alargar `where()` para a empresa única poderia
   * ESCONDER linhas já carimbadas com outra empresa, e tela que perde dado sem aviso é pior do que
   * a lacuna que se quer fechar. A gravação é o contrário — sem resposta a linha nasce NULL, ou
   * seja, visível a todo mundo. Por isso o recurso à empresa única vive só deste lado.
   *
   * Ordem: sessão primeiro (um usuário operando pelo painel decide pela empresa dele); sem sessão
   * — webhook do Tiny, item de fila, worker, cron —, a única empresa cadastrada, quando é uma só.
   */
  public static function empresaParaGravar(): ?int {
    $daSessao = self::currentEmpresaId();
    if ($daSessao !== null) return $daSessao;
    if (!class_exists('EmpresaCatalogService')) return null;
    try { return EmpresaCatalogService::empresaUnicaId(); }
    catch (Throwable $e) { return null; }
  }

  /**
   * Predicado de isolamento para acrescentar a um WHERE já existente.
   *
   * @param string $alias prefixo da tabela na consulta ('p' vira 'p.empresa_id')
   * @return array{sql:string,params:list<int>}
   */
  public static function where(string $table, string $alias = ''): array {
    if (!self::isScoped($table)) return ['sql' => '', 'params' => []];
    $empresa = self::currentEmpresaId();
    if ($empresa === null) return ['sql' => '', 'params' => []];
    $prefixo = $alias !== '' ? rtrim($alias, '.').'.' : '';
    // Linhas antigas, gravadas antes da migration de isolamento, têm empresa_id NULL. Elas
    // pertencem à instalação inteira e continuam visíveis - do contrário, ligar o isolamento faria
    // todo o histórico desaparecer da tela sem aviso. O backfill da migration 20260914_010 resolve
    // isso quando existe uma única empresa; use whereStrict() onde herdar o legado seria errado.
    return [
      'sql' => ' AND ('.$prefixo.self::COLUMN.' = ? OR '.$prefixo.self::COLUMN.' IS NULL)',
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
    if ($empresa === null) return ['sql' => '', 'params' => []];
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
    if ($empresa === null) return $data;
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
    if ($empresa === null) return true;
    if (!array_key_exists(self::COLUMN, $row)) return true;
    $linha = $row[self::COLUMN];
    if ($linha === null || (int)$linha === $empresa) return true;
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
    $aspas = ''; $len = strlen($sql);
    for ($i = 0; $i < $len; $i++) {
      $c = $sql[$i];
      if ($aspas !== '') {
        if ($c === '\\') { $i++; continue; }
        if ($c === $aspas) $aspas = '';
        continue;
      }
      if ($c === "'" || $c === '"' || $c === '`') { $aspas = $c; continue; }
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
   * Acrescenta o predicado de empresa a um SELECT/UPDATE/DELETE, inserindo o parâmetro na posição
   * correta. Consulta sem WHERE ganha um.
   *
   * @param list<mixed> $params
   * @return array{0:string,1:list<mixed>}
   */
  public static function applyToSelect(string $table, string $sql, array $params = [], string $alias = ''): array {
    $escopo = self::where($table, $alias);
    if ($escopo['sql'] === '') return [$sql, $params];

    $fim = self::findClauseOffset($sql, ['GROUP BY', 'HAVING', 'ORDER BY', 'LIMIT', 'FOR UPDATE', 'ON DUPLICATE KEY']);
    $where = self::findClauseOffset($sql, ['WHERE']);

    if ($where === null) {
      // Sem WHERE: o predicado vira o WHERE, mas ainda antes de GROUP/ORDER/LIMIT.
      $prefixo = $alias !== '' ? rtrim($alias, '.').'.' : '';
      $clausula = ' WHERE ('.$prefixo.self::COLUMN.' = ? OR '.$prefixo.self::COLUMN.' IS NULL) ';
      $corte = $fim ?? strlen($sql);
    } else {
      $clausula = $escopo['sql'].' ';
      $corte = $fim ?? strlen($sql);
      if ($fim !== null && $fim < $where) $corte = strlen($sql); // cláusula antes do WHERE: ignora
    }

    $posicao = self::countPlaceholders($sql, $corte);
    $novoSql = rtrim(substr($sql, 0, $corte)).$clausula.substr($sql, $corte);
    array_splice($params, $posicao, 0, $escopo['params']);
    return [preg_replace('/\s+/', ' ', trim($novoSql)), $params];
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
    if ($empresa === null) return [$sql, $params];
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
