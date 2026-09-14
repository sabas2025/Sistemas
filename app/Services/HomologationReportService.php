<?php
class HomologationReportService {
  public static function gerarHtml(PDO $pdo): string {
    $cfg = IntegrationConfig::get();
    $checks = $pdo->query('SELECT id, chave, titulo, descricao, status, resultado, trace_id, atualizado_em, criado_em FROM homologacao_checklist ORDER BY id ASC')->fetchAll();
    $db = (new DatabaseValidationService($pdo))->executar();
    $total=count($checks); $ok=count(array_filter($checks, fn($i)=>$i['status']==='ok'));
    $falha=count(array_filter($checks, fn($i)=>$i['status']==='falha'));
    $pct=$total>0?round(($ok/$total)*100):0;
    ob_start();
    ?>
<!doctype html><html lang="pt-br"><head><meta charset="utf-8"><title>Relatório de Homologação - Hub de Integração</title>
<style>body{font-family:Arial,sans-serif;color:#111827;margin:32px}.card{border:1px solid #e5e7eb;border-radius:12px;padding:16px;margin-bottom:16px}.ok{color:#15803d}.erro{color:#b91c1c}.atencao{color:#a16207}table{width:100%;border-collapse:collapse}td,th{border:1px solid #e5e7eb;padding:8px;text-align:left}code{background:#f3f4f6;padding:2px 5px;border-radius:4px}</style>
</head><body>
<h1>Relatório Final de Homologação</h1>
<p><b>Gerado em:</b> <?=e(date('d/m/Y H:i:s'))?> &nbsp; <b>Trace ID:</b> <code><?=e(RequestContext::id())?></code></p>
<div class="card"><h2>Resumo</h2><p><b>Ambiente:</b> <?=e(strtoupper($cfg['ambiente'] ?? ''))?> | <b>Tiny:</b> <?=e(strtoupper($cfg['tiny_versao'] ?? 'v2'))?> | <b>Checklist OK:</b> <?=$ok?>/<?=$total?> (<?=$pct?>%) | <b>Falhas:</b> <?=$falha?></p><p><b>Banco:</b> <span class="<?=e($db['status'])?>"><?=e(strtoupper($db['status']))?></span> — OK <?=$db['ok']?>, Atenção <?=$db['atencao']?>, Erro <?=$db['erro']?></p></div>
<div class="card"><h2>Checklist de Homologação</h2><table><thead><tr><th>Item</th><th>Status</th><th>Resultado / Evidência</th><th>Trace</th></tr></thead><tbody><?php foreach($checks as $i): ?><tr><td><?=e($i['titulo'])?><br><small><?=e($i['chave'])?></small></td><td class="<?=e($i['status'])?>"><?=e(strtoupper($i['status']))?></td><td><?=nl2br(e($i['resultado'] ?? ''))?></td><td><code><?=e($i['trace_id'] ?? '')?></code></td></tr><?php endforeach; ?></tbody></table></div>
<div class="card"><h2>Validação do Banco</h2><table><thead><tr><th>Status</th><th>Verificação</th><th>Mensagem</th><th>Ação recomendada</th></tr></thead><tbody><?php foreach($db['checks'] as $c): ?><tr><td class="<?=e($c['status'])?>"><?=e(strtoupper($c['status']))?></td><td><?=e($c['titulo'])?></td><td><?=e($c['mensagem'])?></td><td><?=e($c['acao'])?></td></tr><?php endforeach; ?></tbody></table></div>
<div class="card"><h2>Conclusão técnica</h2><p>Este relatório documenta a situação do Hub no momento da homologação. Para produção final, mantenha evidências de payload real Tiny/Olist, payload real VSM, endpoints reais, retorno real das APIs e Trace IDs dos testes executados.</p></div>
</body></html>
    <?php
    return ob_get_clean();
  }
}
