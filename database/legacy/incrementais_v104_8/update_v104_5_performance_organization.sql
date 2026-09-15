-- V104.5 - Organização e performance
-- Execute em manutenção controlada. O helper adiciona índices somente quando ainda não existem.

DELIMITER $$
DROP PROCEDURE IF EXISTS hub_add_index_if_missing $$
CREATE PROCEDURE hub_add_index_if_missing(IN p_table VARCHAR(128), IN p_index VARCHAR(128), IN p_sql TEXT)
BEGIN
  IF EXISTS (SELECT 1 FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = p_table) THEN
    IF NOT EXISTS (SELECT 1 FROM information_schema.STATISTICS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = p_table AND INDEX_NAME = p_index) THEN
      SET @hub_sql = p_sql;
      PREPARE stmt FROM @hub_sql;
      EXECUTE stmt;
      DEALLOCATE PREPARE stmt;
    END IF;
  END IF;
END $$
DELIMITER ;

CALL hub_add_index_if_missing('fila_integracao','idx_fila_execucao_rapida','ALTER TABLE fila_integracao ADD INDEX idx_fila_execucao_rapida (status, prioridade, proxima_tentativa, criado_em)');
CALL hub_add_index_if_missing('fila_morta','idx_fila_morta_data','ALTER TABLE fila_morta ADD INDEX idx_fila_morta_data (criado_em)');
CALL hub_add_index_if_missing('pedidos_integracao','idx_pedidos_status_data','ALTER TABLE pedidos_integracao ADD INDEX idx_pedidos_status_data (status, criado_em)');
CALL hub_add_index_if_missing('pedidos_hub','idx_pedidos_hub_status_data','ALTER TABLE pedidos_hub ADD INDEX idx_pedidos_hub_status_data (status_hub, criado_em)');
CALL hub_add_index_if_missing('pedidos_status_historico','idx_ped_status_hub_data','ALTER TABLE pedidos_status_historico ADD INDEX idx_ped_status_hub_data (pedido_hub_id, criado_em)');
CALL hub_add_index_if_missing('webhook_requisicoes','idx_webhook_status_data','ALTER TABLE webhook_requisicoes ADD INDEX idx_webhook_status_data (status, criado_em)');
CALL hub_add_index_if_missing('integracao_execucoes','idx_exec_tipo_status_data','ALTER TABLE integracao_execucoes ADD INDEX idx_exec_tipo_status_data (tipo, status, criado_em)');
CALL hub_add_index_if_missing('auditoria_eventos','idx_audit_nivel_data','ALTER TABLE auditoria_eventos ADD INDEX idx_audit_nivel_data (nivel, criado_em)');
CALL hub_add_index_if_missing('logs_integracao','idx_logs_nivel_data','ALTER TABLE logs_integracao ADD INDEX idx_logs_nivel_data (nivel, criado_em)');
CALL hub_add_index_if_missing('notificacoes','idx_notif_lida_data','ALTER TABLE notificacoes ADD INDEX idx_notif_lida_data (lida, criada_em)');
CALL hub_add_index_if_missing('circuit_breakers','idx_cb_status_aberto','ALTER TABLE circuit_breakers ADD INDEX idx_cb_status_aberto (status, aberto_ate)');

DROP PROCEDURE IF EXISTS hub_add_index_if_missing;
