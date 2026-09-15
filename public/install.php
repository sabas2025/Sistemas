<?php
// V104.49.3-R5 (2026-08-22) - instalador protegido por autorização temporária fora da raiz pública.
error_reporting(E_ALL);
ini_set('display_errors', '0');
ini_set('log_errors', '1');

$root = dirname(__DIR__);
require_once $root.'/app/Services/InstallDatabaseProbe.php';
$configPath = $root . '/config/config.php';
$lockPath = $root . '/storage/install.lock';
$authorizationPath = $root . '/storage/install-authorization.json';

function request_is_https(): bool {
  if (!empty($_SERVER['HTTPS']) && strtolower((string)$_SERVER['HTTPS']) !== 'off') return true;
  if ((int)($_SERVER['SERVER_PORT'] ?? 0) === 443) return true;

  // X-Forwarded-Proto só é aceito para proxies declarados explicitamente no
  // ambiente do servidor. Endereço privado, por si só, não é prova de proxy.
  $remote = (string)($_SERVER['REMOTE_ADDR'] ?? '');
  $configured = array_values(array_filter(array_map('trim', explode(',', (string)(getenv('HUB_INSTALL_TRUSTED_PROXIES') ?: '')))));
  $isProxy = $remote !== '' && filter_var($remote, FILTER_VALIDATE_IP) !== false && in_array($remote, $configured, true);
  $forwarded = strtolower(trim(explode(',', (string)($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? ''))[0] ?? ''));
  return $isProxy && $forwarded === 'https';
}
function request_is_loopback(): bool {
  $remote = strtolower(trim((string)($_SERVER['REMOTE_ADDR'] ?? '')));
  $host = strtolower(trim(preg_replace('/:\d+$/', '', (string)($_SERVER['HTTP_HOST'] ?? ''))));
  return in_array($remote, ['127.0.0.1', '::1'], true)
    && in_array($host, ['localhost', '127.0.0.1', '[::1]', '::1'], true);
}

$requestIsHttps = request_is_https();
$httpsBlocked = !$requestIsHttps && !request_is_loopback();
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');
header('Referrer-Policy: no-referrer');
header('X-Robots-Tag: noindex, nofollow, noarchive, nosnippet, noimageindex');
if ($requestIsHttps) header('Strict-Transport-Security: max-age=31536000; includeSubDomains');

ini_set('session.use_only_cookies', '1');
ini_set('session.use_strict_mode', '1');
session_name('HUB_INSTALL_AUTH');
session_set_cookie_params([
  'lifetime' => 0,
  'path' => rtrim(str_replace('\\', '/', dirname((string)($_SERVER['SCRIPT_NAME'] ?? '/'))), '/') . '/',
  'secure' => $requestIsHttps,
  'httponly' => true,
  'samesite' => 'Strict',
]);
if (session_status() !== PHP_SESSION_ACTIVE) session_start();
if (empty($_SESSION['install_csrf']) || !is_string($_SESSION['install_csrf'])) {
  $_SESSION['install_csrf'] = bin2hex(random_bytes(32));
}

function h($v) { return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); }
function random_secret() { return bin2hex(random_bytes(32)); }
function export_config(array $data): string { return "<?php\nreturn " . var_export($data, true) . ";\n"; }
function atomic_private_write(string $path, string $contents): void {
  $dir=dirname($path); if(!is_dir($dir)&&!mkdir($dir,0770,true)&&!is_dir($dir)) throw new RuntimeException('Diretório de configuração indisponível.');
  $tmp=$path.'.tmp.'.bin2hex(random_bytes(6));
  if(file_put_contents($tmp,$contents,LOCK_EX)===false){@unlink($tmp);throw new RuntimeException('Não foi possível gravar arquivo temporário seguro.');}
  @chmod($tmp,0600);
  if(!@rename($tmp,$path)){@unlink($tmp);throw new RuntimeException('Não foi possível publicar arquivo de configuração de forma atômica.');}
  @chmod($path,0600);
}
function read_locked_json(string $path, callable $callback) {
  $guard = @fopen($path.'.lock', 'c');
  if ($guard === false) return $callback(null, null);
  try {
    if (!flock($guard, LOCK_EX)) throw new RuntimeException('Não foi possível bloquear a autorização de instalação.');
    $handle = @fopen($path, 'r+');
    if ($handle === false) return $callback(null, null);
    try {
      if (!flock($handle, LOCK_EX)) throw new RuntimeException('Não foi possível bloquear o registro de autorização.');
      rewind($handle);
      $raw = stream_get_contents($handle);
      $record = is_string($raw) ? json_decode($raw, true) : null;
      $result = $callback(is_array($record) ? $record : null, $handle);
      flock($handle, LOCK_UN);
      return $result;
    } finally {
      fclose($handle);
    }
  } finally {
    flock($guard, LOCK_UN);
    fclose($guard);
  }
}
function rewrite_locked_json($handle, array $record): void {
  if (!is_resource($handle)) throw new RuntimeException('Arquivo de autorização indisponível.');
  $json = json_encode($record, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
  if ($json === false) throw new RuntimeException('Não foi possível serializar a autorização de instalação.');
  rewind($handle);
  if (!ftruncate($handle, 0) || fwrite($handle, $json.PHP_EOL) === false || !fflush($handle)) {
    throw new RuntimeException('Não foi possível atualizar a autorização de instalação.');
  }
  @chmod(stream_get_meta_data($handle)['uri'] ?? '', 0600);
}
function install_browser_binding(string $authorizationId): string {
  return hash('sha256', $authorizationId."\n".session_id()."\n".(string)($_SERVER['HTTP_USER_AGENT'] ?? ''));
}
function bind_install_authorization(string $path, string $code): array {
  $code = trim($code);
  if (!preg_match('/^[a-f0-9]{64}$/i', $code)) return [false, 'Código de autorização inválido.'];
  if (is_file($path) && (!is_readable($path) || !is_writable($path))) {
    return [false, 'O PHP-FPM não consegue ler e atualizar a autorização. Gere o código com o mesmo usuário do site ou corrija o proprietário do arquivo em storage.'];
  }
  return read_locked_json($path, function($record, $handle) use ($code) {
    if (!is_array($record) || !is_resource($handle)) return [false, 'Autorização não encontrada. Gere uma nova pelo terminal do servidor.'];
    if (($record['state'] ?? '') !== 'issued' || empty($record['token_hash'])) return [false, 'Esta autorização já foi utilizada. Gere uma nova pelo terminal.'];
    if ((int)($record['expires_at'] ?? 0) < time()) return [false, 'A autorização expirou. Gere uma nova pelo terminal.'];
    if (!hash_equals((string)$record['token_hash'], hash('sha256', $code))) return [false, 'Código de autorização inválido.'];

    session_regenerate_id(true);
    $_SESSION['install_authorization_id'] = (string)($record['id'] ?? '');
    $_SESSION['install_csrf'] = bin2hex(random_bytes(32));
    $record['state'] = 'bound';
    $record['bound_at'] = time();
    $record['browser_binding'] = install_browser_binding((string)$record['id']);
    unset($record['token_hash']); // o código de uso único deixa de existir após o vínculo.
    rewrite_locked_json($handle, $record);
    return [true, 'Autorização vinculada a este navegador.'];
  });
}
function install_authorization_status(string $path): array {
  if (is_file($path) && (!is_readable($path) || !is_writable($path))) {
    return [false, 'Arquivo de autorização sem permissão para o usuário do PHP-FPM.'];
  }
  return read_locked_json($path, function($record) {
    if (!is_array($record)) return [false, 'Autorização temporária não encontrada.'];
    if ((int)($record['expires_at'] ?? 0) < time()) return [false, 'Autorização temporária expirada.'];
    if (($record['state'] ?? '') !== 'bound') return [false, 'Autorização ainda não vinculada ou já está em uso.'];
    $id = (string)($record['id'] ?? '');
    if ($id === '' || !hash_equals($id, (string)($_SESSION['install_authorization_id'] ?? ''))) return [false, 'Autorização vinculada a outro navegador.'];
    if (!hash_equals((string)($record['browser_binding'] ?? ''), install_browser_binding($id))) return [false, 'Vínculo do navegador inválido.'];
    return [true, 'Autorizado'];
  });
}
function claim_install_authorization(string $path): bool {
  return (bool)read_locked_json($path, function($record, $handle) {
    if (!is_array($record) || !is_resource($handle) || ($record['state'] ?? '') !== 'bound') return false;
    if ((int)($record['expires_at'] ?? 0) < time()) return false;
    $id = (string)($record['id'] ?? '');
    if ($id === '' || !hash_equals($id, (string)($_SESSION['install_authorization_id'] ?? ''))) return false;
    if (!hash_equals((string)($record['browser_binding'] ?? ''), install_browser_binding($id))) return false;
    $record['state'] = 'running';
    $record['started_at'] = time();
    rewrite_locked_json($handle, $record);
    return true;
  });
}
function consume_install_authorization(string $path): void {
  read_locked_json($path, function($record, $handle) {
    if (!is_array($record) || !is_resource($handle) || ($record['state'] ?? '') !== 'running') return null;
    $id = (string)($record['id'] ?? '');
    if ($id !== '' && hash_equals($id, (string)($_SESSION['install_authorization_id'] ?? ''))
        && hash_equals((string)($record['browser_binding'] ?? ''), install_browser_binding($id))) {
      $record['state'] = 'consumed';
      $record['consumed_at'] = time();
      unset($record['started_at'], $record['browser_binding']);
      rewrite_locked_json($handle, $record);
    }
    return null;
  });
  unset($_SESSION['install_authorization_id']);
}
function complete_install_authorization(string $path): void {
  if (is_file($path)) @unlink($path);
  unset($_SESSION['install_authorization_id'], $_SESSION['install_csrf']);
}
function valid_csrf(): bool {
  $sent = (string)($_POST['_csrf'] ?? '');
  $known = (string)($_SESSION['install_csrf'] ?? '');
  return $sent !== '' && $known !== '' && hash_equals($known, $sent);
}
function build_fim_manifest(string $root, string $secret): array {
  $base = realpath($root);
  if ($base === false) throw new RuntimeException('Diretório raiz indisponível para gerar o manifesto de integridade.');
  $files = [];
  foreach (['app/Core','app/Services','app/Controllers','public'] as $dir) {
    foreach (glob($base.'/'.$dir.'/*.php') ?: [] as $file) {
      $relative = str_replace($base.'/', '', $file);
      $hash = hash_file('sha256', $file);
      if ($hash === false) throw new RuntimeException('Não foi possível calcular a integridade de '.$relative.'.');
      $files[$relative] = $hash;
    }
  }
  ksort($files);
  $key = $secret !== '' ? $secret : 'hub-fim-dev-key-change-me';
  $binaryKey = hash('sha256', $key, true);
  return [
    'created_at'=>date('c'),
    'files'=>$files,
    'hmac'=>hash_hmac('sha256', json_encode($files, JSON_UNESCAPED_SLASHES), $binaryKey),
  ];
}
function publish_install_artifacts(string $configPath, string $root, array $config): void {
  // Melhoria 5 da seção 8 (relatório V104.49.3-R6): storage/cache/classmap.php é lido por
  // app/Core/Autoload.php mas nada no pacote o gerava - ele era mantido à mão e ficava defasado
  // em silêncio (na auditoria estava com 209 das 236 classes). Agora o instalador o produz a
  // partir da árvore real. Precisa acontecer ANTES do manifesto de integridade, senão o manifesto
  // registraria o hash do mapa antigo e o gate de integridade acusaria divergência logo depois.
  $classmapService=$root.'/app/Services/ClassmapBuilderService.php';
  if(is_file($classmapService)){
    require_once $classmapService;
    // Falha aqui não impede a instalação: o autoloader tem varredura por diretório como fallback.
    if(ClassmapBuilderService::write()===null) error_log('Instalador: não foi possível gerar storage/cache/classmap.php; autoloader seguirá pelo fallback de diretório.');
  }
  $fimPath=$root.'/storage/file_integrity_manifest.json';
  $lockPath=$root.'/storage/install.lock';
  $fim=json_encode(build_fim_manifest($root,(string)($config['security']['fim_manifest_hmac_key']??'')),JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES);
  if($fim===false)throw new RuntimeException('Não foi possível serializar o manifesto de integridade.');
  $contents=[
    $fimPath=>$fim.PHP_EOL,
    $configPath=>export_config($config),
    $lockPath=>'installed_at='.date('c').PHP_EOL.'version='.trim((string)@file_get_contents($root.'/VERSAO.txt')).PHP_EOL.'release_date=2026-09-14'.PHP_EOL.'mode=production_ready'.PHP_EOL,
  ];
  $snapshots=[];
  foreach(array_keys($contents) as $path){
    $exists=is_file($path);
    $previous=$exists?file_get_contents($path):null;
    if($exists&&!is_string($previous))throw new RuntimeException('Não foi possível preservar o estado anterior de '.basename($path).'.');
    $snapshots[$path]=['exists'=>$exists,'contents'=>$previous];
  }
  try{
    foreach($contents as $path=>$value)atomic_private_write($path,$value);
  }catch(Throwable $publishError){
    $rollbackErrors=[];
    foreach(array_reverse(array_keys($contents)) as $path){
      try{
        $snapshot=$snapshots[$path];
        if($snapshot['exists'])atomic_private_write($path,(string)$snapshot['contents']);
        elseif(is_file($path)&&!@unlink($path))$rollbackErrors[]=basename($path);
      }catch(Throwable $rollbackError){$rollbackErrors[]=basename($path);}
    }
    if($rollbackErrors!==[])throw new RuntimeException('Falha ao publicar a instalação e ao restaurar: '.implode(', ',array_unique($rollbackErrors)).'.',0,$publishError);
    throw $publishError;
  }
}
function validate_db_host(string $host): string {
  $host=trim($host);
  if($host===''||strlen($host)>253||preg_match('/[;\s\x00-\x1F\x7F]/',$host))throw new FriendlyInstallException('Host MySQL inválido. Informe somente hostname ou endereço IP, sem porta ou parâmetros extras.');
  if(filter_var($host,FILTER_VALIDATE_IP)!==false)return $host;
  if(!preg_match('/^(?=.{1,253}$)(?:[a-zA-Z0-9](?:[a-zA-Z0-9-]{0,61}[a-zA-Z0-9])?\.)*[a-zA-Z0-9](?:[a-zA-Z0-9-]{0,61}[a-zA-Z0-9])?$/',$host))throw new FriendlyInstallException('Host MySQL inválido. Use um hostname DNS válido, localhost, IPv4 ou IPv6.');
  return $host;
}
function validate_db_name(string $name): string {
  $name=trim($name);
  if($name===''||strlen($name)>64||!preg_match('/^[a-zA-Z0-9_]+$/',$name))throw new FriendlyInstallException('Nome do banco inválido. Use de 1 a 64 caracteres: letras, números e underline. Informe o prefixo completo da hospedagem.');
  return $name;
}
function validate_db_user(string $user): string {
  $user=trim($user);
  if($user===''||strlen($user)>128||preg_match('/[\x00-\x1F\x7F]/',$user))throw new FriendlyInstallException('Usuário MySQL inválido.');
  return $user;
}
function validate_admin_name(string $name): string {
  $name=trim($name);
  if(strlen($name)<2||strlen($name)>120||preg_match('/[\x00-\x1F\x7F]/',$name))throw new FriendlyInstallException('Nome do administrador deve ter de 2 a 120 caracteres e não pode conter controles.');
  return $name;
}
function validate_base_url(string $baseUrl, bool $production): string {
  $baseUrl=trim(str_replace('\\','/',$baseUrl));
  if($baseUrl===''||strlen($baseUrl)>500||preg_match('/[\x00-\x1F\x7F]/',$baseUrl))throw new FriendlyInstallException('Base URL inválida.');
  if(str_starts_with($baseUrl,'/')){
    $decoded=rawurldecode($baseUrl);
    if(str_starts_with($baseUrl,'//')||str_contains($baseUrl,'?')||str_contains($baseUrl,'#')||str_contains($decoded,'..')||preg_match('/[\x00-\x1F\x7F]/',$decoded))throw new FriendlyInstallException('Base URL local inválida. Use um caminho absoluto local, por exemplo /hub/public, sem query ou fragmento.');
    $normalized='/'.trim((string)preg_replace('#/+#','/',$baseUrl),'/');
    return $normalized==='/'?'':$normalized;
  }
  $parts=parse_url($baseUrl);
  if(!is_array($parts)||strtolower((string)($parts['scheme']??''))!=='https'||empty($parts['host'])||isset($parts['user'])||isset($parts['pass'])||isset($parts['query'])||isset($parts['fragment']))throw new FriendlyInstallException('Base URL absoluta deve usar HTTPS e não pode conter credenciais, query ou fragmento.');
  if(isset($parts['port'])&&((int)$parts['port']<1||(int)$parts['port']>65535))throw new FriendlyInstallException('Porta inválida na Base URL.');
  $path=(string)($parts['path']??'');
  $decodedPath=rawurldecode($path);
  if(str_contains($decodedPath,'..')||preg_match('/[\x00-\x1F\x7F]/',$decodedPath))throw new FriendlyInstallException('Base URL contém caminho inseguro ou caracteres de controle.');
  $host=strtolower((string)$parts['host']);
  if(filter_var($host,FILTER_VALIDATE_IP)===false&&!preg_match('/^(?=.{1,253}$)(?:[a-z0-9](?:[a-z0-9-]{0,61}[a-z0-9])?\.)*[a-z0-9](?:[a-z0-9-]{0,61}[a-z0-9])?$/i',$host))throw new FriendlyInstallException('Host inválido na Base URL.');
  if(str_contains($host,':'))$host='['.$host.']';
  $port=isset($parts['port'])?':'.(int)$parts['port']:'';
  $path=$path===''?'':('/'.trim((string)preg_replace('#/+#','/',$path),'/'));
  return 'https://'.$host.$port.$path;
}
class FriendlyInstallException extends RuntimeException {}
function is_loopback_host(string $host): bool {
  $h = strtolower(trim($host));
  return in_array($h, ['localhost','127.0.0.1','::1'], true);
}
function is_root_no_password(string $user, string $pass): bool {
  return strtolower(trim($user)) === 'root' && $pass === '';
}
// P1-06 (reauditoria 2026-08-23): a checagem original só bloqueava root sem senha -
// qualquer OUTRO usuário MySQL com senha vazia passava direto, inclusive em produção.
function is_blank_password_credential(string $pass): bool {
  return $pass === '';
}
function insecure_root_message(): string {
  return 'Credencial MySQL insegura bloqueada: em produção não use usuário root sem senha. Crie um banco e um usuário MySQL exclusivo no cPanel/DirectAdmin, vincule o usuário ao banco com todas as permissões e informe o nome completo com prefixo. Exemplo: banco ctbatop1_hub e usuário ctbatop1_hubuser. Em XAMPP/local, selecione Modo Local e marque a confirmação de ambiente local.';
}
function hosting_db_help(string $db, string $user): string {
  return 'Não foi possível acessar o banco `'.$db.'` com o usuário `'.$user.'`. Em hospedagem compartilhada/cPanel/DirectAdmin o sistema normalmente NÃO consegue criar banco automaticamente. Crie o banco no painel da hospedagem, vincule o usuário MySQL a esse banco e marque TODAS as permissões. Depois confira se o nome do banco está completo com prefixo, exemplo: usuario_loja02, e se o host MySQL está correto.';
}
function test_database_access(string $host, string $db, string $user, string $pass): array {
  try {
    $pdo = new PDO("mysql:host={$host};dbname={$db};charset=utf8mb4", $user, $pass, [PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC]);
    $pdo->query('SELECT 1')->fetchColumn();
    return [true, 'Banco acessível.'];
  } catch (Throwable $e) { install_log_error($e); return [false, 'Falha ao conectar no banco. Confira host, banco, usuário, senha e permissões. Detalhes protegidos no log interno.']; }
}
function test_database_ddl_access(string $host, string $db, string $user, string $pass): array {
  try{
    $pdo=new PDO("mysql:host={$host};dbname={$db};charset=utf8mb4",$user,$pass,[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC,PDO::ATTR_EMULATE_PREPARES=>false]);
    InstallDatabaseProbe::assertDdlPrivileges($pdo);
    return [true,'Privilégios CREATE/ALTER/INDEX/DROP validados com tabela aleatória cuja criação foi comprovada.'];
  }catch(Throwable $e){install_log_error($e);return [false,'O banco abre conexão, mas o usuário não tem permissão DDL suficiente. Consulte o log interno.'];}
}
function split_sql_statements(string $sql): array {
  $out = []; $buf = ''; $quote = null; $escape = false; $len = strlen($sql); $lineComment = false;
  for ($i=0; $i<$len; $i++) {
    $ch = $sql[$i]; $next = $i+1 < $len ? $sql[$i+1] : '';
    if ($lineComment) {
      if ($ch === "\n") { $lineComment = false; $buf .= $ch; }
      continue;
    }
    if (!$quote && $ch === '-' && $next === '-') {
      $prev = $i>0 ? $sql[$i-1] : "\n";
      if ($prev === "\n" || trim($prev) === '') { $lineComment = true; $i++; continue; }
    }
    if ($escape) { $buf .= $ch; $escape = false; continue; }
    if ($quote) {
      $buf .= $ch;
      if ($ch === '\\') { $escape = true; continue; }
      if ($ch === $quote) { $quote = null; }
      continue;
    }
    if ($ch === "'" || $ch === '"' || $ch === '`') { $quote = $ch; $buf .= $ch; continue; }
    if ($ch === ';') {
      $stmt = trim($buf);
      if ($stmt !== '') $out[] = $stmt;
      $buf = '';
      continue;
    }
    $buf .= $ch;
  }
  if (trim($buf) !== '') $out[] = trim($buf);
  return $out;
}
function is_idempotent_sql_error(Throwable $e): bool {
  if (!$e instanceof PDOException) return false;
  $code = (int)($e->errorInfo[1] ?? 0);
  return in_array($code, [1050, 1060, 1061, 1091], true); // tabela/coluna/índice já existe ou não existe em reparo seguro
}
function run_sql(PDO $pdo, string $sql, string $module = 'desconhecido'): void {
  foreach (split_sql_statements($sql) as $stmt) {
    try {
      $pdo->exec($stmt);
    } catch (Throwable $e) {
      if (is_idempotent_sql_error($e)) { continue; }
      throw new RuntimeException('Falha SQL no módulo '.$module.' (comando sha256 '.hash('sha256',$stmt).'). Consulte o log interno.', 0, $e);
    }
  }
}
function without_admin_seed(string $sql): string {
  $pattern = '/INSERT\s+IGNORE\s+INTO\s+`?usuarios`?\s*\([^;]+?;/is';
  $clean = preg_replace($pattern, '', $sql, 1, $count);
  if (!is_string($clean) || $count !== 1 || str_contains($clean, '{ADMIN_')) {
    throw new RuntimeException('O seed administrativo não pôde ser isolado para inserção preparada.');
  }
  return $clean;
}
function install_admin_user(PDO $pdo, string $name, string $email, string $passwordHash): void {
  $pdo->beginTransaction();
  try {
    $existing=(int)$pdo->query('SELECT COUNT(*) FROM usuarios')->fetchColumn();
    if($existing!==0)throw new FriendlyInstallException('Instalação nova bloqueada: a tabela usuarios já contém registros. Use o fluxo separado de atualização/recuperação e preserve as contas existentes.');
    $stmt = $pdo->prepare("INSERT INTO usuarios (nome,email,senha,perfil,ativo) VALUES (:nome,:email,:senha,'admin',1)");
    $stmt->execute([':nome' => $name, ':email' => $email, ':senha' => $passwordHash]);
    $pdo->commit();
  } catch (Throwable $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    throw $e;
  }
}
function split_schema_definitions(string $body): array {
  $items = []; $buffer = ''; $quote = null; $escape = false; $depth = 0; $length = strlen($body);
  for ($i = 0; $i < $length; $i++) {
    $char = $body[$i];
    if ($escape) { $buffer .= $char; $escape = false; continue; }
    if ($quote !== null) {
      $buffer .= $char;
      if ($char === '\\') { $escape = true; continue; }
      if ($char === $quote) $quote = null;
      continue;
    }
    if ($char === "'" || $char === '"' || $char === '`') { $quote = $char; $buffer .= $char; continue; }
    // Comentário SQL fora de aspas é descartado inteiro, até o fim da linha. Sem isto, uma
    // vírgula DENTRO de um comentário partia a lista de definições e o fragmento virava coluna
    // fantasma: o preflight do instalador via colunas chamadas `e`, `os`, `por` e `UPDATE`, e
    // expected_column_contract() lançava "Definição de coluna SQL não reconhecida no contrato
    // canônico", abortando TODA instalação limpa antes do DDL. MySQL exige espaço depois de --;
    // exigir o mesmo evita comer `DEFAULT -1`.
    if ($char === '#' || ($char === '-' && $i + 1 < $length && $body[$i + 1] === '-'
        && ($i + 2 >= $length || preg_match('/\s/', $body[$i + 2]) === 1))) {
      while ($i < $length && $body[$i] !== "\n") $i++;
      $buffer .= ' ';
      continue;
    }
    if ($char === '(') { $depth++; $buffer .= $char; continue; }
    if ($char === ')') { $depth--; $buffer .= $char; continue; }
    if ($char === ',' && $depth === 0) { if (trim($buffer) !== '') $items[] = trim($buffer); $buffer = ''; continue; }
    $buffer .= $char;
  }
  if (trim($buffer) !== '') $items[] = trim($buffer);
  return $items;
}
function sql_schema_contract(string $sql): array {
  $contract = [];
  preg_match_all('/CREATE\s+TABLE\s+(?:IF\s+NOT\s+EXISTS\s+)?`?([a-zA-Z0-9_]+)`?\s*\((.*?)\)\s*ENGINE\s*=\s*([a-zA-Z0-9_]+)([^;]*);/is', $sql, $matches, PREG_SET_ORDER);
  foreach ($matches as $match) {
    $table = strtolower($match[1]);
    preg_match('/(?:DEFAULT\s+)?CHARSET\s*=\s*([a-zA-Z0-9_]+)/i',(string)($match[4]??''),$charsetMatch);
    preg_match('/COLLATE\s*=\s*([a-zA-Z0-9_]+)/i',(string)($match[4]??''),$collationMatch);
    $contract[$table] = [
      'columns'=>[], 'indexes'=>[], 'constraints'=>[],
      'engine'=>strtolower((string)$match[3]),
      'charset'=>strtolower((string)($charsetMatch[1]??'')),
      'collation'=>strtolower((string)($collationMatch[1]??'')),
    ];
    foreach (split_schema_definitions($match[2]) as $definition) {
      if (preg_match('/^PRIMARY\s+KEY\s*\((.*?)\)/is', $definition, $primaryMatch)) {
        $contract[$table]['indexes']['primary'] = ['unique'=>true,'columns'=>schema_index_columns($primaryMatch[1])];
        continue;
      }
      if (preg_match('/^(UNIQUE\s+)?(?:KEY|INDEX)\s+`?([a-zA-Z0-9_]+)`?\s*\((.*?)\)/is', $definition, $indexMatch)) {
        $contract[$table]['indexes'][strtolower($indexMatch[2])] = ['unique'=>trim((string)$indexMatch[1])!=='','columns'=>schema_index_columns($indexMatch[3])];
        continue;
      }
      if (preg_match('/^(?:CONSTRAINT|FOREIGN\s+KEY|CHECK)\b/i', $definition)) {
        $contract[$table]['constraints'][]=normalized_schema_sql($definition);
        continue;
      }
      if (!preg_match('/^`?([a-zA-Z0-9_]+)`?\s+(.+)$/is', $definition, $columnMatch)) continue;
      $column = strtolower($columnMatch[1]);
      $contract[$table]['columns'][$column] = expected_column_contract($columnMatch[2]);
      if (preg_match('/\bPRIMARY\s+KEY\b/i', $columnMatch[2])) $contract[$table]['indexes']['primary'] = ['unique'=>true,'columns'=>[$column]];
      if (preg_match('/\bUNIQUE\b/i', $columnMatch[2])) $contract[$table]['indexes'][$column] = ['unique'=>true,'columns'=>[$column]];
    }
    sort($contract[$table]['constraints']);
  }
  return $contract;
}
function schema_index_columns(string $body): array {
  $columns=[];
  foreach(split_schema_definitions($body) as $part){
    if(preg_match('/^`?([a-zA-Z0-9_]+)`?(?:\s*\(\d+\))?(?:\s+(?:ASC|DESC))?$/i',trim($part),$match))$columns[]=strtolower($match[1]);
  }
  return $columns;
}
function normalized_schema_sql(string $sql): string {
  $sql=strtolower(str_replace('`','',trim($sql)));
  $sql=preg_replace('/\s+/',' ',$sql)??$sql;
  $sql=preg_replace('/\s*,\s*/',',',$sql)??$sql;
  return preg_replace('/\s*([()])\s*/','$1',$sql)??$sql;
}
function expected_column_contract(string $definition): array {
  $definition=trim($definition);
  if(!preg_match('/^([a-zA-Z]+(?:\s*\([^)]*\))?(?:\s+UNSIGNED)?)(?:\s+|$)(.*)$/is',$definition,$match))throw new RuntimeException('Definição de coluna SQL não reconhecida no contrato canônico.');
  $rest=(string)$match[2];
  $default=null;
  if(preg_match('/\bDEFAULT\s+((?:\'(?:\'\'|\\\\.|[^\'])*\')|(?:"(?:""|\\\\.|[^"])*")|(?:\([^)]*\))|(?:[^\s,]+))/i',$rest,$defaultMatch))$default=normalized_schema_default($defaultMatch[1]);
  $onUpdate=null;
  if(preg_match('/\bON\s+UPDATE\s+([^\s,]+)/i',$rest,$updateMatch))$onUpdate=normalized_schema_default($updateMatch[1]);
  return [
    'type'=>normalized_column_type($match[1]),
    'nullable'=>!preg_match('/\bNOT\s+NULL\b/i',$rest)&&!preg_match('/\bPRIMARY\s+KEY\b/i',$rest),
    'default'=>$default,
    'auto_increment'=>(bool)preg_match('/\bAUTO_INCREMENT\b/i',$rest),
    'on_update'=>$onUpdate,
  ];
}
function normalized_column_type(string $type): string {
  $type = normalized_schema_sql($type);
  // Reauditoria 2026-09-14 (bloqueava a instalação em MariaDB 10.11 real): normalized_schema_sql()
  // remove o espaço ao redor de parênteses, então o COLUMN_TYPE do MariaDB "bigint(20) unsigned"
  // chega aqui como "bigint(20)unsigned"; ao remover a largura de exibição sem repor o separador,
  // virava "bigintunsigned" e nunca batia com o esperado do DDL ("bigint unsigned"). O MySQL 8.0.19+
  // não expõe largura de exibição, então o defeito só aparecia em MariaDB - e derrubava o gate
  // pós-instalação de TODA tabela com coluna inteira UNSIGNED (security_events.id, ips_bloqueados.id,
  // rate_limit_hits.id, integration_replay_guard.time_bucket).
  $type = preg_replace('/^(tinyint|smallint|mediumint|int|bigint)\(\d+\)\s*/', '$1 ', $type) ?? $type;
  $type = trim($type);
  return match($type){'integer'=>'int','bool','boolean'=>'tinyint',default=>$type};
}
function normalized_schema_default($value): ?string {
  if ($value === null) return null;
  $value = trim((string)$value, " \t\n\r\0\x0B'\"");
  $value=strtolower($value);
  if($value==='null')return null;
  if($value==='current_timestamp()')return 'current_timestamp';
  return $value;
}
function schema_defaults_equal($actual, $expected, string $type): bool {
  $actual=normalized_schema_default($actual); $expected=normalized_schema_default($expected);
  if($actual===$expected)return true;
  if(preg_match('/^(?:tinyint|smallint|mediumint|int|bigint|decimal|numeric|float|double)/',$type)&&$actual!==null&&$expected!==null&&is_numeric($actual)&&is_numeric($expected))return (float)$actual===(float)$expected;
  return false;
}
function assert_fresh_install_targets(string $root,string $host,string $user,string $pass,array $dbs,array $modules): void {
  $expectedByDatabase=[];
  foreach($modules as $module){
    $source=@file_get_contents($root.'/database/modules/'.$module.'.sql');
    if(!is_string($source))throw new RuntimeException('SQL obrigatório ilegível no preflight: '.$module.'.');
    $database=(string)($dbs[$module]??'');
    foreach(array_keys(sql_schema_contract($source)) as $table)$expectedByDatabase[$database][$table]=true;
  }
  foreach($expectedByDatabase as $database=>$tables){
    $names=array_keys($tables);
    if($names===[])continue;
    $pdo=new PDO("mysql:host={$host};dbname={$database};charset=utf8mb4",$user,$pass,[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC,PDO::ATTR_EMULATE_PREPARES=>false]);
    $placeholders=implode(',',array_fill(0,count($names),'?'));
    $stmt=$pdo->prepare('SELECT TABLE_NAME FROM information_schema.TABLES WHERE TABLE_SCHEMA=? AND LOWER(TABLE_NAME) IN ('.$placeholders.') ORDER BY TABLE_NAME');
    $stmt->execute(array_merge([$database],array_map('strtolower',$names)));
    $collisions=array_map(static fn(array $row):string=>(string)$row['TABLE_NAME'],$stmt->fetchAll());
    if($collisions!==[]){
      $preview=implode(', ',array_slice($collisions,0,12));
      if(count($collisions)>12)$preview.=' e mais '.(count($collisions)-12).' tabela(s)';
      throw new FriendlyInstallException('Instalação nova bloqueada: o banco '.$database.' já contém tabelas do Hub ('.$preview.'). Use Central Técnica > Banco > Enterprise Core ou as migrations de atualização; o instalador nunca sobrescreve uma base existente.');
    }
  }
}
function post_install_schema_gate(string $root, string $host, string $user, string $pass, array $dbs, array $modules): array {
  $missing = []; $checkedTables = 0; $checkedColumns = 0; $checkedIndexes = 0;
  foreach ($modules as $module) {
    $file = $root.'/database/modules/'.$module.'.sql';
    $source = is_file($file) ? file_get_contents($file) : false;
    if (!is_string($source)) { $missing[] = $module.':arquivo_sql'; continue; }
    $contract = sql_schema_contract($source);
    if ($contract === []) { $missing[] = $module.':contrato_vazio'; continue; }

    $database = (string)($dbs[$module] ?? '');
    $pdo = new PDO("mysql:host={$host};dbname={$database};charset=utf8mb4", $user, $pass, [
      PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
      PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
      PDO::ATTR_EMULATE_PREPARES => false,
    ]);
    $tableStmt = $pdo->prepare('SELECT TABLE_NAME,ENGINE,TABLE_COLLATION FROM information_schema.TABLES WHERE TABLE_SCHEMA = :schema');
    $tableStmt->execute([':schema' => $database]);
    $actualTables = [];
    foreach ($tableStmt->fetchAll() as $row) $actualTables[strtolower((string)$row['TABLE_NAME'])] = $row;

    $columnStmt = $pdo->prepare('SELECT TABLE_NAME,COLUMN_NAME,COLUMN_TYPE,IS_NULLABLE,COLUMN_DEFAULT,EXTRA FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = :schema');
    $columnStmt->execute([':schema' => $database]);
    $actualColumns = [];
    foreach ($columnStmt->fetchAll() as $row) $actualColumns[strtolower((string)$row['TABLE_NAME'])][strtolower((string)$row['COLUMN_NAME'])] = $row;

    $indexStmt = $pdo->prepare('SELECT TABLE_NAME,INDEX_NAME,NON_UNIQUE,SEQ_IN_INDEX,COLUMN_NAME FROM information_schema.STATISTICS WHERE TABLE_SCHEMA = :schema ORDER BY TABLE_NAME,INDEX_NAME,SEQ_IN_INDEX');
    $indexStmt->execute([':schema' => $database]);
    $actualIndexes = [];
    foreach ($indexStmt->fetchAll() as $row) {
      $tableKey=strtolower((string)$row['TABLE_NAME']); $indexKey=strtolower((string)$row['INDEX_NAME']);
      $actualIndexes[$tableKey][$indexKey] ??= ['unique'=>(int)$row['NON_UNIQUE']===0,'columns'=>[]];
      $actualIndexes[$tableKey][$indexKey]['columns'][]=strtolower((string)$row['COLUMN_NAME']);
    }

    foreach ($contract as $table => $requirements) {
      $checkedTables++;
      if (empty($actualTables[$table])) { $missing[] = $database.'.'.$table; continue; }
      $actualTable=$actualTables[$table];
      if(strtolower((string)$actualTable['ENGINE'])!==$requirements['engine'])$missing[]=$database.'.'.$table.'!engine';
      $actualCollation=strtolower((string)($actualTable['TABLE_COLLATION']??''));
      $actualCharset=strtolower((string)(explode('_',$actualCollation,2)[0]??''));
      if($requirements['charset']!==''&&$actualCharset!==$requirements['charset'])$missing[]=$database.'.'.$table.'!charset';
      if($requirements['collation']!==''&&$actualCollation!==$requirements['collation'])$missing[]=$database.'.'.$table.'!collation';
      foreach ($requirements['columns'] as $column=>$expected) {
        $checkedColumns++;
        $actual=$actualColumns[$table][$column]??null;
        if (!is_array($actual)){ $missing[] = $database.'.'.$table.'.'.$column; continue; }
        $actualType=normalized_column_type((string)$actual['COLUMN_TYPE']);
        if($actualType!==$expected['type'])$missing[]=$database.'.'.$table.'.'.$column.'!tipo';
        if(((string)$actual['IS_NULLABLE']==='YES')!==$expected['nullable'])$missing[]=$database.'.'.$table.'.'.$column.'!nulabilidade';
        if(!schema_defaults_equal($actual['COLUMN_DEFAULT'],$expected['default'],$expected['type']))$missing[]=$database.'.'.$table.'.'.$column.'!default';
        $extra=strtolower((string)($actual['EXTRA']??''));
        if(str_contains($extra,'auto_increment')!==$expected['auto_increment'])$missing[]=$database.'.'.$table.'.'.$column.'!auto_increment';
        $actualOnUpdate=null;
        if(preg_match('/on update\s+([^\s]+)/i',$extra,$updateMatch))$actualOnUpdate=normalized_schema_default($updateMatch[1]);
        if($actualOnUpdate!==$expected['on_update'])$missing[]=$database.'.'.$table.'.'.$column.'!on_update';
      }
      foreach ($requirements['indexes'] as $index=>$expected) {
        $checkedIndexes++;
        $actual=$actualIndexes[$table][$index] ?? null;
        if(!is_array($actual)){ $missing[]=$database.'.'.$table.'#'.$index; continue; }
        if((bool)$actual['unique']!==$expected['unique']||$actual['columns']!==$expected['columns'])$missing[]=$database.'.'.$table.'#'.$index.'!contrato';
      }
    }
  }
  $missing = array_values(array_unique($missing));
  sort($missing);
  return [
    'ok' => $missing === [],
    'missing' => $missing,
    'tables' => $checkedTables,
    'columns' => $checkedColumns,
    'indexes' => $checkedIndexes,
  ];
}
function install_log_error(Throwable $e, array $context = []): string {
  $trace = 'INST-' . date('Ymd-His') . '-' . strtoupper(bin2hex(random_bytes(4)));
  $dir = dirname(__DIR__) . '/storage/logs';
  if (!is_dir($dir)) @mkdir($dir, 0755, true);
  $message=preg_replace([
    '/(password|senha|token|secret|authorization)(["\'\s:=]+)([^"\'\s,;}]+)/i',
    '/[\w._%+-]+@[\w.-]+\.[A-Za-z]{2,}/',
  ],['$1$2***','***email***'],(string)$e->getMessage())??'Erro técnico protegido.';
  $statement=(string)($context['statement']??'');
  $safeContext = [
    'module' => (string)($context['module'] ?? 'desconhecido'),
    'statement_sha256' => $statement!==''?hash('sha256',$statement):'',
    'sqlstate' => $e instanceof PDOException ? ($e->errorInfo[0] ?? '') : '',
    'driver_code' => $e instanceof PDOException ? ($e->errorInfo[1] ?? '') : '',
    'exception' => get_class($e),
  ];
  @error_log('['.$trace.'] '.json_encode($safeContext, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES).PHP_EOL.mb_substr($message,0,2000).' em '.$e->getFile().':'.$e->getLine().PHP_EOL.$e->getTraceAsString().PHP_EOL, 3, $dir.'/install_errors.log');
  return $trace;
}

function checks(): array {
  return [
    'PHP >= 8.1' => version_compare(PHP_VERSION, '8.1.0', '>='),
    'PDO' => extension_loaded('pdo'), 'PDO MySQL' => extension_loaded('pdo_mysql'), 'cURL' => extension_loaded('curl'),
    'OpenSSL' => extension_loaded('openssl'), 'JSON' => extension_loaded('json'), 'MBString' => extension_loaded('mbstring'),
    // Sugestão pós-análise da doc de Multi-WebServer do aaPanel: o lsphp do OpenLiteSpeed
    // é um build separado (repositório próprio, rpms.litespeedtech.com), então extensões
    // "óbvias" não vêm garantidas por padrão. ZipArchive (backup) e SimpleXML (NF-e) já
    // eram usadas no código sem checagem no instalador - uma delas faltando só aparecia
    // depois, na hora do primeiro backup ou da primeira nota fiscal processada.
    'Extensão ZIP (backup)' => extension_loaded('zip') && class_exists('ZipArchive'),
    'Extensão SimpleXML (NF-e)' => extension_loaded('simplexml') && function_exists('simplexml_load_string'),
    'Config gravável' => is_writable(dirname(__DIR__).'/config'),
    'Storage gravável' => is_dir(dirname(__DIR__).'/storage') && is_writable(dirname(__DIR__).'/storage'),
    'Logs gravável' => (is_dir(dirname(__DIR__).'/storage/logs') || mkdir(dirname(__DIR__).'/storage/logs',0775,true)) && is_writable(dirname(__DIR__).'/storage/logs'),
    'Backups gravável' => (is_dir(dirname(__DIR__).'/storage/backups') || mkdir(dirname(__DIR__).'/storage/backups',0775,true)) && is_writable(dirname(__DIR__).'/storage/backups'),
    'SQL modular fiscal/NF-e V44' => is_file(dirname(__DIR__).'/database/modules/fiscal.sql'),
    'Cache gravável V50' => (is_dir(dirname(__DIR__).'/storage/cache') || mkdir(dirname(__DIR__).'/storage/cache',0775,true)) && is_writable(dirname(__DIR__).'/storage/cache'),
  ];
}

$default = [
  'db_host'=>'127.0.0.1', 'db_name'=>'hub_vsm_tiny', 'db_user'=>'root', 'db_pass'=>'',
  'base_url'=>rtrim(str_replace('\\','/', dirname($_SERVER['SCRIPT_NAME'] ?? '/hub-vsm-tiny-php-puro/public')), '/'),
  'app_env'=>'production', 'ambiente'=>'homologacao', 'admin_nome'=>'Administrador', 'admin_email'=>'admin@hub.com', 'admin_senha'=>'', 'require_admin_2fa'=>'0',
  'modular'=>'0', 'db_create_mode'=>'existing', 'confirm_local_insecure_mysql'=>'0'
];
$messages=[]; $success=false; $installSteps=[];
$authorizationMessage = '';
$authorizationClaimed = false;
$locked = is_file(__DIR__.'/install.lock') || is_file($lockPath);
if ($locked) http_response_code(403);
if ($httpsBlocked) http_response_code(426);

$requestMethod = strtoupper((string)($_SERVER['REQUEST_METHOD'] ?? 'GET'));
$postAction = (string)($_POST['action'] ?? '');
if (!$locked && !$httpsBlocked && $requestMethod === 'POST' && $postAction === 'authorize') {
  if (!valid_csrf()) {
    $authorizationMessage = 'Sessão expirada ou solicitação inválida. Atualize a página e tente novamente.';
  } else {
    [$bound, $authorizationMessage] = bind_install_authorization($authorizationPath, (string)($_POST['authorization_code'] ?? ''));
    if ($bound) {
      header('Location: install.php', true, 303); // o código nunca é colocado na URL.
      exit;
    }
  }
}
[$installAuthorized, $installAuthorizationStatus] = (!$locked && !$httpsBlocked)
  ? install_authorization_status($authorizationPath)
  : [false, 'Indisponível'];

if (!$locked && !$httpsBlocked && $installAuthorized && $requestMethod === 'POST' && $postAction === 'install') {
  $data=[]; foreach($default as $k=>$v){ $data[$k] = trim((string)($_POST[$k] ?? $v)); } $data['db_pass']=(string)($_POST['db_pass'] ?? '');
  try {
    if (!valid_csrf()) throw new FriendlyInstallException('Sessão expirada ou solicitação inválida. Atualize a página e tente novamente.');
    $data['db_host']=validate_db_host($data['db_host']);
    $data['db_name']=validate_db_name($data['db_name']);
    $data['db_user']=validate_db_user($data['db_user']);
    $data['admin_nome']=validate_admin_name($data['admin_nome']);
    if(strlen($data['db_pass'])>4096||str_contains($data['db_pass'],"\0"))throw new FriendlyInstallException('Senha MySQL inválida ou acima do limite permitido.');
    if (!in_array($data['app_env'], ['local','production'], true)) throw new FriendlyInstallException('Modo da aplicação inválido.');
    if (!in_array($data['ambiente'], ['homologacao','producao'], true)) throw new FriendlyInstallException('Ambiente de integração inválido.');
    if (!in_array($data['db_create_mode'], ['existing','create'], true)) throw new FriendlyInstallException('Modo de criação do banco inválido.');
    if ($data['admin_email']==='' || strlen($data['admin_email'])>254 || preg_match('/[\x00-\x1F\x7F]/',$data['admin_email']) || !filter_var($data['admin_email'], FILTER_VALIDATE_EMAIL)) throw new FriendlyInstallException('Informe um e-mail válido para o administrador.');
    $isRootBlank = is_root_no_password($data['db_user'], $data['db_pass']);
    // P1-06: qualquer usuário com senha vazia (não só root) é bloqueado nas mesmas condições.
    $isBlankPasswordUser = is_blank_password_credential($data['db_pass']);
    $isProduction = (($data['app_env'] ?? 'production') === 'production');
    $isLocalConfirmed = (($data['app_env'] ?? '') === 'local') && is_loopback_host($data['db_host']) && (($_POST['confirm_local_insecure_mysql'] ?? '0') === '1');
    $data['base_url']=validate_base_url($data['base_url'],$isProduction);
    if ($isRootBlank && ($isProduction || !$isLocalConfirmed)) {
      throw new FriendlyInstallException(insecure_root_message());
    }
    if ($isBlankPasswordUser && !$isRootBlank && ($isProduction || !$isLocalConfirmed)) {
      throw new FriendlyInstallException('Credencial MySQL insegura bloqueada: o usuário "'.$data['db_user'].'" está com senha em branco. Defina uma senha forte para esse usuário no MySQL antes de instalar em produção, ou use Modo Local com a confirmação de ambiente local marcada.');
    }
    if (($data['ambiente'] ?? 'homologacao') === 'producao' && ($data['app_env'] ?? 'local') !== 'production') throw new FriendlyInstallException('Ambiente produção exige app_env=production. Para teste local, mantenha Ambiente=Homologação.');
    if (strlen($data['admin_senha']) < 10 || strlen($data['admin_senha']) > 4096 || preg_match('/[\x00-\x1F\x7F]/',$data['admin_senha'])) throw new FriendlyInstallException('Senha do administrador deve ter de 10 a 4096 caracteres e não pode conter controles.');
    if (!preg_match('/[A-Z]/', $data['admin_senha']) || !preg_match('/[a-z]/', $data['admin_senha']) || !preg_match('/[0-9]/', $data['admin_senha'])) throw new FriendlyInstallException('Senha deve conter maiúscula, minúscula e número.');
    if (in_array(strtolower($data['admin_senha']), ['admin123','admin1234','123456','12345678','password','senha123'], true)) throw new FriendlyInstallException('Senha administrativa fraca/bloqueada. Use senha forte, exclusiva e com no mínimo 10 caracteres.');
    if (!claim_install_authorization($authorizationPath)) throw new FriendlyInstallException('A autorização temporária expirou ou já está sendo usada. Gere uma nova, se necessário.');
    $authorizationClaimed = true;
    $pdoServer = new PDO("mysql:host={$data['db_host']};charset=utf8mb4", $data['db_user'], $data['db_pass'], [PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC, PDO::ATTR_EMULATE_PREPARES=>false]);
    $base = $data['db_name']; $modular = ($data['modular'] ?? '1') === '1';
    $modules = ['core','pedidos','produtos','estoque','fiscal','fila','observabilidade','backups'];
    if($modular&&strlen($base.'_observabilidade')>64)throw new FriendlyInstallException('O nome base é longo demais para o modo modular. Use no máximo 48 caracteres ou selecione banco único.');
    $dbs=[];
    foreach($modules as $m){ $dbs[$m] = $modular ? $base.'_'.$m : $base; }

    $createDatabase = function(string $db) use ($pdoServer, &$installSteps, $data) {
      $mode = $data['db_create_mode'] ?? 'existing';

      // Modo recomendado para hospedagem compartilhada: banco já criado no painel.
      if ($mode === 'existing') {
        [$ok, $msg] = test_database_access($data['db_host'], $db, $data['db_user'], $data['db_pass']);
        if ($ok) { $installSteps[] = 'Banco existente validado: '.$db; return true; }
        $installSteps[] = 'Falha ao validar banco existente '.$db.': '.$msg;
        return false;
      }

      // Modo local/VPS: tenta criar o banco. Se não conseguir, tenta usar banco existente.
      try {
        $pdoServer->exec("CREATE DATABASE IF NOT EXISTS `{$db}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
        [$ok, $msg] = test_database_access($data['db_host'], $db, $data['db_user'], $data['db_pass']);
        if (!$ok) { $installSteps[] = 'Banco criado/preparado, mas a conexão ao banco falhou '.$db.': '.$msg; return false; }
        $installSteps[] = 'Banco criado/preparado: '.$db;
        return true;
      } catch (Throwable $e) {
        [$ok, $msg] = test_database_access($data['db_host'], $db, $data['db_user'], $data['db_pass']);
        if ($ok) { $installSteps[] = 'Banco existente acessível: '.$db; return true; }
        $installSteps[] = 'Sem permissão/acesso ao banco '.$db.': '.$msg;
        return false;
      }
    };

    $dbAccessOk = true;
    foreach(array_unique($dbs) as $db){ if (!$createDatabase($db)) { $dbAccessOk = false; break; } }

    if (!$dbAccessOk && $modular) {
      // Fallback seguro para hospedagem compartilhada: todos os módulos no banco informado.
      $installSteps[] = 'Fallback automático: usuário MySQL sem acesso aos bancos modulares. Usando banco único: '.$base;
      $modular = false;
      foreach($modules as $m){ $dbs[$m] = $base; }
      if (!$createDatabase($base)) {
        throw new Exception(hosting_db_help($base, $data['db_user']));
      }
    } elseif (!$dbAccessOk) {
      throw new Exception(hosting_db_help($base, $data['db_user']));
    }
    assert_fresh_install_targets($root,$data['db_host'],$data['db_user'],$data['db_pass'],$dbs,$modules);
    $installSteps[]='Preflight aprovado: nenhum banco de destino contém tabelas preexistentes do Hub.';
    foreach(array_unique($dbs) as $db){
      [$ddlOk,$ddlMessage]=test_database_ddl_access($data['db_host'],$db,$data['db_user'],$data['db_pass']);
      if(!$ddlOk)throw new FriendlyInstallException('Privilégio DDL insuficiente no banco '.$db.'. '.$ddlMessage);
    }
    $installSteps[]='Permissão DDL validada após o preflight com tabelas aleatórias, isoladas e removidas somente após criação comprovada.';
    $seedWebhookSecret = random_secret();
    $adminHash = password_hash($data['admin_senha'], PASSWORD_DEFAULT);
    if (!is_string($adminHash) || $adminHash === '') throw new RuntimeException('Não foi possível proteger a senha administrativa.');
    $repl = [
      '{AMBIENTE}'=>$data['ambiente'], '{TINY_VERSION}'=>'v2', '{TINY_V2_URL}'=>'https://api.tiny.com.br/api2', '{TINY_V2_TOKEN}'=>'',
      '{TINY_V3_URL}'=>'https://api.tiny.com.br/public-api/v3', '{TINY_V3_TOKEN}'=>'', '{VSM_URL}'=>'https://conectavenda.homolog.vsm.com.br', '{VSM_TOKEN}'=>'', '{WEBHOOK_SECRET}'=>$seedWebhookSecret,
    ];
    foreach($modules as $m){
      $file = $root.'/database/modules/'.$m.'.sql';
      if (!is_file($file)) throw new RuntimeException('SQL obrigatório ausente: '.$m.'.');
      $moduleSql = file_get_contents($file);
      if (!is_string($moduleSql)) throw new RuntimeException('SQL obrigatório ilegível: '.$m.'.');
      if ($m === 'core') $moduleSql = without_admin_seed($moduleSql);
      $pdo = new PDO("mysql:host={$data['db_host']};dbname={$dbs[$m]};charset=utf8mb4", $data['db_user'], $data['db_pass'], [PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC, PDO::ATTR_EMULATE_PREPARES=>false]);
      run_sql($pdo, strtr($moduleSql, $repl), $m);
      $installSteps[]='Módulo instalado: '.$m.' → '.$dbs[$m];
    }

    $corePdo = new PDO("mysql:host={$data['db_host']};dbname={$dbs['core']};charset=utf8mb4", $data['db_user'], $data['db_pass'], [PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC, PDO::ATTR_EMULATE_PREPARES=>false]);
    install_admin_user($corePdo, $data['admin_nome'], $data['admin_email'], $adminHash);
    $installSteps[] = 'Administrador inserido por comando PDO preparado.';

    $schemaGate = post_install_schema_gate($root, $data['db_host'], $data['db_user'], $data['db_pass'], $dbs, $modules);
    if (!$schemaGate['ok']) {
      $preview = implode(', ', array_slice($schemaGate['missing'], 0, 30));
      if (count($schemaGate['missing']) > 30) $preview .= ' e mais '.(count($schemaGate['missing']) - 30).' item(ns)';
      throw new FriendlyInstallException('Gate pós-instalação reprovado; nenhum lock ou sucesso foi gravado. Contratos ausentes: '.$preview.'.');
    }
    $installSteps[] = 'Gate pós-instalação aprovado: '.$schemaGate['tables'].' tabelas, '.$schemaGate['columns'].' colunas e '.$schemaGate['indexes'].' índices, com tipos, nulabilidade, defaults, ordem/unicidade, engine, charset e collation declarada verificados.';
    $cfg = [
      'app_name'=>'Hub de Integração Enterprise', 'app_version'=>'V104.49.3-R5', 'release_date'=>'2026-08-22', 'installation_id'=>bin2hex(random_bytes(16)), 'base_url'=>$data['base_url'], 'app_env'=>$data['app_env'],
      'security'=>[
        'block_search_engines'=>true,
        'force_https'=>true,
        'admin_ip_allowlist'=>'',
        'session_driver'=>'file', // C-07: trocar para 'database' ao escalar para mais de um servidor
        'queue_done_retention_days'=>30, // C-06
        'session_idle_timeout_seconds'=>1800,
        'session_absolute_timeout_seconds'=>28800,
        'max_login_attempts'=>5,
        'password_min_length'=>10,
        'login_lock_minutes'=>15,
        'protect_sensitive_files'=>true,
        'content_security_policy'=>"default-src 'self'; base-uri 'self'; frame-ancestors 'self'; object-src 'none'; img-src 'self' data:; font-src 'self' data:; style-src 'self' 'nonce-__NONCE__'; script-src 'self' 'nonce-__NONCE__'; connect-src 'self'; form-action 'self'; upgrade-insecure-requests",
        'login_ip_rate_limit_per_hour'=>20,
        'login_ip_rate_limit_per_minute'=>5,
        'waf_panel_only'=>true,
        'waf_panel_routes'=>'dashboard,dashboard-executivo,configuracoes,usuarios,central-tecnica,seguranca-extrema,security-center,security-events,security-ips,security-circuit-breakers,security-assisted-test,security-hardening,security-ssl,security-user-audit,security-pentest,security-score,security-fim,security-soc,security-code-audit,security-inventory,security-backup-trust,security-health,security-audit-signatures,backups,backup,backup-download,backup-importar,backup-restaurar,validar-banco,mapa-banco,health-modulos,enterprise-core,enterprise-core-aplicar,migracoes-seguras,migracao-aplicar,entrada-producao,producao-ready',
    'waf_never_inspect_routes' => 'api/tiny/*,api/vsm/*,api/webhook/tiny/*,api/webhook/vsm/*,webhook/tiny/*,webhook/vsm/*',
        'login_user_rate_limit_per_hour'=>10,
        'login_user_rate_limit_per_minute'=>3,
        'require_admin_2fa'=>(($_POST['require_admin_2fa'] ?? '0') === '1'),
        'session_fingerprint_use_ip_prefix'=>true,
        'tiny_oauth_required'=>true,
        // Melhoria 2 da seção 8 (relatório V104.49.3-R6): instalação NOVA nasce exigindo a
        // assinatura v2, que cobre método HTTP e rota canônica além de timestamp, nonce e corpo.
        // Não existe integração legada numa base nova, então manter a v1 aceita aqui seria criar
        // dívida no dia zero. Instalações existentes continuam com o valor que já têm e recebem
        // o aviso do painel a cada webhook v1 aceito, até migrarem.
        'webhook_signature_require_v2'=>true,
        'integration_retry_attempts'=>3,
        'integration_retry_base_ms'=>350,
        'queue_processing_timeout_minutes'=>30,
        'queue_lease_minutes'=>5,
        'queue_lease_minutes_by_type'=>[
          'pedido_tiny_para_vsm'=>5,'baixa_estoque_vsm'=>5,'produto_vsm_para_tiny'=>10,
          'produto_vsm_atualizar_tiny'=>10,'produto_vsm_estoque_para_tiny'=>5,'produto_vsm_status_para_tiny'=>5,
        ],
        'vsm_hmac_enabled'=>false,
        'vsm_hmac_secret'=>'',
        'vsm_allowed_ips'=>'',
        'vsm_allowed_hosts'=>'conectavenda.homolog.vsm.com.br',
        'vsm_allowed_ports'=>'443,80',
        'tiny_allowed_hosts'=>'api.tiny.com.br,accounts.tiny.com.br',
        'tiny_allowed_ports'=>'443',
        'tiny_allowed_ips'=>'',
        'tiny_webhook_exigir_secret'=>true,
        'require_hmac_in_production'=>true,
        'webhook_hmac_window_seconds'=>300,
        'webhook_max_bytes'=>1048576,
        'webhook_rate_limit_per_minute'=>60,
        'integration_anti_replay_window_seconds'=>600,
        'integration_replay_hmac_key'=>random_secret(),
        'audit_daily_signature_enabled'=>true,
        'audit_daily_signature_key'=>random_secret(),
        'token_vault_hmac_key'=>random_secret(),
        'encryption_key'=>random_secret(),
        'backup_signature_key'=>random_secret(),
        'fim_manifest_hmac_key'=>random_secret(),
        'trusted_proxies'=>'',
        // Reauditoria 2026-09-14 (achado A-04): canonical_host nunca era gravado, então
        // TrustedProxyService::isHostAllowed() nascia permissivo e o cabeçalho Host continuava
        // decidindo sozinho o domínio efetivo. O host usado para concluir a instalação é, por
        // definição, o canônico - fica persistido aqui e pode ser ajustado depois no config.
        'canonical_host'=>strtolower(trim(preg_replace('/:\d+$/', '', (string)($_SERVER['HTTP_HOST'] ?? '')) ?? '')),
        'route_rate_limit_per_minute'=>90,
        'route_rate_limit_sensitive_per_minute'=>20,
        // Achado C-01: webhooks de entrada têm orçamento próprio, alto por natureza.
        'webhook_route_rate_limit_per_minute'=>3000,
        'api_status_public_mode'=>'minimal',
        'csp_report_uri'=>'index.php?page=api/csp-report',
        'webhook_secret'=>random_secret(),
        'tiny_webhook_secret'=>random_secret(),
      ],
      'db'=>['host'=>$data['db_host'],'name'=>$dbs['core'],'user'=>$data['db_user'],'pass'=>$data['db_pass'],'charset'=>'utf8mb4'],
      'db_storage_mode'=>$modular ? 'modular' : 'single',
      'db_modular_strict'=>$modular,
      'db_single_database_rescue'=>!$modular,
      'db_modules'=>array_map(fn($name)=>['name'=>$name], $dbs),
      'enterprise'=>[
        'schema_auto_apply'=>false,
        'schema_runtime_repair_enabled'=>false,
        'queue_worker_batch_limit'=>50,
        'dlq_classification_enabled'=>true,
        'observability_snapshot_enabled'=>true,
        'quality_gate_min_score'=>85,
        'idempotency_strict_mode'=>true,
        'idempotency_failure_policy'=>'dlq',
        'queue_skip_locked_enabled'=>true,
        'queue_retry_profile'=>'enterprise',
        'queue_worker_graceful_stop_seconds'=>300,
        'futuristic_ui_enabled'=>true,
        'futuristic_ui_density'=>'comfortable',
      ],
      'commercial'=>[
        'license_mode'=>'monitor',
        'allow_unlicensed_internal_use'=>true,
        'tenant_scope_required'=>false,
        'public_landing_noindex'=>true,
      ],
      'tiny'=>['version'=>'v2','v2_url'=>'https://api.tiny.com.br/api2','v3_url'=>'https://api.tiny.com.br/public-api/v3'],
      'vsm'=>['url'=>'https://conectavenda.homolog.vsm.com.br'],
    ];
    publish_install_artifacts($configPath,$root,$cfg);
    complete_install_authorization($authorizationPath);
    $authorizationClaimed = false;
    $messages[]='Instalação '.trim((string)@file_get_contents($root.'/VERSAO.txt')).' concluída com banco '.($modular?'modular':'único').', contrato estrutural validado, fiscal/NF-e e segurança forte.'; $success=true;
  } catch(FriendlyInstallException $e) {
    if ($authorizationClaimed) {
      consume_install_authorization($authorizationPath);
      $installAuthorized = false;
      $installAuthorizationStatus = 'Autorização consumida. Gere uma nova pelo terminal para tentar novamente.';
      $authorizationClaimed = false;
    }
    $messages[] = $e->getMessage().' Se a falha ocorreu após o início do DDL, o banco pode ter estrutura parcial; o rollback automático cobre somente config.php, manifesto FIM e install.lock. Revise o banco antes de uma nova tentativa.';
  } catch(Throwable $e) {
    if ($authorizationClaimed) {
      consume_install_authorization($authorizationPath);
      $installAuthorized = false;
      $installAuthorizationStatus = 'Autorização consumida. Gere uma nova pelo terminal para tentar novamente.';
      $authorizationClaimed = false;
    }
    $trace=install_log_error($e); $messages[]='Erro técnico na instalação. Detalhes protegidos no log interno. O rollback automático cobre os artefatos locais, não o DDL MySQL, que pode ter ficado parcial. Trace: '.$trace;
  }
}
$checks=checks(); $okCount=count(array_filter($checks)); $total=count($checks);
function val($key,$default){ return h($_POST[$key] ?? $default[$key] ?? ''); }
?>
<!doctype html><html lang="pt-br"><head><meta charset="utf-8">
<meta name="robots" content="noindex,nofollow,noarchive,nosnippet,noimageindex"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Instalador Hub de Integração</title><meta name="theme-color" content="#2563eb"><link rel="manifest" href="manifest.webmanifest"><link rel="icon" href="assets/img/favicon-32.png" sizes="32x32" type="image/png"><link rel="apple-touch-icon" sizes="180x180" href="assets/img/apple-touch-icon.png"><meta name="apple-mobile-web-app-capable" content="yes"><meta name="apple-mobile-web-app-title" content="Hub Integração"><meta name="apple-mobile-web-app-status-bar-style" content="default"><link href="assets/vendor/bootstrap/bootstrap.min.css" rel="stylesheet"><style>
:root{--bg:#070b1a;--card:rgba(255,255,255,.08);--stroke:rgba(255,255,255,.16);--txt:#ecf3ff;--muted:#9fb2d4;--a:#53e0ff;--b:#8d6bff;--ok:#29e6a7;--bad:#ff5d7a} body{min-height:100vh;background:radial-gradient(circle at top left,#182b69 0,#070b1a 38%,#03050d 100%);color:var(--txt);font-family:Inter,system-ui,Segoe UI,Arial} .grid{position:fixed;inset:0;background-image:linear-gradient(rgba(83,224,255,.08) 1px,transparent 1px),linear-gradient(90deg,rgba(83,224,255,.08) 1px,transparent 1px);background-size:42px 42px;mask-image:linear-gradient(to bottom,black,transparent);pointer-events:none}.wrap{max-width:1180px;margin:0 auto;padding:34px 16px}.hero{border:1px solid var(--stroke);background:linear-gradient(135deg,rgba(83,224,255,.16),rgba(141,107,255,.10));border-radius:28px;padding:28px;box-shadow:0 24px 80px rgba(0,0,0,.35)}.badge-neon{border:1px solid rgba(83,224,255,.45);color:var(--a);border-radius:999px;padding:7px 12px;background:rgba(83,224,255,.08)}.cardx{background:var(--card);border:1px solid var(--stroke);border-radius:22px;box-shadow:0 18px 60px rgba(0,0,0,.25);backdrop-filter:blur(14px)}.form-control,.form-select{background:rgba(255,255,255,.07);border-color:var(--stroke);color:var(--txt)}.form-control:focus,.form-select:focus{background:rgba(255,255,255,.10);color:var(--txt);border-color:var(--a);box-shadow:0 0 0 .2rem rgba(83,224,255,.15)}.form-select option{color:#111}.btn-neon{background:linear-gradient(135deg,var(--a),var(--b));border:0;color:#06101e;font-weight:800}.step{display:flex;gap:12px;align-items:flex-start}.dot{width:13px;height:13px;border-radius:50%;margin-top:5px;background:var(--ok);box-shadow:0 0 18px var(--ok)}.dot.bad{background:var(--bad);box-shadow:0 0 18px var(--bad)}.muted{color:var(--muted)}.alert{border-radius:18px}.progress{height:9px;background:rgba(255,255,255,.10)}.progress-bar{background:linear-gradient(90deg,var(--a),var(--b))} label{color:#dbe8ff;font-weight:600}.small-note{font-size:.88rem;color:var(--muted)}
</style></head><body><div class="grid"></div><div class="wrap"><div class="hero mb-4"><div class="d-flex justify-content-between align-items-start gap-3 flex-wrap"><div class="d-flex gap-3 align-items-center flex-wrap"><img src="assets/img/hub-integracao-logo.svg" alt="" style="width:72px;height:72px;filter:drop-shadow(0 0 18px rgba(83,224,255,.45))"><div><span class="badge-neon">Instalação segura</span><h1 class="display-6 fw-bold mt-3 mb-2">Hub de Integração</h1><p class="muted mb-0">Instalação guiada com banco único recomendado para hospedagem compartilhada, bloqueio claro de credenciais inseguras, teste de permissão MySQL, Tiny/VSM, segurança forte e validação de módulos críticos.</p></div></div><div class="text-end"><div class="h2 mb-0"><?=$okCount?>/<?=$total?></div><div class="muted">checks OK</div></div></div><div class="progress mt-4"><div class="progress-bar" style="width:<?=round(($okCount/max(1,$total))*100)?>%"></div></div></div>
<?php if ($locked): ?>
  <div class="cardx p-4 mb-4">
    <h2>Instalação bloqueada</h2>
    <p class="muted">O arquivo <b>storage/install.lock</b> indica que o sistema já foi instalado. Não existe desbloqueio por URL; remova o lock somente em manutenção local e controlada.</p>
    <a class="btn btn-neon" href="index.php?page=login">Ir para o login</a>
  </div>
<?php elseif ($httpsBlocked): ?>
  <div class="cardx p-4 mb-4">
    <h2>HTTPS obrigatório</h2>
    <p class="muted mb-0">Por segurança, o instalador público só funciona por HTTPS. Corrija o certificado ou o proxy reverso e acesse novamente usando <b>https://</b>. HTTP é aceito apenas em localhost.</p>
  </div>
<?php else: ?>
  <?php if ($messages): ?>
    <div class="alert <?=$success ? 'alert-success' : 'alert-danger'?>">
      <?php foreach ($messages as $m): ?><div><?=h($m)?></div><?php endforeach; ?>
      <?php if ($success): ?><hr><a class="btn btn-success" href="index.php?page=login">Entrar no sistema</a><?php endif; ?>
    </div>
  <?php endif; ?>

  <?php if (!$success && !$installAuthorized): ?>
    <div class="cardx p-4 mb-4">
      <h2 class="h5">Autorização temporária obrigatória</h2>
      <p class="muted">No terminal, a partir da raiz do projeto, execute:</p>
      <p><code>php scripts/create-install-authorization.php</code></p>
      <p class="small-note">O código aleatório expira em 15 minutos, é aceito uma única vez e será vinculado a este navegador. Ele deve ser enviado somente pelo formulário abaixo, nunca pela URL.</p>
      <?php if ($authorizationMessage !== ''): ?><div class="alert alert-danger mt-3"><?=h($authorizationMessage)?></div><?php endif; ?>
      <div class="small-note mb-3">Estado: <?=h($installAuthorizationStatus)?></div>
      <form method="post" autocomplete="off">
        <input type="hidden" name="action" value="authorize">
        <input type="hidden" name="_csrf" value="<?=h($_SESSION['install_csrf'])?>">
        <label for="authorizationCode">Código gerado no terminal</label>
        <input id="authorizationCode" name="authorization_code" type="password" class="form-control mt-2" inputmode="text" autocomplete="one-time-code" required pattern="[A-Fa-f0-9]{64}" maxlength="64">
        <button class="btn btn-neon mt-3" type="submit">Autorizar este navegador</button>
      </form>
    </div>
  <?php elseif (!$success): ?>
    <div class="alert alert-success">Autorização temporária válida e vinculada a este navegador. Ela será consumida na primeira tentativa de instalação que acessar o banco.</div>
    <div class="row g-4">
      <div class="col-lg-4"><div class="cardx p-4 h-100">
        <h2 class="h5 mb-3">Checklist do ambiente</h2>
        <?php foreach ($checks as $label => $ok): ?><div class="step mb-2"><div class="dot <?=$ok ? '' : 'bad'?>"></div><div><b><?=h($label)?></b><div class="small-note"><?=$ok ? 'Pronto' : 'Verificar antes de produção'?></div></div></div><?php endforeach; ?>
        <hr><p class="small-note mb-0">Recomendado: usar XAMPP local para homologação. Em produção, use usuário MySQL exclusivo, senha forte, HTTPS e app_env=production.</p>
      </div></div>
      <div class="col-lg-8"><form method="post" class="cardx p-4" autocomplete="off">
        <input type="hidden" name="action" value="install">
        <input type="hidden" name="_csrf" value="<?=h($_SESSION['install_csrf'])?>">
        <h2 class="h5">Configuração da instalação</h2>
        <div class="alert alert-warning mt-3"><b>Produção segura:</b> use somente banco novo. Se já existir qualquer tabela do Hub, use Enterprise Core/migrations. Não use <code>root</code> sem senha no servidor. Em cPanel/DirectAdmin, crie um banco e usuário MySQL exclusivos, vincule o usuário ao banco e marque todas as permissões.</div>
        <div class="row g-3 mt-1">
          <div class="col-md-3"><label>Host</label><input name="db_host" class="form-control" value="<?=val('db_host', $default)?>" required maxlength="253"></div>
          <div class="col-md-3"><label>Nome base</label><input name="db_name" class="form-control" value="<?=val('db_name', $default)?>" required maxlength="64"></div>
          <div class="col-md-3"><label>Usuário</label><input name="db_user" class="form-control" value="<?=val('db_user', $default)?>" required maxlength="128"></div>
          <div class="col-md-3"><label>Senha MySQL</label><input name="db_pass" type="password" autocomplete="new-password" class="form-control" value="" maxlength="4096"></div>
          <div class="col-md-6"><label>Modo do banco</label><select name="db_create_mode" class="form-select"><option value="existing" <?=($_POST['db_create_mode'] ?? $default['db_create_mode']) === 'existing' ? 'selected' : ''?>>Hospedagem compartilhada: usar banco já criado</option><option value="create" <?=($_POST['db_create_mode'] ?? $default['db_create_mode']) === 'create' ? 'selected' : ''?>>Local/VPS: tentar criar banco automaticamente</option></select><div class="small-note">Para cPanel/DirectAdmin, escolha banco já criado e informe o nome completo, ex.: ctbatop1_loja02.</div></div>
          <div class="col-12"><div class="form-check form-switch mt-2"><input class="form-check-input" type="checkbox" name="confirm_local_insecure_mysql" value="1" id="confirmLocalMysql" <?=($_POST['confirm_local_insecure_mysql'] ?? $default['confirm_local_insecure_mysql']) === '1' ? 'checked' : ''?>> <label class="form-check-label" for="confirmLocalMysql">Estou instalando em ambiente local/XAMPP e aceito usar root sem senha apenas localmente</label><div class="small-note">Não marque em servidor público, hospedagem, VPS ou produção.</div></div></div>
          <div class="col-12"><div class="form-check form-switch mt-2"><input class="form-check-input" type="checkbox" name="modular" value="1" id="modular" <?=($_POST['modular'] ?? $default['modular']) === '1' ? 'checked' : ''?>><label class="form-check-label" for="modular">Usar bancos separados por módulo (somente se o usuário MySQL tiver permissão para criar/acessar todos os bancos)</label></div></div>
          <div class="col-12"><div class="form-check form-switch mt-2"><input class="form-check-input" type="checkbox" name="require_admin_2fa" value="1" id="requireAdmin2fa" <?=($_POST['require_admin_2fa'] ?? $default['require_admin_2fa']) === '1' ? 'checked' : ''?>><label class="form-check-label" for="requireAdmin2fa">Exigir 2FA para administradores já na primeira instalação</label><div class="small-note">Ative somente depois de validar o Authenticator. Por padrão fica desativado para não bloquear o primeiro acesso.</div></div></div>
        </div>
        <h2 class="h5 mt-4">Administrador</h2>
        <div class="row g-3"><div class="col-md-4"><label>Nome</label><input name="admin_nome" class="form-control" value="<?=val('admin_nome', $default)?>" required minlength="2" maxlength="120"></div><div class="col-md-4"><label>E-mail</label><input name="admin_email" type="email" class="form-control" value="<?=val('admin_email', $default)?>" required maxlength="254"></div><div class="col-md-4"><label>Senha forte</label><input name="admin_senha" autocomplete="new-password" type="password" class="form-control" required minlength="10" maxlength="4096"><div class="small-note">Mínimo 10 caracteres, maiúscula, minúscula e número.</div></div></div>
        <h2 class="h5 mt-4">Aplicação</h2>
        <div class="row g-3"><div class="col-md-6"><label>Base URL</label><input name="base_url" class="form-control" value="<?=val('base_url', $default)?>" required maxlength="500"></div><div class="col-md-3"><label>Modo</label><select name="app_env" class="form-select"><option value="local" <?=($_POST['app_env'] ?? $default['app_env']) === 'local' ? 'selected' : ''?>>Local</option><option value="production" <?=($_POST['app_env'] ?? $default['app_env']) === 'production' ? 'selected' : ''?>>Produção</option></select></div><div class="col-md-3"><label>Ambiente</label><select name="ambiente" class="form-select"><option value="homologacao" <?=($_POST['ambiente'] ?? $default['ambiente']) === 'homologacao' ? 'selected' : ''?>>Homologação</option><option value="producao" <?=($_POST['ambiente'] ?? $default['ambiente']) === 'producao' ? 'selected' : ''?>>Produção</option></select></div></div>
        <div class="alert alert-info mt-4 mb-0"><b>Fluxo seguro:</b> o instalador valida ambiente, bloqueia produção insegura, cria estrutura e administrador com PDO preparado e só grava configuração/lock após validar o contrato integral de tabelas, colunas, tipos, defaults, índices e atributos de tabela.</div>
        <div class="d-flex justify-content-between align-items-center mt-4"><span class="small-note">Em hospedagem compartilhada/cPanel, use banco único, modo “usar banco já criado”, usuário MySQL exclusivo e nome completo com prefixo.</span><button class="btn btn-neon btn-lg" type="submit">Instalar agora</button></div>
      </form></div>
    </div>
  <?php endif; ?>

  <?php if ($installSteps): ?><div class="cardx p-4 mt-4"><h2 class="h5">Passos executados</h2><ol class="mb-0"><?php foreach ($installSteps as $s): ?><li><?=h($s)?></li><?php endforeach; ?></ol></div><?php endif; ?>
<?php endif; ?>
</div></body></html>
