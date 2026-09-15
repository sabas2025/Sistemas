const { test, expect } = require('@playwright/test');

test('login público não expõe erro técnico', async ({ page }) => {
  await page.goto('/index.php');
  await expect(page.locator('body')).toContainText(/e-mail|email|senha|Hub/i);
  await expect(page.locator('body')).not.toContainText(/Fatal error|Stack trace|SQLSTATE|PDOException/i);
});

test('install.php deve estar bloqueado após instalação', async ({ request }) => {
  const res = await request.get('/install.php');
  expect([403,404,410,302]).toContain(res.status());
});

test('workers públicos devem bloquear navegador', async ({ request }) => {
  const res = await request.get('/worker_fila.php');
  expect([403,404]).toContain(res.status());
});
