const { defineConfig } = require('@playwright/test');

// A-03: falha cedo e com mensagem clara em vez de rodar contra destino indefinido/errado.
if (!process.env.HUB_BASE_URL) {
  throw new Error('HUB_BASE_URL é obrigatória. Ex.: HUB_BASE_URL=http://127.0.0.1:8000/public npx playwright test');
}

module.exports = defineConfig({
  testDir: './specs',
  timeout: 30000,
  use: {
    // Reauditoria 2026-09-14 (achado A-03): o padrão apontava para um host REMOTO de produção.
    // Sem HUB_BASE_URL definida, a suíte rodava contra o ambiente real - ou falhava por motivo
    // errado. Agora não há fallback remoto: a URL é obrigatória e explícita.
    baseURL: process.env.HUB_BASE_URL,
    trace: 'retain-on-failure',
    ignoreHTTPSErrors: false,
    launchOptions: process.env.PLAYWRIGHT_CHROMIUM_PATH ? { executablePath: process.env.PLAYWRIGHT_CHROMIUM_PATH } : {}
  },
  reporter: [['list'], ['html', { outputFolder: 'playwright-report', open: 'never' }]]
});
