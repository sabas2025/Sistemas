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
  public const CODENAME = 'R7 — Revisão de release e MyOuro de consulta; multicliente bloqueado';
  public const RELEASE = 'R7';
  // Mantido para reconhecer instalações e registros históricos da R4.
  public const SCHEMA_VERSION = 'v104_49_2_enterprise_map_recovery_r4';
  public const INSTALL_SQL = 'database/install_final_current.sql';
  public const INSTALL_CURRENT_SQL = 'database/install_final_current.sql';
  public const REPAIR_CURRENT_SQL = 'database/repair_current.sql';
  public const RELEASE_DATE = '2026-09-17';
  public const BUILD = '20260917.1';
  public const BASELINE_LAST_MIGRATION = '20260713_007_enterprise_map_recovery.sql';
  public static function artifactVersion(): string { return self::VERSION.'-'.self::RELEASE.'+'.self::BUILD; }

  public static function version(): string { return self::VERSION; }
  public static function label(): string { return self::artifactVersion(); }
  public static function fullLabel(): string { return self::artifactVersion() . ' — ' . self::CODENAME; }
  public static function installSql(): string { return self::INSTALL_CURRENT_SQL; }
  public static function repairSql(): string { return self::REPAIR_CURRENT_SQL; }
  public static function migration(): string { return self::SCHEMA_VERSION; }
  public static function schemaMessage(string $context = 'schema'): string {
    return 'Schema pendente em '.$context.'. Consulte UPGRADE-R7-20260917.md e execute php scripts/upgrade-r7.php --dry-run. A tela Migrações Seguras registra a solicitação; não aplica SQL. BIGINT exige janela e restauração testada. Release '.self::artifactVersion().'.';
  }
  public static function updateBlockedMessage(): string {
    return 'Update legado bloqueado na ' . self::VERSION . '. Use Central Técnica > Enterprise Core (Simular e Aplicar), Migrações Seguras ou Validar Banco. Para uma instalação nova, use o instalador oficial; não use repair_current.sql de versões anteriores.';
  }
  public static function info(): array {
    return [
      'version' => self::artifactVersion(),
      'build' => self::BUILD,
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
