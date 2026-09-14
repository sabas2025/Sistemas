<?php
/**
 * V104.49.3-R7 - R6 mais as 12 melhorias da seção 8 do relatório de auditoria, com destaque
 * para o isolamento multiempresa (TenantScopeService), a fachada única de rate limit e o
 * estado de saúde consultável dos controles de segurança. A R6 trouxe as correções da
 * reauditoria (achados A-01 a A-13, B-01 a B-06) e a portabilidade MariaDB; a R7 existe
 * porque o artefato mudou de conteúdo e precisa de identidade própria (achado A-05).
 * Aplica melhorias visuais de grande porte, testes de regressão, idempotência
 * bloqueante antes da chamada externa, retry por categoria e observabilidade ampliada.
 */
class SystemVersionService {
  public const VERSION = 'V104.49.3';
  public const VERSION_NUMBER = '104.49.3';
  public const CODENAME = 'R7 — Isolamento multiempresa e melhorias estruturais aplicadas';
  public const RELEASE = 'R7';
  // Mantido para reconhecer instalações e registros históricos da R4.
  public const SCHEMA_VERSION = 'v104_49_2_enterprise_map_recovery_r4';
  public const INSTALL_SQL = 'database/install_final_current.sql';
  public const INSTALL_CURRENT_SQL = 'database/install_final_current.sql';
  public const REPAIR_CURRENT_SQL = 'database/repair_current.sql';
  public const RELEASE_DATE = '2026-09-14';

  public static function version(): string { return self::VERSION; }
  public static function label(): string { return self::VERSION; }
  public static function fullLabel(): string { return self::VERSION . ' — ' . self::CODENAME; }
  public static function installSql(): string { return self::INSTALL_CURRENT_SQL; }
  public static function repairSql(): string { return self::REPAIR_CURRENT_SQL; }
  public static function migration(): string { return self::SCHEMA_VERSION; }
  public static function schemaMessage(string $context = 'schema'): string {
    return 'Executar Central Técnica > Enterprise Core (Simular e depois Aplicar) ou aplicar, nesta ordem, database/migrations/20260712_001_queue_oauth_concurrency.sql, 20260712_002_schema_runtime_vsm_contract.sql, 20260712_003_missing_core_tables.sql, 20260712_004_vsm_security_selftest_recovery.sql e 20260713_007_enterprise_map_recovery.sql. A V104.49.3-R5 valida tabelas, colunas, tipos, valores padrão e índices críticos sem DDL em requisições operacionais.';
  }
  public static function updateBlockedMessage(): string {
    return 'Update legado bloqueado na ' . self::VERSION . '. Use Central Técnica > Enterprise Core (Simular e Aplicar), Migrações Seguras ou Validar Banco. Para uma instalação nova, use o instalador oficial; não use repair_current.sql de versões anteriores.';
  }
  public static function info(): array {
    return [
      'version' => self::VERSION,
      'number' => self::VERSION_NUMBER,
      'codename' => self::CODENAME,
      'release' => self::RELEASE,
      'schema_version' => self::SCHEMA_VERSION,
      'release_date' => self::RELEASE_DATE,
      'install_sql' => self::INSTALL_CURRENT_SQL,
      'repair_sql' => self::REPAIR_CURRENT_SQL !== '' ? self::REPAIR_CURRENT_SQL : null,
    ];
  }
}
