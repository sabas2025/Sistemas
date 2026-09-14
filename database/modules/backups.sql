-- V104.49.3-R5 (2026-08-22) - Estrutura modular consolidada do banco: backups
-- Execute no banco do módulo correspondente.
SET NAMES utf8mb4;

CREATE TABLE IF NOT EXISTS backups_banco (
  id BIGINT AUTO_INCREMENT PRIMARY KEY,
  usuario_id INT NULL,
  arquivo VARCHAR(255) NULL,
  nome_arquivo VARCHAR(255) NULL,
  caminho VARCHAR(500) NULL,
  tamanho_bytes BIGINT DEFAULT 0,
  hash_sha256 VARCHAR(128) NULL,
  hmac_sha256 VARCHAR(128) NULL,
  trust_score INT NOT NULL DEFAULT 0,
  status VARCHAR(40) NOT NULL DEFAULT 'sucesso',
  -- Melhoria 9 da seção 8 (relatório V104.49.3-R6): a proveniência do achado A-11 vivia apenas
  -- dentro do .sig.json. Na listagem do painel a distinção entre backup local e importado
  -- dependia do prefixo "importado_" no nome do arquivo, que quebra se alguém renomear. A coluna
  -- abaixo é o registro consultável; a assinatura continua sendo a fonte AUTORITATIVA (é ela que
  -- o HMAC protege) e o restore decide por ela, não por esta coluna.
  proveniencia VARCHAR(32) NOT NULL DEFAULT 'local_generated',
  mensagem TEXT NULL,
  trace_id VARCHAR(80) NULL,
  criado_em TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_backup_data(criado_em),
  INDEX idx_backup_status(status),
  INDEX idx_backup_trace(trace_id),
  INDEX idx_backup_proveniencia(proveniencia)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;


-- V58: compatibilidade com tela antiga/serviços que consultam tabela backups.
CREATE TABLE IF NOT EXISTS backups (
  id BIGINT AUTO_INCREMENT PRIMARY KEY,
  arquivo VARCHAR(255) NOT NULL,
  tamanho BIGINT DEFAULT 0,
  status VARCHAR(40) DEFAULT 'gerado',
  criado_em TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;




-- V104.22 - Compatibilidade arquivo/nome_arquivo/caminho já consolidada
-- na definição única de backups_banco acima. O registro de migrações pertence
-- exclusivamente ao banco core e não deve ser escrito pelo módulo backups.
