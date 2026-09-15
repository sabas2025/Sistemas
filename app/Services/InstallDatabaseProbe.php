<?php
/**
 * Sonda mínima de privilégio DDL do instalador.
 * Usa tabela persistente aleatória e só remove o objeto que a própria conexão
 * conseguiu criar; uma tabela persistente homônima nunca é tocada.
 */
final class InstallDatabaseProbe {
  public static function assertDdlPrivileges(PDO $pdo): void {
    $name='_hub_install_probe_'.bin2hex(random_bytes(12));
    $quoted='`'.$name.'`';
    $created=false;
    try{
      $pdo->exec('CREATE TABLE '.$quoted.' (`id` INT NOT NULL PRIMARY KEY) ENGINE=InnoDB');
      $created=true;
      $pdo->exec('ALTER TABLE '.$quoted.' ADD COLUMN `marker` VARCHAR(20) NULL');
      $pdo->exec('CREATE INDEX `idx_marker` ON '.$quoted.' (`marker`)');
      $pdo->exec('INSERT INTO '.$quoted.' (`id`) VALUES (1)');
      if((int)$pdo->query('SELECT COUNT(*) FROM '.$quoted)->fetchColumn()!==1)throw new RuntimeException('A tabela aleatória de permissão não pôde ser validada.');
    }finally{
      if($created)$pdo->exec('DROP TABLE '.$quoted);
    }
  }
}
