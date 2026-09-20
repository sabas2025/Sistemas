<?php
/**
 * Fase A (2026-09-20) — Orquestrador de fallback LLM com circuit breaker.
 *
 * Esqueleto de resiliência: dado uma ORDEM de provedores e um EXECUTOR, tenta cada provedor
 * na ordem, pulando os que estão com o breaker aberto, avançando ao próximo em falha que valha
 * novo provedor, e parando cedo em falha global (governança/custo) que falharia em todos.
 *
 * Reusa os serviços que já existem no Hub — NÃO cria breaker paralelo:
 *   - CircuitBreakerService::permitir/sucesso/falha  (gate + report padrão)
 *   - chave de breaker "llm_<provedor>"
 *
 * O gate e o report são injetáveis para permitir teste determinístico SEM banco. Os defaults
 * chamam o CircuitBreakerService real, protegidos contra ausência de banco (degrada permitindo,
 * porque na Fase A o executor é apenas a simulação — nenhuma chamada real é feita).
 *
 * O EXECUTOR recebe o nome do provedor e devolve:
 *   ['ok'=>bool, 'tentar_proximo'=>bool, 'resultado'=>mixed, 'erro'=>string]
 * Em falha, 'tentar_proximo'=true avança ao próximo provedor; false para (falha global).
 */
class LlmFallbackService {
  /**
   * @param string[] $ordem            provedores em ordem de preferência
   * @param callable $executor         fn(string $provedor): array{ok:bool,tentar_proximo:bool,resultado:mixed,erro:string}
   * @param null|callable $gate        fn(string $provedor): bool  (default: CircuitBreakerService::permitir)
   * @param null|callable $report      fn(string $provedor, bool $ok, string $msg): void (default: sucesso/falha)
   * @return array{ok:bool,provider:?string,resultado:mixed,tentativas:array,erro:?string}
   */
  public static function run(array $ordem, callable $executor, ?callable $gate = null, ?callable $report = null): array {
    $ordem = array_values(array_filter(array_map('strval', $ordem), static fn($p) => $p !== ''));
    if (!$ordem) {
      return ['ok' => false, 'provider' => null, 'resultado' => null, 'tentativas' => [], 'erro' => 'Sem provedores na ordem de fallback.'];
    }
    $gate = $gate ?? self::defaultGate();
    $report = $report ?? self::defaultReport();
    $trilha = [];
    foreach ($ordem as $provedor) {
      if (!$gate($provedor)) {
        $trilha[] = ['provider' => $provedor, 'status' => 'breaker_aberto'];
        continue;
      }
      $res = $executor($provedor);
      $ok = !empty($res['ok']);
      $report($provedor, $ok, (string)($res['erro'] ?? ''));
      if ($ok) {
        $trilha[] = ['provider' => $provedor, 'status' => 'sucesso'];
        return ['ok' => true, 'provider' => $provedor, 'resultado' => $res['resultado'] ?? null, 'tentativas' => $trilha, 'erro' => null];
      }
      $avanca = !empty($res['tentar_proximo']);
      $trilha[] = ['provider' => $provedor, 'status' => $avanca ? 'falha_recuperavel' : 'falha_global'];
      if (!$avanca) {
        return ['ok' => false, 'provider' => $provedor, 'resultado' => null, 'tentativas' => $trilha, 'erro' => (string)($res['erro'] ?? 'Falha global; fallback interrompido.')];
      }
    }
    return ['ok' => false, 'provider' => null, 'resultado' => null, 'tentativas' => $trilha, 'erro' => 'Todos os provedores de fallback falharam ou estão indisponíveis.'];
  }

  /**
   * Resolve a ordem de fallback a partir da política, retrocompatível: se não houver
   * 'fallback_order' declarado, usa o provedor único atual. (Wire de 'fallback_order' na
   * política/config é passo da Fase B; hoje a ausência resulta no comportamento atual.)
   * @return string[]
   */
  public static function ordemDaPolitica(array $policy): array {
    $raw = $policy['fallback_order'] ?? [];
    if (is_string($raw)) $raw = array_filter(array_map('trim', explode(',', $raw)));
    $raw = array_values(array_filter(array_map('strval', (array)$raw), static fn($p) => $p !== '' && $p !== 'none'));
    if ($raw) return $raw;
    $p = (string)($policy['provider'] ?? 'none');
    return ($p !== 'none' && $p !== '') ? [$p] : [];
  }

  private static function defaultGate(): callable {
    return static function (string $provedor): bool {
      if (!class_exists('CircuitBreakerService')) return true;
      try { return CircuitBreakerService::permitir('llm_' . $provedor); }
      catch (Throwable $e) { return true; }
    };
  }

  private static function defaultReport(): callable {
    return static function (string $provedor, bool $ok, string $msg): void {
      if (!class_exists('CircuitBreakerService')) return;
      try {
        if ($ok) CircuitBreakerService::sucesso('llm_' . $provedor);
        else CircuitBreakerService::falha('llm_' . $provedor, $msg !== '' ? $msg : 'falha llm');
      } catch (Throwable $e) { /* best-effort: breaker indisponível não derruba o fluxo */ }
    };
  }
}
