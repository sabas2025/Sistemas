<?php
class BackupService {

  /**
   * Gera SQL usando somente tabelas descobertas na conexão recebida.
   * A lista vem de SHOW TABLES da própria conexão; por isso não depende do
   * mapa estático de módulos e funciona tanto em banco único quanto modular.
   */
  private static function buildSqlForConnection(PDO $pdo, string $module): string {
    $dbAtual=(string)$pdo->query('SELECT DATABASE()')->fetchColumn();
    $out="-- Backup Hub de Integração\n-- Gerado em ".date('Y-m-d H:i:s')."\n-- Módulo: ".$module."\n-- Banco origem: ".$dbAtual."\nSET FOREIGN_KEY_CHECKS=0;\n\n";
    $tables=$pdo->query('SHOW TABLES')->fetchAll(PDO::FETCH_COLUMN);
    foreach($tables as $t){
      $t=SafeDb::assertIdentifier((string)$t, 'Tabela');
      $qt=SafeDb::quoteIdent($t);
      $create=$pdo->query('SHOW CREATE TABLE '.$qt)->fetch(PDO::FETCH_ASSOC);
      if(!$create || !isset($create['Create Table'])) continue;
      $out.="DROP TABLE IF EXISTS `$t`;\n".$create['Create Table'].";\n\n";
      $rs=$pdo->query('SELECT * FROM '.$qt);
      while($row=$rs->fetch(PDO::FETCH_ASSOC)){
        $cols=array_map(fn($c)=>SafeDb::quoteIdent(SafeDb::assertIdentifier((string)$c, 'Coluna')), array_keys($row));
        $vals=array_map(fn($v)=>$v===null?'NULL':$pdo->quote((string)$v), array_values($row));
        $out.='INSERT INTO `'.$t.'` ('.implode(',',$cols).') VALUES ('.implode(',',$vals).');'."\n";
      }
      $out.="\n";
    }
    return $out."SET FOREIGN_KEY_CHECKS=1;\n";
  }

  /** @return array<int,array{module:string,database:string,file:string,sql:string,sha256:string,bytes:int}> */
  private static function buildBackupParts(): array {
    BackupSchemaService::ensure();
    $cfg=App::config();
    $modules=Database::isSingleDatabaseMode($cfg)?['core']:Database::modules();
    $parts=[];$seen=[];
    foreach($modules as $module){
      $module=(string)$module;
      if(!preg_match('/^[a-zA-Z0-9_]+$/',$module)) throw new RuntimeException('Módulo de banco inválido no backup.');
      $dbCfg=Database::moduleConfig($cfg,$module);
      $identity=strtolower((string)($dbCfg['host']??'')).'|'.strtolower((string)($dbCfg['name']??'')).'|'.(string)($dbCfg['user']??'');
      if(isset($seen[$identity])) continue; // módulos apontando para o mesmo banco não são duplicados.
      $seen[$identity]=true;
      $pdo=Database::connection($module);
      $database=(string)$pdo->query('SELECT DATABASE()')->fetchColumn();
      if($database==='') throw new RuntimeException('Banco do módulo '.$module.' não selecionado.');
      $safeDb=preg_replace('/[^a-zA-Z0-9_.-]+/','_',$database)?:'database';
      $safeModule=preg_replace('/[^a-zA-Z0-9_.-]+/','_',$module)?:'core';
      $sql=self::buildSqlForConnection($pdo,$module);
      $parts[]=['module'=>$module,'database'=>$database,'file'=>'database_'.$safeModule.'_'.$safeDb.'.sql','sql'=>$sql,'sha256'=>hash('sha256',$sql),'bytes'=>strlen($sql)];
    }
    if(!$parts) throw new RuntimeException('Nenhuma conexão de banco disponível para backup.');
    return $parts;
  }

  private static function buildSql(): string {
    $parts=self::buildBackupParts();
    if(count($parts)!==1) throw new RuntimeException('Backup SQL simples não representa banco modular. Use Backup ZIP.');
    return $parts[0]['sql'];
  }

  /** @param array<int,array{module:string,database:string,file:string,sql:string,sha256:string,bytes:int}> $parts */
  private static function buildManifest(array $parts, string $origem): array {
    return [
      'format'=>'hub-backup-v2',
      'version'=>'104.49.3',
      'created_at'=>date(DATE_ATOM),
      'installation_id'=>(string)(App::config()['installation_id']??''),
      'storage_mode'=>Database::isSingleDatabaseMode()?'single':'modular',
      'origin'=>$origem,
      'parts'=>array_map(static fn(array $part)=>[
        'module'=>$part['module'],'database'=>$part['database'],'file'=>$part['file'],'sha256'=>$part['sha256'],'bytes'=>$part['bytes']
      ],$parts),
    ];
  }

  public static function gerarSql(): string {
    PermissionService::require('backup','gerar');
    $cfg=App::config(); $db=(string)($cfg['db']['name']??'hub'); $dir=dirname(__DIR__,2).'/storage/backups';
    if(!is_dir($dir)&&!mkdir($dir,0770,true)&&!is_dir($dir)) throw new RuntimeException('Diretório de backups indisponível.');
    $file=$dir.'/backup_'.$db.'_'.date('Ymd_His').'_'.bin2hex(random_bytes(4)).'.sql';
    if(file_put_contents($file,self::buildSql(),LOCK_EX)===false) throw new RuntimeException('Não foi possível gravar o backup SQL.');
    @chmod($file,0600);
    BackupSignatureService::sign($file);
    self::registrar($file,'Backup SQL gerado pelo painel e assinado');
    return $file;
  }

  public static function gerarZip(): string {
    PermissionService::require('backup','gerar');
    return self::gerarZipInterno('Backup solicitado pelo painel');
  }

  private static function gerarZipInterno(string $origem, bool $cleanup=true): string {
    $cfg=App::config(); $db=(string)($cfg['db']['name'] ?? 'hub'); $dir=dirname(__DIR__,2).'/storage/backups';
    if(!is_dir($dir) && !mkdir($dir,0770,true) && !is_dir($dir)) throw new RuntimeException('Diretório de backups indisponível.');
    $base='backup_'.$db.'_'.date('Ymd_His').'_'.bin2hex(random_bytes(4));
    $sqlFile=$dir.'/'.$base.'.sql'; $zipFile=$dir.'/'.$base.'.zip';
    $parts=self::buildBackupParts();
    if(class_exists('ZipArchive')){
      $zip=new ZipArchive();
      if($zip->open($zipFile,ZipArchive::CREATE|ZipArchive::OVERWRITE)!==true) throw new RuntimeException('Não foi possível criar arquivo ZIP de backup.');
      try {
        foreach($parts as $part){if(!$zip->addFromString($part['file'],$part['sql'])) throw new RuntimeException('Não foi possível adicionar o módulo '.$part['module'].' ao ZIP.');}
        $manifest=json_encode(self::buildManifest($parts,$origem),JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_PRETTY_PRINT|JSON_THROW_ON_ERROR);
        if(!$zip->addFromString('manifest.json',$manifest)) throw new RuntimeException('Não foi possível adicionar manifesto ao ZIP.');
        $zip->addFromString('LEIA-ME.txt',"Backup Hub de Integração em ".date('Y-m-d H:i:s')."\nOrigem: ".$origem."\nPartes: ".count($parts)."\n");
      } finally { $zip->close(); }
      @chmod($zipFile,0600); $file=$zipFile;
    } else {
      if(count($parts)!==1) throw new RuntimeException('ZipArchive é obrigatório para backup de banco modular; backup parcial foi bloqueado.');
      if(file_put_contents($sqlFile,$parts[0]['sql'],LOCK_EX)===false) throw new RuntimeException('Não foi possível gravar o ponto de retorno do backup.');
      @chmod($sqlFile,0600); $file=$sqlFile;
    }
    BackupSignatureService::sign($file);
    $mode=Database::isSingleDatabaseMode()?'banco único':'bancos modulares';
    $msg=(str_ends_with($file,'.zip')?'Backup ZIP':'Backup SQL').' gerado e assinado ('.$mode.', '.count($parts).' parte(s)). Origem: '.$origem;
    self::registrar($file,$msg);
    if($cleanup) self::limparAntigos(30);
    return $file;
  }

  private static function registrar(string $file, string $msg): void {
    BackupSchemaService::ensure();
    $pdo=Database::forTable('backups_banco'); $size=file_exists($file) ? (filesize($file) ?: 0) : 0;
    $sha = is_file($file) ? hash_file('sha256', $file) : null;
    $hmac = null; $trust = 0;
    $sigFile = class_exists('BackupSignatureService') ? BackupSignatureService::signaturePath($file) : '';
    if ($sigFile && is_file($sigFile)) {
      $sig = json_decode((string)file_get_contents($sigFile), true);
      if (is_array($sig)) { $hmac = $sig['hmac'] ?? null; }
      try { BackupSignatureService::verify($file); $trust = 100; } catch(Throwable $e) { $trust = 40; }
    }
    $nome = basename($file);
    $caminho = 'storage/backups/'.$nome;
    // Melhoria 9 da seção 8: a proveniência gravada aqui é LIDA DA ASSINATURA, nunca do nome do
    // arquivo. A coluna serve para listar e filtrar; quem decide a restauração continua sendo a
    // assinatura (protegida por HMAC), de modo que renomear o arquivo não muda nada de segurança.
    $proveniencia = class_exists('BackupSignatureService')
      ? BackupSignatureService::provenance($file)
      : 'local_generated';
    $pdo->prepare('INSERT INTO backups_banco(usuario_id,arquivo,nome_arquivo,caminho,tamanho_bytes,hash_sha256,hmac_sha256,trust_score,status,proveniencia,mensagem,trace_id) VALUES(?,?,?,?,?,?,?,?,?,?,?,?)')
      ->execute([Auth::user()['id']??null,$nome,$nome,$caminho,$size,$sha,$hmac,$trust,'sucesso',$proveniencia,$msg,RequestContext::id()]);
    Audit::event('backup.gerar','sucesso',['mensagem'=>$msg,'entidade'=>'backups_banco','entidade_id'=>basename($file),'contexto'=>['tamanho_bytes'=>$size]]);
  }


  public static function importarUpload(array $file): string {
    PermissionService::require('backup','importar');
    if(($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) throw new RuntimeException('Falha no upload do backup. Código: '.(int)($file['error'] ?? -1));
    if((int)($file['size']??0)<=0) throw new RuntimeException('Backup importado vazio.');
    if((int)($file['size']??0)>100*1024*1024) throw new RuntimeException('Backup importado acima do limite seguro de 100MB.');
    $nomeOriginal=(string)($file['name']??'backup.sql');$ext=strtolower(pathinfo($nomeOriginal,PATHINFO_EXTENSION));
    if(!in_array($ext,['sql','zip'],true)) throw new RuntimeException('Formato inválido. Envie somente .sql ou .zip.');
    $tmp=(string)($file['tmp_name']??'');
    if($tmp===''||!is_uploaded_file($tmp)) throw new RuntimeException('Upload inválido ou origem temporária não confiável.');
    if(class_exists('finfo')){
      $finfo=new finfo(FILEINFO_MIME_TYPE);$mime=(string)$finfo->file($tmp);
      $allowed=$ext==='zip'?['application/zip','application/x-zip-compressed','application/octet-stream']:['text/plain','application/sql','application/octet-stream'];
      if($mime!==''&&!in_array($mime,$allowed,true)) throw new RuntimeException('Conteúdo incompatível com a extensão. MIME: '.$mime);
    }
    $dir=dirname(__DIR__,2).'/storage/backups';
    if(!is_dir($dir)&&!mkdir($dir,0770,true)&&!is_dir($dir)) throw new RuntimeException('Diretório de backups indisponível.');
    $dest=$dir.'/importado_'.date('Ymd_His').'_'.bin2hex(random_bytes(8)).'.'.$ext;
    if(!move_uploaded_file($tmp,$dest)) throw new RuntimeException('Não foi possível salvar o backup importado.');
    @chmod($dest,0600);
    try {
      $package=self::extrairPacoteRestore($dest);self::validarPacoteRestore($dest,$package);
    } catch(Throwable $e){@unlink($dest);throw $e;}
    // Reauditoria 2026-09-14 (achado A-11): a assinatura atesta apenas que este Hub ingeriu e
    // validou o arquivo - não que a origem seja confiável. A proveniência fica registrada na
    // própria assinatura e o restore exige confirmação reforçada para importados.
    $sha=hash_file('sha256',$dest);BackupSignatureService::sign($dest, BackupSignatureService::PROV_IMPORTED);
    self::registrar($dest,'Backup importado de origem externa (não confiável), validado estruturalmente e assinado localmente. SHA-256: '.$sha.'. Partes: '.count($package['parts']).'. Restauração exige confirmação reforçada.');
    Audit::event('backup.importar','sucesso',['mensagem'=>'Backup importado','entidade'=>'backups_banco','entidade_id'=>basename($dest),'contexto'=>['partes'=>count($package['parts']),'modo'=>$package['mode']]]);
    return $dest;
  }

  public static function restaurarPorId(int $id, string $confirmacao): void {
    PermissionService::require('backup','restaurar');
    if(!PermissionService::can('backup','restaurar')) throw new RuntimeException('Permissão backup.restaurar necessária.');
    // 'RESTAURAR IMPORTADO' também é aceito aqui: a exigência da frase reforçada para arquivos
    // de origem externa é avaliada adiante, depois de conhecida a proveniência assinada (A-11).
    if(!in_array(trim($confirmacao),['RESTAURAR','RESTAURAR IMPORTADO'],true)) throw new RuntimeException('Confirmação inválida. Digite RESTAURAR para executar.');
    $restoreLock=self::acquireRestoreLock();$rollbackFile=null;
    try {
      BackupSchemaService::ensure();$registryPdo=Database::forTable('backups_banco');
      $st=$registryPdo->prepare('SELECT id,arquivo,nome_arquivo,caminho,tamanho_bytes,hash_sha256,hmac_sha256,trust_score,status,mensagem,trace_id,criado_em FROM backups_banco WHERE id=? LIMIT 1');
      $st->execute([$id]);$b=$st->fetch(PDO::FETCH_ASSOC);if(!$b) throw new RuntimeException('Backup não encontrado.');
      $file=dirname(__DIR__,2).'/storage/backups/'.basename((string)$b['arquivo']);if(!is_file($file)) throw new RuntimeException('Arquivo físico do backup não encontrado.');
      $rollbackFile=self::gerarZipInterno('Ponto de retorno automático antes do restore ID '.$id,false);
      $assinatura=BackupSignatureService::verify($file);
      // Reauditoria 2026-09-14 (achado A-11): backup de origem externa não é equiparado a
      // backup gerado localmente só por ter assinatura. Exige uma confirmação distinta, que
      // não pode ser digitada por reflexo, e fica auditada como restauração de origem externa.
      $proveniencia=(string)($assinatura['provenance'] ?? BackupSignatureService::provenance($file));
      if($proveniencia===BackupSignatureService::PROV_IMPORTED && trim($confirmacao)!=='RESTAURAR IMPORTADO'){
        Audit::event('backup.restaurar.importado_bloqueado','alerta',[
          'codigo_erro'=>'BACKUP_IMPORTED_REQUIRES_STRONG_CONFIRMATION',
          'mensagem'=>'Restauração de backup de origem externa exige confirmação reforçada.',
          'entidade'=>'backups_banco','entidade_id'=>$id,
          'contexto'=>['arquivo'=>basename($file),'proveniencia'=>$proveniencia],
          'acao_recomendada'=>'Se a origem do arquivo for confiável e auditada, digite RESTAURAR IMPORTADO para confirmar.'
        ]);
        throw new RuntimeException('Este backup veio de origem externa. Para restaurá-lo, digite RESTAURAR IMPORTADO em vez de RESTAURAR.');
      }
      $actualSha=hash_file('sha256',$file);
      if(!empty($b['hash_sha256'])&&!hash_equals(strtolower((string)$b['hash_sha256']),strtolower((string)$actualSha))) throw new RuntimeException('Backup recusado: SHA-256 diverge do registro de auditoria.');
      $package=self::extrairPacoteRestore($file);self::validarPacoteRestore($file,$package);
      $parts=$package['parts'];
      usort($parts,static function(array $a,array $b):int{
        $weight=static fn(string $m):int=>$m==='backups'?30:($m==='core'?20:10);
        return $weight((string)$a['module'])<=>$weight((string)$b['module']);
      });
      foreach($parts as $part){
        $pdo=$package['mode']==='modular'?Database::connection((string)$part['module']):$registryPdo;
        self::executarSqlSeguro($pdo,(string)$part['sql']);
      }
      Database::clearSchemaMetadataCache();
      Audit::event('backup.restaurar','sucesso',['mensagem'=>'Backup restaurado pelo painel','entidade'=>'backups_banco','entidade_id'=>$id,'contexto'=>['arquivo'=>basename($file),'sha256'=>$actualSha,'modo'=>$package['mode'],'partes'=>count($parts),'proveniencia'=>$proveniencia,'ponto_retorno'=>basename((string)$rollbackFile)]]);
      if($rollbackFile&&is_file($rollbackFile)) self::registrar($rollbackFile,'Ponto de retorno automático preservado após restore ID '.$id);
    } catch(Throwable $e) {
      try { Audit::event('backup.restaurar','erro',['mensagem'=>'Falha ao restaurar backup.','entidade'=>'backups_banco','entidade_id'=>$id,'codigo_erro'=>'BACKUP_RESTORE_FAILED','contexto'=>['erro'=>class_exists('SensitiveDataService')?SensitiveDataService::maskJson($e->getMessage()):'erro interno','ponto_retorno'=>$rollbackFile?basename($rollbackFile):null]]); } catch(Throwable $ignored){if(class_exists('BestEffortLogService'))BestEffortLogService::warning(__METHOD__,$ignored);}
      throw $e;
    } finally {self::releaseRestoreLock($restoreLock);}
  }

  /** @return resource */
  private static function acquireRestoreLock() {
    $dir=dirname(__DIR__,2).'/storage/locks';
    if(!is_dir($dir)&&!@mkdir($dir,0770,true)&&!is_dir($dir)) throw new RuntimeException('Diretório de lock do restore indisponível.');
    $file=$dir.'/backup_restore.lock'; $handle=@fopen($file,'c+');
    if(!is_resource($handle)||!@flock($handle,LOCK_EX|LOCK_NB)){ if(is_resource($handle))@fclose($handle); throw new RuntimeException('Já existe uma restauração em andamento.'); }
    @ftruncate($handle,0); @fwrite($handle,json_encode(['pid'=>getmypid(),'user_id'=>Auth::user()['id']??null,'trace_id'=>RequestContext::id(),'started_at'=>date('c')],JSON_UNESCAPED_SLASHES)); @fflush($handle); @chmod($file,0600);
    return $handle;
  }

  private static function releaseRestoreLock($handle): void {
    if(is_resource($handle)){ @flock($handle,LOCK_UN); @fclose($handle); }
  }

  /** @return array{mode:string,parts:array<int,array{module:string,database:string,file:string,sql:string,sha256:string,bytes:int}>} */
  private static function extrairPacoteRestore(string $file): array {
    $ext=strtolower(pathinfo($file,PATHINFO_EXTENSION));
    if($ext==='sql'){
      $sql=(string)file_get_contents($file);
      return ['mode'=>'legacy','parts'=>[['module'=>'core','database'=>'','file'=>basename($file),'sql'=>$sql,'sha256'=>hash('sha256',$sql),'bytes'=>strlen($sql)]]];
    }
    if($ext!=='zip') throw new RuntimeException('Formato de backup não suportado.');
    if(!class_exists('ZipArchive')) throw new RuntimeException('ZipArchive não está habilitado para ler .zip.');
    $zip=new ZipArchive();if($zip->open($file)!==true) throw new RuntimeException('Não foi possível abrir o ZIP.');
    try {
      if($zip->numFiles<1||$zip->numFiles>100) throw new RuntimeException('ZIP recusado: quantidade de entradas fora do limite seguro.');
      $entries=[];$sqlNames=[];$total=0;
      for($i=0;$i<$zip->numFiles;$i++){
        $stat=$zip->statIndex($i);if(!is_array($stat))continue;$name=(string)($stat['name']??'');
        if($name===''||str_contains($name,'..')||str_starts_with($name,'/')||str_contains($name,'\\')) throw new RuntimeException('ZIP recusado: nome de entrada inseguro.');
        $size=(int)($stat['size']??0);$compressed=max(1,(int)($stat['comp_size']??1));$total+=$size;
        if($size>80*1024*1024||$total>100*1024*1024||($size/$compressed)>150) throw new RuntimeException('ZIP recusado: possível ZIP bomb ou arquivo descompactado acima do limite.');
        $entries[$name]=$i;if(strtolower(pathinfo($name,PATHINFO_EXTENSION))==='sql')$sqlNames[]=$name;
      }
      if(isset($entries['manifest.json'])){
        $raw=$zip->getFromIndex($entries['manifest.json']);$manifest=json_decode((string)$raw,true,32,JSON_THROW_ON_ERROR);
        if(!is_array($manifest)||($manifest['format']??'')!=='hub-backup-v2') throw new RuntimeException('Manifesto de backup não reconhecido.');
        $mode=(string)($manifest['storage_mode']??'');if(!in_array($mode,['single','modular'],true)) throw new RuntimeException('Modo do manifesto inválido.');
        $declared=$manifest['parts']??null;if(!is_array($declared)||count($declared)<1||count($declared)>20) throw new RuntimeException('Manifesto sem partes válidas.');
        $parts=[];$declaredFiles=[];
        foreach($declared as $part){
          if(!is_array($part))throw new RuntimeException('Parte inválida no manifesto.');
          $module=(string)($part['module']??'');$name=(string)($part['file']??'');$expected=(string)($part['sha256']??'');
          if(!preg_match('/^[a-zA-Z0-9_]+$/',$module)||!preg_match('/^[a-zA-Z0-9_.-]+\.sql$/',$name)||!isset($entries[$name])||isset($declaredFiles[$name])) throw new RuntimeException('Parte insegura ou ausente no manifesto.');
          $declaredFiles[$name]=true;$sql=$zip->getFromIndex($entries[$name]);if($sql===false)throw new RuntimeException('Não foi possível ler parte SQL do backup.');
          $sha=hash('sha256',(string)$sql);if(!preg_match('/^[a-f0-9]{64}$/i',$expected)||!hash_equals(strtolower($expected),$sha)) throw new RuntimeException('Hash de parte SQL divergente: '.$name);
          $parts[]=['module'=>$module,'database'=>(string)($part['database']??''),'file'=>$name,'sql'=>(string)$sql,'sha256'=>$sha,'bytes'=>strlen((string)$sql)];
        }
        sort($sqlNames);$listed=array_keys($declaredFiles);sort($listed);if($sqlNames!==$listed) throw new RuntimeException('ZIP contém SQL não declarado no manifesto.');
        return ['mode'=>$mode,'parts'=>$parts];
      }
      if(count($sqlNames)!==1) throw new RuntimeException('ZIP legado deve conter exatamente um arquivo .sql.');
      $sql=$zip->getFromIndex($entries[$sqlNames[0]]);if($sql===false)throw new RuntimeException('Não foi possível ler o SQL do ZIP.');
      return ['mode'=>'legacy','parts'=>[['module'=>'core','database'=>'','file'=>$sqlNames[0],'sql'=>(string)$sql,'sha256'=>hash('sha256',(string)$sql),'bytes'=>strlen((string)$sql)]]];
    } finally {$zip->close();}
  }

  private static function extrairSqlDoBackup(string $file): string {
    $package=self::extrairPacoteRestore($file);
    if(count($package['parts'])!==1) throw new RuntimeException('Backup modular contém múltiplas partes; use restauração por pacote.');
    return (string)$package['parts'][0]['sql'];
  }

  /** @param array{mode:string,parts:array<int,array<string,mixed>>} $package */
  private static function validarPacoteRestore(string $file, array $package): void {
    $current=Database::isSingleDatabaseMode()?'single':'modular';$mode=(string)($package['mode']??'legacy');
    if($mode==='legacy'&&$current==='modular') throw new RuntimeException('Backup legado de banco único não pode ser restaurado diretamente em ambiente modular.');
    if($mode!=='legacy'&&$mode!==$current) throw new RuntimeException('Backup recusado: modo '.$mode.' incompatível com ambiente '.$current.'.');
    $allowedModules=Database::modules();if($current==='single')$allowedModules=['core'];
    foreach($package['parts']??[] as $part){
      $module=(string)($part['module']??'');if($mode==='modular'&&!in_array($module,$allowedModules,true)) throw new RuntimeException('Módulo do backup não configurado neste ambiente: '.$module);
      $sql=(string)($part['sql']??'');if(trim($sql)==='')throw new RuntimeException('Backup importado recusado: parte SQL vazia.');
      self::validarManifestoRestore($file,$sql);
      foreach(self::splitSqlStatements($sql) as $stmt) self::validarStatementRestore($stmt);
    }
  }

  private static function validarManifestoRestore(string $file, string $sql): void {
    $base=basename($file);
    if (!preg_match('/^(backup_|importado_).+\.(sql|zip)$/i', $base)) {
      throw new RuntimeException('Backup recusado: nome fora do padrão permitido.');
    }
    $danger='/\b(DROP\s+DATABASE|CREATE\s+USER|ALTER\s+USER|GRANT\b|REVOKE\b|LOAD\s+DATA|OUTFILE\b|INFILE\b|CREATE\s+TRIGGER|CREATE\s+EVENT|CREATE\s+PROCEDURE|CREATE\s+FUNCTION|INSTALL\s+PLUGIN|SUPER\b)\b/i';
    if (preg_match($danger, $sql, $m)) {
      throw new RuntimeException('Restore bloqueado por comando SQL perigoso: '.trim($m[0]));
    }
    if (strlen($sql) > 80*1024*1024) {
      throw new RuntimeException('Restore bloqueado: arquivo SQL acima do limite seguro de 80MB.');
    }
  }

  private static function executarSqlSeguro(PDO $pdo, string $sql): void {
    $statements = self::splitSqlStatements($sql);
    if (!$statements) {
      throw new RuntimeException('Arquivo SQL não contém comandos válidos para restore.');
    }

    foreach ($statements as $stmt) {
      self::validarStatementRestore($stmt);
    }

    // MySQL/MariaDB faz COMMIT implícito em DDL como DROP/CREATE/ALTER TABLE.
    // Por isso restore completo de backup não deve ficar dentro de uma única transação: ao final,
    // PDO::commit() pode falhar com "There is no active transaction".
    // Como o sistema cria um backup de segurança antes do restore, a estratégia correta é:
    // - usar transação somente para SQL de dados puro;
    // - executar restore estrutural sem transação;
    // - nunca chamar commit/rollback quando o PDO já não estiver em transação.
    $usarTransacao = !self::containsImplicitCommitStatement($statements) && !$pdo->inTransaction();
    $transacaoIniciada = false;

    try {
      if ($usarTransacao) {
        $pdo->beginTransaction();
        $transacaoIniciada = true;
      }

      foreach ($statements as $stmt) {
        $stmt = trim($stmt);
        if ($stmt === '') continue;
        $pdo->exec($stmt);
      }

      if ($transacaoIniciada && $pdo->inTransaction()) {
        $pdo->commit();
      }
    } catch (Throwable $e) {
      if ($transacaoIniciada && $pdo->inTransaction()) {
        try {
          $pdo->rollBack();
        } catch (Throwable $rollbackError) {
          if(class_exists('BestEffortLogService')) BestEffortLogService::warning(__METHOD__.'.rollback', $rollbackError);
        }
      }
      try { $pdo->exec('SET FOREIGN_KEY_CHECKS=1'); } catch(Throwable $ignored){ if (class_exists('BestEffortLogService')) BestEffortLogService::warning(__METHOD__, $ignored); }
      throw new RuntimeException('Falha ao restaurar backup: '.$e->getMessage());
    }
  }

  private static function splitSqlStatements(string $sql): array {
    $sql = preg_replace('/^\s*--.*$/m', '', $sql);
    $sql = preg_replace('/^\s*#.*$/m', '', $sql);
    $statements = [];
    $buffer = '';
    $inString = false;
    $quote = '';
    $len = strlen($sql);
    for ($i=0; $i<$len; $i++) {
      $ch = $sql[$i];
      $prev = $i>0 ? $sql[$i-1] : '';
      if ($inString && $ch === $quote && $i+1 < $len && $sql[$i+1] === $quote) {
        // Escape SQL padrão por duplicação: 'O''Reilly'.
        $buffer .= $ch.$sql[++$i];
        continue;
      }
      if (($ch === "'" || $ch === '"') && $prev !== '\\') {
        if (!$inString) { $inString = true; $quote = $ch; }
        elseif ($quote === $ch) { $inString = false; $quote = ''; }
      }
      if ($ch === ';' && !$inString) {
        $stmt = trim($buffer);
        if ($stmt !== '') $statements[] = $stmt;
        $buffer = '';
        continue;
      }
      $buffer .= $ch;
    }
    $last = trim($buffer);
    if ($last !== '') $statements[] = $last;
    return $statements;
  }

  private static function containsImplicitCommitStatement(array $statements): bool {
    foreach ($statements as $stmt) {
      if (preg_match('/^\s*(CREATE|DROP|ALTER|TRUNCATE|RENAME|LOCK\s+TABLES|UNLOCK\s+TABLES)\b/i', $stmt)) {
        return true;
      }
    }
    return false;
  }


  private static function validarStatementRestore(string $stmt): void {
    $allowed='/^(SET\s+FOREIGN_KEY_CHECKS\s*=|CREATE\s+TABLE\s+IF\s+NOT\s+EXISTS|CREATE\s+TABLE|DROP\s+TABLE\s+IF\s+EXISTS|INSERT\s+INTO|LOCK\s+TABLES|UNLOCK\s+TABLES|ALTER\s+TABLE\s+`?[a-zA-Z0-9_]+`?\s+(ADD|MODIFY|CHANGE|DROP\s+COLUMN|ADD\s+INDEX|ADD\s+KEY|ADD\s+UNIQUE)|UPDATE\s+`?[a-zA-Z0-9_]+`?\s+SET)\b/i';
    if (!preg_match($allowed, $stmt)) {
      throw new RuntimeException('Restore bloqueado: statement não permitido.');
    }
  }

  public static function limparAntigos(int $dias=30): int {
    $dir=dirname(__DIR__,2).'/storage/backups'; if(!is_dir($dir)) return 0;
    $limite=time()-($dias*86400); $removidos=0;
    foreach(glob($dir.'/backup_*.*') ?: [] as $f){
      if(str_ends_with($f,'.sig.json')||!is_file($f)||filemtime($f)>=$limite) continue;
      $sig=class_exists('BackupSignatureService')?BackupSignatureService::signaturePath($f):$f.'.sig.json';
      if(@unlink($f)){$removidos++;if(is_file($sig))@unlink($sig);}
    }
    return $removidos;
  }
}
