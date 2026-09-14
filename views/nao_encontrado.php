<?php
/**
 * Reauditoria 2026-09-14 (achado A-13): rotas desconhecidas caíam no dashboard, mascarando
 * links quebrados e produzindo falso positivo em smoke tests (qualquer URL respondia 200).
 * Esta página devolve 404 de verdade, sem expor rota, stack ou qualquer detalhe interno.
 */
$pageTitle = $pageTitle ?? 'Página não encontrada';
?>
<!doctype html>
<html lang="pt-br">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Página não encontrada - Hub de Integração</title>
  <link href="assets/app.css" rel="stylesheet">
</head>
<body class="login-page login-futurista">
  <div class="login-bg-orb orb-a"></div>
  <div class="login-bg-orb orb-b"></div>
  <div class="login-bg-grid"></div>
  <main class="login-shell error-shell">
    <section class="login-card login-card-futurista error-card">
      <div class="login-brand-block">
        <div class="hub-logo-sm"><img src="assets/img/hub-integracao-logo.svg" alt=""></div>
        <div>
          <h3 class="fw-bold mb-1">Página não encontrada</h3>
          <p class="text-muted mb-0">O endereço acessado não corresponde a nenhuma tela do sistema.</p>
        </div>
      </div>
      <p class="text-muted mt-4">Verifique o link ou volte ao painel para continuar.</p>
      <a class="btn btn-primary w-100 mt-3" href="index.php?page=dashboard">Ir para o painel</a>
    </section>
  </main>
</body>
</html>
