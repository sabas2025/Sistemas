<?php require __DIR__.'/layout_top.php'; ?>
<?php if(isset($_GET['salvo'])): ?><div class="alert alert-success">Configurações salvas com sucesso. Tokens foram preservados quando mascarados.</div><?php endif; ?>
<?php if(($_GET['teste']??'')==='ok'): ?><div class="alert alert-info">Teste Tiny executado. Veja o retorno completo em Auditoria.</div><?php endif; ?>
<?php if(($_GET['teste']??'')==='erro'): ?><div class="alert alert-danger">Teste Tiny falhou. Veja o Trace ID em Auditoria.</div><?php endif; ?>
<?php if(($_GET['teste_vsm']??'')==='ok'): ?><div class="alert alert-success">Teste VSM executado com sucesso. Veja detalhes na Auditoria.</div><?php endif; ?>
<?php if(($_GET['teste_vsm']??'')==='erro'): ?><div class="alert alert-danger">Teste VSM falhou. Veja causa provável e ação recomendada na Auditoria.</div><?php endif; ?>
<?php if(isset($_GET['tinyv3_bloqueado'])): $tinyV3Issues = $_SESSION['tiny_v3_blocked_issues'] ?? []; unset($_SESSION['tiny_v3_blocked_issues']); ?>
<div class="alert alert-danger">
  <b>Tiny V3 não foi ativado.</b> A proteção operacional bloqueou a ativação porque ainda existem pendências obrigatórias de homologação.
  <?php if(!empty($tinyV3Issues)): ?><ul class="mt-2 mb-2"><?php foreach($tinyV3Issues as $issue): ?><li><?=e($issue)?></li><?php endforeach; ?></ul><?php endif; ?>
  <div class="d-flex gap-2 flex-wrap mt-2"><a class="btn btn-sm btn-danger" href="index.php?page=tiny-v3-ficha">Abrir Ficha Tiny V3</a><a class="btn btn-sm btn-outline-danger" href="index.php?page=homologacao">Abrir Checklist de Homologação</a><a class="btn btn-sm btn-outline-secondary" href="index.php?page=validar-banco">Validar Banco</a></div>
</div>
<?php endif; ?>
<?php
$tinyV2Ready = trim((string)($config['tiny_v2_token'] ?? '')) !== '';
$tinyV3Ready = !empty($config['tiny_v3_client_id']) && !empty($config['tiny_v3_client_secret']) && !empty($config['tiny_v3_redirect_uri']);
$vsmReady = trim((string)($config['vsm_url'] ?? '')) !== '' && trim((string)($config['vsm_token'] ?? '')) !== '';
$webhookReady = trim((string)($config['webhook_secret'] ?? '')) !== '' || trim((string)($config['tiny_webhook_secret'] ?? '')) !== '';
?>
<div class="integration-page integration-config-page">
<div class="alert alert-info">Nova conexão independente: <a href="index.php?page=myouro-configuracoes">MyOuro GraphQL — Consultas e vínculo da empresa</a>. Não substitua a URL REST VSM por /graphql.</div>
<div class="integration-hero integration-hero--config">
  <div>
    <span class="integration-eyebrow"><i class="bi bi-sliders"></i> Central de configurações</span>
    <h1>Tiny, VSM e segurança operacional</h1>
    <p>Configurações críticas ficam em homologação por padrão, com produção liberada somente após checklist, teste real e aprovação humana.</p>
  </div>
  <div class="integration-hero-actions">
    <a class="btn btn-light" href="index.php?page=producao-segura"><i class="bi bi-shield-lock"></i> Produção Segura</a>
    <a class="btn btn-outline-light" href="index.php?page=enterprise-regression-tests"><i class="bi bi-check2-square"></i> Testes</a>
  </div>
</div>
<div class="integration-summary" aria-label="Resumo de prontidão das integrações">
  <div class="integration-summary-card tiny"><small>Tiny V2</small><strong><?= $tinyV2Ready ? 'Configurado' : 'Token pendente' ?></strong><span>Fallback operacional preservado</span></div>
  <div class="integration-summary-card tiny"><small>Tiny V3 OAuth</small><strong><?= $tinyV3Ready ? 'Credenciais prontas' : 'Configuração pendente' ?></strong><span>Ambiente: <?=e($config['tiny_v3_ambiente'] ?? 'homologacao')?></span></div>
  <div class="integration-summary-card vsm"><small>VSM</small><strong><?= $vsmReady ? 'Conexão configurada' : 'Token ou URL pendente' ?></strong><span><?=e($config['vsm_ambiente'] ?? 'homologacao')?> · API <?=e($config['vsm_api_principal'] ?? 'pedidos-integradora')?></span></div>
  <div class="integration-summary-card security"><small>Webhooks</small><strong><?= $webhookReady ? 'Proteção configurada' : 'Segredo pendente' ?></strong><span>Secrets e limites de entrada</span></div>
</div>
<nav class="integration-section-nav" aria-label="Seções da configuração">
  <a href="#sec-geral"><i class="bi bi-sliders"></i> Geral</a>
  <a href="#sec-tiny"><i class="bi bi-cloud-arrow-up"></i> Tiny</a>
  <a href="#sec-vsm"><i class="bi bi-diagram-3"></i> VSM</a>
  <a href="#sec-webhooks"><i class="bi bi-shield-lock"></i> Webhooks</a>
  <a href="#sec-fila"><i class="bi bi-hourglass-split"></i> Fila</a>
  <a href="#sec-testes"><i class="bi bi-activity"></i> Testes</a>
</nav>
<form method="post" action="index.php?page=salvar-configuracoes" class="panel integration-panel">
<?=Csrf::input()?>
<div class="panel-header"><h2>Configurações de integração</h2><span class="text-muted">Credenciais e parâmetros operacionais organizados por domínio</span></div>
<div class="px-4 pt-3"><a class="btn btn-sm btn-outline-primary" href="index.php?page=tiny-ambientes"><i class="bi bi-diagram-3"></i> Abrir separação Tiny V2 / Tiny V3</a></div>
<div class="p-4">
<div class="alert alert-info">
  <b>Instalação limpa:</b> o <code>install.php</code> agora instala apenas banco, administrador e aplicação. As integrações são configuradas aqui no painel para evitar exposição de tokens durante a instalação.
  <div class="mt-2 small">
    <b>Tiny V2 padrão:</b> <code>https://api.tiny.com.br/api2</code><br>
    <b>Tiny V3 API padrão:</b> <code>https://api.tiny.com.br/public-api/v3</code><br>
    <b>Swagger oficial Tiny V3:</b> <code>https://erp.tiny.com.br/public-api/v3/swagger/</code><br>
    <b>VSM homologação:</b> <code>https://conectavenda.homolog.vsm.com.br</code>
  </div>
</div>
<section id="sec-geral" class="integration-card mb-3"><div class="integration-card-title"><div><h2>Ambiente e seleção operacional</h2><p>Defina o ambiente geral e a versão Tiny ativa antes de configurar credenciais.</p></div><span class="integration-card-badge"><i class="bi bi-shield-check"></i> Homologação primeiro</span></div><div class="row g-3">
<div class="col-md-3"><label class="form-label">Ambiente</label><select class="form-select" name="ambiente"><option value="homologacao" <?=($config['ambiente']??'')==='homologacao'?'selected':''?>>Homologação</option><option value="producao" <?=($config['ambiente']??'')==='producao'?'selected':''?>>Produção</option></select></div>
<div class="col-md-3"><label class="form-label">Versão Tiny</label><select class="form-select" name="tiny_versao"><option value="v2" <?=($config['tiny_versao']??'')==='v2'?'selected':''?>>Tiny V2 operacional</option><option value="v3" <?=($config['tiny_versao']??'')==='v3'?'selected':''?>>Tiny V3 preparado</option></select><div class="form-text text-warning">Tiny V3 só deve ser ativado após implementação/homologação real.</div></div>
<div class="col-md-6"><label class="form-label">Webhook Secret</label><input class="form-control" name="webhook_secret" value="<?=e(Secrets::mask($config['webhook_secret']??''))?>"><div class="form-text">Enviar no header <code>X-HUB-SECRET</code>. Para trocar, apague tudo e digite um novo segredo.</div></div>
<div class="col-md-6"><label class="form-label">Tiny V2 URL</label><input class="form-control" name="tiny_v2_url" value="<?=e($config['tiny_v2_url']??'https://api.tiny.com.br/api2')?>"><div class="form-text">Pré-preenchido no painel. Informe apenas o token V2 se optar por usar V2.</div></div>
<div class="col-md-6"><label class="form-label">Tiny V2 Token</label><input class="form-control" name="tiny_v2_token" value="<?=e(Secrets::mask($config['tiny_v2_token']??''))?>"></div>
<div class="col-md-3"><label class="form-label">Ambiente Tiny V3</label><select class="form-select" name="tiny_v3_ambiente"><option value="homologacao" <?=($config['tiny_v3_ambiente']??'homologacao')==='homologacao'?'selected':''?>>Homologação</option><option value="producao" <?=($config['tiny_v3_ambiente']??'')==='producao'?'selected':''?>>Produção</option></select></div><div class="col-md-3"><label class="form-label">Tiny V3 Base API</label><input class="form-control" name="tiny_v3_url" value="<?=e($config['tiny_v3_url']??'https://api.tiny.com.br/public-api/v3')?>"><div class="form-text">Base sugerida conforme Swagger Tiny V3.</div></div>
<div class="col-md-6"><label class="form-label">Tiny V3 Token manual <span class="badge bg-warning text-dark">Legado/Homologação</span></label><input class="form-control" name="tiny_v3_token" value="<?=e(Secrets::mask($config['tiny_v3_token']??''))?>"><div class="form-text">Campo legado para testes controlados. Em produção use tokens OAuth por ambiente na Ficha Tiny V3.</div></div>
<div class="col-md-6"><div class="form-check form-switch mt-4"><input class="form-check-input" type="checkbox" name="tiny_v3_operacional" value="1" <?=!empty($config['tiny_v3_operacional'])?'checked':''?>><label class="form-check-label"><b>Tiny V3 homologado e operacional</b></label><div class="form-text">Mantenha desativado até OAuth por ambiente, testes Tiny V3 e checklist obrigatório estarem aprovados.</div></div></div>
</div></section>


<div class="col-12"><section id="sec-tiny" class="integration-card tiny-card mb-3"><div class="integration-card-title"><div><h2><i class="bi bi-cloud-arrow-up"></i> Tiny V3 — OAuth e ficha técnica</h2><p>Mantenha Tiny V2 como fallback. Ative Tiny V3 somente após homologar OAuth, permissões, produtos, estoque, pedidos e NF-e.</p></div><span class="integration-card-badge">Tiny V3</span></div><div class="alert alert-light border small mb-3"><b>Referência Swagger Tiny V3:</b> <code>https://erp.tiny.com.br/public-api/v3/swagger/</code></div><div class="row g-3">
  <div class="col-md-6"><label class="form-label">Tiny V3 Auth URL</label><input class="form-control" name="tiny_v3_auth_url" value="<?=e($config['tiny_v3_auth_url']??'https://accounts.tiny.com.br/realms/tiny/protocol/openid-connect/auth')?>" placeholder="URL de autorização OAuth"></div>
  <div class="col-md-6"><label class="form-label">Tiny V3 Token URL</label><input class="form-control" name="tiny_v3_token_url" value="<?=e($config['tiny_v3_token_url']??'https://accounts.tiny.com.br/realms/tiny/protocol/openid-connect/token')?>" placeholder="URL para renovar token"></div>
  <div class="col-md-6"><label class="form-label">Client ID</label><input class="form-control" name="tiny_v3_client_id" value="<?=e($config['tiny_v3_client_id']??'')?>"></div>
  <div class="col-md-6"><label class="form-label">Client Secret</label><input class="form-control" name="tiny_v3_client_secret" value="<?=e(Secrets::mask($config['tiny_v3_client_secret']??''))?>"></div>
  <div class="col-md-6"><label class="form-label">Redirect URI</label><input class="form-control" name="tiny_v3_redirect_uri" value="<?=e($config['tiny_v3_redirect_uri']??'')?>"></div>
  <div class="col-md-6"><label class="form-label">Escopos OAuth (opcional)</label><input class="form-control" name="tiny_v3_scopes" value="<?=e($config['tiny_v3_scopes']??'')?>" placeholder="Deixe vazio — recomendado para Tiny V3"><div class="form-text">Não informe <code>produtos estoque pedidos notas-fiscais</code>. No Tiny V3, as permissões são marcadas no aplicativo; o Hub só envia <code>scope</code> se este campo estiver preenchido.</div></div>
  <div class="col-12"><a class="btn btn-outline-info" href="index.php?page=tiny-v3-ficha"><i class="bi bi-file-earmark-code"></i> Abrir ficha técnica Tiny V3</a></div>
</div></section></div>

<div class="col-12"><section id="sec-webhooks" class="integration-card security-card mb-3"><div class="integration-card-title"><div><h2><i class="bi bi-shield-lock"></i> Segurança dos Webhooks Tiny/Olist</h2><p>Use esta camada para evitar eventos falsos, payload grande, CNPJ não autorizado ou excesso de reenvios.</p></div><span class="integration-card-badge">Entrada protegida</span></div><div class="row g-3">
  <div class="col-md-6"><label class="form-label">Tiny Webhook Secret</label><input class="form-control" name="tiny_webhook_secret" value="<?=e(Secrets::mask($config['tiny_webhook_secret']??''))?>"><div class="form-text">Quando ativado, o Tiny/integrador deve enviar <code>X-TINY-HUB-SECRET</code> ou <code>X-HUB-SECRET</code>.</div></div>
  <div class="col-md-6"><label class="form-label">CNPJs autorizados</label><input class="form-control" name="tiny_webhook_cnpj_autorizados" value="<?=e($config['tiny_webhook_cnpj_autorizados']??'')?>" placeholder="00000000000000,11111111111111"><div class="form-text">Opcional. Separe por vírgula. Deixe vazio para aceitar qualquer CNPJ em homologação.</div></div>
  <div class="col-md-4"><label class="form-label">Rate limit/minuto</label><input type="number" min="1" class="form-control" name="tiny_webhook_rate_limit" value="<?=e($config['tiny_webhook_rate_limit']??60)?>"></div>
  <div class="col-md-4"><label class="form-label">Tamanho máximo payload bytes</label><input type="number" min="1024" class="form-control" name="tiny_webhook_max_bytes" value="<?=e($config['tiny_webhook_max_bytes']??1048576)?>"></div>
  <div class="col-md-4 d-flex align-items-end"><div class="form-check form-switch"><input class="form-check-input" type="checkbox" name="tiny_webhook_exigir_secret" value="1" <?=!empty($config['tiny_webhook_exigir_secret'])?'checked':''?>><label class="form-check-label"><b>Exigir secret nos webhooks Tiny</b></label></div></div>
</div></section></div>

<div class="col-12"><section id="sec-vsm" class="integration-card vsm-card mb-3"><div class="integration-card-title"><div><h2><i class="bi bi-diagram-3"></i> VSM / Conecta Venda</h2><p>Para o HUB Tiny ⇄ VSM, use <b>pedidos-integradora</b> como API principal. O Swagger <b>pedidos-loja</b> fica opcional e só deve ser usado se a VSM solicitar endpoint específico de loja.</p></div><span class="integration-card-badge">VSM principal</span></div><?php $vsmSt = $config['vsm_status_operacional'] ?? ['modo'=>'Homologação','detalhe'=>'URL de homologação configurada','acao'=>'Produção bloqueada até liberação manual']; ?><div class="alert alert-warning border small"><b>Status operacional VSM:</b> <?=e($vsmSt['modo'])?> — <?=e($vsmSt['detalhe'])?>.<br><span><?=e($vsmSt['acao'])?></span></div><div class="row g-3">
  <div class="col-md-6"><label class="form-label">VSM URL Base</label><input class="form-control" name="vsm_url" value="<?=e($config['vsm_url']??'https://conectavenda.homolog.vsm.com.br')?>"><div class="form-text">Pré-preenchido para homologação. Troque para produção somente após a VSM liberar URL oficial.</div></div>
  <div class="col-md-6"><label class="form-label">VSM Token</label><input class="form-control" name="vsm_token" value="<?=e(Secrets::mask($config['vsm_token']??''))?>"></div>
  <div class="col-md-4"><label class="form-label">Ambiente VSM</label><select class="form-select" name="vsm_ambiente"><option value="homologacao" <?=($config['vsm_ambiente']??'homologacao')!=='producao'?'selected':''?>>Homologação — padrão seguro</option><option value="producao" <?=($config['vsm_ambiente']??'')==='producao'?'selected':''?>>Produção — somente com liberação VSM</option></select><div class="form-text">Mesmo com URL configurada, o padrão inicial permanece homologação.</div></div>
  <div class="col-md-4"><label class="form-label">Liberação produção VSM</label><div class="form-check form-switch mt-2"><input class="form-check-input" type="checkbox" name="vsm_producao_liberada" value="1" <?=!empty($config['vsm_producao_liberada'])?'checked':''?>><label class="form-check-label"><b>VSM liberou produção oficialmente</b></label></div><div class="form-text">Não marque em homologação. Exige URL de produção e token configurado.</div></div>
  <div class="col-md-4"><label class="form-label">Último teste VSM</label><input class="form-control" readonly value="<?=!empty($config['vsm_ultimo_teste_ok'])?'OK':'Pendente/erro'?><?=!empty($config['vsm_ultimo_teste_em'])?' em '.e($config['vsm_ultimo_teste_em']):''?>"><div class="form-text">Use o botão Testar VSM antes de liberar produção.</div></div>
  <div class="col-md-4"><label class="form-label">API principal VSM</label><select class="form-select" name="vsm_api_principal"><option value="pedidos-integradora" <?=($config['vsm_api_principal']??'pedidos-integradora')==='pedidos-integradora'?'selected':''?>>pedidos-integradora</option><option value="pedidos-loja" <?=($config['vsm_api_principal']??'')==='pedidos-loja'?'selected':''?>>pedidos-loja</option></select><div class="form-text">Recomendado para o HUB: pedidos-integradora.</div></div>
  <div class="col-md-4"><label class="form-label">API loja opcional</label><select class="form-select" name="vsm_api_loja"><option value="desativado" <?=($config['vsm_api_loja']??'desativado')==='desativado'?'selected':''?>>Desativado</option><option value="pedidos-loja" <?=($config['vsm_api_loja']??'')==='pedidos-loja'?'selected':''?>>pedidos-loja</option></select><div class="form-text">Ative apenas se a VSM confirmar necessidade.</div></div>
  <div class="col-md-4"><label class="form-label">WAF em APIs VSM</label><input class="form-control" value="Desativado para payloads Tiny/VSM" readonly><input type="hidden" name="vsm_waf_agressivo" value="0"><div class="form-text">Proteção por token/HMAC, anti-replay, rate limit, auditoria e circuit breaker.</div></div>
  <div class="col-md-6"><label class="form-label">Swagger pedidos-integradora</label><input class="form-control" name="vsm_swagger_integradora" value="<?=e($config['vsm_swagger_integradora']??'https://conectavenda.homolog.vsm.com.br/swagger-ui/index.html?urls.primaryName=pedidos-integradora')?>"></div>
  <div class="col-md-6"><label class="form-label">Swagger pedidos-loja</label><input class="form-control" name="vsm_swagger_loja" value="<?=e($config['vsm_swagger_loja']??'https://conectavenda.homolog.vsm.com.br/swagger-ui/index.html?urls.primaryName=pedidos-loja')?>"></div>
  <div class="col-md-4"><label class="form-label">Endpoint VSM baixa de estoque</label><input class="form-control" name="vsm_endpoint_baixa_estoque" value="<?=e($config['vsm_endpoint_baixa_estoque']??'/api/estoque/baixa')?>"><div class="form-text">Use somente se Tiny precisar enviar baixa para VSM.</div></div>
  <div class="col-md-4"><label class="form-label">Endpoint VSM produto novo</label><input class="form-control" name="vsm_endpoint_produto_novo" value="<?=e($config['vsm_endpoint_produto_novo']??'/api/produtos')?>"><div class="form-text">Referência para produto novo vindo da VSM.</div></div>
  <div class="col-md-4"><label class="form-label">Endpoint VSM consulta de estoque</label><input class="form-control" name="vsm_endpoint_consulta_estoque" value="<?=e($config['vsm_endpoint_consulta_estoque']??'/api/estoque/consulta')?>"><div class="form-text">Usado para reconciliação real Tiny x VSM.</div></div>
  <div class="col-12"><label class="form-label">Observação operacional VSM</label><textarea class="form-control" name="vsm_api_observacao" rows="2" placeholder="Ex.: API principal validada pela VSM em homologação, pedido via pedidos-integradora."><?=e($config['vsm_api_observacao']??'')?></textarea></div>
</div></section></div>

<div class="col-12"><section id="sec-fila" class="integration-card queue-card mb-3"><div class="integration-card-title"><div><h2><i class="bi bi-hourglass-split"></i> Fila, lease e heartbeat</h2><p>O lease define por quanto tempo um worker mantém a posse do item. Tempos diferentes evitam duplicidade em operações longas sem atrasar a recuperação de tarefas curtas.</p></div><span class="integration-card-badge">Concorrência segura</span></div><div class="row g-3">
  <div class="col-md-4"><label class="form-label">Timeout sem heartbeat (minutos)</label><input type="number" min="10" max="240" class="form-control" name="queue_processing_timeout_minutes" value="<?=e($config['queue_processing_timeout_minutes']??30)?>"><div class="form-text">Libera item abandonado quando o worker não renova o heartbeat.</div></div>
  <div class="col-md-4"><label class="form-label">Lease padrão (minutos)</label><input type="number" min="1" max="120" class="form-control" name="queue_lease_minutes" value="<?=e($config['queue_lease_minutes']??5)?>"><div class="form-text">Fallback para tipos não listados abaixo.</div></div>
  <div class="col-md-4"><label class="form-label">Pedido Tiny → VSM</label><input type="number" min="1" max="120" class="form-control" name="queue_lease_pedido_tiny_para_vsm" value="<?=e($config['queue_lease_map']['pedido_tiny_para_vsm']??5)?>"></div>
  <div class="col-md-4"><label class="form-label">Baixa de estoque → VSM</label><input type="number" min="1" max="120" class="form-control" name="queue_lease_baixa_estoque_vsm" value="<?=e($config['queue_lease_map']['baixa_estoque_vsm']??5)?>"></div>
  <div class="col-md-4"><label class="form-label">Produto novo VSM → Tiny</label><input type="number" min="1" max="120" class="form-control" name="queue_lease_produto_vsm_para_tiny" value="<?=e($config['queue_lease_map']['produto_vsm_para_tiny']??10)?>"></div>
  <div class="col-md-4"><label class="form-label">Atualização produto VSM → Tiny</label><input type="number" min="1" max="120" class="form-control" name="queue_lease_produto_vsm_atualizar_tiny" value="<?=e($config['queue_lease_map']['produto_vsm_atualizar_tiny']??10)?>"></div>
  <div class="col-md-4"><label class="form-label">Estoque VSM → Tiny</label><input type="number" min="1" max="120" class="form-control" name="queue_lease_produto_vsm_estoque_para_tiny" value="<?=e($config['queue_lease_map']['produto_vsm_estoque_para_tiny']??5)?>"></div>
  <div class="col-md-4"><label class="form-label">Status VSM → Tiny</label><input type="number" min="1" max="120" class="form-control" name="queue_lease_produto_vsm_status_para_tiny" value="<?=e($config['queue_lease_map']['produto_vsm_status_para_tiny']??5)?>"></div>
</div></section></div>

<div class="col-12"><section class="integration-card mb-3"><div class="integration-card-title"><div><h2><i class="bi bi-sliders"></i> Fluxos ativos</h2><p>A fonte oficial para ligar/desligar fluxos é a Orquestração. Esta tela de Configurações mantém apenas dados de conexão.</p></div><span class="integration-card-badge">Governança</span></div><div class="alert alert-light border small mb-0"><b>Modelo recomendado:</b> pedido gerado no Tiny → HUB valida → VSM; NF-e autorizada e estoque oficial voltam da VSM para Tiny. <a href="index.php?page=orquestracao-integracoes" class="btn btn-sm btn-outline-primary ms-2">Abrir Escolher fluxos ativos</a></div>
  <input type="hidden" name="bloquear_inativo_com_estoque" value="1">
</div></section></div>
</div><div class="integration-savebar"><span>Revise Tiny e VSM antes de salvar. Tokens mascarados são preservados.</span><button class="btn btn-primary"><i class="bi bi-save"></i> Salvar configurações</button></div></div></form>

<div id="sec-testes" class="integration-test-grid"><form method="post" action="index.php?page=testar-tiny" class="panel integration-panel tiny-test">
<?=Csrf::input()?>
<div class="panel-header"><h2>Teste de conexão Tiny</h2><span class="text-muted">Executa consulta simples e registra retorno na Auditoria</span></div>
<div class="p-4 row g-3 align-items-end"><div class="col-md-6"><label class="form-label">SKU para teste</label><input class="form-control" name="sku" placeholder="Digite um SKU/código ou use TESTE"></div><div class="col-md-3"><button class="btn btn-outline-primary w-100">Testar Tiny</button></div></div>
</form>


<form method="post" action="index.php?page=testar-vsm" class="panel integration-panel vsm-test">
<?=Csrf::input()?>
<div class="panel-header"><h2>Teste de conexão VSM</h2><span class="text-muted">Testa DNS, HTTPS, token e endpoint informado</span></div>
<div class="p-4 row g-3 align-items-end">
  <div class="col-md-7"><label class="form-label">Endpoint VSM para teste</label><input class="form-control" name="vsm_endpoint" value="/swagger-ui/index.html"><div class="form-text">Use um endpoint público do Swagger ou um endpoint de consulta autorizado pela VSM.</div></div>
  <div class="col-md-3"><button class="btn btn-outline-success w-100"><i class="bi bi-activity"></i> Testar VSM</button></div>
</div>
</form></div>

<?php if(App::isLocal()): ?>
<div class="panel integration-panel mt-3"><div class="panel-header"><h2>Manutenção técnica</h2><span class="text-muted">Histórico de updates antigos removido</span></div><div class="p-4"><div class="alert alert-warning small"><b>Atualização <?=e(class_exists('SystemVersionService')?SystemVersionService::label():'V104.16')?>:</b> updates legados foram bloqueados na interface. Use <code>database/install_final_current.sql</code> em instalações novas, <code>database/repair_current.sql</code> para reparo e a tela Validar Banco para bases existentes.</div><div class="d-flex gap-2 flex-wrap"><a class="btn btn-outline-primary" href="index.php?page=validar-banco"><i class="bi bi-check2-circle"></i> Validar Banco de Dados</a><a class="btn btn-outline-success" href="index.php?page=relatorio-homologacao"><i class="bi bi-file-earmark-text"></i> Relatório de Homologação</a><a class="btn btn-outline-info" href="index.php?page=tiny-ambientes"><i class="bi bi-diagram-3"></i> Tiny V2/V3 separado</a></div></div></div>
<?php endif; ?>
</div>
<?php require __DIR__.'/layout_bottom.php'; ?>
