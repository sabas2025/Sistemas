<?php
/** Parser para o formato SQL do projeto; preserva PREPARE/EXECUTE na mesma conexão. */
class UpgradeSqlService {
  public static function split(string $sql): array {
    if (preg_match('/^\s*DELIMITER\b/im',$sql)) throw new RuntimeException('DELIMITER não suportado; revisão manual obrigatória.');
    $out=[]; $buffer=''; $quote=''; $length=strlen($sql);
    for ($i=0;$i<$length;$i++) {
      $c=$sql[$i]; $next=$sql[$i+1] ?? '';
      if ($quote !== '') {
        $buffer.=$c;
        if ($c==='\\' && $quote!=='`' && $i+1<$length) { $buffer.=$sql[++$i]; continue; }
        if ($c===$quote) {
          if ($next===$quote) $buffer.=$sql[++$i]; else $quote='';
        }
        continue;
      }
      if ($c==='"' || $c==="'" || $c==='`') { $quote=$c; $buffer.=$c; continue; }
      if ($c==='#' || ($c==='-' && $next==='-' && ctype_space($sql[$i+2] ?? ' '))) {
        while ($i<$length && $sql[$i]!=="\n") $i++;
        $buffer.="\n"; continue;
      }
      if ($c==='/' && $next==='*') {
        if (($sql[$i+2] ?? '')==='!') throw new RuntimeException('Comentário SQL executável exige revisão manual.');
        $end=strpos($sql,'*/',$i+2);
        if ($end===false) throw new RuntimeException('Comentário SQL incompleto.');
        $i=$end+1; $buffer.=' '; continue;
      }
      if ($c===';') { if (trim($buffer)!=='') $out[]=trim($buffer); $buffer=''; }
      else $buffer.=$c;
    }
    if ($quote!=='') throw new RuntimeException('Literal SQL incompleto.');
    if (trim($buffer)!=='') $out[]=trim($buffer);
    return $out;
  }
  public static function execute(PDO $pdo,string $sql): void {
    $st=$pdo->query($sql);
    if ($st instanceof PDOStatement) { while ($st->nextRowset()) {} $st->closeCursor(); }
  }
}
