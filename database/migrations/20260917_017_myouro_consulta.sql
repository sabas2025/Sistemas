-- R7 build 20260917.1. Módulo CORE. Não cria credenciais nem atribui dados legados.
-- Reexecução idempotente. Rollback: desabilitar conexão, voltar código e preservar tabela/coluna.
CREATE TABLE IF NOT EXISTS myouro_conexoes (
  id INT AUTO_INCREMENT PRIMARY KEY,
  empresa_id INT NOT NULL,
  url VARCHAR(255) NOT NULL DEFAULT 'https://msx.vsm.api.br/graphql',
  ambiente VARCHAR(20) NOT NULL DEFAULT 'producao',
  codigo_loja INT NOT NULL,
  token_encrypted TEXT NULL,
  habilitado TINYINT NOT NULL DEFAULT 0,
  ultimo_teste_ok TINYINT NOT NULL DEFAULT 0,
  ultimo_teste_em DATETIME NULL,
  ultimo_teste_codigo VARCHAR(80) NULL,
  criado_em TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uk_myouro_empresa (empresa_id),
  CONSTRAINT fk_myouro_empresa FOREIGN KEY (empresa_id) REFERENCES empresas(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

SET @col := (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='configuracoes_integracao' AND COLUMN_NAME='integracao_empresa_id');
SET @sql := IF(@col=0,'ALTER TABLE configuracoes_integracao ADD COLUMN integracao_empresa_id INT NULL','SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

INSERT IGNORE INTO schema_migrations(migration,version,description,status)
VALUES ('20260917_017_myouro_consulta','V104.49.3-R7','Conexão MyOuro separada e vínculo explícito da instalação.','aplicada');
