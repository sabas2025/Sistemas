-- V104.9 - Instalador compatível com hospedagem MySQL/MariaDB
-- Esta atualização registra a correção do instalador e não altera dados operacionais.
CREATE TABLE IF NOT EXISTS schema_migrations (
  id BIGINT AUTO_INCREMENT PRIMARY KEY,
  migration VARCHAR(180) NOT NULL UNIQUE,
  checksum VARCHAR(128) NULL,
  status ENUM('aplicada','falha') DEFAULT 'aplicada',
  mensagem TEXT NULL,
  aplicada_em TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_schema_migrations_status(status),
  INDEX idx_schema_migrations_data(aplicada_em)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
INSERT INTO schema_migrations(migration, checksum, status, mensagem)
VALUES('v104_9_instalador_compatibilidade', SHA2('v104_9_instalador_compatibilidade', 256), 'aplicada', 'Instalador reforçado: SQL com LONGTEXT no lugar de JSON e log detalhado por módulo.')
ON DUPLICATE KEY UPDATE status='aplicada', mensagem=VALUES(mensagem);
