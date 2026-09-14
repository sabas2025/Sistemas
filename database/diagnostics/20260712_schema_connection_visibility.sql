-- Diagnóstico somente leitura para cPanel/phpMyAdmin.
-- Não cria, altera ou remove dados.
SELECT DATABASE() AS banco_selecionado,
       CURRENT_USER() AS usuario_privilegios,
       USER() AS usuario_sessao,
       VERSION() AS versao_mysql;

SHOW GRANTS FOR CURRENT_USER();

SELECT TABLE_NAME
FROM INFORMATION_SCHEMA.TABLES
WHERE TABLE_SCHEMA = DATABASE()
  AND TABLE_NAME IN (
    'usuarios','configuracoes_integracao','fila_integracao','schema_migrations',
    'security_events','ips_bloqueados','rate_limit_hits',
    'vsm_endpoints','vsm_campos_mapeamento','vsm_endpoint_logs'
  )
ORDER BY TABLE_NAME;
