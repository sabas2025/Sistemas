<?php require __DIR__.'/layout_top.php'; ?>
<div class="card-soft mb-3">
  <h4>Checklist de Homologação Final</h4>
  <p class="text-muted mb-2">Use esta tela para validar o Hub com payload real Tiny/Olist, payload real VSM, endpoints reais e evidências por Trace ID antes de produção.</p><div class="d-flex gap-2 flex-wrap"><a class="btn btn-outline-success btn-sm" href="index.php?page=relatorio-homologacao"><i class="bi bi-file-earmark-text"></i> Gerar relatório de homologação</a><a class="btn btn-outline-primary btn-sm" href="index.php?page=tiny-ambientes"><i class="bi bi-diagram-3"></i> Tiny V2/V3 separado</a><a class="btn btn-outline-secondary btn-sm" href="index.php?page=validar-banco">Validar Banco</a></div>
</div>
<?php if(($cfg['tiny_versao'] ?? 'v2') === 'v3' && empty($cfg['tiny_v3_operacional'])): ?>
<div class="alert alert-warning"><b>Atenção:</b> Tiny V3 está selecionado, mas marcado como não operacional. Para homologação final, use Tiny V2 ou implemente/homologue a TinyV3Service antes.</div>
<?php endif; ?>

<div class="row g-3 mb-3">
  <div class="col-md-6"><div class="card-soft border border-success-subtle"><h5>🟢 Homologação Tiny V2</h5><p class="text-muted small mb-2">Validar token, consulta produto, pedido/NF-e e baixa Tiny → VSM. Pode ser usado como produção/fallback.</p><a class="btn btn-sm btn-outline-success" href="index.php?page=configuracoes">Configurar V2</a></div></div>
  <div class="col-md-6"><div class="card-soft border border-warning-subtle"><h5>🟡 Homologação Tiny V3</h5><p class="text-muted small mb-2">Validar OAuth, Redirect URI, token por ambiente, permissões e testes produto/estoque/pedido/fiscal antes de marcar operacional.</p><a class="btn btn-sm btn-outline-warning" href="index.php?page=tiny-v3-ficha">Ficha V3 OAuth</a></div></div>
</div>
<div class="card-soft table-responsive">
<table class="table table-hover align-middle">
<thead><tr><th>Item</th><th>Descrição</th><th>Status</th><th>Resultado / Evidência</th><th>Ação</th></tr></thead>
<tbody>
<?php foreach($itens as $i): ?>
<tr>
<td><b><?=e($i['titulo'])?></b><br><small><code><?=e($i['chave'])?></code></small></td>
<td><?=e($i['descricao'])?></td>
<td><span class="badge <?=($i['status']==='ok'?'text-bg-success':($i['status']==='falha'?'text-bg-danger':($i['status']==='nao_aplicavel'?'text-bg-secondary':'text-bg-warning')))?>"><?=e($i['status'])?></span></td>
<td><small><?=nl2br(e($i['resultado'] ?? ''))?></small><?php if(!empty($i['trace_id'])): ?><br><code><?=e($i['trace_id'])?></code><?php endif; ?></td>
<td style="min-width:280px">
<form method="post" action="index.php?page=homologacao-acao" class="d-flex gap-1 flex-column">
<?=Csrf::input()?>
<input type="hidden" name="id" value="<?=$i['id']?>">
<select class="form-select form-select-sm" name="status"><option value="pendente">Pendente</option><option value="ok">OK</option><option value="falha">Falha</option><option value="nao_aplicavel">Não aplicável</option></select>
<textarea class="form-control form-control-sm" name="resultado" rows="2" placeholder="Cole aqui o resumo do teste, Trace ID, retorno da API ou observação."></textarea>
<button class="btn btn-sm btn-primary">Salvar evidência</button>
</form>
</td>
</tr>
<?php endforeach; ?>
</tbody>
</table>
</div>
<div class="card-soft mt-3">
  <h5>Ordem recomendada</h5>
  <ol class="mb-0">
    <li>Configurar Tiny V2, VSM URL, endpoint de baixa e segredos.</li>
    <li>Executar testes Tiny e VSM.</li>
    <li>Simular produto VSM → Tiny, estoque VSM → Tiny e status VSM → Tiny.</li>
    <li>Simular baixa Tiny → VSM.</li>
    <li>Conferir Auditoria, Fila, DLQ, Webhooks Tiny e Divergências.</li>
  </ol>
</div>
<?php require __DIR__.'/layout_bottom.php'; ?>
