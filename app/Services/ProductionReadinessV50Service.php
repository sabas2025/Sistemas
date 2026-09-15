<?php
/**
 * V104.31 - Checklist Produção Segura analítico.
 *
 * Mantém compatibilidade da tabela production_readiness_v50, mas melhora a lógica
 * para separar OK, Atenção e Bloqueio. Não altera fluxos Tiny/VSM; apenas diagnostica.
 */
class ProductionReadinessV50Service {
  public static function ensureSchema(): void {
    SchemaRuntimePolicyService::requireTable('production_readiness_v50', 'prontidão de produção');
  }

  public static function executar(): array {
    self::ensureSchema();
    try { if (class_exists('VsmEnvironmentService')) VsmEnvironmentService::ensureSchema(); } catch(Throwable $ignored){ if (class_exists('BestEffortLogService')) BestEffortLogService::warning(__METHOD__, $ignored); }

    $cfg = IntegrationConfig::get();
    $checks = [];
    $add = function(string $nome, string $status, string $risco, string $acao, string $detalhe = '', string $grupo = 'Produção') use (&$checks): void {
      $status = in_array($status, ['ok','atencao','bloqueio'], true) ? $status : 'atencao';
      $checks[] = [
        'grupo'=>$grupo,
        'nome'=>$nome,
        'ok'=>$status === 'ok',
        'status'=>$status,
        'risco'=>$risco,
        'acao'=>$acao,
        'detalhe'=>$detalhe,
      ];
    };

    $https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || (str_starts_with((string)($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? ''),'https'));
    $publicHost = App::isPublicHost();
    $appEnv = App::env();
    $isProdEnv = App::isProduction();

    $add('HTTPS ativo', (App::isLocal() || $https) ? 'ok' : 'bloqueio', 'Webhook e OAuth sem HTTPS expõem tokens e callbacks.', 'Ativar certificado SSL antes da produção.', $https ? 'HTTPS detectado.' : 'HTTPS não detectado no request atual.', 'Ambiente');
    $add('Ambiente produção configurado conscientemente', (!$publicHost || $isProdEnv) ? 'ok' : 'bloqueio', 'Produção com app_env local/dev pode exibir erros sensíveis.', 'Usar app_env=production/producao em servidor público.', 'app_env atual: '.$appEnv, 'Ambiente');

    $dbCfg = (require __DIR__.'/../../config/config.php')['db'] ?? [];
    $mysqlPassOk = App::isLocal() || trim((string)($dbCfg['pass'] ?? '')) !== '';
    $add('Senha MySQL não vazia em produção', $mysqlPassOk ? 'ok' : 'bloqueio', 'Senha vazia em produção é risco crítico.', 'Definir usuário e senha específicos para o HUB.', $mysqlPassOk ? 'Senha não vazia ou ambiente local.' : 'Senha vazia detectada.', 'Banco');

    // Tiny: considerar V2 token, V3 operacional + token manual/OAuth. Bloqueio aqui não é erro de código; é bloqueio de entrada em produção.
    $tinyVersao = strtolower((string)($cfg['tiny_versao'] ?? 'v2'));
    $tinyV2Token = trim((string)($cfg['tiny_v2_token'] ?? '')) !== '';
    $tinyV3Operational = !empty($cfg['tiny_v3_operacional']);
    $tinyV3ManualToken = trim((string)($cfg['tiny_v3_manual_access_token'] ?? '')) !== '' || trim((string)($cfg['tiny_v3_token'] ?? '')) !== '';
    $tinyV3OAuthToken = false;
    try {
      if (class_exists('TinyV3TokenService')) {
        $tinyV3OAuthToken = (bool)TinyV3TokenService::getTokenRow((string)($cfg['tiny_v3_ambiente'] ?? 'homologacao'));
      }
    } catch(Throwable $ignored){ if (class_exists('BestEffortLogService')) BestEffortLogService::warning(__METHOD__, $ignored); }
    $tinyConfigured = $tinyV2Token || ($tinyV3Operational && ($tinyV3ManualToken || $tinyV3OAuthToken));
    $tinyDetalhe = $tinyV2Token ? 'Tiny V2 token configurado.' : (($tinyV3Operational && ($tinyV3ManualToken || $tinyV3OAuthToken)) ? 'Tiny V3 operacional com token.' : 'Nenhum token Tiny válido detectado para produção.');
    $add('Tiny configurado', $tinyConfigured ? 'ok' : 'bloqueio', 'Integração Tiny sem token não processa pedido, estoque ou NF-e.', 'Configurar Tiny V2 em produção ou validar Tiny V3 100% com OAuth/token antes da liberação.', $tinyDetalhe.' Versão selecionada: '.$tinyVersao, 'Integrações Tiny');

    try { $v3 = TinyValidationService::podeMarcarV3Operacional(); }
    catch(Throwable $e) { $v3 = ['permitido'=>false,'motivo'=>$e->getMessage()]; }
    $tinyV3CheckStatus = empty($cfg['tiny_v3_operacional']) ? 'ok' : (!empty($v3['permitido']) ? 'ok' : 'bloqueio');
    $add('Tiny V3 validado se operacional', $tinyV3CheckStatus, 'Tiny V3 marcado operacional sem checklist pode cair em falhas silenciosas.', 'Executar Validação Tiny V3 100% antes de marcar como operacional.', empty($cfg['tiny_v3_operacional']) ? 'Tiny V3 não está marcado como operacional.' : ((string)($v3['motivo'] ?? 'Checklist consultado.')), 'Integrações Tiny');

    $vsmUrl = trim((string)($cfg['vsm_url'] ?? ''));
    $vsmStatus = class_exists('VsmEnvironmentService') ? VsmEnvironmentService::dashboardStatus($cfg) : ['modo'=>'Indefinido','detalhe'=>'Serviço de ambiente VSM indisponível.'];
    $vsmEnv = class_exists('VsmEnvironmentService') ? VsmEnvironmentService::normalizedEnv($cfg) : (string)($cfg['vsm_ambiente'] ?? 'homologacao');
    $vsmReleased = class_exists('VsmEnvironmentService') ? VsmEnvironmentService::isProductionReleased($cfg) : (!empty($cfg['vsm_producao_liberada']) && !empty($cfg['vsm_ultimo_teste_ok']));
    $isHomolog = class_exists('VsmEnvironmentService') ? VsmEnvironmentService::isHomologUrl($vsmUrl) : str_contains(strtolower($vsmUrl),'homolog');

    $add('VSM URL configurada', $vsmUrl !== '' ? 'ok' : 'bloqueio', 'Sem URL VSM não há comunicação com produção/homologação.', 'Cadastrar URL base oficial da VSM.', $vsmUrl !== '' ? 'URL detectada. Modo atual: '.($vsmStatus['modo'] ?? '-') : 'URL ausente.', 'Integrações VSM');
    $add('VSM ambiente seguro', ($vsmUrl !== '' && ($isHomolog || $vsmReleased || $vsmEnv === 'producao_pendente')) ? 'ok' : 'bloqueio', 'URL de produção sem liberação pode enviar dados reais antes da autorização.', 'Manter homologação por padrão; produção somente com liberação manual, teste OK e autorização VSM.', (string)($vsmStatus['detalhe'] ?? '').' — '.(string)($vsmStatus['acao'] ?? ''), 'Integrações VSM');
    $goLiveStatus = $vsmReleased ? 'ok' : 'bloqueio';
    $add('VSM produção liberada para go-live', $goLiveStatus, 'Produção VSM sem checklist oficial pode gerar pedidos/estoque em ambiente errado.', 'Liberar produção VSM somente após URL oficial, token, teste real OK, checklist aprovado e autorização da VSM.', $vsmReleased ? 'Produção VSM liberada com teste OK.' : 'Produção VSM ainda bloqueada por segurança. Homologação é o padrão inicial correto.', 'Integrações VSM');

    $produtoNovoBloqueado = !empty($cfg['sync_bloquear_produto_novo_vsm']) || !empty($cfg['fluxo_produto_novo_vsm_bloquear']) || (($cfg['produto_novo_aprovacao_modo'] ?? 'manual') === 'manual');
    $add('Produto novo VSM bloqueado/aprovação manual', $produtoNovoBloqueado ? 'ok' : 'atencao', 'Produto novo automático pode duplicar cadastro e errar fiscal.', 'Manter produto novo em aprovação manual até mapeamento SKU/categoria fiscal.', $produtoNovoBloqueado ? 'Fluxo seguro/manual detectado.' : 'Fluxo automático detectado; revisar antes de produção.', 'Fluxos');

    $storage = __DIR__.'/../../storage';
    $cache = __DIR__.'/../../storage/cache';
    $cacheWritable = is_writable($storage) || is_writable($cache) || @mkdir($cache,0775,true);
    $add('Cache gravável', $cacheWritable ? 'ok' : 'bloqueio', 'Cache não gravável prejudica performance e PWA.', 'Garantir permissão de escrita em storage/cache.', $cacheWritable ? 'Permissão de escrita detectada.' : 'storage/cache não está gravável.', 'Performance');

    $sw = __DIR__.'/../../public/sw.js';
    $swContent = is_file($sw) ? (string)@file_get_contents($sw) : '';
    $swVersion = preg_match("/const\s+HUB_VERSION\s*=\s*['\"]([0-9]+\.[0-9]+\.[0-9]+)['\"]/", $swContent, $m) ? (string)($m[1] ?? '') : '';
    $requiredVersion = class_exists('SystemVersionService') ? SystemVersionService::VERSION_NUMBER : '104.49.3';
    $swOk = $swVersion !== '' && version_compare($swVersion, $requiredVersion, '>=');
    $add('PWA cache na versão atual', $swOk ? 'ok' : 'atencao', 'Service Worker antigo pode manter CSS/JS quebrado no iOS/Android.', 'Atualizar PWA no painel e limpar cache após subir a nova versão.', $swOk ? 'Service Worker '.$swVersion.' detectado.' : 'Service Worker ausente ou inferior a '.$requiredVersion.'.', 'PWA');

    $add('Rotação/limpeza habilitada', class_exists('RetentionService') ? 'ok' : 'atencao', 'Logs e payloads crescem com o tempo.', 'Executar rotina Limpeza/Retenção por cron e monitorar storage.', class_exists('RetentionService') ? 'RetentionService disponível.' : 'RetentionService não encontrado; revisar cron.', 'Operação');

    $ok = count(array_filter($checks, fn($c)=>$c['status']==='ok'));
    $alertas = count(array_filter($checks, fn($c)=>$c['status']==='atencao'));
    $bloqueios = count(array_filter($checks, fn($c)=>$c['status']==='bloqueio'));
    $score = (int)round((($ok + ($alertas * 0.5)) / max(1, count($checks))) * 100);
    $status = $bloqueios > 0 ? 'bloqueado' : ($alertas > 0 ? 'atencao' : 'aprovado');
    $resumo = $status === 'aprovado'
      ? 'Sem bloqueios para produção. Manter monitoramento de fila, logs e integrações.'
      : ($status === 'atencao'
        ? 'Sem bloqueios críticos, mas existem alertas que devem ser tratados antes do go-live.'
        : 'Existem bloqueios de produção. Isso não significa erro do sistema; significa que a liberação segura ainda não foi concluída.');

    $res = [
      'status'=>$status,
      'score'=>$score,
      'checks'=>$checks,
      'ok'=>$ok,
      'alertas'=>$alertas,
      'bloqueios'=>$bloqueios,
      'trace_id'=>RequestContext::id(),
      'criado_em'=>date('Y-m-d H:i:s'),
      'resumo'=>$resumo,
      'proxima_acao'=>$bloqueios > 0 ? 'Resolver bloqueios vermelhos antes de ligar produção.' : ($alertas > 0 ? 'Tratar alertas amarelos e executar nova validação.' : 'Gerar evidência de homologação e liberar produção com monitoramento.'),
    ];

    $st=Database::connection('core')->prepare('INSERT INTO production_readiness_v50(trace_id,status,score,resultado_json) VALUES(?,?,?,?)');
    $st->execute([RequestContext::id(),$status,$score,json_encode($res,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES)]);
    Audit::event('production.v50.checklist',$status==='aprovado'?'sucesso':($status==='bloqueado'?'alerta':'info'),['mensagem'=>'Checklist produção segura executado.','contexto'=>$res]);
    return $res;
  }
}
