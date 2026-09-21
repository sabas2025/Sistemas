<?php
class SensitiveDataService {
  private const KEYS = [
    'cpf','cnpj','cpf_cnpj','documento','telefone','fone','celular','email',
    'access_token','refresh_token','token','client_secret','secret','senha','password',
    'authorization','api_key','chave','webhook_secret','endereco','address','logradouro','numero_documento',
    'bairro','cep','postal_code','xml_nfe','xml','nome_cliente','cliente_nome'
  ];

  public static function maskJson(string $body): string {
    return self::maskJsonString($body);
  }

  public static function mask(mixed $value): mixed {
    if (is_array($value)) {
      $out = [];
      foreach ($value as $k => $v) {
        $key = strtolower((string)$k);
        $out[$k] = self::isSensitiveKey($key) ? self::maskSensitiveValue($v) : self::mask($v);
      }
      return $out;
    }
    if (is_object($value)) return self::mask(json_decode(json_encode($value), true));
    if (is_string($value)) return self::maskJsonString($value);
    return $value;
  }


  /**
   * Sanitiza payload para logs/snapshots e limita volume armazenado.
   * O hash deve ser calculado sobre o conteúdo original pelo chamador quando necessário.
   */
  public static function sanitizeForStorage(mixed $value, int $maxBytes=200000): string {
    $masked=self::mask($value);
    $json=is_string($masked)?$masked:json_encode($masked,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
    $json=(string)$json;
    $limit=max(1024,min(2*1024*1024,$maxBytes));
    if(strlen($json)<=$limit)return $json;
    return substr($json,0,$limit).'...[TRUNCADO sha256='.hash('sha256',$json).' bytes='.strlen($json).']';
  }

  private static function isSensitiveKey(string $key): bool {
    foreach (self::KEYS as $needle) {
      if (str_contains($key, $needle)) return true;
    }
    return false;
  }

  /**
   * Achado K-01 (2026-09-21): sob uma chave sensível o valor pode ser um ARRAY aninhado
   * (ex.: `itens`, `endereco`). Antes, mask() chamava maskScalar() direto, e `(string)$array`
   * disparava "Array to string conversion" no worker e mascarava tudo como a string "Array",
   * perdendo a estrutura. Aqui a máscara desce recursivamente e mascara cada folha como
   * sensível, preservando o formato. Family do G-01 (tipo em caminho de mascaramento).
   */
  private static function maskSensitiveValue(mixed $v): mixed {
    if (is_array($v)) {
      $out = [];
      foreach ($v as $k => $vv) $out[$k] = self::maskSensitiveValue($vv);
      return $out;
    }
    if (is_object($v)) return self::maskSensitiveValue(json_decode(json_encode($v), true));
    return self::maskScalar($v);
  }

  private static function maskScalar(mixed $v): string {
    $s = (string)$v;
    if ($s === '') return '';
    $len = strlen($s);
    if ($len <= 6) return str_repeat('*', $len);
    return substr($s, 0, 2) . str_repeat('*', max(4, min(12, $len-4))) . substr($s, -2);
  }

  private static function maskJsonString(string $body): string {
    $trim = trim($body);
    if ($trim === '') return $body;
    $json = json_decode($trim, true);
    if (is_array($json)) {
      return json_encode(self::mask($json), JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
    }
    // Redação defensiva para textos/erros que contenham tokens ou dados pessoais.
    $patterns = [
      '/(access_token|refresh_token|client_secret|token|authorization|secret)(["\'\s:=]+)([^"\'\s,}]+)/i',
      '/([\w._%+-]+)@([\w.-]+\.[A-Za-z]{2,})/',
      '/\b\d{11}\b/',
      '/\b\d{14}\b/',
      '/(?i:(telefone|fone|celular|whatsapp))\s*[:=]\s*(?:\+?55\s*)?(?:\(?\d{2}\)?\s*)?9?\d{4}[-\s]?\d{4}/',
      '/(?:(?i:cep|postal_code)\s*[:=]\s*\d{5}-?\d{3}|(?<!\d)\d{5}-\d{3}(?!\d))/'
    ];
    $replacements = [
      '$1$2***mascarado***',
      '***email***@$2',
      '***cpf***',
      '***cnpj***',
      '***telefone***',
      '***cep***'
    ];
    return preg_replace($patterns, $replacements, $body) ?? $body;
  }
}
