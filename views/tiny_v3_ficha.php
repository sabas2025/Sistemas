<?php require __DIR__.'/layout_top.php'; ?>
<?php
$effectiveRedirectUri = (string)($config['tiny_v3_redirect_uri_effective'] ?? ($config['tiny_v3_redirect_uri'] ?? ''));
$scopeTinyV3 = trim((string)($config['tiny_v3_scopes'] ?? ''));
$oauthConnectUrl = '';
if (!empty($config['tiny_v3_auth_url']) && !empty($config['tiny_v3_client_id']) && $effectiveRedirectUri !== '') {
  // P0-01: state real de uso único (não o token CSRF genérico) + PKCE S256.
  $oauthState = $config['tiny_v3_oauth_state'] ?? ['state' => '', 'code_challenge' => ''];
  $paramsOAuthTinyV3 = [
    'response_type' => 'code',
    'client_id' => $config['tiny_v3_client_id'],
    'redirect_uri' => $effectiveRedirectUri,
    'state' => $oauthState['state'],
    'code_challenge' => $oauthState['code_challenge'],
    'code_challenge_method' => 'S256',
  ];
  if ($scopeTinyV3 !== '') $paramsOAuthTinyV3['scope'] = $scopeTinyV3;
  $oauthConnectUrl = $config['tiny_v3_auth_url'] . '?' . http_build_query($paramsOAuthTinyV3);
}
$diag = $tinyV3Diagnostico ?? [];
$okCount = count(array_filter($diag));
$totalDiag = max(1, count($diag));
$diagPct = (int)round(($okCount / $totalDiag) * 100);
function badge_bool($ok){ return $ok ? '<span class="badge bg-success">OK</span>' : '<span class="badge bg-warning text-dark">Pendente</span>'; }
?>
<div class="integration-page tiny-v3-center">
<?php if(isset($_GET['token'])): ?><div class="alert alert-<?=($_GET['token']==='ok'?'success':'danger')?>">Token Tiny V3: <?=e($_GET['token']==='ok'?'salvo com sucesso.':'falha ao salvar.')?></div><?php endif; ?>
<?php if(isset($_GET['refresh'])): ?><div class="alert alert-<?=($_GET['refresh']==='ok'?'success':'danger')?>">Renovação do token Tiny V3: <?=e($_GET['refresh']==='ok'?'executada.':'falhou. Veja Auditoria.')?></div><?php endif; ?>
<?php if(isset($_GET['teste_modulo'])): ?><div class="alert alert-info">Teste Tiny V3 do módulo <b><?=e($_GET['teste_modulo'])?></b> executado. Veja Auditoria e Logs V3.</div><?php endif; ?>
<?php if(isset($_GET['endpoints'])): ?><div class="alert alert-success">Endpoints Tiny V3 salvos com sucesso.</div><?php endif; ?>
<?php if(isset($_GET['revogar'])): ?><div class="alert alert-<?=($_GET['revogar']==='ok'?'warning':'danger')?>">Revogação de token Tiny V3: <?=e($_GET['revogar']==='ok'?'tokens removidos do ambiente selecionado.':'falhou. Veja Auditoria.')?></div><?php endif; ?>
<?php if(isset($_GET['oauth'])): ?><div class="alert alert-<?=($_GET['oauth']==='ok'?'success':'danger')?>">Conexão OAuth Tiny V3: <?=e($_GET['oauth']==='ok'?'concluída e token salvo por ambiente.':'falhou. Veja Auditoria. Se aparecer invalid_scope, deixe Escopos OAuth vazio e conecte novamente.')?></div><?php endif; ?>

<?php if(!empty($config['tiny_v3_auth_url_error'])): ?><div class="alert alert-danger"><b>OAuth bloqueado:</b> <?=e($config['tiny_v3_auth_url_error'])?> Revise <code>security.tiny_allowed_hosts</code> e use somente o host oficial.</div><?php endif; ?>
<?php if(!empty($config['tiny_v3_redirect_uri_is_relative'])): ?>
<div class="alert alert-warning"><b>Redirect URI corrigida automaticamente:</b> havia uma URL relativa cadastrada. O OAuth será enviado com <code><?=e($effectiveRedirectUri)?></code>. Salve as configurações para gravar a URL absoluta.</div>
<?php endif; ?>
<?php if(($config['tiny_versao'] ?? 'v2') === 'v3' && empty($config['tiny_v3_operacional'])): ?>
<div class="alert alert-danger"><b>Tiny V3 bloqueado para operação:</b> a versão V3 está selecionada, mas ainda não foi marcada como homologada/operacional. O sistema mantém fallback seguro para Tiny V2.</div>
<?php endif; ?>

<div class="integration-hero integration-hero--tiny">
  <div>
    <span class="integration-eyebrow"><i class="bi bi-cloud-check"></i> Tiny ERP V3</span>
    <h1>OAuth, tokens e prontidão operacional</h1>
    <p>Configure a conexão V3 por ambiente, valide o diagnóstico e execute testes controlados antes de liberar produção.</p>
  </div>
  <div class="integration-hero-actions">
    <a class="btn btn-light" href="index.php?page=configuracoes#sec-tiny"><i class="bi bi-gear"></i> Editar credenciais</a>
    <a class="btn btn-outline-light" href="index.php?page=oauth-v3-checklist"><i class="bi bi-list-check"></i> Checklist OAuth</a>
  </div>
</div>
<nav class="integration-section-nav" aria-label="Seções da ficha Tiny V3">
  <a href="#tiny-status"><i class="bi bi-speedometer2"></i> Status</a>
  <a href="#tiny-central"><i class="bi bi-key"></i> OAuth e tokens</a>
  <a href="#tiny-ficha"><i class="bi bi-clipboard-check"></i> Ficha técnica</a>
  <a href="#tiny-maintenance"><i class="bi bi-tools"></i> Manutenção</a>
</nav>
<div id="tiny-status" class="integration-summary">
  <div class="integration-summary-card tiny"><small>Score ficha Tiny V3</small><strong><?=$score?>%</strong><span><?= $score>=90?'Pronto para homologação':'Requer configuração' ?></span></div>
  <div class="integration-summary-card tiny"><small>Diagnóstico OAuth</small><strong><?=$diagPct?>%</strong><span><?=$okCount?> de <?=$totalDiag?> itens OK</span></div>
  <div class="integration-summary-card security"><small>Token</small><strong><?=(!empty($tokenStatus['tem_token_tabela']) || !empty($tokenStatus['tem_token_manual']))?'Configurado':'Ausente'?></strong><span>Ambiente: <?=e($tokenStatus['ambiente'] ?? 'homologacao')?> | Origem: <?=e($tokenStatus['origem'] ?? 'manual/teste')?></span></div>
  <div class="integration-summary-card queue"><small>Base API</small><strong><?=e($config['tiny_v3_url'] ?: 'não configurada')?></strong><span>Ambiente V3: <?=e($config['tiny_v3_ambiente'] ?? 'homologacao')?></span></div>
</div>

<div id="tiny-central" class="panel integration-panel mb-3">
  <div class="panel-header"><h2>Tiny V3 — OAuth, tokens e diagnóstico</h2><span class="text-muted">Central única para configuração e homologação</span></div>
  <div class="p-3">
    <ul class="nav nav-pills integration-tabs" id="tinyV3Tabs" role="tablist">
      <li class="nav-item" role="presentation"><button class="nav-link active" data-bs-toggle="tab" data-bs-target="#tab-oauth" type="button">OAuth</button></li>
      <li class="nav-item" role="presentation"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#tab-token" type="button">Token Manual / Legado</button></li>
      <li class="nav-item" role="presentation"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#tab-diagnostico" type="button">Diagnóstico</button></li>
      <li class="nav-item" role="presentation"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#tab-testes" type="button">Testes por módulo</button></li>
      <li class="nav-item" role="presentation"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#tab-endpoints" type="button">Endpoints</button></li>
    </ul>

    <div class="tab-content pt-3">
      <div class="tab-pane fade show active" id="tab-oauth" role="tabpanel">
        <div class="row g-3">
          <div class="col-md-6"><div class="integration-tab-card"><b>Base API</b><code class="integration-url"><?=e($config['tiny_v3_url'] ?: 'https://api.tiny.com.br/public-api/v3')?></code></div></div>
          <div class="col-md-6"><div class="integration-tab-card"><b>Swagger oficial</b><code class="integration-url">https://erp.tiny.com.br/public-api/v3/swagger/</code></div></div>
          <div class="col-md-6"><div class="integration-tab-card"><b>Auth URL</b><code class="integration-url"><?=e($config['tiny_v3_auth_url'] ?? 'https://accounts.tiny.com.br/realms/tiny/protocol/openid-connect/auth')?></code></div></div>
          <div class="col-md-6"><div class="integration-tab-card"><b>Token URL</b><code class="integration-url"><?=e($config['tiny_v3_token_url'] ?? 'https://accounts.tiny.com.br/realms/tiny/protocol/openid-connect/token')?></code></div></div>
          <div class="col-12"><div class="integration-tab-card"><b>Redirect URI para cadastrar exatamente no Tiny/Olist</b><code class="integration-url" id="redirectUriTinyV3"><?=e($effectiveRedirectUri)?></code><button class="btn btn-sm btn-outline-secondary mt-2" type="button" data-copy-target="#redirectUriTinyV3">Copiar</button></div></div>
          <div class="col-12"><div class="integration-tab-card"><b>Escopos OAuth</b><code class="integration-url"><?=e($scopeTinyV3 !== '' ? $scopeTinyV3 : 'vazio — recomendado para Tiny V3')?></code><small class="text-muted d-block mt-2">Para evitar <code>invalid_scope</code>, deixe vazio. Só preencha se o Tiny/Olist informar escopos válidos para o seu aplicativo.</small></div></div>
        </div>
        <div class="mt-3 d-flex flex-wrap gap-2">
          <?php if($oauthConnectUrl): ?>
            <a class="btn btn-success" href="<?=e($oauthConnectUrl)?>"><i class="bi bi-box-arrow-up-right"></i> Conectar Tiny V3 via OAuth</a>
          <?php else: ?>
            <a class="btn btn-outline-secondary disabled" href="#">Preencha Client ID, Client Secret e Redirect URI em Configurações</a>
          <?php endif; ?>
          <a class="btn btn-outline-primary" target="_blank" href="https://erp.tiny.com.br/public-api/v3/swagger/"><i class="bi bi-file-earmark-code"></i> Abrir Swagger Tiny V3</a>
          <a class="btn btn-outline-secondary" href="index.php?page=configuracoes"><i class="bi bi-gear"></i> Editar OAuth</a>
          <a class="btn btn-outline-dark" href="index.php?page=oauth-v3-checklist"><i class="bi bi-list-check"></i> Checklist OAuth V3</a>
        </div>
        <div class="alert alert-info mt-3 mb-0"><b>Fluxo correto:</b> o <code>install.php</code> não coleta credenciais Tiny. Configure Client ID, Client Secret, Redirect URI e tokens somente no painel, com auditoria.</div>
      </div>

      <div class="tab-pane fade" id="tab-token" role="tabpanel">
        <div class="alert alert-warning"><b>Uso controlado:</b> token manual é legado de homologação. Em produção, use OAuth por ambiente.</div>
        <form class="row g-3" method="post" action="index.php?page=tiny-v3-token-salvar"><?=Csrf::input()?>
          <div class="col-md-6"><label class="form-label">Access Token</label><input class="form-control" name="access_token" autocomplete="off" placeholder="Cole o access_token somente para teste controlado"></div>
          <div class="col-md-6"><label class="form-label">Refresh Token</label><input class="form-control" name="refresh_token" autocomplete="off" placeholder="Opcional em homologação manual"></div>
          <div class="col-md-3"><label class="form-label">Ambiente do token</label><select class="form-select" name="ambiente_token"><option value="homologacao" <?=($config['tiny_v3_ambiente']??'homologacao')==='homologacao'?'selected':''?>>Homologação</option><option value="producao" <?=($config['tiny_v3_ambiente']??'')==='producao'?'selected':''?>>Produção</option></select></div>
          <div class="col-md-3"><label class="form-label">Expira em segundos</label><input class="form-control" type="number" name="expires_in" value="3600"></div>
          <div class="col-md-6"><label class="form-label">Escopos do token</label><input class="form-control" name="scope" placeholder="Deixe vazio, salvo se Tiny informar escopos válidos"></div>
          <div class="col-md-3 d-flex align-items-end"><button class="btn btn-primary w-100"><i class="bi bi-shield-lock"></i> Salvar token manual</button></div>
        </form>

        <hr>
        <h5>Tokens salvos por ambiente</h5>
        <div class="table-responsive"><table class="table table-hover"><thead><tr><th>ID</th><th>Ambiente</th><th>Origem</th><th>Status</th><th>Expira em</th><th>Última renovação</th><th>Ações</th></tr></thead><tbody>
        <?php if(empty($tokenRows)): ?><tr><td colspan="7" class="text-muted">Nenhum token salvo.</td></tr><?php endif; ?>
        <?php foreach($tokenRows as $t): $expired=!empty($t['expires_at']) && strtotime($t['expires_at']) <= time()+60; ?>
          <tr><td><?=e($t['id'])?></td><td><span class="badge bg-<?=($t['ambiente']??'homologacao')==='producao'?'danger':'info'?>"><?=e($t['ambiente']??'homologacao')?></span></td><td><?=e($t['origem']??'manual')?></td><td><?=$expired?'<span class="badge bg-danger">Expirado</span>':'<span class="badge bg-success">Válido</span>'?></td><td><?=e($t['expires_at']??'não informado')?></td><td><?=e($t['atualizado_em']??$t['criado_em']??'')?></td><td class="d-flex gap-1"><form method="post" action="index.php?page=tiny-v3-token-renovar"><?=Csrf::input()?><button class="btn btn-sm btn-outline-success">Renovar</button></form><form method="post" action="index.php?page=tiny-v3-token-revogar" data-confirm="Remover tokens Tiny V3 deste ambiente?"><?=Csrf::input()?><input type="hidden" name="ambiente_token" value="<?=e($t['ambiente']??'homologacao')?>"><button class="btn btn-sm btn-outline-danger">Revogar</button></form></td></tr>
        <?php endforeach; ?>
        </tbody></table></div>
      </div>

      <div class="tab-pane fade" id="tab-diagnostico" role="tabpanel">
        <div class="row g-3 mb-3">
          <div class="col-md-4"><div class="integration-tab-card"><b>Client ID</b><br><?=badge_bool($diag['client_id'] ?? false)?><p class="text-muted mb-0 mt-2">Deve vir do aplicativo criado no Tiny/Olist.</p></div></div>
          <div class="col-md-4"><div class="integration-tab-card"><b>Client Secret</b><br><?=badge_bool($diag['client_secret'] ?? false)?><p class="text-muted mb-0 mt-2">Salvo criptografado; não aparece completo.</p></div></div>
          <div class="col-md-4"><div class="integration-tab-card"><b>Redirect URI absoluta</b><br><?=badge_bool($diag['redirect_uri'] ?? false)?><p class="text-muted mb-0 mt-2">Precisa começar com http:// ou https://.</p></div></div>
          <div class="col-md-4"><div class="integration-tab-card"><b>Scopes seguros</b><br><?=badge_bool($diag['scope_seguro'] ?? false)?><p class="text-muted mb-0 mt-2">Campo vazio evita invalid_scope.</p></div></div>
          <div class="col-md-4"><div class="integration-tab-card"><b>Token salvo</b><br><?=badge_bool($diag['token'] ?? false)?><p class="text-muted mb-0 mt-2">OAuth ou token manual de homologação.</p></div></div>
          <div class="col-md-4"><div class="integration-tab-card"><b>Operacional</b><br><?=badge_bool($diag['operacional'] ?? false)?><p class="text-muted mb-0 mt-2">Só marque após homologação.</p></div></div>
        </div>
        <h5>Últimos logs técnicos Tiny V3</h5>
        <div class="table-responsive"><table class="table table-sm"><thead><tr><th>Data</th><th>Método</th><th>Endpoint</th><th>HTTP</th><th>Sucesso</th><th>Tempo</th><th>Trace</th><th>Erro</th></tr></thead><tbody>
        <?php if(empty($tinyV3Logs)): ?><tr><td colspan="8" class="text-muted">Nenhum log Tiny V3 registrado ainda.</td></tr><?php endif; ?>
        <?php foreach($tinyV3Logs as $l): ?><tr><td><?=e($l['criado_em']??'')?></td><td><?=e($l['metodo']??'')?></td><td><code><?=e($l['endpoint']??'')?></code></td><td><?=e($l['http_code']??'')?></td><td><?=!empty($l['sucesso'])?'<span class="badge bg-success">sim</span>':'<span class="badge bg-danger">não</span>'?></td><td><?=e($l['tempo_ms']??'')?>ms</td><td><code><?=e($l['trace_id']??'')?></code></td><td><small><?=e($l['erro']??'')?></small></td></tr><?php endforeach; ?>
        </tbody></table></div>
      </div>

      <div class="tab-pane fade" id="tab-testes" role="tabpanel">
        <form class="row g-3" method="post" action="index.php?page=tiny-v3-testar-modulo"><?=Csrf::input()?>
          <div class="col-md-3"><label class="form-label">Módulo</label><select class="form-select" name="modulo"><option value="token">Testar token/base</option><option value="listar_produtos">Listar produtos</option><option value="produto">Obter produto por SKU</option><option value="estoque">Consultar estoque por SKU</option><option value="pedido">Consultar pedido por ID</option><option value="nota_fiscal">Consultar NF-e por ID</option></select></div>
          <div class="col-md-3"><label class="form-label">SKU</label><input class="form-control" name="sku" placeholder="SKU real"></div>
          <div class="col-md-3"><label class="form-label">ID Pedido</label><input class="form-control" name="id_pedido" placeholder="idPedido real"></div>
          <div class="col-md-3"><label class="form-label">ID NF-e</label><input class="form-control" name="id_nota" placeholder="idNota real"></div>
          <div class="col-md-3 d-flex align-items-end"><button class="btn btn-outline-primary w-100"><i class="bi bi-play-circle"></i> Executar teste</button></div>
          <div class="col-12"><small class="text-muted">Todos os resultados completos ficam em Auditoria e <code>tiny_v3_endpoint_logs</code>.</small></div>
        </form>
      </div>

      <div class="tab-pane fade" id="tab-endpoints" role="tabpanel">
        <form method="post" action="index.php?page=tiny-v3-endpoints-salvar"><?=Csrf::input()?>
          <div class="table-responsive"><table class="table table-sm"><thead><tr><th>Chave</th><th>Endpoint efetivo</th><th>Padrão sugerido</th></tr></thead><tbody><?php foreach($endpoints as $k=>$v): ?><tr><td><code><?=e($k)?></code></td><td><input class="form-control form-control-sm" name="tiny_v3_<?=e($k)?>" value="<?=e($endpointValues[$k] ?? $v)?>"></td><td><code><?=e($v)?></code></td></tr><?php endforeach; ?></tbody></table></div>
          <button class="btn btn-primary"><i class="bi bi-save"></i> Salvar endpoints V3</button>
        </form>
      </div>
    </div>
  </div>
</div>

<div id="tiny-ficha" class="panel integration-panel mb-3"><div class="panel-header"><h2>Ficha técnica Tiny V3</h2><span class="text-muted">Módulos necessários para VSM ⇄ Tiny</span></div><div class="table-responsive"><table class="table table-hover mb-0"><thead><tr><th>Grupo</th><th>Item</th><th>Status</th><th>Ação recomendada</th></tr></thead><tbody><?php foreach($ficha as $i): ?><tr><td><?=e($i['grupo'])?></td><td><?=e($i['item'])?></td><td><?=!empty($i['ok'])?'<span class="badge bg-success">OK</span>':'<span class="badge bg-warning text-dark">Atenção</span>'?></td><td><?=e($i['acao'])?></td></tr><?php endforeach; ?></tbody></table></div></div>

<div id="tiny-maintenance" class="panel integration-panel mb-3"><div class="panel-header"><h2>Manutenção Tiny V3</h2><span class="text-muted">Estruturas e token</span></div><div class="p-4 d-flex gap-2 flex-wrap">
  <?php if(App::isLocal()): ?><form method="post" action="index.php?page=atualizar-v17-tiny-v3"><?=Csrf::input()?><button class="btn btn-warning"><i class="bi bi-database-gear"></i> Estrutura V17</button></form>
  <form method="post" action="index.php?page=atualizar-v18-tiny-v3-final"><?=Csrf::input()?><button class="btn btn-outline-warning"><i class="bi bi-database-check"></i> Estrutura V18</button></form>
  <form method="post" action="index.php?page=atualizar-v19-tiny-v3-operacional"><?=Csrf::input()?><button class="btn btn-outline-danger"><i class="bi bi-shield-check"></i> Estrutura V19</button></form>
  <form method="post" action="index.php?page=atualizar-v20-tiny-v3-seguranca"><?=Csrf::input()?><button class="btn btn-outline-dark"><i class="bi bi-shield-lock"></i> Estrutura V20</button></form>
  <form method="post" action="index.php?page=atualizar-v21-tiny-v3-final"><?=Csrf::input()?><button class="btn btn-outline-primary"><i class="bi bi-check2-square"></i> Estrutura V21</button></form><?php endif; ?>
  <form method="post" action="index.php?page=tiny-v3-token-renovar"><?=Csrf::input()?><button class="btn btn-outline-success"><i class="bi bi-arrow-clockwise"></i> Renovar token V3</button></form>
  <a href="index.php?page=configuracoes" class="btn btn-outline-secondary">Voltar às configurações</a>
</div></div>

<div class="alert alert-warning"><b>Diagnóstico do erro invalid_scope:</b> o Tiny/Olist recusou <code>produtos estoque pedidos notas-fiscais</code>. Deixe Escopos OAuth vazio por padrão. Produtos, estoque, pedidos e NF-e devem ser liberados nas permissões do aplicativo dentro do Tiny/Olist, não como scopes livres na URL.</div>
</div>
<?php require __DIR__.'/layout_bottom.php'; ?>
