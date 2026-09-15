const { test, expect } = require('@playwright/test');
const visualTest = process.env.PWA_BROWSER_TEST === '0' ? test.skip : test;

test.describe('PWA V104.48', () => {
  test('manifesto possui identidade, escopo e ícones essenciais', async ({ request }) => {
    const response = await request.get('/manifest.webmanifest');
    expect(response.ok()).toBeTruthy();
    const manifest = await response.json();
    expect(manifest.id).toBe('./');
    expect(manifest.scope).toBe('./');
    expect(manifest.display).toBe('standalone');
    expect(manifest.icons.some(i => i.sizes === '192x192' && /maskable/.test(i.purpose || ''))).toBeTruthy();
    expect(manifest.icons.some(i => i.sizes === '512x512')).toBeTruthy();
  });

  test('service worker não cacheia páginas operacionais', async ({ request }) => {
    const response = await request.get('/sw.js');
    expect(response.ok()).toBeTruthy();
    const source = await response.text();
    expect(source).toContain("HUB_VERSION = '104.49.3'");
    expect(source).toContain("request.mode==='navigate'");
    expect(source).toContain("cache:'no-store'");
    expect(source).toContain('validAssetResponse');
    expect(source).not.toContain('caches.keys().then(keys=>Promise.all(keys.map');
  });

  test('estrutura da tela offline permanece clara e externa scripts/estilos', async ({ request }) => {
    const htmlResponse = await request.get('/offline.html');
    expect(htmlResponse.ok()).toBeTruthy();
    const html = await htmlResponse.text();
    expect(html).toContain('offline.min.css?v=104.49.3');
    expect(html).toContain('offline.min.js?v=104.49.3');
    expect(html).not.toContain('background: radial-gradient');
    expect(html).not.toMatch(/Fatal error|SQLSTATE|PDOException/i);
    const cssResponse = await request.get('/assets/offline.min.css?v=104.49.3');
    expect(cssResponse.ok()).toBeTruthy();
    const css = await cssResponse.text();
    expect(css).toContain('#f8fafc');
  });

  visualTest('tela offline é clara, acessível e sem erro técnico', async ({ page, request }) => {
    // Usa o request context para carregar os arquivos e renderiza localmente. Isso evita
    // falsos negativos em runners corporativos que bloqueiam navegação do Chromium para loopback.
    const htmlResponse = await request.get('/offline.html');
    const cssResponse = await request.get('/assets/offline.min.css?v=104.49.3');
    expect(htmlResponse.ok()).toBeTruthy();
    expect(cssResponse.ok()).toBeTruthy();
    await page.setContent(await htmlResponse.text(), { waitUntil: 'domcontentloaded' });
    await page.addStyleTag({ content: await cssResponse.text() });
    await expect(page.locator('main, [role="main"]')).toBeVisible();
    await expect(page.locator('body')).not.toContainText(/Fatal error|SQLSTATE|PDOException/i);
    const bg = await page.locator('body').evaluate(el => getComputedStyle(el).backgroundColor);
    expect(bg).not.toBe('rgb(2, 6, 23)');
    await expect(page.locator('#reloadBtn')).toBeVisible();
  });

  test('telemetria rejeita GET e eventos fora da lista branca', async ({ request }) => {
    const get = await request.get('/pwa_telemetry.php');
    expect(get.status()).toBe(405);

    // PR #2: o endpoint checa Sec-Fetch-Site ANTES da lista branca e trata a AUSÊNCIA do cabeçalho
    // como não confiável — é a correção do achado A-07, que fechava a porta para curl e scripts.
    // O `request` do Playwright é cliente HTTP, não navegação de navegador, e não envia esse
    // cabeçalho: por isso a resposta era 403 e o ramo do 422 nunca era alcançado. Medido no
    // endpoint: sem o cabeçalho 403; com ele e evento fora da lista 422; com evento válido 202.
    // Agora o teste cobre as DUAS garantias, em vez de só tentar a segunda.
    const semOrigem = await request.post('/pwa_telemetry.php', { data: { event: 'token_dump', message: 'x' } });
    expect(semOrigem.status(), 'sem Sec-Fetch-Site o endpoint recusa (A-07)').toBe(403);

    const invalid = await request.post('/pwa_telemetry.php', {
      headers: { 'Sec-Fetch-Site': 'same-origin' },
      data: { event: 'token_dump', message: 'x' }
    });
    expect(invalid.status(), 'evento fora da lista branca é recusado com 422').toBe(422);
  });
});
