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
    // PR #2, dois defeitos deste teste (o Hub está correto nos dois casos):
    // 1. O menu .topbar-more é MOBILE por desenho: app.css declara display:none e só o mostra em
    //    @media(max-width:991.98px). O teste rodava no viewport padrão (1280x720), onde o botão
    //    está corretamente escondido, e o clique esperava para sempre. Daí o viewport estreito.
    // 2. O seletor [data-hub-theme] casa com o <body> (que carrega data-hub-theme="light"), não
    //    com o botão. O botão é [data-hub-theme-toggle]. Clicar no body não alterna nada, então a
    //    asserção da linha seguinte falharia mesmo com o menu aberto.
    await page.setViewportSize({width:390,height:844});
    await page.goto(baseURL+'index.php?page=dashboard');
    await page.locator('[data-topbar-more]').click();
    await page.locator('[data-hub-theme-toggle]').click();
    await expect(page.locator('body')).toHaveAttribute('data-hub-theme','dark');
    await page.reload();
    await expect(page.locator('body')).toHaveAttribute('data-hub-theme','dark');
  });
});
