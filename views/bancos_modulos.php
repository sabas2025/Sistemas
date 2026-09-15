<?php require __DIR__.'/layout_top.php'; ?>
<div class="row g-3">
  <div class="col-12">
    <div class="card-soft">
      <div class="d-flex justify-content-between align-items-start flex-wrap gap-2">
        <div>
          <h4 class="mb-1"><i class="bi bi-hdd-network"></i> Bancos separados por módulo</h4>
          <p class="text-muted mb-0">V42 separa o HUB em bancos lógicos com instalador por módulo: Core, Pedidos, Produtos, Estoque, Fiscal, Fila, Observabilidade e Backups.</p>
        </div>
        <span class="badge bg-<?=($relatorio['falha']??0)?'warning':'success'?>">Conectados: <?=e($relatorio['ok']??0)?> / <?=e($relatorio['total']??0)?></span>
      </div>
    </div>
  </div>

  <div class="col-12">
    <div class="alert alert-info">
      <b>Importante:</b> a V42 ampliou o mapa de roteamento por tabela. Novos services devem usar
      <code>Database::connection('estoque')</code>, <code>Database::connection('pedidos')</code> ou <code>Database::forTable('fila_integracao')</code>.
    </div>
  </div>


  <?php if(!empty($_SESSION['multidb_v42_resultado'])): $res=$_SESSION['multidb_v42_resultado']; unset($_SESSION['multidb_v42_resultado']); ?>
  <div class="col-12">
    <div class="alert alert-secondary">
      <b>Resultado da instalação V42:</b>
      <ul class="mb-0"><?php foreach($res as $mod=>$r): ?><li><b><?=e($mod)?>:</b> <?=!empty($r['ok'])?'OK':'Falha'?> — <?=e($r['mensagem'] ?? '')?></li><?php endforeach; ?></ul>
    </div>
  </div>
  <?php endif; ?>

  <div class="col-12">
    <div class="card-soft border border-primary-subtle">
      <h5><i class="bi bi-database-gear"></i> Instalador modular V42</h5>
      <p class="text-muted small">Use após criar os bancos com <code>database/install_final_v104_12.sql</code>. O botão abaixo executa o arquivo SQL correspondente em cada conexão configurada.</p>
      <form method="post" action="index.php?page=bancos-modulos-instalar" class="d-flex gap-2 flex-wrap align-items-end">
        <?=Csrf::input()?>
        <div>
          <label class="form-label small">Módulo</label>
          <select class="form-select" name="modulo">
            <option value="todos">Todos os módulos</option>
            <?php foreach(array_keys($relatorio['modulos'] ?? []) as $mod): ?><option value="<?=e($mod)?>"><?=e($mod)?></option><?php endforeach; ?>
          </select>
        </div>
        <button class="btn btn-primary"><i class="bi bi-play-circle"></i> Instalar/atualizar estrutura</button>
      </form>
    </div>
  </div>

  <?php foreach(($relatorio['modulos'] ?? []) as $m): ?>
    <div class="col-lg-6">
      <div class="card-soft h-100">
        <div class="d-flex justify-content-between align-items-start">
          <div>
            <h5 class="mb-1"><?=e($m['titulo'])?></h5>
            <div class="text-muted small">Módulo: <code><?=e($m['modulo'])?></code></div>
          </div>
          <span class="badge bg-<?=$m['status']['ok']?'success':'danger'?>"><?=$m['status']['ok']?'OK':'FALHA'?></span>
        </div>
        <hr>
        <div class="small"><b>Host:</b> <?=e($m['host'])?></div>
        <div class="small"><b>Banco:</b> <code><?=e($m['banco'])?></code></div>
        <div class="small"><b>Usuário:</b> <?=e($m['user'])?></div>
        <div class="small mt-2"><b>Status:</b> <?=e($m['status']['mensagem'])?> — <?=e($m['status']['detalhe'])?></div>
        <div class="mt-3">
          <b class="small">Tabelas principais:</b>
          <div class="mt-1 d-flex flex-wrap gap-1">
            <?php foreach($m['tabelas'] as $t): ?><span class="badge text-bg-light border"><?=e($t)?></span><?php endforeach; ?>
          </div>
        </div>
      </div>
    </div>
  <?php endforeach; ?>

  <div class="col-12">
    <div class="card-soft">
      <h5>Como instalar os bancos separados</h5>
      <ol class="mb-0">
        <li>Abra o phpMyAdmin ou MySQL.</li>
        <li>Execute <code>database/install_final_v104_12.sql</code> para criar os bancos.</li><li>Depois use o instalador modular acima ou rode os arquivos em <code>database/modules/*.sql</code>.</li>
        <li>Confirme os nomes em <code>config/config.php</code> dentro de <code>db_modules</code>.</li>
        <li>Para produção, use usuários MySQL separados por módulo e permissões mínimas.</li>
      </ol>
    </div>
  </div>
</div>
<?php require __DIR__.'/layout_bottom.php'; ?>
