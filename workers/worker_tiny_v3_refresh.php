<?php
if (PHP_SAPI !== 'cli') {
  http_response_code(403);
  header('Content-Type: text/plain; charset=utf-8');
  echo "Worker bloqueado para execução via navegador. Execute pelo terminal/Agendador usando php ".basename(__FILE__).".";
  exit;
}
/**
 * Worker de renovação proativa do token OAuth Tiny V3 — auditoria Fase 5 2026-09-21 (achado T-03).
 *
 * A documentação oficial da Olist/Tiny (autenticacao) define: o ACCESS token expira em 4 horas e o
 * REFRESH token dura apenas 1 DIA. O Hub só renovava o token SOB DEMANDA (quando `accessToken()`
 * encontrava o token expirado durante uma chamada de saída) ou pelo botão manual da ficha V3 —
 * NÃO havia renovação agendada. Consequência: se o Hub ficar mais de 1 dia sem fazer chamada V3
 * (fim de semana quieto, instalação que opera em V2, período de baixa), o refresh token expira e
 * só o OAuth manual reconecta.
 *
 * Este worker renova o token DENTRO da janela de 1 dia, mantendo o refresh token vivo. É ADITIVO e
 * GUARDADO: se a V3 não estiver configurada (sem client_id/secret/token_url) ou sem token salvo,
 * ele não faz nada e sai 0. Nunca bloqueia chamada legítima; no máximo faz uma renovação a mais.
 *
 * Agende no cron a cada 6 horas (folgado dentro da janela de 24h do refresh token):
 *
 *     0 STAR/6 STAR STAR STAR php /caminho/para/workers/worker_tiny_v3_refresh.php >> /var/log/hub-tiny-v3-refresh.log 2>&1
 *
 * (troque STAR por asterisco; a sequência literal fecharia este bloco de comentário)
 *
 * Só agende quando a V3 for o caminho operacional do cliente. Onde o Hub opera em V2
 * (`tiny.version='v2'`), o worker é inócuo — sai 0 sem renovar.
 *
 * Sem rede (ambiente sem acesso ao accounts.tiny.com.br) a renovação falha e o worker sai 1 —
 * comportamento correto, igual ao worker de consulta de estoque VSM.
 */
require_once __DIR__.'/../app/Core/Autoload.php';
WorkerCliGuardService::enforce(basename(__FILE__));
require_once __DIR__.'/../app/Core/Helpers.php';
App::setupErrors();

// Renova quando a última renovação foi há mais de METADE da janela do refresh token (24h),
// bem antes de ele morrer. O access token (4h) já estará expirado num Hub ocioso, então este
// limite é a rede de segurança para o caso de rotação do refresh token.
const REFRESH_SAFETY_HOURS = 12;

$inicio = microtime(true);

try {
    $cfg = IntegrationConfig::get();
    $amb = (string)($cfg['tiny_v3_ambiente'] ?? 'homologacao');

    // Guarda 1: V3 precisa estar configurada com credenciais e token URL.
    $configurada = trim((string)($cfg['tiny_v3_token_url'] ?? '')) !== ''
        && trim((string)($cfg['tiny_v3_client_id'] ?? '')) !== ''
        && trim((string)($cfg['tiny_v3_client_secret'] ?? '')) !== '';
    if (!$configurada) {
        echo "[OK] Tiny V3 não configurada (token_url/client_id/client_secret ausentes) — nada a renovar.\n";
        exit(0);
    }

    // Guarda 2: precisa haver token salvo para o ambiente (conexão OAuth já feita).
    $row = TinyV3TokenService::getTokenRow($amb);
    if (!$row) {
        echo "[OK] Sem token Tiny V3 salvo para o ambiente '{$amb}' — conecte via OAuth primeiro. Nada a renovar.\n";
        exit(0);
    }

    $expirado = TinyV3TokenService::isExpired($row);
    $ultima = strtotime((string)($row['atualizado_em'] ?? $row['criado_em'] ?? ''));
    $horasDesdeUltima = $ultima ? (time() - $ultima) / 3600 : PHP_INT_MAX;

    if (!$expirado && $horasDesdeUltima < REFRESH_SAFETY_HOURS) {
        printf("[OK] Token Tiny V3 saudável (última renovação há %.1f h; não expirado) — nada a fazer.\n", $horasDesdeUltima);
        exit(0);
    }

    $motivo = $expirado ? 'access token expirado' : sprintf('preventivo (última renovação há %.1f h)', $horasDesdeUltima);
    // force=true: renova mesmo sem chamada em curso, para manter o REFRESH token vivo dentro da janela de 1 dia.
    $r = TinyV3TokenService::refresh(true, $amb);
    if (empty($r['erro'])) {
        printf("[OK] Token Tiny V3 renovado (%s) em %.2fs.\n", $motivo, microtime(true) - $inicio);
        exit(0);
    }

    fwrite(STDERR, sprintf("[FALHA] Renovação Tiny V3 (%s): %s [%s]\n", $motivo, (string)$r['erro'], (string)($r['codigo_erro'] ?? '')));
    exit(1);
} catch (Throwable $e) {
    fwrite(STDERR, "[FALHA] Worker Tiny V3 refresh: ".$e->getMessage()."\n");
    exit(1);
}
