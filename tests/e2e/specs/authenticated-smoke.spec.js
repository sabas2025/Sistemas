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
  await page.goto('/index.php?page=diagnostico-config-real');
  await expect(page.locator('body')).toContainText(/Diagnóstico de Configuração Real/i);
  await page.goto('/index.php?page=security-assisted-test');
  await expect(page.locator('body')).toContainText(/Teste Segurança Assistido/i);
});
