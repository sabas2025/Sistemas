<?php require __DIR__.'/layout_top.php'; ?>
<div class="hero-sabas mb-4">
  <div class="signature">Hub de Integração ↔ Tiny</div>
  <h2><?=e($branding)?></h2>
  <p class="mb-0">Plataforma de integração operacional entre VSM e Tiny.</p>
</div>

<div class="row g-3">
  <div class="col-lg-5">
    <div class="build-card h-100">
      <h4>Identidade</h4>
      <table class="table table-sm">
        <tr><th>Produto</th><td>Hub de Integração Enterprise</td></tr>
        <tr><th>Desenvolvedor</th><td><b><?=e($assinatura)?></b></td></tr>
        <tr><th>Ambiente</th><td><?=e(App::env())?></td></tr>
      </table>
    </div>
  </div>
  <div class="col-lg-7">
    <div class="build-card h-100">
      <h4>Função do sistema</h4>
      <p>O HUB centraliza pedidos, estoque, produtos, NF-e/XML, filas, auditoria e validações entre VSM e Tiny.</p>
      <ul class="mb-0">
        <li>VSM como fonte real do estoque.</li>
        <li>Validação antes de envio entre sistemas.</li>
        <li>Auditoria completa com Trace ID.</li>
        <li>Reprocessamento controlado por filas.</li>
      </ul>
    </div>
  </div>
</div>
<?php require __DIR__.'/layout_bottom.php'; ?>
