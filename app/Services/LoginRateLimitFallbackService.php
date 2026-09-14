<?php
/**
 * Rate limit degradado para o login quando a tabela/consulta principal fica indisponível.
 *
 * Melhoria 7 da seção 8 (relatório V104.49.3-R6): esta classe tinha armazenamento próprio, com
 * leitura sob LOCK_SH e escrita sob LOCK_EX em chamadas SEPARADAS - exatamente o defeito de
 * atomicidade do achado A-07, que na ocasião foi corrigido apenas no contador do PWA. Duas
 * tentativas simultâneas liam o mesmo valor e o limite era ultrapassado por corrida, justamente
 * no caminho que existe para conter força bruta de credenciais.
 *
 * Agora o armazenamento é o AtomicRateCounterService (leitura e escrita sob o mesmo lock
 * exclusivo) através da fachada RateLimitService, onde a política de cada superfície está
 * declarada. Cada uma das quatro janelas (IP/minuto, IP/hora, usuário/minuto, usuário/hora) é um
 * contador independente com a sua própria janela, em vez de um arquivo único reinterpretado.
 *
 * Sobre falha de armazenamento: esta camada JÁ é o degradado do banco. Se o disco também falhar,
 * responder "não limitado" seria liberar força bruta (o fail-open do A-06/B-01), e responder
 * "limitado" para sempre trancaria todo mundo para fora por causa de dois problemas de
 * infraestrutura. Por isso evaluate() devolve 'degraded' e quem chama (Auth::tooManyAttempts)
 * escala para o contador de sessão - que é fraco, mas é um limite de verdade e não tranca o
 * sistema. isLimited() continua existindo com a assinatura antiga e, sem conseguir avaliar,
 * responde de forma conservadora (limitado).
 */
class LoginRateLimitFallbackService {
  /** Teto de eventos guardados por janela; espelha o antigo array_slice(...,-200). */
  private const STORAGE_CEILING = 200;

  private const SURFACES = [
    'ip_minute'   => 'login_ip_minute',
    'ip_hour'     => 'login_ip_hour',
    'user_minute' => 'login_user_minute',
    'user_hour'   => 'login_user_hour',
  ];

  private static function identity(string $kind, string $value): string {
    return $kind.':'.strtolower(trim($value));
  }

  /** @return list<array{0:string,1:string}> pares [chaveDeLimite, identidade] aplicáveis */
  private static function targets(string $email, ?string $ip): array {
    $out = [];
    $email = trim($email);
    $ip = trim((string)$ip);
    if ($ip !== '')    { $out[] = ['ip_minute', self::identity('ip', $ip)];       $out[] = ['ip_hour', self::identity('ip', $ip)]; }
    if ($email !== '') { $out[] = ['user_minute', self::identity('email', $email)]; $out[] = ['user_hour', self::identity('email', $email)]; }
    return $out;
  }

  /** Registra o resultado de uma tentativa de login. Sucesso zera os contadores da identidade. */
  public static function record(string $email, ?string $ip, bool $success): void {
    foreach (self::targets($email, $ip) as [$limitKey, $identity]) {
      $surface = self::SURFACES[$limitKey];
      if ($success) { RateLimitService::reset($surface, $identity); continue; }
      // O teto de armazenamento é fixo e generoso: o limite real é aplicado em evaluate(), que
      // recebe os valores configurados. Contar com um teto baixo aqui truncaria o histórico
      // antes de a avaliação acontecer.
      RateLimitService::hit($surface, $identity, self::STORAGE_CEILING);
    }
  }

  /**
   * @param array{ip_minute?:int,ip_hour?:int,user_minute?:int,user_hour?:int} $limits
   * @return array{limited:bool,degraded:bool,motivo:string}
   */
  public static function evaluate(string $email, ?string $ip, array $limits): array {
    $degraded = false;
    foreach (self::targets($email, $ip) as [$limitKey, $identity]) {
      $limit = (int)($limits[$limitKey] ?? 0);
      if ($limit <= 0) continue;
      $state = RateLimitService::peek(self::SURFACES[$limitKey], $identity, $limit);
      if ($state['degraded']) { $degraded = true; continue; }
      if ((int)$state['count'] >= $limit) {
        return ['limited' => true, 'degraded' => false, 'motivo' => $limitKey];
      }
    }
    if ($degraded) {
      return ['limited' => true, 'degraded' => true, 'motivo' => 'armazenamento_indisponivel'];
    }
    return ['limited' => false, 'degraded' => false, 'motivo' => ''];
  }

  /** Compatibilidade com a assinatura anterior. Sem conseguir avaliar, responde conservador. */
  public static function isLimited(string $email, ?string $ip, array $limits): bool {
    return self::evaluate($email, $ip, $limits)['limited'];
  }
}
