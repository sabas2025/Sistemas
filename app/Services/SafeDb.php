<?php
class SafeDb {
  public static function assertIdentifier(string $value, string $label='identificador'): string {
    $value = trim($value);
    if (!preg_match('/^[a-zA-Z_][a-zA-Z0-9_]*$/', $value)) {
      throw new InvalidArgumentException($label.' inválido.');
    }
    return $value;
  }
  public static function assertTable(string $table): string {
    $table = self::assertIdentifier($table, 'Tabela');
    if (!Database::isKnownTable($table)) {
      throw new InvalidArgumentException('Tabela não permitida no mapa do sistema: '.$table);
    }
    if (!Database::tableExists($table)) {
      throw new InvalidArgumentException('Tabela permitida, mas inexistente no banco atual: '.$table);
    }
    return $table;
  }
  public static function assertColumn(string $table, string $column): string {
    $column = self::assertIdentifier($column, 'Coluna');
    if (!Database::columnExists($table, $column)) {
      throw new InvalidArgumentException('Coluna não permitida para '.$table.': '.$column);
    }
    return $column;
  }
  public static function quoteIdent(string $ident): string {
    return '`'.str_replace('`','``', self::assertIdentifier($ident)).'`';
  }
  public static function selectAll(string $table, ?string $orderBy=null, string $direction='DESC', int $limit=100): array {
    $table = self::assertTable($table);
    $pdo = Database::forTable($table);
    $sql = 'SELECT * FROM '.self::quoteIdent($table);
    if ($orderBy !== null && $orderBy !== '') {
      $orderBy = self::assertColumn($table, $orderBy);
      $direction = strtoupper($direction) === 'ASC' ? 'ASC' : 'DESC';
      $sql .= ' ORDER BY '.self::quoteIdent($orderBy).' '.$direction;
    }
    $limit = max(1, min(500, $limit));
    $sql .= ' LIMIT '.$limit;
    return $pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC);
  }
  public static function countWhere(string $table, array $filters=[]): int {
    $table = self::assertTable($table);
    $pdo = Database::forTable($table);
    $where=[]; $values=[];
    foreach($filters as $col=>$val){
      $col = self::assertColumn($table, (string)$col);
      if (is_array($val)) {
        if (!$val) { $where[]='1=0'; continue; }
        $ph = implode(',', array_fill(0, count($val), '?'));
        $where[] = self::quoteIdent($col).' IN ('.$ph.')';
        foreach($val as $v) $values[]=$v;
      } else {
        $where[] = self::quoteIdent($col).' = ?';
        $values[]=$val;
      }
    }
    $sql='SELECT COUNT(*) FROM '.self::quoteIdent($table).($where ? ' WHERE '.implode(' AND ', $where) : '');
    $st=$pdo->prepare($sql); $st->execute($values); return (int)$st->fetchColumn();
  }
  public static function updateAllowed(string $table, array $data, array $allowed, array $where): int {
    $table = self::assertTable($table);
    $set=[]; $values=[];
    foreach($data as $col=>$val){
      if (!in_array($col, $allowed, true)) continue;
      $col = self::assertColumn($table, (string)$col);
      $set[] = self::quoteIdent($col).' = ?'; $values[]=$val;
    }
    if (!$set) return 0;
    $conds=[];
    foreach($where as $col=>$val){
      $col=self::assertColumn($table,(string)$col); $conds[]=self::quoteIdent($col).' = ?'; $values[]=$val;
    }
    if (!$conds) throw new InvalidArgumentException('UPDATE sem WHERE bloqueado.');
    $sql='UPDATE '.self::quoteIdent($table).' SET '.implode(',', $set).' WHERE '.implode(' AND ', $conds);
    $st=Database::forTable($table)->prepare($sql); $st->execute($values); return $st->rowCount();
  }
}
