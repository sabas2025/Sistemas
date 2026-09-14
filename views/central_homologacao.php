<?php require __DIR__.'/layout_top.php'; ?>
<?php
function homBadge(array $resumo): string {
  $p = (int)($resumo['progresso'] ?? 0);
  if ($p >= 100) return '<span class="badge text-bg-success">🟢 Aprovado</span>';
  if ($p >= 60) return '<span class="badge text-bg-warning">🟡 Homologação</span>';
  return '<span class="badge text-bg-secondary">⚪ Pendente</span>';
}
function homCheck(array $checks, string $key): string {
  foreach ($checks as $c) if (($c['key'] ?? '') === $key) return (($c['status'] ?? '') === 'ok') ? '✔' : ((($c['status'] ?? '') === 'falha') ? '✖' : '○');
  return '○';
}
$v2Checks = $v2['checks'] ?? [];
$v3Checks = $v3['checks'] ?? [];
$v2Ult = $v2['ultimo'] ?? null;
$v3Ult = $v3['ultimo'] ?? null;
$v2Json = $v2Ult ? json_decode((string)($v2Ult['resultado_json'] ?? '{}'), true) : [];
$v3Json = $v3Ult ? json_decode((string)($v3Ult['resultado_json'] ?? '{}'), true) : [];
if (!is_array($v2Json)) $v2Json=[];
if (!is_array($v3Json)) $v3Json=[];
?>

<div class="card-soft mb-3 border border-primary-subtle">
  <div class="d-flex flex-wrap justify-content-between gap-3 align-items-start">
    <div>
      <h4 class="mb-1"><i class="bi bi-diagram-3"></i> Central de Homologação</h4>
      <p class="text-muted mb-0">Homologação alinhada somente ao fluxo real: <b>VSM → HUB → Tiny</b>. Sem testar emissão fiscal pelo Tiny, certificado, SEFAZ, inutilização ou carta de correção.</p>
    </div>
    <div class="text-end small text-muted">Trace atual<br><code><?=e(RequestContext::id())?></code></div>
  </div>
</div>

<div class="alert alert-info">
  <b>Regra operacional:</b> a VSM é a origem da NF-e/XML. O HUB valida, audita, compara e encaminha para o Tiny. A homologação do Tiny aprova apenas Produto, Estoque, Pedido, Retorno Tiny e Auditoria. XML/NF-e fica em módulo separado porque a origem é a VSM.
</div>

<div class="row g-3">
  <div class="col-xl-4">
    <div class="card-soft h-100">
      <div class="d-flex justify-content-between align-items-start">
        <h5>🟢 VSM</h5>
        <?= $vsmOk ? '<span class="badge text-bg-success">URL configurada</span>' : '<span class="badge text-bg-danger">Pendente</span>' ?>
      </div>
      <p class="text-muted small mb-2">Origem operacional de estoque, pedidos e emissão XML/NF-e.</p>
      <ul class="small mb-0">
        <li>Produto/estoque oficial: <?= $vsmOk ? '✔' : '✖' ?></li>
        <li>Comparação com Tiny: depende dos testes V2/V3</li>
        <li>XML/NF-e recebido: <?= ((int)($xmlResumo['total'] ?? 0) > 0) ? '✔' : '○' ?></li>
      </ul>
    </div>
  </div>
  <div class="col-xl-4">
    <div class="card-soft h-100">
      <div class="d-flex justify-content-between align-items-start">
        <h5>Tiny V2</h5>
        <?=homBadge($v2)?>
      </div>
      <div class="progress my-3" style="height:18px"><div class="progress-bar" style="width: <?=(int)($v2['progresso'] ?? 0)?>%"> <?=(int)($v2['progresso'] ?? 0)?>%</div></div>
      <table class="table table-sm mb-0">
        <tr><th>Token</th><td><?=homCheck($v2Checks,'auth')?></td></tr>
        <tr><th>Produto</th><td><?=homCheck($v2Checks,'produto')?></td></tr>
        <tr><th>Estoque</th><td><?=homCheck($v2Checks,'estoque')?></td></tr>
        <tr><th>Pedido</th><td><?=homCheck($v2Checks,'pedido')?></td></tr>
        <tr><th>Retorno Tiny</th><td><?=homCheck($v2Checks,'retorno_tiny')?></td></tr>
      </table>
      <a class="btn btn-sm btn-outline-primary mt-3" href="index.php?page=tiny-v2-homologacao">Abrir Tiny V2</a>
    </div>
  </div>
  <div class="col-xl-4">
    <div class="card-soft h-100">
      <div class="d-flex justify-content-between align-items-start">
        <h5>Tiny V3</h5>
        <?=homBadge($v3)?>
      </div>
      <div class="progress my-3" style="height:18px"><div class="progress-bar" style="width: <?=(int)($v3['progresso'] ?? 0)?>%"> <?=(int)($v3['progresso'] ?? 0)?>%</div></div>
      <table class="table table-sm mb-0">
        <tr><th>OAuth</th><td><?=homCheck($v3Checks,'oauth')?></td></tr>
        <tr><th>Produto</th><td><?=homCheck($v3Checks,'produto')?></td></tr>
        <tr><th>Estoque</th><td><?=homCheck($v3Checks,'estoque')?></td></tr>
        <tr><th>Pedido</th><td><?=homCheck($v3Checks,'pedido')?></td></tr>
        <tr><th>Retorno Tiny</th><td><?=homCheck($v3Checks,'retorno_tiny')?></td></tr>
      </table>
      <a class="btn btn-sm btn-outline-success mt-3" href="index.php?page=tiny-v3-homologacao">Abrir Tiny V3</a>
    </div>
  </div>
</div>

<div class="row g-3 mt-1">
  <div class="col-lg-6">
    <div class="card-soft h-100">
      <h5>🧾 XML/NF-e VSM → HUB → Tiny</h5>
      <table class="table table-sm mb-0">
        <tr><th>NF-e/XML registradas</th><td><?=e((string)($xmlResumo['total'] ?? 0))?></td></tr>
        <tr><th>XML sem envio</th><td><?=e((string)($xmlResumo['xml_sem_envio'] ?? 0))?></td></tr>
        <tr><th>Erros integração</th><td><?=e((string)($xmlResumo['erro_integracao'] ?? ($xmlResumo['erros'] ?? 0)))?></td></tr>
      </table>
      <a class="btn btn-sm btn-outline-secondary mt-3" href="index.php?page=fiscal">Abrir XML/NF-e</a>
    </div>
  </div>
  <div class="col-lg-6">
    <div class="card-soft h-100">
      <h5>🚀 Fluxo completo homologado</h5>
      <ol class="mb-0 small">
        <li>Produto consultado/vinculado.</li>
        <li>Estoque Tiny comparado com VSM.</li>
        <li>Pedido de teste localizado.</li>
        <li>Retorno/status confirmado no Tiny.</li>
        <li>XML/NF-e validado em módulo separado quando o fluxo fiscal VSM estiver ativo.</li>
        <li>Auditoria com Trace ID e histórico.</li>
      </ol>
    </div>
  </div>
</div>

<div class="card-soft mt-3">
  <h5>Melhorias futuras recomendadas</h5>
  <div class="row g-3 small">
    <div class="col-md-4"><b>Homologação agendada</b><br>Rodar teste diário de SKU, estoque, pedido e retorno Tiny com alerta automático.</div>
    <div class="col-md-4"><b>Relatório PDF de evidência</b><br>Gerar documento com data, usuário, Trace ID, SKU, pedido, estoque e resultado.</div>
    <div class="col-md-4"><b>Monitor de divergências</b><br>Fila própria para diferenças Tiny x VSM em estoque e pedido; XML/NF-e fica em monitor próprio.</div>
  </div>
</div>
<?php require __DIR__.'/layout_bottom.php'; ?>
