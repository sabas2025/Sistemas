<?php require __DIR__.'/layout_top.php'; ?>
<div class="futuristic-hero mb-4">
  <div><span class="eyebrow"><i class="bi bi-stars"></i> V104.35 Enterprise UI</span><h1>Design System Futurista</h1><p>Camada visual para grande porte: foco em leitura rápida, status operacional, toque mobile, PWA e redução de erro humano.</p></div>
  <div class="hero-actions"><a class="btn btn-light" href="index.php?page=configuracoes"><i class="bi bi-sliders"></i> Configurações</a><a class="btn btn-outline-light" href="index.php?page=enterprise-observabilidade"><i class="bi bi-activity"></i> Observabilidade</a></div>
</div>
<div class="row g-3">
  <?php $cards=[['bi-speedometer2','Operação clara','Cards com status, risco e ação recomendada para decisões rápidas.'],['bi-phone','Mobile/PWA','Áreas de toque maiores, safe-area iOS e tabelas em cards no celular.'],['bi-shield-check','Segurança visual','Badges de OK, Atenção e Bloqueio com cores consistentes.'],['bi-diagram-3','Integração','Fluxos Tiny/VSM com homologação, produção pendente e produção liberada.'],['bi-robot','LLM controlado','IA como assistente auditável, sem ação automática.'],['bi-database-check','Banco seguro','Migração versionada, validação e reparo manual controlado.']]; foreach($cards as $c): ?>
  <div class="col-md-4"><div class="feature-tile h-100"><i class="bi <?=e($c[0])?>"></i><h5><?=e($c[1])?></h5><p><?=e($c[2])?></p></div></div>
  <?php endforeach; ?>
</div>
<div class="card-soft mt-3">
  <h5><i class="bi bi-palette2"></i> Regras visuais aplicadas</h5>
  <div class="row g-3 mt-1">
    <div class="col-md-3"><span class="status-pill ok">OK</span><p class="small text-muted mt-2">Operação normal.</p></div>
    <div class="col-md-3"><span class="status-pill warn">ATENÇÃO</span><p class="small text-muted mt-2">Exige validação antes de produção.</p></div>
    <div class="col-md-3"><span class="status-pill danger">BLOQUEIO</span><p class="small text-muted mt-2">Impede go-live ou ação automática.</p></div>
    <div class="col-md-3"><span class="status-pill info">HOMOLOGAÇÃO</span><p class="small text-muted mt-2">Padrão seguro inicial.</p></div>
  </div>
</div>
<div class="alert alert-warning mt-3 small"><b>Próximo passo recomendado:</b> converter gradualmente as telas antigas para componentes reutilizáveis: <code>metric-orb</code>, <code>feature-tile</code>, <code>status-pill</code>, tabelas mobile e cards de ação. Não refatorar todas as views de uma vez sem testes.</div>
<?php require __DIR__.'/layout_bottom.php'; ?>
