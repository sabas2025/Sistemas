<!doctype html>
<html lang="pt-br">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Erro - Hub de Integração</title>
  <link href="assets/app.css" rel="stylesheet">
</head>
<body class="login-page login-futurista">
  <div class="login-bg-orb orb-a"></div>
  <div class="login-bg-orb orb-b"></div>
  <div class="login-bg-grid"></div>
  <main class="login-shell error-shell" aria-label="Erro do Hub de Integração">
    <section class="login-card login-card-futurista error-card">
      <div class="login-brand-block">
        <div class="hub-logo-sm" aria-hidden="true"><img src="assets/img/hub-integracao-logo.svg" alt=""></div>
        <div>
          <h3 class="fw-bold mb-1">Hub de Integração</h3>
          <p class="text-muted mb-0">Erro operacional registrado com rastreabilidade.</p>
        </div>
      </div>
      <div class="alert alert-danger mt-4">
        <b>Erro no sistema</b><br>
        Trace ID: <strong><?= e($trace ?? '') ?></strong>
      </div>
      <p class="text-muted">Abra o painel em <b>Auditoria</b> e pesquise pelo Trace ID para ver causa provável e ação recomendada.</p>
      <?php if(!empty($isLocal)): ?>
        <div class="codebox mt-3"><b>Mensagem:</b> <?= e($message ?? '') ?>\n\n<?= e($where ?? '') ?></div>
      <?php endif; ?>
      <a class="btn btn-primary w-100 mt-4" href="index.php?page=login">Voltar ao acesso</a>
    </section>
  </main>
</body>
</html>
