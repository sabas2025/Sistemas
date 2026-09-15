const { test, expect } = require('@playwright/test');

const email = process.env.HUB_USER;
const password = process.env.HUB_PASS;

test.skip(!email || !password, 'Defina HUB_USER e HUB_PASS para smoke autenticado');

test('login e telas técnicas principais', async ({ page }) => {
  await page.goto('/index.php?page=login');
  await page.fill('input[name="email"]', email);
  await page.fill('input[name="senha"], input[name="password"]', password);
  await page.click('button[type="submit"]');
  await expect(page.locator('body')).not.toContainText(/Fatal error|SQLSTATE|PDOException/i);
  // PR #2: sem esta conferência o teste seguia adiante DESAUTENTICADO e falhava na linha do
  // diagnóstico, com uma mensagem que não apontava o login. Foi assim que a marca
  // deve_trocar_senha do administrador de CI ficou escondida: o login funcionava, a sessão era
  // criada, e FastRouteDispatcherService mandava toda rota para a troca de senha.
  await expect(page.locator('#hubMainContent'), 'o login precisa autenticar e cair no painel, não na troca de senha').toBeVisible({timeout:15000});
  await page.goto('/index.php?page=diagnostico-config-real');
  await expect(page.locator('body')).toContainText(/Diagnóstico de Configuração Real/i);
  await page.goto('/index.php?page=security-assisted-test');
  // PR #2: a tela se chama "Teste DE Segurança Assistido" — é assim no <title>, no <h2> de
  // views/security_assisted_test.php, no $pageTitle do controller e no relatório do serviço.
  // A única grafia sem o "de" é o rótulo do menu lateral (views/layout_top.php:118), que só
  // aparece quando o perfil visual em uso mostra o grupo Segurança. A asserção antiga passava por
  // acidente, quando o item do menu era renderizado, e falhava no ambiente recém-provisionado do
  // CI, onde não é. Passa a afirmar sobre o título da própria tela, que é o que o teste quer.
  await expect(page.locator('body')).toContainText(/Teste de Segurança Assistido/i);
});
