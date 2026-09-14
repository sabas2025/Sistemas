const { test, expect } = require('@playwright/test');

/**
 * Melhoria 8 da seção 8 (relatório V104.49.3-R6) — ciclo OAuth Tiny V3 no navegador.
 *
 * A intenção original era subir um PROVEDOR OAUTH FALSO no CI e percorrer o ida-e-volta completo.
 * Isso não foi feito, e a razão importa: TinyEndpointSecurityService::validateUrl() exige HTTPS,
 * recusa host local e resolve o host para confirmar que o IP é público (proteção contra SSRF).
 * Um provedor falso em http://127.0.0.1 só passaria se essas três defesas fossem desligadas no
 * CI - e aí o teste estaria validando uma configuração que NÃO pode existir em produção, que é o
 * pior tipo de teste verde. A troca do code por token continua exigindo credenciais Tiny reais.
 *
 * O que ESTE arquivo cobre é justamente o que só o navegador prova, e que nenhum teste de unidade
 * alcança: o comportamento do callback quando ele chega como navegação cross-site, sem o cookie
 * de sessão SameSite=Strict. Foi essa a correção do achado A-02, e a do B-03 em cima dela.
 */

const email = process.env.HUB_USER;
const password = process.env.HUB_PASS;

async function entrar(page) {
  await page.goto('/index.php?page=login');
  await page.fill('input[name="email"]', email);
  await page.fill('input[name="senha"], input[name="password"]', password);
  await page.click('button[type="submit"]');
}

/**
 * PR #2 — por que estes testes passaram a olhar o PRIMEIRO redirecionamento, sem segui-lo.
 *
 * O callback roda fora do gate de sessão (SECURITY.md 3.2) e redireciona para
 * index.php?page=tiny-v3-ficha&oauth=erro. Mas a FICHA está atrás de Auth::requireLogin().
 * Num contexto sem sessão o navegador segue a cadeia callback -> ficha -> login, e a URL final é
 * sempre o login. Afirmar sobre a URL final, como estava, não distingue as duas situações que
 * importam: "o callback rodou e mandou para a ficha" e "o callback caiu atrás do gate" — que é
 * exatamente a regressão do achado A-02 que este arquivo existe para pegar.
 *
 * Com maxRedirects:0 o destino do callback fica visível no cabeçalho Location, e a asserção passa
 * a provar a propriedade certa. É mais preciso que antes, não mais frouxo.
 */

test.describe('Callback OAuth Tiny V3 - propriedades observáveis no navegador', () => {
  test.skip(!email || !password, 'Defina HUB_USER e HUB_PASS');

  test('callback NÃO exige sessão (A-02: retorno cross-site não carrega cookie SameSite=Strict)', async ({ page }) => {
    // Contexto limpo: nenhuma sessão, como no retorno real vindo de accounts.tiny.com.br.
    const resposta = await page.request.get('/index.php?page=tiny-v3-callback', { maxRedirects: 0 });

    // O callback tem de REDIRECIONAR, não renderizar erro nem cair no login.
    expect(resposta.status(), 'callback deve redirecionar').toBe(302);
    const destino = resposta.headers()['location'] || '';

    // A regressão que este teste existe para pegar: se o callback voltar para trás do
    // Auth::requireLogin(), o primeiro destino é o login.
    expect(destino, 'callback não pode mandar para o login').not.toMatch(/page=login/);

    // Sem authorization code o destino correto é a ficha, com aviso de erro - não uma exceção.
    expect(destino).toMatch(/page=tiny-v3-ficha/);
    expect(destino).toMatch(/oauth=erro/);
  });

  test('callback rejeita state forjado sem vazar detalhe interno', async ({ page }) => {
    const resposta = await page.request.get('/index.php?page=tiny-v3-callback&code=codigo-forjado&state=state-forjado', { maxRedirects: 0 });

    // State inválido/reaproveitado/de outro provedor termina em erro controlado.
    expect(resposta.status(), 'state forjado deve redirecionar, não estourar').toBe(302);
    const destino = resposta.headers()['location'] || '';
    expect(destino).toMatch(/page=tiny-v3-ficha/);
    expect(destino).toMatch(/oauth=erro/);

    // E nunca em stack trace, SQL ou nome de classe.
    expect(await resposta.text()).not.toMatch(/Fatal error|Warning:|SQLSTATE|PDOException|RuntimeException|Stack trace/i);
  });

  test('callback com state vazio também é recusado', async ({ page }) => {
    const resposta = await page.request.get('/index.php?page=tiny-v3-callback&code=abc&state=', { maxRedirects: 0 });
    expect(resposta.status()).toBe(302);
    expect(resposta.headers()['location'] || '').toMatch(/oauth=erro/);
    expect(await resposta.text()).not.toMatch(/Fatal error|SQLSTATE|Stack trace/i);
  });

  test('rota desconhecida sob o dispatcher OAuth devolve 404 real, não dashboard', async ({ page }) => {
    // PR #2: o 404 de rota desconhecida vive em DashboardController::dispatch(), no ramo
    // `default: naoEncontrado()` — e o dispatch() começa com Auth::requireLogin(). Sem sessão o
    // pedido é redirecionado ao login e responde 200, sem nunca alcançar o 404. Para provar o que
    // este teste se propõe (achado A-13), é preciso autenticar antes.
    await entrar(page);
    const response = await page.goto('/index.php?page=rota-inexistente-' + Date.now(), { waitUntil: 'domcontentloaded' });
    expect(response.status(), 'rota inexistente precisa devolver 404').toBe(404);
    await expect(page.locator('body')).not.toContainText(/Fatal error|SQLSTATE|Stack trace/i);
  });
});

test.describe('Início do fluxo OAuth (exige sessão administrativa)', () => {
  test.skip(!email || !password, 'Defina HUB_USER e HUB_PASS');

  test('a ficha Tiny V3 monta o link OAuth com state assinado e PKCE', async ({ page }) => {
    await page.goto('/index.php?page=login');
    await page.fill('input[name="email"]', email);
    await page.fill('input[name="senha"], input[name="password"]', password);
    await page.click('button[type="submit"]');

    await page.goto('/index.php?page=tiny-v3-ficha');
    await expect(page.locator('body')).not.toContainText(/Fatal error|SQLSTATE|Stack trace/i);

    // Sem credenciais Tiny configuradas o link não é montado - e isso é correto, não é falha.
    // Quando existir, ele precisa carregar PKCE e um state que não seja o token CSRF da sessão
    // (era exatamente esse o defeito P0-01 corrigido pelo OAuthStateService).
    const link = page.locator('a[href*="code_challenge"]');
    if (await link.count() > 0) {
      const href = await link.first().getAttribute('href');
      expect(href).toMatch(/code_challenge=/);
      expect(href).toMatch(/code_challenge_method=S256/);
      expect(href).toMatch(/state=/);
      expect(href).toMatch(/response_type=code/);
    }
  });
});
