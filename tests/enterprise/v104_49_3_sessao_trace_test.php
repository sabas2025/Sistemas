<?php
declare(strict_types=1);
require __DIR__.'/_helpers.php';
$checks = [];

/**
 * Login/sessão (fase 8) e observabilidade (fase 11) — auditoria de 2026-09-15.
 *
 * Achado I-17: `auditoria-detalhe` lia APENAS `?id=`, mas `auditoriaAssinarTrace()` redireciona
 * para ela com `?trace_id=`. Assinar um trace — ação forense — levava o operador a um
 * **404 "Evento não encontrado"**, sem confirmação de que a assinatura funcionou. A tela de
 * Auditoria linka com `?id=` e sempre funcionou; só o retorno da assinatura quebrava.
 *
 * Achado I-16: o provisionamento de E2E relaxa os limites de login em ~100x. Não é defeito — sem
 * isso a suíte se trancaria —, mas o bloco não dizia nada, e medir força bruta contra aquele
 * ambiente leva à conclusão errada de que o limitador não existe.
 */

// ------------------------------------------------------------------ I-17
$aud = hub_read('app/Controllers/AuditoriaController.php');
hub_check($checks, 'O detalhe da auditoria aceita ?id= e ?trace_id=',
    str_contains($aud, "\$_GET['id']") && str_contains($aud, "\$_GET['trace_id']"));
hub_check($checks, 'A busca por trace pega o primeiro evento e monta a linha do tempo',
    str_contains($aud, 'WHERE trace_id=? ORDER BY id ASC LIMIT 1')
    && str_contains($aud, 'WHERE trace_id=? ORDER BY id ASC'));
hub_check($checks, 'O handler mora no AuditoriaController, com dispatch',
    str_contains($aud, 'public function dispatch(string $page)') && str_contains($aud, 'public function detalhe()'));

$dash = hub_read('app/Controllers/DashboardController.php');
// A segunda metade é uma asserção NEGATIVA, e negativa sobre arquivo ausente passa por engano
// (hub_read devolve string vazia). O $dash !== '' à frente é o que a torna honesta — achado I-20.
hub_check($checks, 'O DashboardController delega a rota e não guarda mais o handler',
    $dash !== ''
    && str_contains($dash, "case 'auditoria-detalhe': (new AuditoriaController())->dispatch(\$page); break;")
    && !str_contains($dash, 'private function auditoriaDetalhe'));
hub_check($checks, 'O redirect de assinatura continua usando trace_id (era o link que quebrava)',
    str_contains($dash, "page=auditoria-detalhe&trace_id='.urlencode(\$trace)"));

// O teto que motivou a mudança de lugar: medir, não supor.
$bytes = strlen($dash);
hub_check($checks, "DashboardController em {$bytes} bytes, abaixo do teto de 163840", $bytes < 163840);

// ------------------------------------------------------------------ I-16
$prov = hub_read('scripts/ci/provision-e2e-environment.php');
hub_check($checks, 'O provisionamento avisa que os limites de login são relaxados só para a E2E',
    str_contains($prov, 'RELAXADOS DE PROPÓSITO') && str_contains($prov, 'força bruta'));
hub_check($checks, 'Os padrões do código continuam restritivos (o relaxamento é só do ambiente de teste)',
    str_contains(hub_read('app/Core/Auth.php'), "login_ip_rate_limit_per_minute'] ?? 5")
    && str_contains(hub_read('app/Core/Auth.php'), "login_user_rate_limit_per_minute'] ?? 3"));

// ------------------------------------------------------------------ fase 8: o que foi medido por HTTP
$auth = hub_read('app/Core/Auth.php');
hub_check($checks, 'O limite é consultado ANTES de verificar a senha',
    strpos($auth, 'if (self::tooManyAttempts($email, $ip))') < strpos($auth, '$senhaValida = password_verify'));
hub_check($checks, 'Tentativa bloqueada ainda gasta um password_verify (tempo constante, sem enumerar usuário)',
    str_contains($auth, 'password_verify($senha, self::DUMMY_PASSWORD_HASH)'));
$idx = hub_read('public/index.php');
hub_check($checks, 'Cookie de sessão: HttpOnly, SameSite Strict e Secure condicionado ao HTTPS',
    str_contains($idx, "'httponly' => true") && str_contains($idx, "'samesite' => 'Strict'") && str_contains($idx, "'secure' => \$https"));
hub_check($checks, 'Sessão em modo estrito e só por cookie',
    str_contains($idx, "session.use_strict_mode', '1'") && str_contains($idx, "session.use_only_cookies', '1'"));

hub_finish($checks);
