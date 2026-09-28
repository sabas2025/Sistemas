<?php require __DIR__.'/layout_top.php'; ?>
<?php
  $sv = class_exists('SystemVersionService') ? 'SystemVersionService' : null;
  $versaoAtual = $sv ? SystemVersionService::artifactVersion() : 'V104.49.3';
  $codinome    = $sv && defined('SystemVersionService::CODENAME') ? SystemVersionService::CODENAME : '';
  $releaseData = $sv && defined('SystemVersionService::RELEASE_DATE') ? SystemVersionService::RELEASE_DATE : '';
  $build       = $sv && defined('SystemVersionService::BUILD') ? SystemVersionService::BUILD : '';
  $ambiente    = App::env();
  $deploy      = 'scripts/ops/deploy.sh';
  // Selo de integridade (reusa o FIM existente; pode não haver manifesto ainda).
  $fimStatus   = is_array($fim ?? null) ? (string)($fim['status'] ?? 'indisponivel') : 'indisponivel';
  $fimAlter    = is_array($fim ?? null) ? count($fim['alterados'] ?? []) : 0;
  $fimFalta    = is_array($fim ?? null) ? count($fim['faltantes'] ?? []) : 0;
?>

<div class="panel mb-3">
  <div class="panel-header"><h2><i class="bi bi-arrow-repeat"></i> Atualização do Hub</h2><span class="text-muted">Status da versão e passo a passo seguro para atualizar</span></div>
  <div class="p-4">
    <div class="row g-3">
      <div class="col-lg-6">
        <div class="build-card h-100">
          <h4 class="mb-3">Versão instalada</h4>
          <div style="font-size:1.6rem;font-weight:800;line-height:1.2"><?=e($versaoAtual)?></div>
          <?php if($codinome!==''): ?><div class="text-muted mt-1"><?=e($codinome)?></div><?php endif; ?>
          <table class="table table-sm mt-3 mb-0">
            <tr><th style="width:42%">Release</th><td><?=e($sv ? SystemVersionService::RELEASE : '—')?></td></tr>
            <?php if($build!==''): ?><tr><th>Build</th><td><?=e($build)?></td></tr><?php endif; ?>
            <?php if($releaseData!==''): ?><tr><th>Data da release</th><td><?=e($releaseData)?></td></tr><?php endif; ?>
            <tr><th>Ambiente</th><td><?=e(strtoupper((string)$ambiente))?></td></tr>
          </table>
        </div>
      </div>
      <div class="col-lg-6">
        <div class="build-card h-100">
          <h4 class="mb-3">Existe versão mais nova?</h4>
          <p class="mb-2">Por segurança, o Hub <b>não</b> se atualiza sozinho nem consulta servidor externo. Para saber se há atualização:</p>
          <ol class="mb-2">
            <li>Baixe o pacote mais recente do Hub (o arquivo <code>hub-&lt;versão&gt;-&lt;sha&gt;.zip</code>).</li>
            <li>Compare a versão do pacote com a exibida ao lado.</li>
            <li>Se a do pacote for <b>maior</b>, siga o passo a passo abaixo para aplicar.</li>
          </ol>
          <div class="alert alert-light border small mb-0">A aplicação é manual por decisão de segurança: nada nesta tela sobrescreve arquivos do sistema.</div>
        </div>
      </div>
    </div>
  </div>
</div>

<div class="panel mb-3">
  <div class="panel-header"><h2><i class="bi bi-terminal"></i> Como atualizar (no servidor)</h2><span class="text-muted">Atualiza o código preservando config e dados; faz backup e confere a integridade</span></div>
  <div class="p-4">
    <p>Na pasta da instalação, com o pacote novo baixado, rode o utilitário <code><?=e($deploy)?></code>. Ele preserva <code>config/config.php</code> e todo o <code>storage/</code> (logs, backups, sessões), cria um backup automático e confere o FIM ao final.</p>
    <p class="mb-1"><b>1.</b> Simular primeiro (não altera nada, só mostra o que mudaria):</p>
    <pre class="codebox mb-3"><code>DRY_RUN=1 ./<?=e($deploy)?> hub-&lt;versão&gt;.zip /caminho/da/instalacao</code></pre>
    <p class="mb-1"><b>2.</b> Aplicar de verdade (com backup automático):</p>
    <pre class="codebox mb-3"><code>./<?=e($deploy)?> hub-&lt;versão&gt;.zip /caminho/da/instalacao</code></pre>
    <p class="mb-1"><b>3.</b> Se o servidor web usa usuário próprio, ajuste o dono no fim:</p>
    <pre class="codebox mb-3"><code>./<?=e($deploy)?> hub-&lt;versão&gt;.zip /caminho/da/instalacao www-data:www-data</code></pre>
    <p class="mb-0"><b>4.</b> No navegador, pressione <b>Ctrl+F5</b> (o app PWA cacheia os assets) e confira o rodapé com a versão. Se algo der errado, restaure o backup <code>&lt;instalacao&gt;-backup-&lt;data&gt;.tar.gz</code> que o script criou.</p>
  </div>
</div>

<div class="panel">
  <div class="panel-header"><h2><i class="bi bi-shield-check"></i> Integridade da instalação</h2><span class="text-muted">Confere se os arquivos batem com o manifesto assinado</span></div>
  <div class="p-4">
    <?php if($fimStatus==='ok'): ?>
      <div class="alert alert-success mb-2"><b>Íntegra.</b> Nenhum arquivo crítico alterado ou faltante em relação ao manifesto.</div>
    <?php elseif($fimStatus==='falha'): ?>
      <div class="alert alert-danger mb-2"><b>Divergência detectada:</b> <?=e((string)$fimAlter)?> alterado(s), <?=e((string)$fimFalta)?> faltante(s). Verifique na tela de Integridade de Arquivos.</div>
    <?php elseif($fimStatus==='sem_manifesto'): ?>
      <div class="alert alert-warning mb-2"><b>Sem manifesto de integridade ainda.</b> Gere o baseline na tela de Integridade de Arquivos para habilitar a verificação.</div>
    <?php else: ?>
      <div class="alert alert-light border mb-2">Verificação de integridade indisponível neste ambiente.</div>
    <?php endif; ?>
    <a class="btn btn-outline-secondary btn-sm" href="index.php?page=security-fim"><i class="bi bi-fingerprint"></i> Abrir Integridade de Arquivos</a>
  </div>
</div>
<?php require __DIR__.'/layout_bottom.php'; ?>
