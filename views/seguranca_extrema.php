<?php require __DIR__.'/layout_top.php'; ?>
<div class="d-flex justify-content-between align-items-center mb-3">
  <div><h2 class="m-0">Segurança Extrema</h2><p class="text-muted mb-0">Bloqueio de buscadores, tokens protegidos, headers, sessões, banco e superfície de ataque.</p></div>
  <span class="badge text-bg-<?=($status['score']>=85?'success':($status['score']>=65?'warning':'danger'))?> fs-6">Score <?=e($status['score'])?>%</span>
</div>
<div class="alert alert-info"><b>Importante:</b> motores de busca são bloqueados por <code>robots.txt</code>, meta robots e <code>X-Robots-Tag</code>. Para segurança real, mantenha login, HTTPS, permissões e arquivos internos bloqueados.</div>
<div class="row g-3">
<?php foreach($status['checks'] as $c): ?>
  <div class="col-lg-6"><div class="card h-100 border-<?= $c['ok']?'success':'warning' ?>"><div class="card-body">
    <h5><?= $c['ok']?'🟢':'🟡' ?> <?=e($c['titulo'])?></h5>
    <p class="mb-1"><b>Status:</b> <?=e(strtoupper($c['status']))?></p>
    <p class="mb-1"><b>Risco:</b> <?=e($c['risco'])?></p>
    <p class="mb-0"><b>Ação recomendada:</b> <?=e($c['acao'])?></p>
  </div></div></div>
<?php endforeach; ?>
</div>
<div class="card mt-3"><div class="card-header"><b>Regras aplicadas na V94</b></div><div class="card-body"><ul class="mb-0">
<li>Bloqueio de indexação: robots.txt, meta robots e X-Robots-Tag.</li>
<li>Proteção Apache: .htaccess no public e raiz bloqueando app/config/database/storage/logs/backups.</li>
<li>Headers: CSP, HSTS, X-Frame-Options, nosniff, Referrer-Policy e Permissions-Policy.</li>
<li>Sessão: cookie HttpOnly, SameSite=Strict, Secure em HTTPS, strict mode, timeout por inatividade e expiração absoluta.</li>
<li>Tokens: criptografia AES-256-GCM e mascaramento em logs/auditoria.</li>
<li>Banco: prepared statements com emulação desativada e recomendação de usuário MySQL exclusivo.</li>
</ul></div></div>
<?php require __DIR__.'/layout_bottom.php'; ?>
