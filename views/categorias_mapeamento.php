<?php require __DIR__.'/layout_top.php'; ?>
<?php if(isset($_GET['salvo'])): ?><div class="alert alert-success">Mapeamento salvo.</div><?php endif; ?>
<?php if(isset($_GET['erro'])): ?><div class="alert alert-danger">Preencha nome da categoria VSM, ID Tiny e nome Tiny.</div><?php endif; ?>
<?php if(isset($erro)): ?><div class="alert alert-danger">Erro: <?=e($erro)?></div><?php endif; ?>
<div class="card-soft mb-3">
  <h4><i class="bi bi-tags"></i> Mapeamento de Categorias VSM ↔ Tiny</h4>
  <p class="text-muted mb-0">O Hub usa esta tabela para traduzir categorias recebidas da VSM antes de criar ou atualizar produto no Tiny.</p>
</div>
<div class="row g-3 mb-3">
  <div class="col-lg-5">
    <div class="card-soft h-100">
      <h5>Novo mapeamento</h5>
      <form method="post" action="index.php?page=categoria-mapeamento-salvar" class="row g-2">
        <?=Csrf::input()?>
        <div class="col-md-5"><label class="form-label">ID Categoria VSM</label><input class="form-control" name="id_categoria_vsm" placeholder="ex: 123"></div>
        <div class="col-md-7"><label class="form-label">Nome Categoria VSM</label><input class="form-control" name="nome_categoria_vsm" required></div>
        <div class="col-md-5"><label class="form-label">ID Categoria Tiny</label><input class="form-control" name="id_categoria_tiny" required></div>
        <div class="col-md-7"><label class="form-label">Nome Categoria Tiny</label><input class="form-control" name="nome_categoria_tiny" required></div>
        <div class="col-md-4"><label class="form-label">Prioridade</label><input class="form-control" name="prioridade" type="number" value="0"></div>
        <div class="col-md-8"><label class="form-label">Observação</label><input class="form-control" name="observacao"></div>
        <div class="col-12"><div class="form-check form-switch"><input class="form-check-input" type="checkbox" name="ativo" id="ativo" checked><label class="form-check-label" for="ativo">Ativo</label></div></div>
        <div class="col-12"><button class="btn btn-primary w-100">Salvar mapeamento</button></div>
      </form>
    </div>
  </div>
  <div class="col-lg-7">
    <div class="card-soft h-100">
      <h5>Boas práticas</h5>
      <ul class="mb-0">
        <li>Não crie produto no Tiny se a categoria VSM não estiver mapeada.</li>
        <li>Use prioridade maior quando houver categoria VSM genérica que possa apontar para categoria Tiny mais específica.</li>
        <li>Revise mapeamentos antes de liberar criação manual de produto novo.</li>
      </ul>
    </div>
  </div>
</div>
<form class="row g-2 mb-3"><input type="hidden" name="page" value="categorias-mapeamento"><div class="col-md-9"><input class="form-control" name="busca" value="<?=e($busca)?>" placeholder="Buscar categoria VSM ou Tiny"></div><div class="col-md-3"><button class="btn btn-outline-primary w-100">Filtrar</button></div></form>
<div class="card-soft table-responsive">
<table class="table table-hover align-middle"><thead><tr><th>ID</th><th>VSM</th><th>Tiny</th><th>Prioridade</th><th>Status</th><th>Observação</th></tr></thead><tbody>
<?php foreach(($categorias ?? []) as $c): ?><tr>
<td><?=e($c['id'])?></td>
<td><small><?=e($c['id_categoria_vsm'] ?: '-')?></small><br><b><?=e($c['nome_categoria_vsm'])?></b></td>
<td><small><?=e($c['id_categoria_tiny'])?></small><br><b><?=e($c['nome_categoria_tiny'])?></b></td>
<td><?=e($c['prioridade'])?></td>
<td><span class="badge bg-<?=((int)$c['ativo']===1?'success':'secondary')?>"><?=((int)$c['ativo']===1?'Ativo':'Inativo')?></span></td>
<td><?=e($c['observacao'] ?: '-')?></td>
</tr><?php endforeach; ?>
<?php if(empty($categorias)): ?><tr><td colspan="6" class="text-center text-muted py-4">Nenhum mapeamento cadastrado.</td></tr><?php endif; ?>
</tbody></table>
</div>
<?php require __DIR__.'/layout_bottom.php'; ?>
