-- V104.6 - Correção idempotente da Orquestração
-- Objetivo: evitar erro fatal "Duplicate column name" quando a tela Integrações > Escolher fluxos ativos
-- tenta garantir colunas que já existem no banco.
--
-- Esta correção é principalmente no código PHP:
-- - app/Controllers/OrquestracaoController.php
-- - app/Controllers/DashboardController.php
-- - app/Core/Database.php
--
-- Não há ALTER obrigatório para bases já instaladas.
-- Execute apenas para registrar a migração, caso a tabela schema_migrations exista.

CREATE TABLE IF NOT EXISTS schema_migrations (
  id INT AUTO_INCREMENT PRIMARY KEY,
  versao VARCHAR(80) NOT NULL UNIQUE,
  descricao TEXT NULL,
  aplicado_em TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT IGNORE INTO schema_migrations(versao, descricao)
VALUES ('v104.6-orquestracao-colunas-idempotentes', 'Corrige criação idempotente de colunas na orquestração e evita erro Duplicate column name.');
