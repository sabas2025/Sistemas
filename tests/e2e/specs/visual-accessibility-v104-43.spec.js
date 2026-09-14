const { test, expect } = require('@playwright/test');
const baseURL = process.env.HUB_BASE_URL || 'http://127.0.0.1:8000/public/';
const user = process.env.HUB_USER || '';
const pass = process.env.HUB_PASS || '';
async function login(page){
  await page.goto(baseURL+'index.php?page=login');
  if(await page.locator('input[name="email"],input[name="usuario"]').count()){
    await page.locator('input[name="email"],input[name="usuario"]').first().fill(user);
    await page.locator('input[name="senha"],input[type="password"]').first().fill(pass);
    await Promise.all([page.waitForLoadState('networkidle'),page.locator('button[type="submit"]').first().click()]);
  }
}
test.describe('V104.43 visual accessibility',()=>{
  test.beforeEach(async({page})=>{await login(page);});
  test('command palette and keyboard navigation',async({page})=>{
    await page.goto(baseURL+'index.php?page=dashboard');
    await page.keyboard.press('Control+K');
    await expect(page.locator('[data-hub-command-palette]')).toBeVisible();
    await page.locator('[data-hub-command-input]').fill('Pedidos');
    await expect(page.locator('[data-hub-command-results]')).toContainText('Pedidos');
    await page.keyboard.press('Escape');
    await expect(page.locator('[data-hub-command-palette]')).toBeHidden();
  });
  for(const size of [{name:'landscape-mobile',width:844,height:390},{name:'zoom-200',width:683,height:384}]){
    test(`layout ${size.name}`,async({page})=>{
      await page.setViewportSize({width:size.width,height:size.height});
      await page.goto(baseURL+'index.php?page=dashboard');
      if(size.name==='zoom-200')await page.evaluate(()=>document.documentElement.style.zoom='2');
      const overflow=await page.evaluate(()=>document.documentElement.scrollWidth-document.documentElement.clientWidth);
      expect(overflow).toBeLessThanOrEqual(3);
      await expect(page.locator('#hubMainContent')).toBeVisible();
    });
  }
  test('dark theme persists',async({page})=>{
    await page.goto(baseURL+'index.php?page=dashboard');
    await page.locator('[data-topbar-more]').click();
    await page.locator('[data-hub-theme]').click();
    await expect(page.locator('body')).toHaveAttribute('data-hub-theme','dark');
    await page.reload();
    await expect(page.locator('body')).toHaveAttribute('data-hub-theme','dark');
  });
});
