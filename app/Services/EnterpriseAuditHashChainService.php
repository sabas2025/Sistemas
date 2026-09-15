<?php
class EnterpriseAuditHashChainService {
  public static function assinarProximos(int $limite=500): int {
    $pdo = Database::forTable('auditoria_hash_chain');
    self::garantirTabela($pdo);
    $ultimo = $pdo->query("SELECT hash_atual FROM auditoria_hash_chain ORDER BY id DESC LIMIT 1")->fetch();
    $hashAnterior = $ultimo['hash_atual'] ?? str_repeat('0',64);
    $st = $pdo->prepare("SELECT e.* FROM auditoria_eventos e LEFT JOIN auditoria_hash_chain h ON h.auditoria_id=e.id WHERE h.id IS NULL ORDER BY e.id ASC LIMIT ?");
    $st->bindValue(1,$limite,PDO::PARAM_INT); $st->execute();
    $eventos=$st->fetchAll(); $total=0;
    foreach($eventos as $ev){
      $base = $hashAnterior.'|'.$ev['id'].'|'.$ev['trace_id'].'|'.$ev['acao'].'|'.$ev['status'].'|'.$ev['mensagem'].'|'.$ev['criado_em'];
      $hashAtual = hash('sha256',$base);
      $ins=$pdo->prepare("INSERT INTO auditoria_hash_chain(auditoria_id,trace_id,hash_anterior,hash_atual,base_assinatura) VALUES(?,?,?,?,?)");
      $ins->execute([(int)$ev['id'],$ev['trace_id'],$hashAnterior,$hashAtual,$base]);
      $hashAnterior=$hashAtual; $total++;
    }
    return $total;
  }
  public static function validar(): array {
    $pdo=Database::forTable('auditoria_hash_chain'); self::garantirTabela($pdo);
    $rows=$pdo->query("SELECT * FROM auditoria_hash_chain ORDER BY id ASC LIMIT 5000")->fetchAll();
    $prev=str_repeat('0',64); $erros=[]; $total=0;
    foreach($rows as $r){
      if($r['hash_anterior'] !== $prev){ $erros[]='Quebra de cadeia no ID '.$r['id']; }
      $calc=hash('sha256',$r['base_assinatura']);
      if($calc !== $r['hash_atual']){ $erros[]='Hash divergente no ID '.$r['id']; }
      $prev=$r['hash_atual']; $total++;
    }
    return ['total'=>$total,'valida'=>empty($erros),'erros'=>$erros];
  }
  private static function garantirTabela(PDO $pdo): void {
    SchemaRuntimePolicyService::requireTable('auditoria_hash_chain', 'hash chain da auditoria');
  }
}
