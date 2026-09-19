<?php
class MyOuroConfigService {
  public const URL = 'https://msx.vsm.api.br/graphql';
  public static function ready(): bool {
    return Database::tableExists('myouro_conexoes') && Database::columnExists('configuracoes_integracao','integracao_empresa_id');
  }
  public static function get(int $empresa): array {
    $st = Database::forTable('myouro_conexoes')->prepare('SELECT * FROM myouro_conexoes WHERE empresa_id=?');
    $st->execute([$empresa]); $row = $st->fetch() ?: []; $st->closeCursor();
    return $row;
  }
  public static function save(int $empresa, array $input): void {
    if ($empresa !== IntegrationTenantService::boundEmpresaId()) throw new RuntimeException('TENANT_SCOPE_VIOLATION');
    $url = trim((string)($input['url'] ?? self::URL));
    if ($url !== self::URL) throw new InvalidArgumentException('Use a URL MyOuro de produção informada no contrato: '.self::URL);
    $loja = filter_var($input['codigo_loja'] ?? '', FILTER_VALIDATE_INT, ['options'=>['min_range'=>1,'max_range'=>2147483647]]);
    if ($loja === false) throw new InvalidArgumentException('Informe um código de loja VSM válido.');
    $pdo = Database::forTable('myouro_conexoes');
    $pdo->beginTransaction();
    try {
      $st = $pdo->prepare('SELECT * FROM myouro_conexoes WHERE empresa_id=? FOR UPDATE');
      $st->execute([$empresa]); $old = $st->fetch() ?: []; $st->closeCursor();
      $value = trim((string)($input['token'] ?? ''));
      if ($value === '' || $value === '••••••••') {
        $cipher = (string)($old['token_encrypted'] ?? '');
      } else {
        if (str_contains($value, '•') || preg_match('/[\s\x00-\x1F\x7F]/', $value) || CryptoService::isEncrypted($value)) throw new InvalidArgumentException('Informe apenas o token, sem Bearer, espaços ou valor criptografado.');
        $cipher = (string)CryptoService::encrypt($value, 'myouro:'.$empresa);
      }
      $enabled = !empty($input['habilitado']) ? 1 : 0;
      if ($enabled && $cipher === '') throw new InvalidArgumentException('Cadastre o token antes de habilitar consultas.');
      $st = $pdo->prepare('INSERT INTO myouro_conexoes(empresa_id,url,codigo_loja,token_encrypted,habilitado) VALUES(?,?,?,?,?) ON DUPLICATE KEY UPDATE url=VALUES(url),codigo_loja=VALUES(codigo_loja),token_encrypted=VALUES(token_encrypted),habilitado=VALUES(habilitado),ultimo_teste_ok=0,ultimo_teste_em=NULL,ultimo_teste_codigo=NULL');
      $st->execute([$empresa,$url,$loja,$cipher,$enabled]);
      $pdo->commit();
    } catch (Throwable $e) { if ($pdo->inTransaction()) $pdo->rollBack(); throw $e; }
  }
}
