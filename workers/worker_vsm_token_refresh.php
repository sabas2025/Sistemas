<?php
if (PHP_SAPI !== 'cli') {
  http_response_code(403);
  header('Content-Type: text/plain; charset=utf-8');
  echo "Worker bloqueado para execução via navegador. Execute pelo terminal/Agendador usando php ".basename(__FILE__).".";
  exit;
}
/**
 * Worker de renovação proativa do token da VSM Conecta Venda — F6-07 etapa 5 (2026-09-26).
 *
 * A VSM autentica por POST /v1/auth/token {clientToken, clientSecret} -> JWT (expiresIn ~7200s).
 * DIFERENTE do Tiny V3, a VSM NÃO usa refresh_token: as credenciais ficam sempre disponíveis, então
 * a renovação sob demanda (VsmTokenService::accessToken() quando o JWT expira) nunca perde a
 * capacidade de reautenticar. Este worker NÃO é salva-vidas como o do Tiny V3 — é aquecimento e
 * alarme precoce: mantém o JWT quente (o primeiro pedido de uma rajada não paga a latência da
 * troca) e falha cedo se as credenciais estiverem erradas.
 *
 * ADITIVO e GUARDADO: sem credenciais VSM configuradas, sai 0 sem fazer nada. Nunca bloqueia
 * chamada legítima; no máximo emite um token a mais.
 *
 * Agende no cron a cada hora (folgado dentro da janela de ~2h do JWT):
 *
 *     0 STAR/1 STAR STAR STAR php /caminho/para/workers/worker_vsm_token_refresh.php >> /var/log/hub-vsm-token.log 2>&1
 *
 * (troque STAR por asterisco; a sequência literal fecharia este bloco de comentário)
 *
 * Sem rede (ambiente sem acesso à VSM) a renovação falha e o worker sai 1 — comportamento correto,
 * igual ao worker de consulta de estoque VSM.
 */
require_once __DIR__.'/../app/Core/Autoload.php';
WorkerCliGuardService::enforce(basename(__FILE__));
require_once __DIR__.'/../app/Core/Helpers.php';
App::setupErrors();

// Renova quando faltam menos de 30 min para o JWT (~2h) vencer, mantendo-o quente para o cron horário.
const VSM_WARM_MINUTES = 30;

$inicio = microtime(true);

try {
    $st = VsmTokenService::status();

    // Guarda: precisa de base + credenciais da integradora configuradas.
    if (empty($st['base_configurada']) || empty($st['client_token_configurado']) || empty($st['client_secret_configurado'])) {
        echo "[OK] VSM não configurada (URL/clientToken/clientSecret ausentes) — nada a renovar.\n";
        exit(0);
    }

    $expiraTs = !empty($st['expira_em']) ? strtotime((string)$st['expira_em']) : false;
    $minutosRestantes = $expiraTs ? ($expiraTs - time()) / 60 : -1;

    if (!empty($st['tem_token_cache']) && empty($st['expirado']) && $minutosRestantes > VSM_WARM_MINUTES) {
        printf("[OK] Token VSM saudável (expira em %.1f min) — nada a fazer.\n", $minutosRestantes);
        exit(0);
    }

    $motivo = empty($st['tem_token_cache']) ? 'sem token em cache'
        : (!empty($st['expirado']) ? 'token expirado' : sprintf('preventivo (%.1f min p/ vencer)', $minutosRestantes));
    $r = VsmTokenService::refresh(true);
    if (empty($r['erro'])) {
        printf("[OK] Token VSM emitido (%s) em %.2fs. Expira em %s.\n", $motivo, microtime(true) - $inicio, (string)($r['expira_em'] ?? '?'));
        exit(0);
    }

    fwrite(STDERR, sprintf("[FALHA] Emissão de token VSM (%s): %s [%s]\n", $motivo, (string)$r['erro'], (string)($r['codigo_erro'] ?? '')));
    exit(1);
} catch (Throwable $e) {
    fwrite(STDERR, "[FALHA] Worker VSM token refresh: ".$e->getMessage()."\n");
    exit(1);
}
