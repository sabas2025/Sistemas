# V96 — Classificação de Tabelas por Uso
Data: 2026-06-23

| Tabela | Status | Evidência |
|---|---|---|
| `audit_exports` | SCHEMA / MIGRAÇÃO / LEGADO CONTROLADO | database/install.sql, database/install_final_v82.sql, database/install_final_v83.sql |
| `auditoria_assinaturas` | EM USO DIRETO NO CÓDIGO | app/Services/AuditIntegrityService.php |
| `auditoria_detalhes` | SCHEMA / MIGRAÇÃO / LEGADO CONTROLADO | database/install.sql, database/install_final_v82.sql, database/install_final_v83.sql |
| `auditoria_eventos` | EM USO DIRETO NO CÓDIGO | app/Controllers/DashboardController.php, app/Controllers/EvidenceController.php, app/Controllers/PedidoController.php |
| `auditoria_hash_chain` | EM USO DIRETO NO CÓDIGO | app/Services/EnterpriseAuditHashChainService.php |
| `auditoria_timeline` | EM USO DIRETO NO CÓDIGO | app/Controllers/DashboardController.php, app/Services/AuditTimelineService.php |
| `backups` | SCHEMA / MIGRAÇÃO / LEGADO CONTROLADO | database/install.sql, database/install_final_v82.sql, database/install_final_v83.sql |
| `backups_banco` | EM USO DIRETO NO CÓDIGO | app/Controllers/BackupController.php, app/Controllers/DashboardController.php, app/Services/BackupService.php |
| `categorias_mapeamento` | EM USO DIRETO NO CÓDIGO | app/Controllers/DashboardController.php, app/Services/ProdutoVsmGovernanceService.php |
| `circuit_breakers` | EM USO DIRETO NO CÓDIGO | app/Controllers/ApiController.php, app/Controllers/DashboardController.php, app/Services/CircuitBreakerService.php |
| `configuracoes_historico` | EM USO DIRETO NO CÓDIGO | app/Controllers/DashboardController.php |
| `configuracoes_integracao` | EM USO DIRETO NO CÓDIGO | app/Controllers/ApiController.php, app/Controllers/DashboardController.php, app/Services/AutoHomologationService.php |
| `dashboard_testes_execucoes` | SCHEMA / MIGRAÇÃO / LEGADO CONTROLADO | database/install.sql, database/install_final_v82.sql, database/install_final_v83.sql |
| `diagnostico_api` | EM USO DIRETO NO CÓDIGO | app/Services/DiagnosticoApiService.php, app/Services/RetentionService.php |
| `empresas` | SCHEMA / MIGRAÇÃO / LEGADO CONTROLADO | database/install.sql, database/install_final_v82.sql, database/install_final_v83.sql |
| `estoque_alertas` | EM USO DIRETO NO CÓDIGO | app/Controllers/EstoqueController.php, app/Services/EstoqueEnterpriseService.php |
| `estoque_auditoria_sku` | EM USO DIRETO NO CÓDIGO | app/Services/EstoqueEnterpriseService.php |
| `estoque_configuracoes` | EM USO DIRETO NO CÓDIGO | app/Services/EstoqueEnterpriseService.php |
| `estoque_consulta_vsm_execucoes` | EM USO DIRETO NO CÓDIGO | app/Controllers/EstoqueController.php, app/Services/EstoqueVsmSchedulerService.php |
| `estoque_consulta_vsm_resultados` | EM USO DIRETO NO CÓDIGO | app/Controllers/EstoqueController.php, app/Services/EstoqueVsmSchedulerService.php |
| `estoque_divergencias` | EM USO DIRETO NO CÓDIGO | app/Controllers/DashboardController.php, app/Controllers/DivergenceMonitorController.php, app/Controllers/EstoqueController.php |
| `estoque_eventos_sincronizacao` | EM USO DIRETO NO CÓDIGO | app/Services/EstoqueEnterpriseService.php |
| `estoque_movimentos` | EM USO DIRETO NO CÓDIGO | app/Controllers/ApiController.php, app/Controllers/DashboardController.php, app/Controllers/EstoqueController.php |
| `estoque_reconciliacao` | EM USO DIRETO NO CÓDIGO | app/Services/ReconciliationService.php |
| `estoque_reconciliacao_agendada` | SCHEMA / MIGRAÇÃO / LEGADO CONTROLADO | database/install.sql, database/install_final_v82.sql, database/install_final_v83.sql |
| `estoque_saldos_cache` | EM USO DIRETO NO CÓDIGO | app/Services/EstoqueVsmSchedulerService.php |
| `evento_correlacao` | EM USO DIRETO NO CÓDIGO | app/Controllers/DashboardController.php |
| `eventos_processados` | EM USO DIRETO NO CÓDIGO | app/Controllers/ApiController.php |
| `fila_analytics_snapshots` | EM USO DIRETO NO CÓDIGO | app/Services/QueueV24AnalyticsService.php |
| `fila_estoque` | EM USO DIRETO NO CÓDIGO | app/Controllers/EstoqueController.php, app/Services/EstoqueEnterpriseService.php, public/worker_estoque.php |
| `fila_fiscal` | EM USO DIRETO NO CÓDIGO | app/Services/FiscalEnterpriseService.php, public/worker_fiscal.php |
| `fila_integracao` | EM USO DIRETO NO CÓDIGO | app/Controllers/ApiController.php, app/Controllers/DashboardController.php, app/Controllers/DivergenceMonitorController.php |
| `fila_morta` | EM USO DIRETO NO CÓDIGO | app/Controllers/ApiController.php, app/Controllers/DashboardController.php, app/Services/DeadLetterQueueService.php |
| `filiais` | SCHEMA / MIGRAÇÃO / LEGADO CONTROLADO | database/install.sql, database/install_final_v82.sql, database/install_final_v83.sql |
| `fiscal_configuracoes_cache` | EM USO DIRETO NO CÓDIGO | app/Services/FiscalEnterpriseService.php |
| `fiscal_reconciliacao_snapshots` | SCHEMA / MIGRAÇÃO / LEGADO CONTROLADO | database/install.sql, database/install_final_v82.sql, database/install_final_v83.sql |
| `homologacao_automatica_relatorios` | EM USO DIRETO NO CÓDIGO | app/Controllers/DashboardController.php, app/Services/AutoHomologationService.php |
| `homologacao_checklist` | EM USO DIRETO NO CÓDIGO | app/Controllers/DashboardController.php, app/Services/AutoHomologationService.php, app/Services/HomologationReportService.php |
| `hosting_checks` | EM USO DIRETO NO CÓDIGO | app/Controllers/DashboardController.php |
| `instalacao_prechecks` | SCHEMA / MIGRAÇÃO / LEGADO CONTROLADO | database/install.sql, database/install_final_v82.sql, database/install_final_v83.sql |
| `integracao_execucoes` | EM USO DIRETO NO CÓDIGO | app/Controllers/ApiController.php |
| `login_tentativas` | EM USO DIRETO NO CÓDIGO | app/Core/Auth.php |
| `logs_integracao` | EM USO DIRETO NO CÓDIGO | app/Controllers/DashboardController.php, app/Services/DashboardIntegrityService.php, app/Services/Logger.php |
| `mapper_versions` | SCHEMA / MIGRAÇÃO / LEGADO CONTROLADO | database/install.sql, database/install_final_v82.sql, database/install_final_v83.sql |
| `metricas_api` | EM USO DIRETO NO CÓDIGO | app/Controllers/DashboardController.php, app/Services/MetricsService.php, app/Services/RetentionService.php |
| `module_health_snapshots` | SCHEMA / MIGRAÇÃO / LEGADO CONTROLADO | database/install.sql, database/install_final_v82.sql, database/install_final_v83.sql |
| `nfe_integracao` | EM USO DIRETO NO CÓDIGO | app/Controllers/DashboardController.php, app/Controllers/FiscalController.php, app/Services/FiscalEnterpriseService.php |
| `nfe_status_historico` | EM USO DIRETO NO CÓDIGO | app/Services/FiscalEnterpriseService.php |
| `nfe_xml` | EM USO DIRETO NO CÓDIGO | app/Services/FiscalEnterpriseService.php, public/worker_fiscal.php |
| `notas_fiscais` | EM USO DIRETO NO CÓDIGO | app/Controllers/DashboardController.php, app/Controllers/FiscalController.php, app/Services/DashboardIntegrityService.php |
| `notas_fiscais_eventos` | EM USO DIRETO NO CÓDIGO | app/Services/FiscalEnterpriseService.php, app/Services/FiscalIntegrationService.php |
| `notificacoes` | EM USO DIRETO NO CÓDIGO | app/Controllers/DashboardController.php, app/Services/NotificationService.php |
| `notificacoes_config` | SCHEMA / MIGRAÇÃO / LEGADO CONTROLADO | database/install.sql, database/install_final_v82.sql, database/install_final_v83.sql |
| `orquestracao_fluxos_historico` | EM USO DIRETO NO CÓDIGO | app/Controllers/DashboardController.php |
| `payload_snapshots` | EM USO DIRETO NO CÓDIGO | app/Services/PayloadSnapshotService.php |
| `pedidos_hub` | EM USO DIRETO NO CÓDIGO | app/Controllers/DashboardController.php, app/Services/PedidoCicloVidaService.php |
| `pedidos_integracao` | EM USO DIRETO NO CÓDIGO | app/Controllers/ApiController.php, app/Controllers/DashboardController.php, app/Controllers/PedidoController.php |
| `pedidos_nfe_xml` | EM USO DIRETO NO CÓDIGO | app/Services/PedidoCicloVidaService.php |
| `pedidos_payloads` | EM USO DIRETO NO CÓDIGO | app/Services/PedidoCicloVidaService.php |
| `pedidos_status_historico` | EM USO DIRETO NO CÓDIGO | app/Services/PedidoCicloVidaService.php |
| `pedidos_validacao` | EM USO DIRETO NO CÓDIGO | app/Controllers/ApiController.php, app/Controllers/V51Controller.php, app/Services/PedidoCicloVidaService.php |
| `pedidos_validacao_historico` | EM USO DIRETO NO CÓDIGO | app/Controllers/V51Controller.php, app/Services/PedidoTinyVsmValidationService.php |
| `permissoes_perfil` | EM USO DIRETO NO CÓDIGO | app/Controllers/DashboardController.php, app/Services/DatabaseValidationService.php, app/Services/PermissionService.php |
| `post_install_tests` | EM USO DIRETO NO CÓDIGO | app/Controllers/DashboardController.php |
| `production_go_live_checks` | EM USO DIRETO NO CÓDIGO | app/Services/ProductionGoLiveService.php |
| `production_readiness_v50` | EM USO DIRETO NO CÓDIGO | app/Services/ProductionReadinessV50Service.php |
| `produto_pendencias` | EM USO DIRETO NO CÓDIGO | app/Controllers/DashboardController.php, app/Controllers/ProdutoController.php, app/Services/ProdutoTinyPreflightService.php |
| `produtos_aprovacao_historico` | EM USO DIRETO NO CÓDIGO | app/Controllers/DashboardController.php, app/Services/ProdutoVsmApprovalGuardService.php |
| `produtos_mapeamento` | EM USO DIRETO NO CÓDIGO | app/Controllers/ApiController.php, app/Controllers/DashboardController.php, app/Controllers/ProdutoController.php |
| `produtos_pendentes_integracao` | EM USO DIRETO NO CÓDIGO | app/Controllers/DashboardController.php, app/Services/ProdutoVsmGovernanceService.php |
| `produtos_tiny` | EM USO DIRETO NO CÓDIGO | app/Services/EstoqueVsmSchedulerService.php, app/Services/ProdutoVsmApprovalGuardService.php |
| `produtos_vsm` | SCHEMA / MIGRAÇÃO / LEGADO CONTROLADO | database/install.sql, database/install_final_v82.sql, database/install_final_v83.sql |
| `produtos_vsm_eventos` | EM USO DIRETO NO CÓDIGO | app/Controllers/ApiController.php, app/Controllers/DashboardController.php |
| `reconciliacao_execucoes` | EM USO DIRETO NO CÓDIGO | app/Services/EstoqueEnterpriseService.php, public/worker_estoque.php |
| `reconciliacao_itens` | SCHEMA / MIGRAÇÃO / LEGADO CONTROLADO | database/install.sql, database/install_final_v82.sql, database/install_final_v83.sql |
| `schema_migrations` | EM USO DIRETO NO CÓDIGO | app/Controllers/DashboardController.php, app/Controllers/V50Controller.php, app/Controllers/VsmController.php |
| `security_audit` | EM USO DIRETO NO CÓDIGO | app/Services/SecurityAuditService.php |
| `security_hardening_checks` | SCHEMA / MIGRAÇÃO / LEGADO CONTROLADO | database/install.sql, database/install_final_v94.sql, database/install_final_v96.sql |
| `selftest_relatorios` | EM USO DIRETO NO CÓDIGO | app/Controllers/DashboardController.php, app/Services/RetentionService.php, app/Services/SelfTestService.php |
| `system_build_info` | SCHEMA / MIGRAÇÃO / LEGADO CONTROLADO | database/install.sql, database/install_final_v82.sql, database/install_final_v83.sql |
| `tiny_error_catalogo` | EM USO DIRETO NO CÓDIGO | app/Services/TinyV2ObservabilityService.php |
| `tiny_testes_reais` | EM USO DIRETO NO CÓDIGO | app/Services/TesteRealTinyService.php |
| `tiny_v2_endpoint_logs` | EM USO DIRETO NO CÓDIGO | app/Services/TinyV2ObservabilityService.php, app/Services/TinyV2Service.php |
| `tiny_v2_homologacao_testes` | EM USO DIRETO NO CÓDIGO | app/Services/TinyV2HomologationService.php |
| `tiny_v2_retry_policies` | SCHEMA / MIGRAÇÃO / LEGADO CONTROLADO | database/install.sql, database/install_final_v82.sql, database/install_final_v83.sql |
| `tiny_v3_endpoint_logs` | EM USO DIRETO NO CÓDIGO | app/Controllers/DashboardController.php, app/Services/AutoHomologationService.php, app/Services/TinyV3Service.php |
| `tiny_v3_homologacao_testes` | EM USO DIRETO NO CÓDIGO | app/Services/TinyV3HomologationService.php |
| `tiny_v3_tokens` | EM USO DIRETO NO CÓDIGO | app/Controllers/DashboardController.php, app/Services/TinyV3TokenService.php |
| `tiny_validacoes_execucoes` | EM USO DIRETO NO CÓDIGO | app/Services/TinyValidationService.php |
| `tiny_webhooks` | EM USO DIRETO NO CÓDIGO | app/Controllers/DashboardController.php, app/Services/RetentionService.php, app/Services/TinyWebhookSecurityService.php |
| `upgrade_snapshots` | EM USO DIRETO NO CÓDIGO | app/Services/ProductionReadinessV24Service.php |
| `usuarios` | EM USO DIRETO NO CÓDIGO | app/Controllers/DashboardController.php, app/Core/Auth.php |
| `vsm_campos_mapeamento` | EM USO DIRETO NO CÓDIGO | app/Services/VsmEndpointService.php |
| `vsm_endpoint_catalogo` | EM USO DIRETO NO CÓDIGO | app/Services/VsmFichaTecnicaService.php |
| `vsm_endpoint_logs` | EM USO DIRETO NO CÓDIGO | app/Services/VsmEndpointService.php |
| `vsm_endpoint_metricas` | EM USO DIRETO NO CÓDIGO | app/Services/VsmFichaTecnicaService.php |
| `vsm_endpoints` | EM USO DIRETO NO CÓDIGO | app/Services/VsmEndpointService.php |
| `vsm_payload_catalogo` | EM USO DIRETO NO CÓDIGO | app/Services/VsmFichaTecnicaService.php |
| `webhook_requisicoes` | EM USO DIRETO NO CÓDIGO | app/Services/WebhookSecurityService.php |
