-- V104.20 - Instalador seguro MySQL / credenciais produção guiadas
INSERT INTO schema_migrations (migration, checksum, status, mensagem)
VALUES('v104_20_instalador_seguro_mysql', SHA2('v104_20_instalador_seguro_mysql',256), 'aplicada', 'Instalador com mensagem amigável para root sem senha e modo local confirmado.')
ON DUPLICATE KEY UPDATE status='aplicada', mensagem=VALUES(mensagem);
