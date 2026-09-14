-- Reauditoria 2026-09-14 (achado A-10) — catálogo RBAC de backup incompleto.
-- O schema ativo semeava apenas backup.gerar e backup.visualizar, mas o BackupController
-- exige seis ações: visualizar, gerar, baixar, excluir, importar e restaurar. As quatro
-- ausentes não existiam no catálogo, então não podiam ser delegadas a perfis não
-- administrativos (o bypass de admin mantinha o fluxo funcionando e escondia a lacuna).
--
-- Política aplicada: admin recebe todas; gerente recebe apenas baixar; operador não recebe
-- nenhuma. Importar e restaurar permanecem negadas fora do admin por serem destrutivas.
--
-- Idempotente: INSERT IGNORE não altera permissões já existentes nem remove dados. Pode ser
-- executada novamente com segurança.

INSERT IGNORE INTO permissoes_perfil(perfil,modulo,acao,permitido) VALUES
('admin','backup','baixar',1),
('admin','backup','excluir',1),
('admin','backup','importar',1),
('admin','backup','restaurar',1),
('gerente','backup','baixar',1),
('gerente','backup','excluir',0),
('gerente','backup','importar',0),
('gerente','backup','restaurar',0),
('operador','backup','baixar',0),
('operador','backup','excluir',0),
('operador','backup','importar',0),
('operador','backup','restaurar',0);

INSERT IGNORE INTO schema_migrations(migration,version,description,status)
VALUES ('20260914_008_rbac_backup_actions','V104.49.3-R5','Catálogo RBAC de backup completo (A-10).','aplicada');
