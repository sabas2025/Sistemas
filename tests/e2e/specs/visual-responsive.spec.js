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
      // PR #2: a folha scroll-enterprise.css (correção V104.39) declara html{scroll-behavior:smooth}.
      // Com isso window.scrollTo ANIMA e retorna na hora, e ler window.scrollY no evaluate seguinte
      // pegava 0 — não porque a página estivesse travada, mas porque a animação ainda não tinha
      // andado. expect.poll espera a rolagem assentar e continua provando o que importa: que rola.
      await expect.poll(() => page.evaluate(() => window.scrollY), {timeout: 5000}).toBeGreaterThan(0);
    }
    await page.screenshot({path:`test-results/visual-${size.name}-${route}.png`, fullPage:true});
   }
  });
 });
}
