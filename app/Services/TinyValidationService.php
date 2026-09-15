<?php
/**
 * V50 - Validador Tiny V2/V3 100% operacional.
 * Executa testes controlados, sem criar produto real por padrão, e consolida semáforo.
 */
class TinyValidationService {
  public static function ensureSchema(): void {
    SchemaRuntimePolicyService::requireTable('tiny_validacoes_execucoes', 'validação Tiny');
  }

  public static function ultimo(): ?array {
    self::ensureSchema();
    $st = Database::connection('core')->query('SELECT * FROM tiny_validacoes_execucoes ORDER BY id DESC LIMIT 1');
    $row = $st->fetch();
    return $row ?: null;
  }

  public static function executar(string $versao, array $params=[]): array {
    self::ensureSchema();
    $versao = strtolower($versao) === 'v3' ? 'v3' : 'v2';
    $cfg = IntegrationConfig::get();
    $sku = trim((string)($params['sku_teste'] ?? 'HUB-TESTE-VALIDACAO'));
    if ($sku === '') $sku = 'HUB-TESTE-VALIDACAO';
    $steps = [];
    $add = function(string $nome, bool $ok, string $detalhe, array $extra=[]) use (&$steps) {
      $steps[] = ['nome'=>$nome,'ok'=>$ok,'status'=>$ok?'ok':'falha','detalhe'=>$detalhe,'extra'=>$extra];
    };

    if ($versao === 'v2') {
      $token = (string)($cfg['tiny_v2_token'] ?? '');
      $url = (string)($cfg['tiny_v2_url'] ?? cfg('tiny.v2_url'));
      $add('Configuração URL V2', $url !== '', $url ? 'URL Tiny V2 configurada.' : 'URL Tiny V2 ausente.');
      $add('Token V2', $token !== '', $token ? 'Token Tiny V2 configurado.' : 'Token Tiny V2 ausente.');
      if ($token !== '') {
        try {
          $client = new TinyV2Service($cfg);
          $ret = $client->consultarProduto($sku);
          $ok = empty($ret['erro']);
          $add('Consulta produto por SKU', $ok, $ok ? 'Consulta V2 executada. Produto pode existir ou não.' : ($ret['erro'] ?? 'Falha V2'), ['http_code'=>$ret['http_code'] ?? null, 'trace_id'=>$ret['trace_id'] ?? null]);
        } catch(Throwable $e) { $add('Consulta produto por SKU', false, $e->getMessage()); }
      } else {
        $add('Consulta produto por SKU', false, 'Teste bloqueado porque o token V2 não está configurado.');
      }
      $add('Fallback controlado', true, 'Tiny V2 é o fallback operacional quando V3 não está homologado.');
    } else {
      $add('Client ID V3', !empty($cfg['tiny_v3_client_id']), !empty($cfg['tiny_v3_client_id']) ? 'Client ID configurado.' : 'Client ID ausente.');
      $add('Client Secret V3', !empty($cfg['tiny_v3_client_secret']), !empty($cfg['tiny_v3_client_secret']) ? 'Client Secret configurado.' : 'Client Secret ausente.');
      $add('Redirect URI V3', !empty($cfg['tiny_v3_redirect_uri']), !empty($cfg['tiny_v3_redirect_uri']) ? 'Redirect URI configurado.' : 'Redirect URI ausente.');
      try {
        $access = TinyV3TokenService::accessToken();
        $add('Access token V3', $access !== '', $access ? 'Access token disponível.' : 'Access token ausente ou expirado.');
      } catch(Throwable $e) { $add('Access token V3', false, $e->getMessage()); }
      try {
        $client = new TinyV3Service($cfg);
        $ret = $client->testarModulo('token', []);
        $ok = !empty($ret['ok']) || (($ret['http_code'] ?? 0) >= 200 && ($ret['http_code'] ?? 0) < 300);
        $add('Teste de autenticação API V3', $ok, $ok ? 'API V3 respondeu com sucesso.' : ($ret['erro'] ?? 'Falha HTTP/API V3'), ['http_code'=>$ret['http_code'] ?? null, 'trace_id'=>$ret['trace_id'] ?? null]);
        $produto = $client->consultarProduto($sku);
        $okProduto = empty($produto['erro']);
        $add('Consulta produto V3 por SKU', $okProduto, $okProduto ? 'Consulta por SKU executada.' : ($produto['erro'] ?? 'Falha ao consultar SKU V3'), ['http_code'=>$produto['http_code'] ?? null]);
        $estoque = $client->consultarEstoquePorSku($sku);
        $okEstoque = empty($estoque['erro']) || (($estoque['codigo_erro'] ?? '') === 'TINY_V3_SKU_NOT_FOUND');
        $add('Consulta estoque V3 por SKU', $okEstoque, $okEstoque ? 'Endpoint de estoque validado ou SKU de teste não encontrado.' : ($estoque['erro'] ?? 'Falha ao validar estoque V3'), ['codigo_erro'=>$estoque['codigo_erro'] ?? null]);
      } catch(Throwable $e) { $add('Execução API V3', false, $e->getMessage()); }
      $add('Tiny V3 operacional', !empty($cfg['tiny_v3_operacional']), !empty($cfg['tiny_v3_operacional']) ? 'Marcado como operacional.' : 'Ainda não marcado como operacional. Correto manter bloqueado até passar no checklist.');
    }

    $okCount = count(array_filter($steps, fn($s)=>!empty($s['ok'])));
    $score = $steps ? (int)round(($okCount / count($steps)) * 100) : 0;
    $status = $score >= 90 ? 'aprovado' : ($score >= 60 ? 'atencao' : 'reprovado');
    $res = ['versao'=>$versao,'score'=>$score,'status'=>$status,'steps'=>$steps,'trace_id'=>RequestContext::id(),'sku_teste'=>$sku,'executado_em'=>date('Y-m-d H:i:s')];
    $pdo = Database::connection('core');
    $st = $pdo->prepare('INSERT INTO tiny_validacoes_execucoes(trace_id,versao,status,score,ambiente,sku_teste,resumo,resultado_json) VALUES(?,?,?,?,?,?,?,?)');
    $st->execute([RequestContext::id(), $versao, $status, $score, $cfg['ambiente'] ?? App::env(), $sku, 'Validação Tiny '.$versao.' score '.$score.'%', json_encode($res, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES)]);
    Audit::event('tiny.validacao.executada', $status === 'aprovado' ? 'sucesso' : 'alerta', ['mensagem'=>'Validação Tiny '.$versao.' executada.','contexto'=>$res]);
    return $res;
  }

  public static function podeMarcarV3Operacional(): array {
    $last = null;
    try {
      self::ensureSchema();
      $st = Database::connection('core')->query("SELECT * FROM tiny_validacoes_execucoes WHERE versao='v3' ORDER BY id DESC LIMIT 1");
      $last = $st->fetch() ?: null;
    } catch(Throwable $e){ if (class_exists('BestEffortLogService')) BestEffortLogService::warning(__METHOD__, $e); }
    $ok = $last && (int)$last['score'] >= 90 && $last['status'] === 'aprovado';
    return ['permitido'=>$ok, 'ultima'=>$last, 'motivo'=>$ok ? 'Tiny V3 validado.' : 'Execute a validação Tiny V3 e obtenha score mínimo de 90%.'];
  }
}
