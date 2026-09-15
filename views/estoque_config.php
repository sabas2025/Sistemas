<?php require __DIR__.'/layout_top.php'; ?>
<div class="card-soft">
  <h4>⚙️ Configurações de Estoque Enterprise</h4>
  <p class="text-muted">Defina a política bidirecional. Para sua operação, a VSM é a fonte real do estoque: vendas na VSM atualizam Tiny, vendas no Tiny avisam a VSM, e o HUB evita loop por auditoria/idempotência.</p>
  <form method="post" action="index.php?page=estoque-config-salvar" class="row g-3">
    <?=Csrf::input()?>
    <div class="col-md-4"><label class="form-label">Estoque mestre</label><select name="estoque_mestre" class="form-select"><option value="vsm" <?=($config['estoque_mestre']??'vsm')==='vsm'?'selected':''?>>VSM é mestre / estoque real</option><option value="tiny" <?=($config['estoque_mestre']??'vsm')==='tiny'?'selected':''?>>Tiny é mestre</option></select></div>
    <div class="col-md-4"><label class="form-label">Estratégia</label><select name="estrategia_estoque" class="form-select"><option value="vsm_fonte_real" <?=($config['estrategia_estoque']??'vsm_fonte_real')==='vsm_fonte_real'?'selected':''?>>VSM fonte real + sincronização dois lados</option><option value="tiny_fonte_real" <?=($config['estrategia_estoque']??'')==='tiny_fonte_real'?'selected':''?>>Tiny fonte real</option><option value="manual" <?=($config['estrategia_estoque']??'')==='manual'?'selected':''?>>Manual/conferência</option></select></div>
    <div class="col-md-4"><label class="form-label">Janela anti-loop/minutos</label><input class="form-control" name="ignorar_retorno_espelhado_minutos" value="<?=e($config['ignorar_retorno_espelhado_minutos']??'10')?>"></div>
    <div class="col-md-4"><label class="form-label">Retenção de histórico/dias</label><input class="form-control" name="retencao_dias" value="<?=e($config['retencao_dias']??'90')?>"></div>
    <div class="col-md-4"><label class="form-label">Retry em minutos</label><input class="form-control" name="retry_minutos" value="<?=e($config['retry_minutos']??'5,15,30,60')?>"></div>
    <div class="col-12"><div class="alert alert-info">📦 Política recomendada: <b>VSM é estoque real</b>. Mantenha <b>VSM → Tiny</b> e <b>Tiny → VSM</b> ativos, com bloqueio anti-loop ligado.</div></div>
    <div class="col-12"><div class="form-check"><input class="form-check-input" type="checkbox" name="permitir_vsm_tiny" <?=($config['permitir_vsm_tiny']??'0')==='1'?'checked':''?>><label class="form-check-label">Permitir fluxo VSM → Tiny para estoque autoritativo</label></div></div>
    <div class="col-12"><div class="form-check"><input class="form-check-input" type="checkbox" name="permitir_tiny_vsm" <?=($config['permitir_tiny_vsm']??'1')==='1'?'checked':''?>><label class="form-check-label">Permitir fluxo Tiny → VSM para vendas/baixas do Tiny</label></div></div>
    <div class="col-12"><div class="form-check"><input class="form-check-input" type="checkbox" name="bloquear_loop_bidirecional" <?=($config['bloquear_loop_bidirecional']??'1')==='1'?'checked':''?>><label class="form-check-label">Bloquear loop bidirecional automaticamente</label></div></div>
    <div class="col-12"><div class="form-check"><input class="form-check-input" type="checkbox" name="reconciliacao_automatica" <?=($config['reconciliacao_automatica']??'1')==='1'?'checked':''?>><label class="form-check-label">Ativar reconciliação automática</label></div></div>
    <div class="col-12"><div class="form-check"><input class="form-check-input" type="checkbox" name="alertar_estoque_negativo" <?=($config['alertar_estoque_negativo']??'1')==='1'?'checked':''?>><label class="form-check-label">Alertar estoque negativo</label></div></div>
    <div class="col-12"><div class="form-check"><input class="form-check-input" type="checkbox" name="alertar_produto_sem_mapeamento" <?=($config['alertar_produto_sem_mapeamento']??'1')==='1'?'checked':''?>><label class="form-check-label">Alertar produto sem mapeamento</label></div></div>

    <div class="col-12"><hr><h5>⏱️ Consulta programada de estoque VSM</h5>
  <div class="alert alert-warning">🎯 <b>Fonte real:</b> a VSM continua sendo a verdade do estoque. O Tiny recebe atualização quando a VSM mudar; quando houver venda no Tiny, o HUB envia a baixa para a VSM e aguarda a VSM devolver o saldo real.</div>
  <p class="text-muted mb-2">O HUB consulta periodicamente o estoque real na VSM dos produtos cadastrados/mapeados. Se o saldo VSM mudar, o HUB registra auditoria e envia a atualização para o Tiny.</p></div>
    <div class="col-md-3"><label class="form-label">Modo da consulta</label><select name="consulta_vsm_modo" class="form-select"><option value="automatico" <?=($config['consulta_vsm_modo']??'automatico')==='automatico'?'selected':''?>>Automática</option><option value="manual" <?=($config['consulta_vsm_modo']??'automatico')==='manual'?'selected':''?>>Manual</option></select><small class="text-muted">Manual só executa pelo botão/worker --force</small></div>
    <div class="col-md-3"><label class="form-label">Intervalo de consulta/minutos</label><input class="form-control" type="number" min="5" max="1440" name="consulta_vsm_intervalo_minutos" value="<?=e($config['consulta_vsm_intervalo_minutos']??'60')?>"><small class="text-muted">Ex.: 60 = de hora em hora</small></div>
    <div class="col-md-3"><label class="form-label">Qtd. produtos por execução</label><input class="form-control" type="number" min="1" max="1000" name="consulta_vsm_quantidade_produtos" value="<?=e($config['consulta_vsm_quantidade_produtos']??'100')?>"><small class="text-muted">Evita sobrecarga na API</small></div>
    <div class="col-md-3"><label class="form-label">Ordem da consulta</label><select name="consulta_vsm_ordem" class="form-select"><option value="menos_recente" <?=($config['consulta_vsm_ordem']??'menos_recente')==='menos_recente'?'selected':''?>>Menos recente primeiro</option><option value="sku" <?=($config['consulta_vsm_ordem']??'')==='sku'?'selected':''?>>SKU A-Z</option></select></div>
    <div class="col-md-3"><label class="form-label">Variação mínima</label><input class="form-control" name="consulta_vsm_variacao_minima" value="<?=e($config['consulta_vsm_variacao_minima']??'0')?>"><small class="text-muted">0 = qualquer alteração</small></div>
    <div class="col-md-3"><label class="form-label">Método HTTP VSM</label><select class="form-select" name="consulta_vsm_metodo_http"><option value="POST" <?=($config['consulta_vsm_metodo_http']??'POST')==='POST'?'selected':''?>>POST</option><option value="GET" <?=($config['consulta_vsm_metodo_http']??'POST')==='GET'?'selected':''?>>GET</option></select></div>
    <div class="col-md-5"><label class="form-label">Endpoint consulta VSM</label><input class="form-control" name="consulta_vsm_endpoint" value="<?=e($config['consulta_vsm_endpoint']??'/api/estoque/consulta')?>"><small class="text-muted">Somente caminho relativo. Ex.: /api/estoque/consulta ou /estoque/{sku}</small></div>
    <div class="col-md-4"><label class="form-label">Timeout/segundos</label><input class="form-control" type="number" min="5" max="120" name="consulta_vsm_timeout_segundos" value="<?=e($config['consulta_vsm_timeout_segundos']??'30')?>"></div>
    <div class="col-md-12"><label class="form-label">Payload consulta VSM</label><textarea class="form-control" rows="3" name="consulta_vsm_payload_template"><?=e($config['consulta_vsm_payload_template']??'{"sku":"{{sku}}","trace_id":"{{trace_id}}"}')?></textarea><small class="text-muted">Use variáveis {{sku}} e {{trace_id}}. Em GET o payload vira query string.</small></div>
    <div class="col-md-4"><label class="form-label">Alerta se falhas acima de %</label><input class="form-control" type="number" min="1" max="100" name="consulta_vsm_alerta_falhas_percentual" value="<?=e($config['consulta_vsm_alerta_falhas_percentual']??'30')?>"></div>
    <div class="col-12"><div class="form-check"><input class="form-check-input" type="checkbox" name="consulta_vsm_ativa" <?=($config['consulta_vsm_ativa']??'1')==='1'?'checked':''?>><label class="form-check-label">Ativar consulta programada de estoque VSM</label></div></div>
    <div class="col-12"><div class="form-check"><input class="form-check-input" type="checkbox" name="consulta_vsm_enviar_tiny_se_alterou" <?=($config['consulta_vsm_enviar_tiny_se_alterou']??'1')==='1'?'checked':''?>><label class="form-check-label">Quando saldo VSM alterar, enfileirar atualização para Tiny</label></div></div>
    <div class="col-12"><div class="form-check"><input class="form-check-input" type="checkbox" name="consulta_vsm_apenas_produtos_ativos" <?=($config['consulta_vsm_apenas_produtos_ativos']??'1')==='1'?'checked':''?>><label class="form-check-label">Consultar somente produtos ativos/mapeados</label></div></div>

    <div class="col-12"><button class="btn btn-primary">Salvar política de estoque</button></div>
  </form>

</div>
<?php if(!empty($_SESSION['flash_ok'])): ?><div class="alert alert-success mt-3"><?=e($_SESSION['flash_ok']); unset($_SESSION['flash_ok']);?></div><?php endif; ?>
<?php if(!empty($_SESSION['flash_error'])): ?><div class="alert alert-danger mt-3"><?=e($_SESSION['flash_error']); unset($_SESSION['flash_error']);?></div><?php endif; ?>
<div class="card-soft mt-3">
  <h5>▶️ Executar consulta VSM agora</h5>
  <p class="text-muted">Use para testar a consulta programada sem aguardar o agendamento. A execução respeita limite de produtos e registra auditoria.</p>
  <form method="post" action="index.php?page=estoque-consulta-vsm-executar" class="row g-2 align-items-end">
    <?=Csrf::input()?>
    <div class="col-md-3"><label class="form-label">Limite opcional</label><input class="form-control" type="number" min="1" max="1000" name="limite" placeholder="<?=e($config['consulta_vsm_quantidade_produtos']??'100')?>"></div>
    <div class="col-md-3"><button class="btn btn-outline-primary w-100">Consultar VSM agora</button></div>
    <div class="col-md-3"><a class="btn btn-outline-secondary w-100" href="index.php?page=estoque-consultas-vsm">Ver histórico</a></div>
  </form>
  <hr>
  <h5>🧪 Testar consulta VSM por SKU</h5>
  <form method="post" action="index.php?page=estoque-consulta-vsm-testar-sku" class="row g-2 align-items-end">
    <?=Csrf::input()?>
    <div class="col-md-4"><label class="form-label">SKU</label><input class="form-control" name="sku" placeholder="Digite o SKU para testar"></div>
    <div class="col-md-3"><button class="btn btn-outline-success w-100">Testar SKU</button></div>
  </form>

</div>
<?php require __DIR__.'/layout_bottom.php'; ?>
