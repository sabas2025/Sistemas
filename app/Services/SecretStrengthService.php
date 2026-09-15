<?php
/**
 * Melhoria 4 da seção 8 (relatório V104.49.3-R6): verificação de força e de reuso dos segredos.
 *
 * A recomendação operacional era "rotacione todas as chaves de config.php". Recomendação sozinha
 * não é aplicável nem verificável, então aqui ela vira checagem: o serviço sabe quais chaves
 * existem, quais são obrigatórias em produção, quais estão fracas, vazias, repetidas entre si ou
 * iguais a um valor de exemplo conhecido. É consumido pelo painel de segurança e pelo
 * scripts/rotate-secrets.php.
 *
 * O serviço NUNCA devolve o valor de um segredo - só o veredito sobre ele.
 */
class SecretStrengthService {
  public const MIN_LENGTH = 32;

  /** chave => se é obrigatória em produção */
  private const KEYS = [
    'encryption_key'                => true,
    'backup_signature_key'          => true,
    'integration_replay_hmac_key'   => true,
    'audit_daily_signature_key'     => true,
    'token_vault_hmac_key'          => true,
    'fim_manifest_hmac_key'         => true,
    'webhook_secret'                => true,
    'license_hmac_key'              => false,
    'vsm_hmac_secret'               => false,
  ];

  /**
   * Valores que NUNCA podem estar em uso: exemplos publicados, defaults de código e placeholders
   * de CI. Comparados já normalizados (minúsculas, sem espaços nas pontas).
   * @var list<string>
   */
  private const FORBIDDEN = [
    'hub-backup-dev-key-change-me',
    'change-me', 'changeme', 'troque-me', 'alterar', 'exemplo', 'example',
    'ci-e2e-encryption-key-0123456789abcdef',
    'ci-e2e-backup-signature-0123456789abcdef',
    'ci-e2e-replay-hmac-0123456789abcdef',
    'ci-e2e-audit-signature-0123456789abcdef',
    'ci-e2e-token-vault-0123456789abcdef',
    'ci-e2e-fim-manifest-0123456789abcdef',
    'ci-e2e-webhook-secret-0123456789abcdef',
    'ci-webhook-secret',
    'teste-de-regressao-a11-0123456789abcdef',
  ];

  /** Gera um segredo novo, adequado para qualquer uma das chaves acima. */
  public static function generate(int $bytes = 48): string {
    return rtrim(strtr(base64_encode(random_bytes(max(32, $bytes))), '+/', '-_'), '=');
  }

  /**
   * Avalia os segredos de um array de configuração de segurança.
   *
   * @param array<string,mixed> $security bloco 'security' do config
   * @return array{ok:bool,problemas:list<array{chave:string,severidade:string,problema:string,acao:string}>,avaliadas:int}
   */
  public static function audit(array $security, bool $producao = true): array {
    $problemas = [];
    $vistos = [];
    foreach (self::KEYS as $key => $obrigatoria) {
      $valor = (string)($security[$key] ?? '');
      $norm = strtolower(trim($valor));

      if ($norm === '') {
        if ($obrigatoria && $producao) {
          $problemas[] = self::p($key, 'alto', 'Segredo obrigatório está vazio.', 'Gere um valor com scripts/rotate-secrets.php.');
        }
        continue;
      }
      if (in_array($norm, self::FORBIDDEN, true)) {
        $problemas[] = self::p($key, 'critico', 'Segredo é um valor de exemplo/CI publicado, portanto conhecido por qualquer pessoa.', 'Rotacione imediatamente.');
        continue;
      }
      if (strlen($valor) < self::MIN_LENGTH) {
        $problemas[] = self::p($key, $obrigatoria ? 'alto' : 'medio', 'Segredo com menos de '.self::MIN_LENGTH.' caracteres.', 'Gere um valor novo com scripts/rotate-secrets.php.');
        continue;
      }
      if (self::isLowEntropy($valor)) {
        $problemas[] = self::p($key, 'medio', 'Segredo com pouca variedade de caracteres (parece digitado à mão).', 'Prefira um valor aleatório gerado.');
      }
      // Reuso entre chaves: comprometer um segredo comprometeria vários subsistemas de uma vez.
      $hash = hash('sha256', $valor);
      if (isset($vistos[$hash])) {
        $problemas[] = self::p($key, 'alto', 'Segredo idêntico ao de "'.$vistos[$hash].'".', 'Use um valor distinto por chave: HMAC de backup, de auditoria e de replay devem ser independentes.');
      } else {
        $vistos[$hash] = $key;
      }
    }
    return ['ok' => $problemas === [], 'problemas' => $problemas, 'avaliadas' => count(self::KEYS)];
  }

  /** @return array<string,bool> chave => obrigatória em produção */
  public static function keys(): array { return self::KEYS; }

  private static function isLowEntropy(string $valor): bool {
    $unicos = count(array_unique(str_split($valor)));
    return $unicos < 10;
  }

  /** @return array{chave:string,severidade:string,problema:string,acao:string} */
  private static function p(string $chave, string $sev, string $problema, string $acao): array {
    return ['chave' => $chave, 'severidade' => $sev, 'problema' => $problema, 'acao' => $acao];
  }
}
