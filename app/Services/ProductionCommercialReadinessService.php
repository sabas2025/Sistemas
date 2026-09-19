<?php
/** V104.16 - Checklist de prontidão comercial/produção. */
class ProductionCommercialReadinessService {
  public static function run(): array {
    $items = [];
    $add = function(string $grupo, string $nome, string $status, string $detalhe, string $acao='') use (&$items) {
      $items[] = compact('grupo','nome','status','detalhe','acao');
    };
    $cfg = App::config();
    $add('Versão','Versão centralizada', class_exists('SystemVersionService') ? 'ok':'erro', class_exists('SystemVersionService') ? SystemVersionService::fullLabel() : 'SystemVersionService ausente', 'Centralizar mensagens e SQL oficial.');
    $license = class_exists('LicenseEnforcementService') ? LicenseEnforcementService::status() : ['status'=>'erro','mensagem'=>'Serviço ausente'];
    $add('Comercial','Licenciamento', (($license['status'] ?? '') === 'ok') ? 'ok' : 'alerta', $license['mensagem'] ?? 'Sem status', 'Ativar commercial.license_mode=enforce quando vender como SaaS.');
    $add('Comercial','Modo licença', (string)($cfg['commercial']['license_mode'] ?? 'monitor') === 'enforce' ? 'ok':'alerta', 'Modo atual: '.(string)($cfg['commercial']['license_mode'] ?? 'monitor'), 'Para produção comercial, usar enforce após cadastrar licença.');
    $add('Tenant','Escopo multiempresa/multifilial', !empty($cfg['commercial']['tenant_scope_required']) ? 'ok':'alerta', !empty($cfg['commercial']['tenant_scope_required']) ? 'Escopo obrigatório ligado.' : 'Escopo obrigatório ainda em monitoramento.', 'Isolamento por empresa APLICADO desde a R6: as consultas a tabelas com escopo passam pelo TenantScopeService (portao tenant-scope-check.php na CI). Leitura filtra pela empresa da sessao e mantem visiveis as linhas legadas com empresa_id NULL; gravacao carimba a empresa da sessao ou, fora dela (webhook, fila, worker, cron), a UNICA empresa cadastrada. O que continua em aberto e instalacao com DUAS OU MAIS empresas: ai a entrada sem sessao nao tem como decidir a empresa e a linha nasce NULL. Para varios clientes reais, decida antes a origem da empresa na entrada. appendWhereIfColumns() e legado e segue sem uso - quem isola e o TenantScopeService.');
    $add('Banco','SchemaGuard', class_exists('DatabaseSchemaGuardService') ? 'ok':'erro', 'SchemaGuard disponível.', 'Executar Central Técnica > Validar Banco.');
    $add('Segurança','Teste assistido', class_exists('SecurityAssistedTestService') ? 'ok':'erro', 'Teste de Segurança Assistido disponível.', 'Gerar relatório antes de produção.');
    $add('Conectores','Registry plugável', class_exists('ConnectorRegistryService') ? 'ok':'erro', class_exists('ConnectorRegistryService') ? count(ConnectorRegistryService::catalog()).' conectores catalogados.' : 'Registry ausente', 'Homologar conectores ativos Tiny/VSM.');
    $add('Documentação','Documentos comerciais', is_dir(__DIR__.'/../../docs/comercial') ? 'ok':'alerta', 'Pasta docs/comercial '.(is_dir(__DIR__.'/../../docs/comercial')?'existe':'ausente'), 'Revisar contrato, SLA e termos com jurídico.');
    if (class_exists('DatabaseConfigDiagnosticService')) {
      $diag = DatabaseConfigDiagnosticService::run();
      $add('Banco','Diagnóstico config real', empty($diag['risk']) ? 'ok':'alerta', 'Bancos conectados: '.implode(', ', $diag['connected_databases'] ?? []), 'Abrir Central Técnica > Diagnóstico Config Real.');
    }
    $add('Comercial','Portal self-service', is_file(__DIR__.'/../../views/commercial_client_portal.php') ? 'ok':'erro', 'Portal do cliente disponível.', 'Publicar somente com licença e permissões corretas.');
    $add('Suporte','SLA e chamados', Database::isKnownTable('comercial_suporte_chamados') ? 'ok':'alerta', 'Tabela/rota de suporte comercial preparada.', 'Definir SLA contratual por plano.');
    $add('Testes','Playwright E2E', is_file(__DIR__.'/../../tests/e2e/playwright.config.js') ? 'ok':'alerta', 'Suite E2E segura incluída.', 'Rodar em homologação com usuário temporário.');
    $add('Testes','Carga leve', is_file(__DIR__.'/../../tests/load/light-smoke.sh') ? 'ok':'alerta', 'Script de carga leve incluído.', 'Executar com baixa concorrência fora do horário crítico.');

    if (class_exists('CommercialHardeningService')) {
      $hard = CommercialHardeningService::run();
      $add('Comercial','Checklist final alta/média', ($hard['status'] ?? '') === 'ok' ? 'ok':'alerta', 'Score final: '.($hard['score'] ?? 0).'%', 'Abrir Comercial > Checklist Final Comercial.');
    }
    if (class_exists('BillingGatewayService')) {
      $billing = BillingGatewayService::status();
      $add('Cobrança','Gateway de cobrança', ($billing['status'] ?? '')==='ok'?'ok':'alerta', $billing['mensagem'] ?? 'Sem status', 'Configurar gateway real antes de cobrar clientes.');
    }
    if (class_exists('LicenseServerClientService')) {
      $remote = LicenseServerClientService::status();
      $add('Licença','Servidor licenciador remoto', ($remote['status'] ?? '')==='ok'?'ok':'alerta', $remote['mensagem'] ?? 'Sem status', 'Usar servidor licenciador para SaaS/white-label.');
    }

    $add('SQL','SQL oficial atual', is_file(__DIR__.'/../../database/install_final_current.sql') ? 'ok':'erro', 'install_final_current.sql '.(is_file(__DIR__.'/../../database/install_final_current.sql')?'existe':'ausente'), 'Manter somente current na raiz e legados em database/legacy.');
    $statusGeral = 'ok';
    foreach ($items as $it) { if ($it['status']==='erro') $statusGeral='erro'; elseif ($it['status']==='alerta' && $statusGeral==='ok') $statusGeral='alerta'; }
    return ['status'=>$statusGeral,'versao'=>SystemVersionService::info(),'items'=>$items];
  }
}
