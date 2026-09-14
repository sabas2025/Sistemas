<?php require __DIR__.'/layout_top.php'; ?>
<?php
function badgeStatusHomologacao(string $status): string {
  return $status === 'ok' ? '<span class="badge text-bg-success">🟢 OK</span>' : ($status === 'alerta' ? '<span class="badge text-bg-warning">🟡 Alerta</span>' : ($status === 'falha' ? '<span class="badge text-bg-danger">🔴 Falha</span>' : '<span class="badge text-bg-secondary">⚪ Pendente</span>'));
}
$checks = $resumo['checks'] ?? [];
$progresso = (int)($resumo['progresso'] ?? 0);
$statusGeral = (string)($resumo['status_geral'] ?? 'pendente');
$diagnostico = $resumo['diagnostico'] ?? [];
$ultimo = $resumo['ultimo'] ?? null;
$hist = $resumo['historico'] ?? [];
$lastJson = $ultimo ? json_decode((string)($ultimo['resultado_json'] ?? '{}'), true) : [];
if (!is_array($lastJson)) $lastJson = [];
$lastEtapas = $lastJson['etapas'] ?? [];
$estoque = $lastEtapas['estoque'] ?? [];
$statusBadge = $statusGeral === 'aprovada' ? 'text-bg-success' : ($statusGeral === 'homologacao_avancada' ? 'text-bg-info' : ($statusGeral === 'homologacao_parcial' ? 'text-bg-warning' : 'text-bg-secondary'));
$statusLabel = $statusGeral === 'aprovada' ? '🟢 Aprovada' : ($statusGeral === 'homologacao_avancada' ? '🔵 Homologação avançada' : ($statusGeral === 'homologacao_parcial' ? '🟡 Homologação parcial' : '⚪ Pendente'));
$versao = 'V2';
$rotaExecutar = 'tiny-v2-homologacao-executar';
$tituloAuth = '🔐 Autenticação Tiny V2';
$authLista = '<ul class="small mb-0 mt-2">'
  . '<li>URL: <code>'.e($cfg['tiny_v2_url'] ?? '').'</code></li>'
  . '<li>Token: '.(trim((string)($cfg['tiny_v2_token'] ?? ''))!=='' ? '✔ informado' : '<span class="text-danger">✖ ausente</span>').'</li>'
  . '<li>Última validação: '.e($ultimo['criado_em'] ?? 'sem histórico').'</li>'
  . '</ul>';
$pedidoHint = 'ID numérico do pedido Tiny V2 para homologação completa.';
?>

<div class="card-soft mb-3 border border-success-subtle">
  <div class="d-flex flex-wrap justify-content-between gap-3 align-items-start">
    <div>
      <h4 class="mb-1"><i class="bi bi-check2-circle"></i> Tiny V2 - Homologação</h4>
      <p class="text-muted mb-0">Diagnóstico inteligente para validar somente o fluxo real do Tiny: autenticação, produto, estoque, pedido e retorno da API. XML/NF-e fica no módulo separado VSM → HUB → Tiny.</p>
    </div>
    <div class="text-end">
      <span class="badge <?=$statusBadge?> fs-6"><?=$statusLabel?></span>
      <div class="small text-muted mt-1">Trace atual: <code><?=e(RequestContext::id())?></code></div>
    </div>
  </div>
</div>

<?php if($ultimoResultado): ?>
<div class="alert <?= !empty($ultimoResultado['aprovado']) ? 'alert-success' : 'alert-warning' ?>">
  <b><?=e($ultimoResultado['status_final'] ?? 'Homologação executada')?></b><br>
  Trace ID: <code><?=e($ultimoResultado['trace_id'] ?? '')?></code>
  <?php if(!empty($ultimoResultado['modo_homologacao'])): ?><span class="ms-2 badge text-bg-light border">Modo: <?=e($ultimoResultado['modo_homologacao'])?></span><?php endif; ?>
  <?php if(!empty($ultimoResultado['erro'])): ?><div class="small text-danger mt-1"><?=e($ultimoResultado['erro'])?></div><?php endif; ?>
</div>
<?php endif; ?>

<div class="row g-3">
  <div class="col-xl-4">
    <div class="card-soft h-100">
      <h5>📊 Status Geral Tiny V2</h5>
      <div class="progress my-3" style="height:20px"><div class="progress-bar" role="progressbar" style="width: <?=$progresso?>%" aria-valuenow="<?=$progresso?>" aria-valuemin="0" aria-valuemax="100"><?=$progresso?>%</div></div>
      <table class="table table-sm align-middle mb-0">
        <?php foreach($checks as $c): ?>
        <tr>
          <th><?=e($c['label'])?></th>
          <td><?=badgeStatusHomologacao((string)$c['status'])?></td>
        </tr>
        <?php endforeach; ?>
      </table>
      <div class="alert alert-info border small mt-3 mb-0">
        <b>O que significa o percentual?</b><br>
        Ele mede as etapas homologadas. Etapa verde soma completo, alerta soma parcial e pendente/falha não aprova produção.
      </div>
      <div class="alert alert-light border small mt-3 mb-0">
        <b>Retorno Tiny:</b> confirmação técnica de que a API do Tiny respondeu às consultas de produto, estoque e pedido. Não é XML/NF-e.
      </div>
    </div>
  </div>

  <div class="col-xl-8">
    <div class="card-soft h-100">
      <div class="d-flex flex-wrap justify-content-between gap-2 align-items-start">
        <h5 class="mb-0">🧠 Diagnóstico Inteligente da Homologação</h5>
        <span class="badge text-bg-dark"><?=e($diagnostico['status'] ?? 'pendente')?></span>
      </div>
      <p class="text-muted small mt-2 mb-3"><?=e($diagnostico['resumo'] ?? 'Execute a homologação para gerar diagnóstico inteligente.')?></p>
      <div class="row g-2">
        <div class="col-md-3"><div class="p-2 rounded border bg-light h-100"><b>Passou</b><div class="small text-success mt-1"><?=e(implode(', ', $diagnostico['passou'] ?? []) ?: '—')?></div></div></div>
        <div class="col-md-3"><div class="p-2 rounded border bg-light h-100"><b>Alerta</b><div class="small text-warning mt-1"><?=e(implode(', ', $diagnostico['alerta'] ?? []) ?: '—')?></div></div></div>
        <div class="col-md-3"><div class="p-2 rounded border bg-light h-100"><b>Falhou</b><div class="small text-danger mt-1"><?=e(implode(', ', $diagnostico['falhou'] ?? []) ?: '—')?></div></div></div>
        <div class="col-md-3"><div class="p-2 rounded border bg-light h-100"><b>Pendente</b><div class="small text-muted mt-1"><?=e(implode(', ', $diagnostico['pendente'] ?? []) ?: '—')?></div></div></div>
      </div>
      <hr>
      <h6>✅ Ações recomendadas</h6>
      <?php $acoes = $diagnostico['acoes_recomendadas'] ?? []; ?>
      <?php if(!$acoes): ?><div class="small text-muted">Nenhuma ação pendente.</div><?php else: ?>
      <ol class="small mb-0">
        <?php foreach(array_slice($acoes,0,6) as $acao): ?><li><?=e($acao)?></li><?php endforeach; ?>
      </ol>
      <?php endif; ?>
    </div>
  </div>
</div>

<div class="row g-3 mt-1">
  <div class="col-xl-8">
    <div class="card-soft h-100">
      <h5>▶ Executar Homologação Tiny V2</h5>
      <div class="alert alert-primary border small">
        <b>Campo de estoque obrigatório para finalizar:</b> informe o saldo esperado do SKU de homologação. O sistema compara esse valor com o retorno do Tiny e com o saldo oficial da VSM.
      </div>
      <form method="post" action="index.php?page=<?=$rotaExecutar?>" class="row g-3">
        <?=Csrf::input()?>
        <div class="col-md-3">
          <label class="form-label">SKU de Homologação</label>
          <input class="form-control" name="sku" value="<?=e($skuPadrao)?>" placeholder="HUB-TESTE-001">
          <div class="form-text">Usado para produto. O saldo fica no campo Estoque de Homologação.</div>
        </div>
        <div class="col-md-3">
          <label class="form-label">Pedido de Teste</label>
          <input class="form-control" name="pedido_teste" value="<?=e($pedidoPadrao)?>" placeholder="ID do pedido Tiny">
          <div class="form-text"><?=$pedidoHint?></div>
        </div>
        <div class="col-md-3">
          <label class="form-label">Estoque de Homologação</label>
          <input class="form-control" name="estoque_homologacao" value="<?=e($estoquePadrao ?? '')?>" placeholder="Ex.: 10">
          <div class="form-text">Saldo esperado para finalizar a homologação de estoque.</div>
        </div>
        <div class="col-md-3">
          <label class="form-label">Modo</label>
          <select class="form-select" name="modo_homologacao">
            <option value="completa">Completa: exige pedido e todas as etapas</option>
            <option value="parcial">Parcial: produto/estoque sem travar pedido</option>
            <option value="pendentes">Continuar de onde parou</option>
          </select>
          <div class="form-text">Use parcial para avançar sem pedido; use continuar para focar pendências.</div>
        </div>
        <div class="col-md-4"><button name="modo_homologacao" value="completa" class="btn btn-success w-100"><i class="bi bi-play-circle"></i> Homologação Completa</button></div>
        <div class="col-md-4"><button name="modo_homologacao" value="parcial" class="btn btn-warning w-100"><i class="bi bi-check2-square"></i> Homologação Parcial</button></div>
        <div class="col-md-4"><button name="modo_homologacao" value="pendentes" class="btn btn-primary w-100"><i class="bi bi-arrow-repeat"></i> Continuar de onde parou</button></div>
      </form>
      <div class="alert alert-secondary border small mt-3 mb-0">
        <b>Diferença dos modos:</b> completa exige tudo para aprovar; parcial registra evidência com pendências; continuar orienta a execução pelas etapas ainda pendentes no diagnóstico.
      </div>
    </div>
  </div>

  <div class="col-xl-4">
    <div class="card-soft h-100">
      <h5><?=$tituloAuth?></h5>
      <?=$authLista?>
      <?php if('V2'==='V3'): ?>
      <div class="alert alert-info border small mt-2 mb-0">
        OAuth OK só deve aparecer quando Client ID, Client Secret, Redirect URI e access token utilizável estiverem válidos no mesmo ambiente.
      </div>
      <?php endif; ?>
    </div>
  </div>
</div>

<div class="row g-3 mt-1">
  <div class="col-lg-6">
    <div class="card-soft h-100">
      <h5>📊 Homologação de Estoque Tiny V2 x VSM</h5>
      <div class="small text-muted">Valida se o SKU existe nos dois lados e se o saldo Tiny confere com o saldo oficial VSM.</div>
      <table class="table table-sm mb-0 mt-2">
        <tr><th>SKU</th><td><?=e($lastJson['sku'] ?? $skuPadrao)?></td></tr>
        <tr><th>Tiny V2</th><td><?=isset($estoque['tiny_saldo']) && $estoque['tiny_saldo'] !== null ? e((string)$estoque['tiny_saldo']) : '—'?></td></tr>
        <tr><th>VSM</th><td><?=isset($estoque['vsm_saldo']) && $estoque['vsm_saldo'] !== null ? e((string)$estoque['vsm_saldo']) : '—'?></td></tr>
        <tr><th>Estoque esperado</th><td><?=isset($estoque['estoque_esperado']) && $estoque['estoque_esperado'] !== null ? e((string)$estoque['estoque_esperado']) : '—'?></td></tr>
        <tr><th>Diferença</th><td><?=isset($estoque['diferenca']) && $estoque['diferenca'] !== null ? e((string)$estoque['diferenca']) : '—'?></td></tr>
        <tr><th>Status</th><td><?=!empty($estoque['ok']) ? '<span class="text-success">🟢 Confere</span>' : (($estoque['status_homologacao'] ?? '')==='alerta' ? '<span class="text-warning">🟡 Divergência registrada</span>' : '<span class="text-danger">🔴 Pendente/Falha</span>')?></td></tr>
      </table>
      <?php if(!empty($estoque['acao_recomendada'])): ?><div class="alert alert-warning small mt-2 mb-0"><?=e($estoque['acao_recomendada'])?></div><?php endif; ?>
    </div>
  </div>
  <div class="col-lg-6">
    <div class="card-soft h-100">
      <h5>📦 Etapas da última execução</h5>
      <div class="list-group list-group-flush">
        <?php foreach(['produto'=>'Produto','estoque'=>'Estoque Tiny x VSM','pedido'=>'Pedido','retorno_tiny'=>'Retorno Tiny'] as $k=>$label): $et=$lastEtapas[$k] ?? []; $st=!empty($et['ok'])?'ok':(($et['status_homologacao'] ?? '') ?: ($et?'falha':'pendente')); ?>
          <div class="list-group-item px-0">
            <div class="d-flex justify-content-between gap-2"><b><?=e($label)?></b><?=badgeStatusHomologacao($st)?></div>
            <div class="small text-muted mt-1"><?=e($et['mensagem'] ?? 'Ainda sem execução recente.')?></div>
            <?php if(!empty($et['acao_recomendada'])): ?><div class="small text-primary mt-1">Ação: <?=e($et['acao_recomendada'])?></div><?php endif; ?>
          </div>
        <?php endforeach; ?>
      </div>
    </div>
  </div>
</div>

<div class="row g-3 mt-1">
  <div class="col-lg-7">
    <div class="card-soft h-100">
      <h5>📜 Últimos Testes</h5>
      <?php if(!$hist): ?>
        <div class="text-muted small">Nenhum teste de homologação registrado.</div>
      <?php else: ?>
        <div class="table-responsive">
          <table class="table table-sm align-middle">
            <thead><tr><th>Data</th><th>SKU</th><th>Pedido</th><th>Status</th><th>Trace</th><th></th></tr></thead>
            <tbody>
              <?php foreach($hist as $h): ?>
                <tr>
                  <td><?=e(date('d/m H:i', strtotime((string)$h['criado_em'])))?></td>
                  <td><code><?=e($h['sku'])?></code></td>
                  <td><?=e($h['pedido_teste'] ?: '—')?></td>
                  <td><?=!empty($h['aprovado']) ? '🟢 OK' : '🔴 Falha/Pendente'?></td>
                  <td><code id="tr-<?=e($h['id'])?>"><?=e($h['trace_id'])?></code></td>
                  <td><button type="button" class="btn btn-sm btn-outline-secondary" data-copy-target="#tr-<?=e($h['id'])?>">Copiar</button></td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      <?php endif; ?>
    </div>
  </div>
  <div class="col-lg-5">
    <div class="card-soft h-100 border border-info-subtle">
      <h5>🚀 Melhorias V88 aplicadas</h5>
      <ul class="small mb-0">
        <li>Diagnóstico inteligente: passou, alerta, falhou e pendente.</li>
        <li>Percentual com explicação e status parcial/avançado.</li>
        <li>Homologação completa, parcial e continuar de onde parou.</li>
        <li>Ação recomendada por etapa.</li>
        <li>Estoque divergente tratado como alerta para homologação parcial.</li>
        <li>Botão Copiar Trace ID no histórico.</li>
      </ul>
    </div>
  </div>
</div>

<div class="card-soft mt-3">
  <h5>🧾 Evidências da última execução</h5>
  <?php if($lastJson): ?>
    <pre class="json-box"><?=e(json_encode($lastJson, JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES))?></pre>
  <?php else: ?>
    <div class="text-muted small">Execute a homologação para gerar evidências com Trace ID, retornos Tiny, retorno VSM e comparação de estoque.</div>
  <?php endif; ?>
</div>
<?php require __DIR__.'/layout_bottom.php'; ?>
