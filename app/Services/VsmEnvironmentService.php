<?php
/**
 * V104.31 - Ambiente VSM seguro.
 *
 * Regra de negócio preservada: URL/token VSM continuam sendo configuráveis e os fluxos Tiny ⇄ VSM não são alterados.
 * Esta camada corrige apenas a leitura de ambiente/status para evitar que "URL configurada" seja tratada como produção liberada.
 */
class VsmEnvironmentService {
  public const HOMOLOG_HOST = 'conectavenda.homolog.vsm.com.br';

  public static function ensureSchema(): void {
    $columns = [
      'vsm_ambiente','vsm_producao_liberada','vsm_ultimo_teste_ok','vsm_ultimo_teste_em',
      'vsm_host_producao_liberado','vsm_producao_liberada_em','vsm_producao_liberada_por'
    ];
    SchemaRuntimePolicyService::requireColumns('configuracoes_integracao', $columns, 'controle de ambiente VSM');
    $pdo = Database::forTable('configuracoes_integracao');
    $pdo->exec("INSERT IGNORE INTO configuracoes_integracao(id) VALUES(1)");
    $pdo->exec("UPDATE configuracoes_integracao SET vsm_ambiente=COALESCE(NULLIF(vsm_ambiente,''),'homologacao'), vsm_producao_liberada=COALESCE(vsm_producao_liberada,0), vsm_ultimo_teste_ok=COALESCE(vsm_ultimo_teste_ok,0) WHERE id=1");
  }

  public static function isHomologUrl(string $url): bool {
    $host = strtolower((string)(parse_url($url, PHP_URL_HOST) ?: ''));
    return $host === '' || str_contains($host, '.homolog.') || str_contains($host, 'homolog') || $host === self::HOMOLOG_HOST;
  }

  public static function normalizedEnv(array $cfg): string {
    $declared = strtolower(trim((string)($cfg['vsm_ambiente'] ?? 'homologacao')));
    $url = trim((string)($cfg['vsm_url'] ?? ''));
    if ($url === '') return 'nao_configurado';
    if (self::isHomologUrl($url)) return 'homologacao';
    if ($declared === 'producao') return 'producao';
    return 'producao_pendente';
  }

  public static function isProductionReleased(array $cfg): bool {
    $url = trim((string)($cfg['vsm_url'] ?? ''));
    if ($url === '' || self::isHomologUrl($url)) return false;
    $liberado = !empty($cfg['vsm_producao_liberada']) && !empty($cfg['vsm_ultimo_teste_ok']) && strtolower((string)($cfg['vsm_ambiente'] ?? '')) === 'producao';
    if (!$liberado) return false;
    // P0-04 (reauditoria 2026-08-23): defesa em profundidade contra flag "vsm_ultimo_teste_ok"
    // desatualizada em relação à URL atual (ex.: escrita direta no banco fora do fluxo normal
    // de salvarConfiguracoes, que já revoga o teste quando URL/token mudam).
    $hostAtual = strtolower((string)(parse_url($url, PHP_URL_HOST) ?: ''));
    $hostLiberado = strtolower(trim((string)($cfg['vsm_host_producao_liberado'] ?? '')));
    return $hostLiberado !== '' && $hostAtual !== '' && hash_equals($hostLiberado, $hostAtual);
  }

  public static function dashboardStatus(array $cfg): array {
    $url = trim((string)($cfg['vsm_url'] ?? ''));
    if ($url === '') {
      return [
        'status'=>'erro',
        'modo'=>'Não configurado',
        'detalhe'=>'URL VSM pendente',
        'acao'=>'Configure URL/token da VSM em homologação',
        'badge'=>'status-erro'
      ];
    }

    if (self::isHomologUrl($url)) {
      return [
        'status'=>'atencao',
        'modo'=>'Homologação',
        'detalhe'=>'URL de homologação configurada',
        'acao'=>'Produção bloqueada até liberação manual e teste real',
        'badge'=>'status-pendente'
      ];
    }

    if (self::isProductionReleased($cfg)) {
      return [
        'status'=>'online',
        'modo'=>'Produção liberada',
        'detalhe'=>'URL produção validada com teste OK',
        'acao'=>'Monitorar logs, fila e circuit breaker',
        'badge'=>'status-sucesso'
      ];
    }

    return [
      'status'=>'atencao',
      'modo'=>'Produção pendente',
      'detalhe'=>'URL produção informada, mas liberação/checklist ainda pendente',
      'acao'=>'Execute teste VSM e marque liberação somente após autorização da VSM',
      'badge'=>'status-alerta'
    ];
  }

  public static function sanitizePost(array $post, array $current = []): array {
    $url = trim((string)($post['vsm_url'] ?? ($current['vsm_url'] ?? '')));
    $requested = strtolower(trim((string)($post['vsm_ambiente'] ?? ($current['vsm_ambiente'] ?? 'homologacao'))));
    if (!in_array($requested, ['homologacao','producao'], true)) $requested = 'homologacao';

    $isHomolog = self::isHomologUrl($url);
    $liberar = !empty($post['vsm_producao_liberada']) ? 1 : 0;
    if ($isHomolog) { $requested = 'homologacao'; $liberar = 0; }
    if ($requested !== 'producao') $liberar = 0;

    $host = strtolower((string)(parse_url($url, PHP_URL_HOST) ?: ''));
    return [
      'vsm_ambiente' => $requested,
      'vsm_producao_liberada' => $liberar,
      'vsm_host_producao_liberado' => ($requested === 'producao' && $liberar) ? $host : null,
    ];
  }

  public static function registerTestResult(bool $ok): void {
    try {
      $fields = [];
      $values = [];
      if (Database::columnExists('configuracoes_integracao','vsm_ultimo_teste_ok')) { $fields[]='vsm_ultimo_teste_ok=?'; $values[]=$ok ? 1 : 0; }
      if (Database::columnExists('configuracoes_integracao','vsm_ultimo_teste_em')) { $fields[]='vsm_ultimo_teste_em=NOW()'; }
      if ($fields) Database::forTable('configuracoes_integracao')->prepare('UPDATE configuracoes_integracao SET '.implode(',', $fields).' WHERE id=1')->execute($values);
    } catch (Throwable $e) {
      Audit::exception($e, 'vsm.ambiente.registrar_teste.erro', ['codigo_erro'=>'VSM_TEST_RESULT_SAVE_ERROR']);
    }
  }
}
