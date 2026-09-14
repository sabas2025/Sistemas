<?php
declare(strict_types=1);
require __DIR__.'/_helpers.php';
$checks=[];
class App { public static function config():array{return ['security'=>['password_min_length'=>10]];} }
require_once hub_root().'/app/Services/PasswordPolicyService.php';
hub_check($checks,'Senha forte compatível é aceita',PasswordPolicyService::isValid('ForteSenha9!','usuario@example.com'));
hub_check($checks,'Senha curta/comum é bloqueada',!PasswordPolicyService::isValid('admin123','usuario@example.com'));
hub_check($checks,'Senha contendo nome do e-mail é bloqueada',!PasswordPolicyService::isValid('UsuarioForte9!','usuario@example.com'));
$generated=PasswordPolicyService::generateTemporary();
hub_check($checks,'Senha temporária gerada cumpre a política',PasswordPolicyService::isValid($generated,'novo@example.com')&&strlen($generated)>=16);
$dash=hub_read('app/Controllers/DashboardController.php');
hub_check($checks,'Troca de senha incrementa versão da sessão',str_contains($dash,'session_version=COALESCE(session_version,0)+1')&&str_contains($dash,'$_SESSION[\'session_version\']'));
hub_check($checks,'Senha temporária é exibida somente uma vez',str_contains($dash,'unset($_SESSION[\'senha_temporaria_ultima\'])')&&str_contains(hub_read('views/usuarios.php'),'Senha temporária exibida uma única vez'));
hub_finish($checks);
