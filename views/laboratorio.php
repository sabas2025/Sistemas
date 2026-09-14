<?php require __DIR__.'/layout_top.php'; ?>

<div class="alert alert-info">
  <b>Laboratório guiado Tiny ↔ VSM:</b> use estes testes antes da homologação real. Cada ação cria fila/auditoria com Trace ID, sem depender de tráfego externo.
</div>

<div class="row g-3">
  <div class="col-lg-6"><div class="cardx h-100"><h4>1. Simular baixa Tiny → VSM</h4><p class="text-muted">Cria um item de fila de baixa de estoque, como se o Tiny tivesse enviado um evento após pedido/NF-e.</p><form method="post" action="index.php?page=laboratorio-executar"><?=Csrf::input()?><input type="hidden" name="tipo" value="tiny_estoque"><div class="mb-2"><label>SKU</label><input class="form-control" name="sku" value="TESTE001"></div><div class="mb-2"><label>Quantidade</label><input class="form-control" name="quantidade" value="1"></div><div class="mb-2"><label>Referência</label><input class="form-control" name="referencia" value="TINY-LAB-<?=date('YmdHis')?>"></div><button class="btn btn-primary">Criar baixa teste</button></form></div></div>

  <div class="col-lg-6"><div class="cardx h-100"><h4>2. Simular produto VSM → Tiny</h4><p class="text-muted">Valida criação de produto no Tiny a partir de dados da VSM.</p><form method="post" action="index.php?page=laboratorio-executar"><?=Csrf::input()?><input type="hidden" name="tipo" value="vsm_produto"><div class="mb-2"><label>SKU</label><input class="form-control" name="sku" value="VSM<?=date('His')?>"></div><div class="mb-2"><label>Nome</label><input class="form-control" name="nome" value="Produto Laboratório VSM"></div><div class="row"><div class="col"><label>Preço</label><input class="form-control" name="preco_venda" value="10.00"></div><div class="col"><label>Estoque</label><input class="form-control" name="estoque" value="5"></div></div><button class="btn btn-primary mt-3">Criar produto teste</button></form></div></div>

  <div class="col-lg-6"><div class="cardx h-100"><h4>3. Simular estoque VSM → Tiny</h4><p class="text-muted">Cria evento de atualização de saldo no Tiny. Respeita regra de bloquear estoque negativo.</p><form method="post" action="index.php?page=laboratorio-executar"><?=Csrf::input()?><input type="hidden" name="tipo" value="vsm_estoque"><div class="mb-2"><label>SKU</label><input class="form-control" name="sku" value="TESTE001"></div><div class="mb-2"><label>Novo estoque</label><input class="form-control" name="estoque" value="10"></div><button class="btn btn-success">Simular estoque VSM</button></form></div></div>

  <div class="col-lg-6"><div class="cardx h-100"><h4>4. Simular status VSM → Tiny</h4><p class="text-muted">Cria evento ativo/inativo. Se o produto estiver inativo com estoque, a regra pode bloquear e mandar para pendência.</p><form method="post" action="index.php?page=laboratorio-executar"><?=Csrf::input()?><input type="hidden" name="tipo" value="vsm_status"><div class="mb-2"><label>SKU</label><input class="form-control" name="sku" value="TESTE001"></div><div class="mb-2"><label>Status</label><select class="form-select" name="status"><option value="ativo">Ativo</option><option value="inativo">Inativo</option></select></div><div class="mb-2"><label>Estoque atual informado pela VSM</label><input class="form-control" name="estoque" value="0"></div><button class="btn btn-warning">Simular status VSM</button></form></div></div>

  <div class="col-12"><div class="cardx"><h4>Self-test rápido</h4><p class="text-muted">Executa validações internas de banco, storage, filas, auditoria e serviços.</p><form method="post" action="index.php?page=laboratorio-executar"><?=Csrf::input()?><input type="hidden" name="tipo" value="selftest"><button class="btn btn-outline-dark">Executar self-test</button></form></div></div>
</div>

<?php require __DIR__.'/layout_bottom.php'; ?>
