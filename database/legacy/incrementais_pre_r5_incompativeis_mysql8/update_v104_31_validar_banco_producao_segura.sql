-- V104.31 - Validar Banco UX + Produção Segura analítica
-- Não altera regra de negócio Tiny/VSM. Apenas registra migração e garante colunas VSM seguras.
-- Observação: o reparo automático seguro via PHP usa Database::addColumnIfMissing para compatibilidade MySQL/MariaDB.

ALTER TABLE configuracoes_integracao ADD COLUMN IF NOT EXISTS vsm_ambiente VARCHAR(30) DEFAULT 'homologacao';
ALTER TABLE configuracoes_integracao ADD COLUMN IF NOT EXISTS vsm_producao_liberada TINYINT DEFAULT 0;
ALTER TABLE configuracoes_integracao ADD COLUMN IF NOT EXISTS vsm_ultimo_teste_ok TINYINT DEFAULT 0;
ALTER TABLE configuracoes_integracao ADD COLUMN IF NOT EXISTS vsm_ultimo_teste_em DATETIME NULL;
ALTER TABLE configuracoes_integracao ADD COLUMN IF NOT EXISTS vsm_host_producao_liberado VARCHAR(255) NULL;

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

INSERT INTO schema_migrations(migration,checksum,status,mensagem,aplicada_em)
VALUES('v104_31_validar_banco_producao_segura','v104.31','aplicada','Validar Banco com HTML amigável e Produção Segura OK/Atenção/Bloqueio',NOW())
ON DUPLICATE KEY UPDATE status=VALUES(status), mensagem=VALUES(mensagem), aplicada_em=VALUES(aplicada_em);
