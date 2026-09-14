const { test, expect } = require('@playwright/test');
const email = process.env.HUB_USER;
const password = process.env.HUB_PASS;
const routes = ['dashboard','pedidos','produtos','estoque-dashboard','fiscal','fila','vsm-saude','logs','configuracoes','backups'];
const sizes = [
  {name:'android-small', width:360, height:800},
  {name:'iphone', width:390, height:844},
  {name:'tablet', width:768, height:1024},
  {name:'notebook', width:1366, height:768},
  {name:'desktop', width:1920, height:1080},
];
test.skip(!email || !password, 'Defina HUB_USER e HUB_PASS para regressão visual autenticada');
for (const size of sizes) {
 test.describe(size.name, () => {
  test.use({ viewport: {width:size.width,height:size.height} });
  test('principais páginas sem erro fatal ou overflow global', async ({page}) => {
   await page.goto('/index.php?page=login');
   await page.fill('input[name="email"]', email);
   await page.fill('input[name="senha"], input[name="password"]', password);
   await page.click('button[type="submit"]');
   for (const route of routes) {
    await page.goto('/index.php?page='+route, {waitUntil:'domcontentloaded'});
    await expect(page.locator('body')).not.toContainText(/Fatal error|Uncaught|PDOException|SQLSTATE\[/i);
    const metrics = await page.evaluate(() => ({scrollWidth:document.documentElement.scrollWidth, clientWidth:document.documentElement.clientWidth, scrollHeight:document.documentElement.scrollHeight, clientHeight:document.documentElement.clientHeight}));
    expect(metrics.scrollWidth).toBeLessThanOrEqual(metrics.clientWidth + 2);
    if (metrics.scrollHeight > metrics.clientHeight + 20) {
      await page.evaluate(() => window.scrollTo(0, Math.min(300, document.documentElement.scrollHeight)));
      expect(await page.evaluate(() => window.scrollY)).toBeGreaterThan(0);
    }
    await page.screenshot({path:`test-results/visual-${size.name}-${route}.png`, fullPage:true});
   }
  });
 });
}
