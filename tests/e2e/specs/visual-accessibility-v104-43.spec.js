const { test, expect } = require('@playwright/test');
const baseURL = process.env.HUB_BASE_URL || 'http://127.0.0.1:8000/public/';
const user = process.env.HUB_USER || '';
const pass = process.env.HUB_PASS || '';
/**
 * PR #2 — este helper era a causa das QUATRO falhas deste arquivo.
 *
 * Ele fazia `Promise.all([page.waitForLoadState('networkidle'), click()])`. Como a tela de login
 * já estava ociosa, o waitForLoadState resolvia de imediato e o Promise.all terminava assim que o
 * clique era DESPACHADO — antes de a navegação do POST completar. O beforeEach retornava, o teste
 * chamava goto(dashboard) e atropelava o POST em voo: a sessão nunca era criada.
 *
 * E o helper não conferia nada. Então os quatro testes caíam no login e falhavam mais adiante
 * procurando elementos que só existem no layout AUTENTICADO — #hubMainContent e
 * [data-topbar-more] — cada um com uma mensagem diferente, nenhuma apontando a causa.
 *
 * O spec irmão visual-responsive.spec.js sempre passou porque faz o clique simples, sem esse
 * Promise.all. Agora o login é CONFERIDO aqui: se não autenticar, falha neste ponto e diz por quê.
 */
async function login(page){
  await page.goto(baseURL+'index.php?page=login');
  const campoUsuario = page.locator('input[name="email"],input[name="usuario"]');
  if(await campoUsuario.count()){
    await campoUsuario.first().fill(user);
    await page.locator('input[name="senha"],input[type="password"]').first().fill(pass);
    await page.locator('button[type="submit"]').first().click();
  }
  await expect(page.locator('#hubMainContent'),'o login do E2E precisa autenticar antes dos testes').toBeVisible({timeout:15000});
}
test.describe('V104.43 visual accessibility',()=>{
  test.skip(!user || !pass, 'Defina HUB_USER e HUB_PASS');
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
