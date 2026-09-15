<?php
/**
 * Melhoria 6 da seção 8 (relatório V104.49.3-R6): estado de saúde dos controles de segurança.
 *
 * Antes, quando um controle degradava (rate limit sem banco, bloqueio de IP sem tabela, log de
 * evento de segurança falhando), isso era marcado em $GLOBALS['HUB_SECURITY_DEGRADED'] e escrito
 * no log do servidor. Duas consequências ruins: a variável global morria no fim da requisição, de
 * modo que ninguém conseguia consultá-la depois; e o operador só descobria que a proteção estava
 * desligada lendo o log do servidor - ou seja, normalmente pelo incidente.
 *
 * Aqui o estado é persistido com TTL, exposto em api/status e no painel, e continua registrado no
 * log. O serviço NUNCA lança: ele é chamado justamente nos caminhos em que algo já falhou, e
 * derrubar a requisição por não conseguir anotar a degradação seria trocar um problema por outro.
 */
class SecurityHealthService {
  /** Uma degradação deixa de ser reportada se o controle não falhar de novo nesta janela. */
  public const TTL_SECONDS = 3600;

  /** Controles conhecidos, com o texto que o operador precisa ler. */
  private const CONTROLS = [
    'event_log'                  => 'Registro de eventos de segurança (tabela security_events indisponível; usando arquivo de fallback)',
    'ip_block'                   => 'Bloqueio de IPs (tabela ips_bloqueados indisponível; IPs bloqueados não estão sendo barrados)',
    'route_rate_limit'           => 'Rate limit de rotas operando em modo degradado (contador local, sem banco)',
    'route_rate_limit_counter'   => 'Rate limit de rotas SEM limite algum (banco e contador local indisponíveis)',
    'login_rate_limit'           => 'Rate limit de login operando em modo degradado',
    'tiny_webhook_rate_limit'    => 'Contador de tentativas do webhook Tiny indisponível (webhooks recusados por precaução)',
    'pwa_telemetry_rate_limit'   => 'Contador da telemetria PWA indisponível (telemetria recusada por precaução)',
    'tenant_scope'               => 'Isolamento multiempresa não pôde ser aplicado em uma consulta (ver auditoria)',
    'webhook_signature_v1'       => 'Webhook aceito com assinatura v1 legada (não cobre método nem rota)',
    'session_store'              => 'Armazenamento de sessão em banco indisponível (usuários podem ser deslogados)',
    'queue_backpressure'         => 'Fila crescendo mais rápido do que é drenada (pedidos atrasando)',
  ];

  private static ?array $cache = null;

  /** Marca um controle como degradado. Idempotente dentro da janela. */
  public static function degrade(string $control, string $detail = '', array $context = []): void {
    try {
      $state = self::load();
      $now = time();
      $existing = $state[$control] ?? null;
      $state[$control] = [
        'control'     => $control,
        'label'       => self::CONTROLS[$control] ?? $control,
        'detail'      => substr($detail, 0, 500),
        'first_seen'  => (int)($existing['first_seen'] ?? $now),
        'last_seen'   => $now,
        'hits'        => (int)($existing['hits'] ?? 0) + 1,
        'context'     => array_slice($context, 0, 10, true),
      ];
      self::save($state);
      error_log('SecurityHealth: controle degradado "'.$control.'" - '.$detail);
    } catch (Throwable $e) {
      // Último recurso: o serviço de saúde nunca pode ser o motivo de uma falha.
      error_log('SecurityHealth: não foi possível registrar a degradação de '.$control.': '.$e->getMessage());
    }
  }

  /** Marca um controle como saudável de novo (remove a degradação). */
  public static function healthy(string $control): void {
    try {
      $state = self::load();
      if (!isset($state[$control])) return;
      unset($state[$control]);
      self::save($state);
    } catch (Throwable $e) {
      error_log('SecurityHealth: não foi possível limpar '.$control.': '.$e->getMessage());
    }
  }

  /**
   * Controles degradados dentro da janela de TTL.
   * @return list<array<string,mixed>>
   */
  public static function degraded(): array {
    try {
      $cut = time() - self::TTL_SECONDS;
      $out = [];
      foreach (self::load() as $entry) {
        if ((int)($entry['last_seen'] ?? 0) < $cut) continue;
        $out[] = $entry;
      }
      usort($out, static fn(array $a, array $b): int => ($b['last_seen'] ?? 0) <=> ($a['last_seen'] ?? 0));
      return $out;
    } catch (Throwable $e) {
      return [];
    }
  }

  public static function isDegraded(): bool { return self::degraded() !== []; }

  /** Resumo pronto para api/status e para o painel. */
  public static function summary(): array {
    $degraded = self::degraded();
    return [
      'degradado' => $degraded !== [],
      'controles' => array_map(static fn(array $e): array => [
        'controle'   => $e['control'] ?? '',
        'descricao'  => $e['label'] ?? '',
        'ocorrencias'=> (int)($e['hits'] ?? 0),
        'desde'      => date('c', (int)($e['first_seen'] ?? time())),
        'ultima'     => date('c', (int)($e['last_seen'] ?? time())),
      ], $degraded),
    ];
  }

  private static function file(): ?string {
    $dir = dirname(__DIR__, 2).'/storage/cache/security';
    if (!is_dir($dir) && !@mkdir($dir, 0770, true) && !is_dir($dir)) return null;
    return $dir.'/health-state.json';
  }

  /** @return array<string,array<string,mixed>> */
  private static function load(): array {
    if (self::$cache !== null) return self::$cache;
    $file = self::file();
    if ($file === null || !is_file($file)) return self::$cache = [];
    $raw = @file_get_contents($file);
    $data = json_decode((string)$raw, true);
    return self::$cache = is_array($data) ? $data : [];
  }

  /** @param array<string,array<string,mixed>> $state */
  private static function save(array $state): void {
    self::$cache = $state;
    $file = self::file();
    if ($file === null) return;
    // Mesma disciplina do AtomicRateCounterService: leitura e escrita sob o mesmo lock exclusivo.
    $handle = @fopen($file, 'c+');
    if (!is_resource($handle)) return;
    try {
      if (!@flock($handle, LOCK_EX)) return;
      @ftruncate($handle, 0);
      @rewind($handle);
      @fwrite($handle, (string)json_encode($state, JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE));
      @fflush($handle);
      @flock($handle, LOCK_UN);
    } finally {
      @fclose($handle);
      @chmod($file, 0600);
    }
  }
}
