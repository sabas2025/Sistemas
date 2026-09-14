-- V104.27 - Reset seguro de 2FA para recuperar primeiro acesso administrativo
-- Use APENAS se o administrador ficou bloqueado por código 2FA inválido.
-- Troque o e-mail abaixo pelo e-mail do administrador criado no install.php.
-- Depois de entrar no painel, recadastre o 2FA pelo menu de usuários/segurança.

UPDATE usuarios
SET two_factor_enabled = 0,
    two_factor_secret = NULL,
    two_factor_created_at = NULL,
    two_factor_last_verified_at = NULL,
    tentativas_login = 0,
    bloqueado_ate = NULL
WHERE email = 'admin@hub.com'
  AND perfil = 'admin';
