<?php
declare(strict_types=1);
require __DIR__.'/_helpers.php';
$checks = [];

/**
 * Fase 3 (decomposição do controller-deus, 2026-09-27) — etapa Usuários & Sessão.
 *
 * A gestão de usuários/permissões e a troca de senha (usuarios, usuario-salvar, usuario-excluir,
 * permissoes-salvar, trocar-senha) saíram do DashboardController (A3-01) para o novo
 * UsuarioController, despachado pelo FastRouteDispatcherService::$dispatchGroups. Reprova sobre o
 * código antigo (os handlers estavam no Dashboard). Handlers movidos verbatim; PermissionService,
 * Csrf, Auth e a revogação de sessão (session_version) preservados.
 *
 * Comportamento medido contra MariaDB provisionado por HTTP: usuarios responde 200 autenticado;
 * usuario-salvar/-excluir/permissoes-salvar/trocar-senha (POST/CSRF) recusam GET com 403 (trocar-senha
 * via GET renderiza a tela). O gate de troca obrigatória segue no dispatcher (antes do roteamento).
 */
$dash = hub_read('app/Controllers/DashboardController.php');
$usr  = hub_read('app/Controllers/UsuarioController.php');
$disp = hub_read('app/Services/FastRouteDispatcherService.php');

$metodos = ['usuarios','usuarioSalvar','usuarioExcluir','permissoesSalvar','trocarSenha'];
$rotas   = ['usuarios','usuario-salvar','usuario-excluir','permissoes-salvar','trocar-senha'];

hub_check($checks, 'UsuarioController lido (N>0)', strlen($usr) > 800);
hub_check($checks, 'UsuarioController estende BaseModuleController', str_contains($usr, 'extends BaseModuleController'));
hub_check($checks, 'UsuarioController inicializa $pdo próprio', str_contains($usr, 'Database::getConnection()'));
hub_check($checks, 'UsuarioController tem helper db()', str_contains($usr, 'function db(string $table): PDO'));
foreach ($metodos as $m) {
    hub_check($checks, "UsuarioController tem o handler {$m}", str_contains($usr, "function {$m}("));
}
foreach (['usuario-salvar','usuario-excluir','permissoes-salvar','trocar-senha'] as $r) {
    hub_check($checks, "UsuarioController despacha {$r}", str_contains($usr, "case '{$r}':"));
}
// Preservação de segurança: revogação de sessão e CSRF viajaram junto.
hub_check($checks, 'UsuarioController preserva bump de session_version', str_contains($usr, 'session_version=COALESCE(session_version,0)+1'));
hub_check($checks, 'UsuarioController preserva Csrf::validate', str_contains($usr, 'Csrf::validate'));
hub_check($checks, 'UsuarioController preserva trava do último admin', str_contains($usr, 'ultimo_admin'));

hub_check($checks, 'dispatchGroups registra UsuarioController', str_contains($disp, 'UsuarioController::class => ['));
foreach ($rotas as $r) {
    hub_check($checks, "rota {$r} mapeada no dispatchGroups", (bool)preg_match('/UsuarioController::class => \[[^\]]*\''.preg_quote($r,'/').'\'/', $disp));
}
// O gate de troca de senha obrigatória continua no dispatcher.
hub_check($checks, 'gate de troca de senha permanece no dispatcher', str_contains($disp, "\$page !== 'trocar-senha'"));

hub_check($checks, 'DashboardController lido (N>0)', strlen($dash) > 1000);
foreach ($metodos as $m) {
    hub_check($checks, "DashboardController não tem mais o handler {$m}", $dash !== '' && !str_contains($dash, "function {$m}("));
}
foreach ($rotas as $r) {
    hub_check($checks, "DashboardController não tem mais o case {$r}", !str_contains($dash, "case '{$r}'"));
}

hub_check($checks, 'DashboardController abaixo de 160 KB', strlen($dash) < 160*1024, 'bytes='.strlen($dash));

hub_finish($checks);
