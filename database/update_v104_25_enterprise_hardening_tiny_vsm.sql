-- V104.25 - Hardening Enterprise Tiny/VSM
-- Aplicar no banco principal. Idempotente e seguro.

INSERT IGNORE INTO schema_migrations(migration, checksum, status, mensagem)
VALUES('v104_25_enterprise_hardening_tiny_vsm', SHA2('v104_25_enterprise_hardening_tiny_vsm',256), 'aplicada', 'Hardening Tiny webhook legado, HMAC público, anti-replay sem fallback, SSRF VSM e SQL repair sem placeholders.');

UPDATE configuracoes_integracao
SET ambiente = 'homologacao'
WHERE ambiente IS NULL OR ambiente = '' OR ambiente LIKE '{%';
