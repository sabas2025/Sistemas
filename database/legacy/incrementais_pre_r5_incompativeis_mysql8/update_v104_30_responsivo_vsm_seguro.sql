-- V104.30 - Responsividade/PWA + VSM homologação segura
-- Compatível com bases existentes. Não altera fluxos Tiny/VSM.

ALTER TABLE configuracoes_integracao ADD COLUMN IF NOT EXISTS vsm_ambiente VARCHAR(30) DEFAULT 'homologacao';
ALTER TABLE configuracoes_integracao ADD COLUMN IF NOT EXISTS vsm_producao_liberada TINYINT DEFAULT 0;
ALTER TABLE configuracoes_integracao ADD COLUMN IF NOT EXISTS vsm_ultimo_teste_ok TINYINT DEFAULT 0;
ALTER TABLE configuracoes_integracao ADD COLUMN IF NOT EXISTS vsm_ultimo_teste_em DATETIME NULL;
ALTER TABLE configuracoes_integracao ADD COLUMN IF NOT EXISTS vsm_host_producao_liberado VARCHAR(255) NULL;
ALTER TABLE configuracoes_integracao ADD COLUMN IF NOT EXISTS vsm_producao_liberada_em DATETIME NULL;
ALTER TABLE configuracoes_integracao ADD COLUMN IF NOT EXISTS vsm_producao_liberada_por INT NULL;

UPDATE configuracoes_integracao
SET vsm_ambiente = CASE
  WHEN vsm_url IS NULL OR vsm_url = '' THEN 'homologacao'
  WHEN LOWER(vsm_url) LIKE '%homolog%' THEN 'homologacao'
  WHEN COALESCE(vsm_producao_liberada,0) = 1 THEN 'producao'
  ELSE 'homologacao'
END,
vsm_producao_liberada = CASE
  WHEN vsm_url IS NULL OR vsm_url = '' THEN 0
  WHEN LOWER(vsm_url) LIKE '%homolog%' THEN 0
  ELSE COALESCE(vsm_producao_liberada,0)
END,
vsm_ultimo_teste_ok = COALESCE(vsm_ultimo_teste_ok,0)
WHERE id = 1;

INSERT IGNORE INTO schema_migrations(migration, checksum, status, mensagem, aplicada_em)
VALUES('v104_30_responsivo_vsm_seguro','v104.30','aplicada','Responsividade PWA offline local e VSM homologação segura por padrão',NOW());
