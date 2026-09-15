<?php require __DIR__.'/layout_top.php'; ?>
<?php if(isset($_GET['salvo'])): ?><div class="alert alert-success">Regras de sincronização salvas com sucesso.</div><?php endif; ?>
<?php if(isset($_GET['erro'])): ?><div class="alert alert-danger">Falha ao salvar regras. Veja a Auditoria para causa provável e ação recomendada.</div><?php endif; ?>
<div class="panel">
  <div class="panel-header">
    <h2>Regras de Sincronização Tiny ⇄ VSM</h2>
    <span class="text-muted">Controle analítico do que pode criar, atualizar e baixar estoque no Tiny</span>
  </div>
  <div class="p-4">
    <div class="alert alert-info">
      <b>Objetivo:</b> esta tela confirma se o Hub possui campos e regras para <b>criação de produto no Tiny</b>, <b>atualização de estoque no Tiny</b> e <b>ativo/inativo</b> vindo da VSM. Todas as ações geram auditoria por Trace ID.
      <div class="mt-2 small">Fluxo correto: <code>VSM → Hub → Tiny</code> para produto/estoque/status e <code>Tiny → Hub → VSM</code> para baixa de estoque gerada por pedido/NF-e no Tiny.</div>
    </div>

    <form method="post" action="index.php?page=salvar-regras-sincronizacao">
      <?=Csrf::input()?>
      <div class="row g-3">
        <div class="col-lg-6">
          <div class="card-soft h-100 border border-primary-subtle">
            <h5><i class="bi bi-box-seam"></i> Produto VSM → Tiny</h5>
            <p class="text-muted small">Define se o Hub pode criar ou alterar cadastro de produtos no Tiny quando receber evento da VSM.</p>
            <?php $produtoRules=['sync_criar_produto_tiny','sync_atualizar_produto_tiny','sync_atualizar_preco_tiny','sync_atualizar_descricao_tiny','sync_atualizar_categoria_tiny','sync_atualizar_marca_tiny','sync_criar_produto_se_nao_existir']; foreach($produtoRules as $k): ?>
              <div class="form-check form-switch mb-2">
                <input class="form-check-input" type="checkbox" name="<?=$k?>" id="<?=$k?>" value="1" <?=!empty($rules[$k])?'checked':''?>>
                <label class="form-check-label" for="<?=$k?>"><?=e(SyncRulesService::explain($k))?></label>
              </div>
            <?php endforeach; ?>
            <div class="alert alert-warning small mt-3 mb-0"><b>Recomendação:</b> deixe <b>criar produto automaticamente se SKU não existir</b> desativado até validar payload real da VSM com EAN, NCM, unidade, preço e descrição.</div>
          </div>
        </div>

        <div class="col-lg-6">
          <div class="card-soft h-100 border border-success-subtle">
            <h5><i class="bi bi-archive"></i> Estoque e status VSM → Tiny</h5>
            <p class="text-muted small">Controla alteração de saldo e situação do produto no Tiny.</p>
            <?php $estoqueRules=['sync_atualizar_estoque_tiny','sync_bloquear_estoque_negativo','sync_atualizar_status_tiny']; foreach($estoqueRules as $k): ?>
              <div class="form-check form-switch mb-2">
                <input class="form-check-input" type="checkbox" name="<?=$k?>" id="<?=$k?>" value="1" <?=!empty($rules[$k])?'checked':''?>>
                <label class="form-check-label" for="<?=$k?>"><?=e(SyncRulesService::explain($k))?></label>
              </div>
            <?php endforeach; ?>
            <div class="alert alert-danger small mt-3 mb-0"><b>Segurança:</b> se estoque negativo estiver bloqueado, o evento vai para auditoria/pendência em vez de alterar o Tiny silenciosamente.</div>
          </div>
        </div>

        <div class="col-12">
          <div class="card-soft border border-info-subtle">
            <h5><i class="bi bi-list-check"></i> Conferência técnica dos campos no Hub</h5>
            <div class="table-responsive">
              <table class="table align-middle">
                <thead><tr><th>Recurso</th><th>Origem</th><th>Destino</th><th>Status</th><th>Onde testar</th></tr></thead>
                <tbody>
                  <tr><td>Criar produto no Tiny</td><td>VSM</td><td>Tiny</td><td><span class="badge bg-success">Campo/regra disponível</span></td><td><a href="index.php?page=produtos-vsm">Produtos VSM</a> / <a href="index.php?page=laboratorio">Laboratório</a></td></tr>
                  <tr><td>Atualizar estoque no Tiny</td><td>VSM</td><td>Tiny</td><td><span class="badge bg-success">Campo/regra disponível</span></td><td><a href="index.php?page=produtos-vsm">Produtos VSM</a> / Simular estoque</td></tr>
                  <tr><td>Ativar/Inativar produto no Tiny</td><td>VSM</td><td>Tiny</td><td><span class="badge bg-success">Campo/regra disponível</span></td><td><a href="index.php?page=produtos-pendencias">Pendências</a></td></tr>
                  <tr><td>Baixa de estoque na VSM</td><td>Tiny</td><td>VSM</td><td><span class="badge bg-primary">Fluxo separado</span></td><td><a href="index.php?page=baixas-estoque">Baixas de Estoque</a></td></tr>
                  <tr><td>Pré-validação SKU no Tiny</td><td>Hub</td><td>Tiny</td><td><span class="badge bg-success">Ativo</span></td><td><a href="index.php?page=tiny-v3-ficha">Ficha Tiny V3</a></td></tr>
                </tbody>
              </table>
            </div>
          </div>
        </div>
      </div>
      <div class="d-flex gap-2 flex-wrap mt-4">
        <button class="btn btn-primary"><i class="bi bi-save"></i> Salvar regras</button>
        <a class="btn btn-outline-primary" href="index.php?page=produtos-vsm"><i class="bi bi-box-arrow-in-down"></i> Ver Produtos VSM</a>
        <a class="btn btn-outline-success" href="index.php?page=laboratorio"><i class="bi bi-flask"></i> Laboratório</a>
        <a class="btn btn-outline-secondary" href="index.php?page=validar-banco"><i class="bi bi-database-check"></i> Validar Banco</a>
      </div>
    </form>
  </div>
</div>

<div class="panel mt-3">
  <div class="panel-header"><h2>Atualização técnica V31</h2><span class="text-muted">Use se seu banco foi criado em versão anterior</span></div>
  <div class="p-4">
    <?php if(App::isLocal()): ?><form method="post" action="index.php?page=atualizar-v31-regras-sincronizacao">
      <?=Csrf::input()?>
      <button class="btn btn-warning"><i class="bi bi-database-gear"></i> Aplicar estrutura V31 no banco</button>
    </form><?php endif; ?>
  </div>
</div>
<?php require __DIR__.'/layout_bottom.php'; ?>
