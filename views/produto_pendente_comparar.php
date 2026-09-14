<?php require __DIR__.'/layout_top.php'; ?>
<?php if(isset($_GET['erro'])): ?><div class="alert alert-danger">Ação bloqueada: <?=e($_GET['erro'])?></div><?php endif; ?>
<div class="d-flex justify-content-between align-items-center mb-3">
  <div>
    <h3 class="mb-1"><i class="bi bi-shield-lock"></i> Comparar Produto VSM x Tiny</h3>
    <p class="text-muted mb-0">Camada extra V47: nenhuma criação no Tiny é liberada sem checklist, botões protegidos, CSRF, permissão e histórico.</p>
  </div>
  <a class="btn btn-outline-secondary" href="index.php?page=produtos-pendentes-integracao">Voltar</a>
</div>

<div class="row g-3 mb-3">
  <div class="col-lg-6">
    <div class="card-soft h-100">
      <h5>Produto recebido da VSM</h5>
      <table class="table table-sm">
        <tr><th>ID pendência</th><td><?=e($p['id'])?></td></tr>
        <tr><th>SKU VSM</th><td><code><?=e($p['sku'])?></code></td></tr>
        <tr><th>Nome</th><td><?=e($p['nome'] ?: '-')?></td></tr>
        <tr><th>EAN/GTIN</th><td><?=e($check['ean'] ?: '-')?></td></tr>
        <tr><th>NCM</th><td><?=e($check['ncm'] ?: '-')?></td></tr>
        <tr><th>Categoria VSM</th><td><?=e(($p['categoria_vsm_id'] ?: '-').' - '.($p['categoria_vsm_nome'] ?: '-'))?></td></tr>
        <tr><th>Categoria Tiny sugerida</th><td><?=e(($p['categoria_tiny_id_sugerida'] ?: '-').' - '.($p['categoria_tiny_nome_sugerida'] ?: '-'))?></td></tr>
        <tr><th>Trace ID</th><td><code><?=e($p['trace_id'])?></code></td></tr>
      </table>
    </div>
  </div>
  <div class="col-lg-6">
    <div class="card-soft h-100">
      <h5>Checklist de bloqueio</h5>
      <?php foreach($check['items'] as $key=>$item): ?>
        <div class="d-flex justify-content-between border-bottom py-2">
          <div><b><?=e($item['titulo'])?></b><br><small class="text-muted"><?=e($item['mensagem'])?></small></div>
          <span class="badge <?=($item['ok']?'bg-success':'bg-danger')?> align-self-start"><?=($item['ok']?'OK':'Pendente')?></span>
        </div>
      <?php endforeach; ?>
      <?php if($check['pode_aprovar']): ?>
        <div class="alert alert-success mt-3 mb-0">Checklist liberável. A ação agora é feita por botão protegido com confirmação visual.</div>
      <?php else: ?>
        <div class="alert alert-danger mt-3 mb-0"><b>Criação no Tiny bloqueada.</b><br><?=e(implode(' | ', $check['bloqueios']))?></div>
      <?php endif; ?>
    </div>
  </div>
</div>

<?php if(!empty($check['duplicidades'])): ?>
<div class="card-soft mb-3">
  <h5><i class="bi bi-exclamation-triangle"></i> Possíveis duplicidades encontradas</h5>
  <div class="table-responsive"><table class="table table-sm table-hover"><thead><tr><th>Fonte</th><th>ID</th><th>SKU</th><th>Produto Tiny</th><th>Descrição/Nome</th><th>Motivo</th></tr></thead><tbody>
    <?php foreach($check['duplicidades'] as $d): ?><tr>
      <td><?=e($d['fonte'] ?? '-')?></td><td><?=e($d['id'] ?? '-')?></td><td><?=e(($d['sku_tiny'] ?? $d['sku'] ?? $d['codigo'] ?? '-'))?></td><td><?=e($d['produto_tiny_id'] ?? '-')?></td><td><?=e($d['descricao'] ?? $d['nome'] ?? '-')?></td><td><?=e($d['motivo'] ?? '-')?></td>
    </tr><?php endforeach; ?>
  </tbody></table></div>
  <div class="alert alert-warning mb-0">Recomendação: use <b>Vincular a produto Tiny existente</b> em vez de criar novo.</div>
</div>
<?php endif; ?>

<?php if($p['status']==='pendente'): ?>
<div class="row g-3 mb-3">
  <div class="col-lg-4">
    <div class="card-soft h-100 border border-primary-subtle">
      <h5>Vincular a produto Tiny existente</h5>
      <form method="post" action="index.php?page=produto-pendente-integracao-acao">
        <?=Csrf::input()?><input type="hidden" name="id" value="<?=e($p['id'])?>">
        <input class="form-control mb-2" name="sku_tiny" placeholder="SKU Tiny existente" required>
        <input class="form-control mb-2" name="produto_tiny_id" placeholder="ID Tiny opcional">
        <button class="btn btn-primary w-100" name="acao" value="vincular" data-confirm="Confirmar vinculação deste produto VSM ao produto Tiny informado?">
          <i class="bi bi-link-45deg"></i> Vincular com segurança
        </button>
        <small class="text-muted d-block mt-2">A vinculação mantém o bloqueio contra criação duplicada no Tiny.</small>
      </form>
    </div>
  </div>
  <div class="col-lg-4">
    <div class="card-soft h-100 border border-success-subtle">
      <h5>Aprovar criação no Tiny</h5>
      <form method="post" action="index.php?page=produto-pendente-integracao-acao">
        <?=Csrf::input()?><input type="hidden" name="id" value="<?=e($p['id'])?>">
        <input type="hidden" name="categoria_tiny_id" value="<?=e($p['categoria_tiny_id_sugerida'])?>">
        <button class="btn btn-success w-100" name="acao" value="aprovar_criar_tiny" <?=$check['pode_aprovar']?'':'disabled'?> data-confirm="Confirmar aprovação e envio para fila de criação no Tiny? Verifique EAN/GTIN, NCM, categoria e duplicidade antes de continuar.">
          <i class="bi bi-check2-circle"></i> Aprovar e enviar para fila Tiny
        </button>
        <?php if(!$check['pode_aprovar']): ?><small class="text-danger d-block mt-2">Corrija os bloqueios antes de aprovar.</small><?php endif; ?>
      </form>
    </div>
  </div>
  <div class="col-lg-4">
    <div class="card-soft h-100 border border-danger-subtle">
      <h5>Rejeitar</h5>
      <form method="post" action="index.php?page=produto-pendente-integracao-acao">
        <?=Csrf::input()?><input type="hidden" name="id" value="<?=e($p['id'])?>">
        <textarea class="form-control mb-2" name="motivo_rejeicao" placeholder="Motivo da rejeição"></textarea>
        <button class="btn btn-outline-danger w-100" name="acao" value="rejeitar" data-confirm="Confirmar rejeição deste produto pendente?">
          <i class="bi bi-x-circle"></i> Rejeitar produto
        </button>
        <small class="text-muted d-block mt-2">A rejeição será registrada no histórico de aprovação.</small>
      </form>
    </div>
  </div>
</div>
<?php endif; ?>

<div class="row g-3">
  <div class="col-lg-6"><div class="card-soft"><h5>Payload recebido</h5><pre class="json-box"><?=e(json_encode($check['payload'], JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES))?></pre></div></div>
  <div class="col-lg-6"><div class="card-soft"><h5>Histórico de aprovação</h5>
    <?php if(empty($historico)): ?><p class="text-muted">Nenhum histórico ainda.</p><?php else: ?><div class="table-responsive"><table class="table table-sm"><thead><tr><th>Data</th><th>Ação</th><th>Resultado</th><th>Mensagem</th></tr></thead><tbody><?php foreach($historico as $h): ?><tr><td><?=e($h['criado_em'])?></td><td><?=e($h['acao'])?></td><td><?=e($h['resultado'])?></td><td><?=e($h['mensagem'])?></td></tr><?php endforeach; ?></tbody></table></div><?php endif; ?>
  </div></div>
</div>
<?php require __DIR__.'/layout_bottom.php'; ?>
