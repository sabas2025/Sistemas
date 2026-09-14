<?php require __DIR__.'/layout_top.php'; ?>
<?php
  $cfg = $rules ?? IntegrationOrchestratorService::all();
  $ordemAtual = explode(',', $cfg['sync_ordem_envio'] ?? '');
  $catalogoOrdem = IntegrationOrchestratorService::catalogoOrdem();
  $gruposFluxos = IntegrationOrchestratorService::gruposFluxos($cfg);
  $totalFluxos = count(IntegrationOrchestratorService::catalogoFluxos());
  $ativos = 0; foreach(IntegrationOrchestratorService::fluxos($cfg) as $f){ if(!empty($f['ativo'])) $ativos++; }
?>
<?php if(isset($_GET['salvo'])): ?><div class="alert alert-success"><b>Orquestração salva com sucesso.</b> Ativos: <?=e($_GET['ativos'] ?? $ativos)?>/<?=e($totalFluxos)?>. <?php if(!empty($_GET['trace'])): ?>Trace ID: <code><?=e($_GET['trace'])?></code><?php endif; ?></div><?php endif; ?>
<?php if(isset($_GET['erro'])): ?><div class="alert alert-danger">Falha ao salvar orquestração. Veja Auditoria/Logs.</div><?php endif; ?>
<?php if(isset($_GET['teste'])): ?><div class="alert alert-<?=($_GET['teste']??'')==='ok'?'success':'warning'?>">Teste de fluxo: <?=e($_GET['teste'])?> <?=!empty($_GET['fluxo'])?' - '.e($_GET['fluxo']):''?></div><?php endif; ?>
<div class="panel">
  <div class="panel-header">
    <h2>Orquestração Tiny ⇄ VSM</h2>
    <span class="text-muted">Escolha exatamente quais fluxos ficam ativos</span>
  </div>
  <div class="p-4">
    <div class="alert alert-info">
      <b>Fonte oficial dos fluxos:</b> esta tela é a fonte principal para ativar/desativar integrações. A tela antiga de Configurações mostra apenas um resumo e deve apontar para cá.
      <br><b>Modelo recomendado para seu HUB:</b> pedido nasce no Tiny, o HUB valida e envia para VSM; NF-e autorizada e estoque oficial voltam da VSM para Tiny.
      <br><b>Resumo:</b> <?=e($ativos)?> de <?=e($totalFluxos)?> fluxos específicos ativos.
    </div>

    <form method="post" action="index.php?page=salvar-orquestracao-integracoes" id="formFluxosAtivos">
      <?=Csrf::input()?>

      <div class="card-soft border border-primary-subtle mb-3">
        <div class="d-flex justify-content-between flex-wrap gap-2 align-items-center">
          <div>
            <h5 class="mb-1"><i class="bi bi-sliders"></i> Seleção rápida de fluxo</h5>
            <div class="small text-muted">Marque somente o que você realmente quer operar. Fluxo desligado não envia, não cria e não atualiza.</div>
          </div>
          <div class="d-flex gap-2 flex-wrap">
            <button type="button" class="btn btn-sm btn-outline-success" id="btnAtivarTodosFluxos">Ativar todos</button>
            <button type="button" class="btn btn-sm btn-outline-secondary" id="btnDesativarTodosFluxos">Desativar todos</button>
            <button type="button" class="btn btn-sm btn-outline-primary" id="btnModeloSeguroFluxos">Modelo seguro recomendado</button>
          </div>
        </div>
      </div>

      <div class="row g-3">
        <?php foreach($gruposFluxos as $grupo=>$lista): ?>
          <div class="col-lg-6">
            <div class="card-soft h-100">
              <div class="d-flex justify-content-between align-items-center mb-2">
                <h5 class="mb-0"><i class="bi bi-diagram-3"></i> <?=e($grupo)?></h5>
                <span class="badge bg-light text-dark border"><?=count($lista)?> fluxos</span>
              </div>
              <?php foreach($lista as $f): $name='fluxo_'.$f['id']; $marcado=!empty($cfg[$name]); $efetivo=!empty($f['ativo']); ?>
                <div class="border rounded p-3 mb-2 bg-white">
                  <div class="d-flex justify-content-between gap-2 align-items-start">
                    <div class="form-check form-switch">
                      <input class="form-check-input fluxo-check" data-fluxo="<?=e($f['id'])?>" type="checkbox" name="<?=$name?>" id="<?=$name?>" value="1" <?=$marcado?'checked':''?>>
                      <label class="form-check-label fw-semibold" for="<?=$name?>"><?=e($f['titulo'])?></label>
                    </div>
                    <span class="badge <?=$efetivo?'bg-success':($marcado?'bg-warning text-dark':'bg-secondary')?>"><?=$efetivo?'Ativo':($marcado?'Marcado':'Desativado')?></span>
                  </div>
                  <div class="small text-muted mt-1"><b>Direção:</b> <?=e($f['direcao'])?> | <b>Tipo:</b> <?=e($f['tipo'])?></div>
                  <div class="small mt-1"><?=e($f['descricao'])?></div>
                  <div class="small text-warning-emphasis mt-1"><b>Controle:</b> <?=e($f['risco'])?></div>
                  <button type="submit" form="formTesteFluxo" name="fluxo" value="<?=e($f['id'])?>" class="btn btn-sm btn-outline-primary mt-2"><i class="bi bi-play-circle"></i> Testar regra deste fluxo</button>
                </div>
              <?php endforeach; ?>
            </div>
          </div>
        <?php endforeach; ?>

        <div class="col-lg-6">
          <div class="card-soft h-100 border border-dark-subtle">
            <h5><i class="bi bi-shield-lock"></i> Travamentos obrigatórios</h5>
            <?php foreach(['sync_exigir_mapeamento_sku','sync_exigir_nfe_autorizada','sync_bloquear_produto_novo_vsm','sync_tiny_bloquear_produto_novo_vsm','sync_permitir_produto_novo_vsm_manual','sync_aprovacao_manual_produto_novo_vsm','sync_exigir_categoria_mapeada_vsm','sync_permitir_atualizar_produto_existente_vsm','sync_permitir_estoque_vsm_tiny','sync_permitir_status_vsm_tiny'] as $k): ?>
              <div class="form-check form-switch mb-2">
                <input class="form-check-input" type="checkbox" name="<?=$k?>" id="<?=$k?>" value="1" <?=!empty($cfg[$k])?'checked':''?>>
                <label class="form-check-label" for="<?=$k?>"><?=e(IntegrationOrchestratorService::label($k))?></label>
              </div>
            <?php endforeach; ?>
            <div class="alert alert-warning small mb-0 mt-3">
              <b>Recomendado:</b> manter SKU mapeado, categoria mapeada, aprovação manual, NF-e autorizada e bloqueio de produto novo sempre ativos.
            </div>
          </div>
        </div>

        <div class="col-lg-6">
          <div class="card-soft h-100 border border-info-subtle">
            <h5><i class="bi bi-sort-numeric-down"></i> Ordem de envio</h5>
            <p class="text-muted small">A ordem abaixo define a prioridade operacional. Reordene manualmente as linhas ou use os botões de modelo.</p>
            <textarea class="form-control font-monospace" name="sync_ordem_envio" id="sync_ordem_envio" rows="10"><?=e(implode("\n", $ordemAtual))?></textarea>
            <div class="small text-muted mt-2">Fluxos aceitos:</div>
            <div class="small">
              <?php foreach($catalogoOrdem as $id=>$label): ?>
                <code><?=e($id)?></code> - <?=e($label)?><br>
              <?php endforeach; ?>
            </div>
          </div>
        </div>

        <div class="col-12">
          <div class="card-soft border border-secondary-subtle">
            <h5><i class="bi bi-table"></i> Matriz analítica dos fluxos ativos/inativos</h5>
            <div class="table-responsive">
              <table class="table table-sm align-middle">
                <thead><tr><th>Fluxo</th><th>Origem</th><th>Destino</th><th>Tipo</th><th>Status efetivo</th><th>Regra</th><th>Controle</th></tr></thead>
                <tbody>
                <?php foreach(IntegrationOrchestratorService::fluxos($cfg) as $f): $on=!empty($f['ativo']); ?>
                  <tr>
                    <td><?=e($f['titulo'])?></td>
                    <td><?=e($f['grupo'])?></td>
                    <td><?=e($f['destino'])?></td>
                    <td><span class="badge bg-light text-dark border"><?=e($f['tipo'])?></span></td>
                    <td><span class="badge <?=$on?'bg-success':'bg-secondary'?>"><?=$on?'Ativo':'Desativado'?></span></td>
                    <td><code><?=e($f['regra'])?></code></td>
                    <td class="small"><?=e($f['risco'])?></td>
                  </tr>
                <?php endforeach; ?>
                </tbody>
              </table>
            </div>
          </div>
        </div>
      </div>

      <div class="d-flex gap-2 flex-wrap mt-4">
        <button class="btn btn-primary"><i class="bi bi-save"></i> Salvar fluxos ativos</button>
        <a class="btn btn-outline-primary" href="index.php?page=regras-sincronizacao">Regras de Sincronização</a>
        <a class="btn btn-outline-success" href="index.php?page=homologacao-automatica">Homologação Automática</a>
      </div>
    </form>
  </div>
</div>

<form method="post" action="index.php?page=testar-orquestracao-fluxo" id="formTesteFluxo" class="d-none">
  <?=Csrf::input()?>
</form>

<div class="panel mt-3">
  <div class="panel-header"><h2>Histórico de Orquestração</h2><span class="text-muted">Últimas alterações e testes de fluxo</span></div>
  <div class="p-4">
    <div class="table-responsive">
      <table class="table table-sm align-middle">
        <thead><tr><th>Data</th><th>Ação</th><th>Status</th><th>Mensagem</th><th>Trace ID</th></tr></thead>
        <tbody>
          <?php foreach(($historicoOrquestracao ?? []) as $h): ?>
            <tr><td><?=e($h['criado_em'] ?? '')?></td><td><?=e($h['acao'] ?? '')?></td><td><span class="badge <?=($h['status'] ?? '')==='sucesso'?'bg-success':(($h['status'] ?? '')==='erro'?'bg-danger':'bg-warning text-dark')?>"><?=e($h['status'] ?? '')?></span></td><td class="small"><?=e($h['mensagem'] ?? '')?></td><td><code><?=e($h['trace_id'] ?? '')?></code></td></tr>
          <?php endforeach; ?>
          <?php if(empty($historicoOrquestracao)): ?><tr><td colspan="5" class="text-muted">Nenhum histórico registrado ainda.</td></tr><?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>
<script nonce="<?=App::cspNonce()?>">
(function(){
  const checks = () => Array.from(document.querySelectorAll('.fluxo-check'));
  const setTodos = (valor) => checks().forEach((el) => { el.checked = !!valor; });
  const aplicarModeloSeguro = () => {
    const ativos = ['pedido_tiny_enviar_vsm','nfe_vsm_enviar_tiny','estoque_vsm_enviar_tiny','produto_status_vsm_enviar_tiny','produto_novo_vsm_bloquear','produto_novo_tiny_bloquear'];
    checks().forEach((el) => { el.checked = ativos.includes(el.dataset.fluxo); });
    const ordem = document.getElementById('sync_ordem_envio');
    if(ordem){
      ordem.value = ['pedido_tiny_enviar_vsm','nfe_vsm_enviar_tiny','estoque_vsm_enviar_tiny','produto_status_vsm_enviar_tiny','produto_novo_vsm_bloquear','produto_novo_tiny_bloquear'].join('\n');
    }
  };
  document.getElementById('btnAtivarTodosFluxos')?.addEventListener('click', () => setTodos(true));
  document.getElementById('btnDesativarTodosFluxos')?.addEventListener('click', () => setTodos(false));
  document.getElementById('btnModeloSeguroFluxos')?.addEventListener('click', aplicarModeloSeguro);
})();
</script>
<?php require __DIR__.'/layout_bottom.php'; ?>
