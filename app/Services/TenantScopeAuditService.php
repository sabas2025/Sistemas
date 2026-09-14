<?php
/**
 * Auditoria de prontidão multiempresa.
 *
 * Auditoria 2026-09-14 (achado F-02): este serviço mantinha o PRÓPRIO catálogo de 15 tabelas,
 * divergente das 31 de TenantScopeService — que a R7 estabeleceu como fonte única — e exigia
 * `filial_id` em todas elas. Dois problemas:
 *
 *  1. `filiais` foi consolidada em `empresas` na R6 (seção 2.4 do relatório). No schema atual
 *     sobrou UMA coluna `filial_id`, em `pedidos_integracao`, que nenhum código lê ou grava —
 *     vestígio anterior à consolidação, preservado porque remover coluna exige plano seguro e
 *     autorização. Exigir `filial_id` em todas as tabelas era, portanto, uma condição
 *     insatisfazível: a auditoria acusava ALERTA permanente por decisão de projeto.
 *  2. O catálogo local listava `logs_integracao`, `auditoria_eventos` e `backups_banco`, que são
 *     deliberadamente GLOBAIS à instalação — exigir `empresa_id` nelas contradizia o desenho do
 *     isolamento, e a divergência entre os dois catálogos ia crescer a cada tabela nova.
 *
 * Agora o catálogo vem de TenantScopeService::scopedTables(): um lugar só, e a auditoria mede
 * exatamente o que o isolamento aplica.
 */
class TenantScopeAuditService {
  public static function run(bool $persist=true): array {
    $items=[]; $missingEmpresa=0; $checked=0;
    $tabelas = class_exists('TenantScopeService') ? TenantScopeService::scopedTables() : [];
    foreach($tabelas as $table){
      if(!Database::isKnownTable($table)) continue;
      $exists = Database::tableExists($table);
      $empresa = $exists && Database::columnExists($table,'empresa_id');
      $checked++;
      if(!$empresa) $missingEmpresa++;
      $items[]=['table'=>$table,'exists'=>$exists,'empresa_id'=>$empresa,'status'=>($exists&&$empresa)?'ok':'alerta'];
    }
    $status = ($missingEmpresa===0 && $checked>0) ? 'ok' : 'alerta';
    $summary = $checked===0
      ? 'Nenhuma tabela do catálogo de escopo encontrada neste banco.'
      : "$checked tabelas avaliadas; $missingEmpresa sem empresa_id.";
    $result=['status'=>$status,'summary'=>$summary,'items'=>$items,'strict'=>TenantContextService::strictEnabled()];
    if($persist) self::persist($result);
    return $result;
  }

  private static function persist(array $result): void {
    try {
      SchemaRuntimePolicyService::requireTable('tenant_scope_audit_snapshots', 'auditoria de escopo multiempresa');
      $pdo=Database::forTable('tenant_scope_audit_snapshots');
      $st=$pdo->prepare('INSERT INTO tenant_scope_audit_snapshots(status,resumo,snapshot_json,criado_em) VALUES(?,?,?,NOW())');
      $st->execute([$result['status'],$result['summary'],json_encode($result, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES)]);
    } catch(Throwable $e){ if (class_exists('BestEffortLogService')) BestEffortLogService::warning(__METHOD__, $e); }
  }
}
