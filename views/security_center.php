<?php require __DIR__.'/layout_top.php'; ?>
<?php
$tabItems = [
  'security-center'=>'Visão geral',
  'security-soc'=>'SOC',
  'security-events'=>'Eventos',
  'security-ips'=>'IPs bloqueados',
  'security-circuit-breakers'=>'Circuit Breakers',
  'security-code-audit'=>'Execução de Código',
  'security-inventory'=>'Legado/Banco',
  'security-backup-trust'=>'Backup Trust',
  'security-health'=>'Health Real Time',
  'security-audit-signatures'=>'Assinaturas Auditoria',
  'security-fim'=>'Integridade de arquivos',
  'security-score'=>'Score de Segurança',
  'security-hardening'=>'Hardening',
  'security-ssl'=>'Certificados SSL',
  'security-user-audit'=>'Auditoria de Usuários',
  'security-pentest'=>'Pentest Checklist',
];
$activeSecurityPage = $activeSecurityPage ?? ($_GET['page'] ?? 'security-center');
$criticos = count(array_filter($eventos ?? [], fn($e)=>($e['severidade'] ?? '') === 'critico'));
$cbAbertos = count(array_filter($circuitBreakers ?? [], fn($c)=>($c['status'] ?? '') === 'aberto'));
$integridadeOk = ($fim['status'] ?? '') === 'ok';
$backupScore = (int)($backupTrust['score'] ?? 0);
$healthScore = (int)($realtimeHealth['score'] ?? 0);
$osExecReal = (int)($codeAudit['counts']['os_exec_real'] ?? 0);
?>
<div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
  <div><h2 class="m-0">Security Operations Center</h2><p class="text-muted mb-0">Proteção máxima no painel; Tiny/VSM com proteção compatível por OAuth, secret, HMAC, anti-replay, auditoria, retry e circuit breaker.</p></div>
  <div class="d-flex gap-2"><a class="btn btn-outline-primary" href="index.php?page=security-score"><i class="bi bi-speedometer2"></i> Score</a><a class="btn btn-outline-dark" href="index.php?page=security-fim"><i class="bi bi-fingerprint"></i> FIM</a></div>
</div>

<div class="d-flex gap-2 flex-wrap mb-3">
<?php foreach($tabItems as $route=>$label): ?>
  <a class="btn btn-sm <?=($activeSecurityPage===$route || ($route==='security-center' && $activeSecurityPage==='security-center'))?'btn-primary':'btn-outline-secondary'?>" href="index.php?page=<?=$route?>"><?=e($label)?></a>
<?php endforeach; ?>
</div>

<div class="row g-3 mb-3">
  <div class="col-md-2"><div class="card h-100"><div class="card-body"><div class="small text-muted">Integridade</div><div class="h5 mb-0"><?=$integridadeOk?'🟢 OK':'🔴 Revisar'?></div></div></div></div>
  <div class="col-md-2"><div class="card h-100"><div class="card-body"><div class="small text-muted">Tiny</div><div class="h5 mb-0"><?=($cbAbertos===0?'🟢 Monitorado':'🟡 Ver CB')?></div></div></div></div>
  <div class="col-md-2"><div class="card h-100"><div class="card-body"><div class="small text-muted">VSM</div><div class="h5 mb-0"><?=($cbAbertos===0?'🟢 Monitorado':'🟡 Ver CB')?></div></div></div></div>
  <div class="col-md-2"><div class="card h-100"><div class="card-body"><div class="small text-muted">SSL/HTTPS</div><div class="h5 mb-0"><?=!empty($ssl['https'])?'🟢 Ativo':'🔴 Pendente'?></div></div></div></div>
  <div class="col-md-2"><div class="card h-100"><div class="card-body"><div class="small text-muted">Backup Trust</div><div class="h5 mb-0"><?=$backupScore?>/100</div></div></div></div>
  <div class="col-md-2"><div class="card h-100"><div class="card-body"><div class="small text-muted">Health</div><div class="h5 mb-0"><?=$healthScore?>/100</div></div></div></div>
</div>

<div class="row g-3 mb-4">
  <div class="col-md-3"><div class="card"><div class="card-body"><div class="text-muted small">Tentativas de login bloqueadas</div><div class="display-6"><?=count(array_filter($loginTentativas ?? [], fn($l)=>empty($l['sucesso'])))?></div></div></div></div>
  <div class="col-md-3"><div class="card"><div class="card-body"><div class="text-muted small">IPs bloqueados</div><div class="display-6"><?=count($ips ?? [])?></div></div></div></div>
  <div class="col-md-3"><div class="card"><div class="card-body"><div class="text-muted small">Eventos críticos</div><div class="display-6"><?=$criticos?></div></div></div></div>
  <div class="col-md-3"><div class="card"><div class="card-body"><div class="text-muted small">Chamadas OS reais</div><div class="display-6"><?=$osExecReal?></div></div></div></div>
</div>

<?php if(in_array($activeSecurityPage, ['security-center','security-soc'], true)): ?>
<div class="row g-3 mb-3">
  <div class="col-lg-6"><div class="card h-100"><div class="card-header"><b>Resumo SOC</b></div><div class="card-body">
    <div class="row g-2">
      <div class="col-6"><div class="p-3 border rounded">Eventos críticos<br><b class="h4"><?=$criticos?></b></div></div>
      <div class="col-6"><div class="p-3 border rounded">Circuit Breakers abertos<br><b class="h4"><?=$cbAbertos?></b></div></div>
      <div class="col-6"><div class="p-3 border rounded">Backup Trust<br><b class="h4"><?=$backupScore?>%</b></div></div>
      <div class="col-6"><div class="p-3 border rounded">Health Real Time<br><b class="h4"><?=$healthScore?>%</b></div></div>
    </div>
  </div></div></div>
  <div class="col-lg-6"><div class="card h-100"><div class="card-header"><b>Proteções ativas</b></div><div class="card-body">
    <ul class="mb-0">
      <li>WAF com exceções permanentes para <code>/api/tiny/*</code>, <code>/api/vsm/*</code>, <code>/webhook/tiny/*</code> e <code>/webhook/vsm/*</code>.</li>
      <li>Tiny V2, Tiny V3, VSM e Fiscal tratados como circuit breakers independentes.</li>
      <li>VSM com anti-replay por <code>request_hash</code> e <code>request_time</code>.</li>
      <li>Auditoria com hash chain + assinatura diária HMAC.</li>
      <li>Backup com HMAC, SHA-256 e Trust Score antes de restore.</li>
    </ul>
  </div></div></div>
</div>
<?php endif; ?>

<?php if(in_array($activeSecurityPage, ['security-center','security-events'], true)): ?>
<div class="card mb-3"><div class="card-header"><b>Eventos recentes</b></div><div class="table-responsive"><table class="table table-sm align-middle mb-0"><thead><tr><th>Data</th><th>Severidade</th><th>Tipo</th><th>IP</th><th>Rota</th><th>Detalhe</th></tr></thead><tbody>
<?php foreach(($eventos ?? []) as $ev): ?><tr><td><?=e($ev['created_at'] ?? $ev['criado_em'] ?? '')?></td><td><span class="badge text-bg-<?=($ev['severidade']==='critico'?'danger':($ev['severidade']==='alto'?'warning':'secondary'))?>"><?=e($ev['severidade'] ?? '')?></span></td><td><?=e($ev['tipo'] ?? '')?></td><td><?=e($ev['ip'] ?? '')?></td><td><?=e($ev['rota'] ?? '')?></td><td><?=e($ev['detalhe'] ?? $ev['mensagem'] ?? '')?></td></tr><?php endforeach; ?>
<?php if(empty($eventos)): ?><tr><td colspan="6" class="text-muted">Nenhum evento registrado.</td></tr><?php endif; ?>
</tbody></table></div></div>
<?php endif; ?>

<?php if(in_array($activeSecurityPage, ['security-center','security-ips'], true)): ?>
<div class="card mb-3"><div class="card-header"><b>IPs bloqueados</b></div><div class="table-responsive"><table class="table table-sm align-middle mb-0"><thead><tr><th>IP</th><th>Motivo</th><th>Severidade</th><th>Até</th><th>Data</th></tr></thead><tbody>
<?php foreach(($ips ?? []) as $ip): ?><tr><td><?=e($ip['ip'])?></td><td><?=e($ip['motivo'])?></td><td><?=e($ip['severidade'])?></td><td><?=e($ip['bloqueado_ate'] ?? 'indeterminado')?></td><td><?=e($ip['created_at'] ?? '')?></td></tr><?php endforeach; ?>
<?php if(empty($ips)): ?><tr><td colspan="5" class="text-muted">Nenhum IP bloqueado ativo.</td></tr><?php endif; ?>
</tbody></table></div></div>
<?php endif; ?>

<?php if(in_array($activeSecurityPage, ['security-center','security-circuit-breakers'], true)): ?>
<div class="card mb-3"><div class="card-header"><b>Circuit Breakers independentes</b></div><div class="table-responsive"><table class="table table-sm align-middle mb-0"><thead><tr><th>Sistema</th><th>Status</th><th>Falhas</th><th>Aberto até</th><th>Última falha</th><th>Último sucesso</th></tr></thead><tbody>
<?php foreach(($circuitBreakers ?? []) as $cb): ?><tr><td><?=e($cb['sistema'])?></td><td><span class="badge text-bg-<?=($cb['status']==='aberto'?'danger':($cb['status']==='meio_aberto'?'warning':'success'))?>"><?=e($cb['status'])?></span></td><td><?=e((string)$cb['falhas_consecutivas'])?></td><td><?=e($cb['aberto_ate'] ?? '')?></td><td><?=e(mb_substr((string)($cb['ultima_falha'] ?? ''),0,120))?></td><td><?=e($cb['ultimo_sucesso_em'] ?? '')?></td></tr><?php endforeach; ?>
<?php if(empty($circuitBreakers)): ?><tr><td colspan="6" class="text-muted">Nenhum circuit breaker encontrado.</td></tr><?php endif; ?>
</tbody></table></div></div>
<?php endif; ?>

<?php if($activeSecurityPage === 'security-code-audit'): ?>
<div class="card mb-3"><div class="card-header"><b>Varredura exec/execute/curl_exec</b> · Status <?=e($codeAudit['status'] ?? '')?></div><div class="card-body">
  <div class="row g-2 mb-3">
    <?php foreach(($codeAudit['counts'] ?? []) as $k=>$v): ?><div class="col-md-2"><div class="border rounded p-2"><div class="small text-muted"><?=e($k)?></div><b><?=e(is_bool($v)?($v?'sim':'não'):(string)$v)?></b></div></div><?php endforeach; ?>
  </div>
  <p class="text-muted mb-0"><?=e($codeAudit['recomendacao'] ?? '')?></p>
</div><div class="table-responsive"><table class="table table-sm align-middle mb-0"><thead><tr><th>Tipo</th><th>Classe</th><th>Arquivo</th><th>Linha</th><th>Análise</th></tr></thead><tbody>
<?php foreach(array_slice(($codeAudit['hits'] ?? []),0,220) as $h): ?><tr><td><?=e($h['tipo'])?></td><td><span class="badge text-bg-<?=($h['classe']==='bloqueio_obrigatorio'?'danger':($h['classe']==='revisar_sql_textual'?'warning':'secondary'))?>"><?=e($h['classe'])?></span></td><td><?=e($h['arquivo'])?></td><td><?=e((string)$h['linha'])?></td><td><?=e($h['analise'])?></td></tr><?php endforeach; ?>
<?php if(empty($codeAudit['hits'])): ?><tr><td colspan="5" class="text-muted">Nenhuma ocorrência encontrada.</td></tr><?php endif; ?>
</tbody></table></div></div>
<?php endif; ?>

<?php if($activeSecurityPage === 'security-inventory'): ?>
<div class="row g-3 mb-3">
  <div class="col-lg-6"><div class="card h-100"><div class="card-header"><b>Controllers/Services · ATIVO / LEGADO / FUTURO</b></div><div class="table-responsive"><table class="table table-sm mb-0"><thead><tr><th>Nome</th><th>Tipo</th><th>Status</th><th>Ação</th></tr></thead><tbody>
  <?php foreach(($legacyInventory['items'] ?? []) as $it): ?><tr><td><?=e($it['nome'])?><div class="small text-muted"><?=e($it['arquivo'])?></div></td><td><?=e($it['tipo'])?></td><td><span class="badge text-bg-<?=($it['status']==='LEGADO'?'warning':($it['status']==='FUTURO'?'info':'success'))?>"><?=e($it['status'])?></span></td><td><?=e($it['acao'])?></td></tr><?php endforeach; ?>
  </tbody></table></div></div></div>
  <div class="col-lg-6"><div class="card h-100"><div class="card-header"><b>Tabelas do Banco · classificação</b> · Total <?=e((string)($databaseInventory['total'] ?? 0))?></div><div class="table-responsive"><table class="table table-sm mb-0"><thead><tr><th>Tabela</th><th>Grupo</th><th>Status</th><th>Módulo</th></tr></thead><tbody>
  <?php foreach(($databaseInventory['items'] ?? []) as $it): ?><tr><td><?=e($it['tabela'])?></td><td><?=e($it['grupo'])?></td><td><span class="badge text-bg-<?=($it['status']==='LEGADO'?'warning':($it['status']==='FUTURA'?'info':'success'))?>"><?=e($it['status'])?></span></td><td><?=e($it['modulo'])?></td></tr><?php endforeach; ?>
  <?php if(empty($databaseInventory['items'])): ?><tr><td colspan="4" class="text-muted">Banco não conectado nesta tela ou sem tabelas visíveis.</td></tr><?php endif; ?>
  </tbody></table></div></div></div>
</div>
<?php endif; ?>

<?php if($activeSecurityPage === 'security-backup-trust'): ?>
<div class="card mb-3"><div class="card-header"><b>Backup Trust Score</b> · Média <?=$backupScore?>%</div><div class="table-responsive"><table class="table table-sm align-middle mb-0"><thead><tr><th>Arquivo</th><th>Score</th><th>Status</th><th>Tamanho</th><th>Modificado</th></tr></thead><tbody>
<?php foreach(($backupTrust['items'] ?? []) as $b): ?><tr><td><?=e($b['arquivo'])?></td><td><?=e((string)$b['score'])?>%</td><td><span class="badge text-bg-<?=($b['status']==='confiavel'?'success':($b['status']==='atencao'?'warning':'danger'))?>"><?=e($b['status'])?></span></td><td><?=e(number_format((float)$b['tamanho']/1024,1,',','.'))?> KB</td><td><?=e($b['modificado_em'])?></td></tr><?php endforeach; ?>
<?php if(empty($backupTrust['items'])): ?><tr><td colspan="5" class="text-muted">Nenhum backup encontrado em storage/backups.</td></tr><?php endif; ?>
</tbody></table></div></div>
<?php endif; ?>

<?php if($activeSecurityPage === 'security-health'): ?>
<div class="card mb-3"><div class="card-header"><b>Health Check em tempo real</b> · Score <?=$healthScore?>%</div><div class="table-responsive"><table class="table table-sm align-middle mb-0"><thead><tr><th>Serviço</th><th>Status</th><th>Detalhe</th><th>Trace ID</th></tr></thead><tbody>
<?php foreach(($realtimeHealth['checks'] ?? []) as $c): ?><tr><td><?=e($c['nome'])?></td><td><span class="badge text-bg-<?=($c['status']==='ok'?'success':($c['status']==='atencao'?'warning':'danger'))?>"><?=e($c['status'])?></span></td><td><?=e($c['detalhe'])?></td><td><?=e($c['trace_id'] ?? '')?></td></tr><?php endforeach; ?>
</tbody></table></div></div>
<?php endif; ?>

<?php if($activeSecurityPage === 'security-audit-signatures'): ?>
<div class="card mb-3"><div class="card-header d-flex justify-content-between align-items-center"><b>Assinatura diária da auditoria</b><form method="post" action="index.php?page=security-audit-sign" class="d-flex gap-2"><?=Csrf::input()?><input type="date" name="audit_date" value="<?=e(date('Y-m-d'))?>" class="form-control form-control-sm"><button class="btn btn-sm btn-primary">Gerar assinatura</button></form></div><div class="table-responsive"><table class="table table-sm align-middle mb-0"><thead><tr><th>Data</th><th>Eventos</th><th>SHA-256</th><th>Arquivo</th><th>Criado em</th></tr></thead><tbody>
<?php foreach(($auditDailySignatures ?? []) as $a): ?><tr><td><?=e($a['audit_date'])?></td><td><?=e((string)$a['event_count'])?></td><td><code><?=e(substr((string)$a['sha256'],0,20))?>...</code></td><td><?=e($a['signature_file'])?></td><td><?=e($a['created_at'])?></td></tr><?php endforeach; ?>
<?php if(empty($auditDailySignatures)): ?><tr><td colspan="5" class="text-muted">Nenhuma assinatura diária gerada ainda.</td></tr><?php endif; ?>
</tbody></table></div></div>
<?php endif; ?>

<?php if($activeSecurityPage === 'security-hardening'): ?>
<div class="card mb-3"><div class="card-header"><b>Hardening de Produção</b> · Score <?=e((string)($hardening['score'] ?? 0))?>%</div><div class="table-responsive"><table class="table table-sm align-middle mb-0"><thead><tr><th>Verificação</th><th>Status</th><th>Risco se pendente</th><th>Ação recomendada</th></tr></thead><tbody>
<?php foreach(($hardening['checks'] ?? []) as $c): ?><tr><td><?=e($c['titulo'])?></td><td><?=$c['ok']?'🟢 OK':'🔴 Pendente'?></td><td><?=e($c['risco'])?></td><td><?=e($c['acao'])?></td></tr><?php endforeach; ?>
</tbody></table></div></div>
<?php endif; ?>

<?php if($activeSecurityPage === 'security-ssl'): ?>
<div class="card mb-3"><div class="card-header"><b>Certificados SSL / HTTPS</b></div><div class="card-body"><p><b>Host:</b> <?=e($ssl['host'])?></p><p><b>HTTPS detectado:</b> <?=!empty($ssl['https'])?'🟢 Sim':'🔴 Não'?></p><p><b>HSTS em produção:</b> <?=!empty($ssl['hsts'])?'🟢 Header enviado quando produção':'🟡 Ambiente não produção'?></p><p class="text-muted mb-0"><?=e($ssl['acao'])?></p></div></div>
<?php endif; ?>

<?php if($activeSecurityPage === 'security-user-audit'): ?>
<div class="card mb-3"><div class="card-header"><b>Auditoria de Usuários e Login</b></div><div class="table-responsive"><table class="table table-sm align-middle mb-0"><thead><tr><th>Data</th><th>E-mail</th><th>IP</th><th>Status</th><th>Mensagem</th></tr></thead><tbody>
<?php foreach(($loginTentativas ?? []) as $l): ?><tr><td><?=e($l['criado_em'] ?? '')?></td><td><?=e($l['email'] ?? '')?></td><td><?=e($l['ip'] ?? '')?></td><td><?=!empty($l['sucesso'])?'🟢 Sucesso':'🔴 Falha/Bloqueio'?></td><td><?=e($l['mensagem'] ?? '')?></td></tr><?php endforeach; ?>
<?php if(empty($loginTentativas)): ?><tr><td colspan="5" class="text-muted">Sem tentativas registradas.</td></tr><?php endif; ?>
</tbody></table></div></div>
<?php endif; ?>

<?php if($activeSecurityPage === 'security-pentest'): ?>
<div class="card mb-3"><div class="card-header"><b>Pentest Checklist</b></div><div class="table-responsive"><table class="table table-sm align-middle mb-0"><thead><tr><th>Item</th><th>Status</th></tr></thead><tbody>
<?php foreach(($pentestChecklist ?? []) as $p): ?><tr><td><?=e($p['item'])?></td><td><?=$p['ok']?'🟢 OK':'🔴 Revisar'?></td></tr><?php endforeach; ?>
</tbody></table></div></div>
<?php endif; ?>

<div class="alert alert-info"><b>Regra aplicada:</b> WAF agressivo somente no painel administrativo. Rotas Tiny/VSM têm exceções permanentes e ficam protegidas por OAuth/secret/HMAC/allowlist/rate limit/auditoria/anti-replay/circuit breaker, sem bloqueio genérico por palavras SQL/XSS no payload.</div>
<?php require __DIR__.'/layout_bottom.php'; ?>
