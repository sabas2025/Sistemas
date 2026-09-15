<?php
/**
 * V104.16 - Contexto multiempresa/multifilial.
 * Mantém compatibilidade com banco antigo e prepara SaaS real com escopo por tenant.
 */
class TenantContextService {
  public static function currentEmpresaId(): ?int {
    // P0-02 (reauditoria 2026-08-23): $_GET nunca é uma fonte confiável para "qual
    // empresa está ativa" - qualquer link/bookmark podia forjar o valor. Também é
    // importante registrar aqui, com honestidade: nenhuma query do projeto hoje usa
    // appendWhereIfColumns() para filtrar dados por empresa/filial (ver método abaixo).
    // Isto é só o seletor de contexto de sessão; não é isolamento de dados por tenant.
    $id = $_SESSION['tenant_empresa_id'] ?? null;
    $id = is_numeric($id) ? (int)$id : 0;
    return $id > 0 ? $id : null;
  }

  public static function currentFilialId(): ?int {
    $id = $_SESSION['tenant_filial_id'] ?? null;
    $id = is_numeric($id) ? (int)$id : 0;
    return $id > 0 ? $id : null;
  }

  public static function set(?int $empresaId, ?int $filialId): void {
    if ($empresaId && $empresaId > 0) $_SESSION['tenant_empresa_id'] = $empresaId; else unset($_SESSION['tenant_empresa_id']);
    if ($filialId && $filialId > 0) $_SESSION['tenant_filial_id'] = $filialId; else unset($_SESSION['tenant_filial_id']);
  }

  public static function context(): array {
    return [
      'empresa_id' => self::currentEmpresaId(),
      'filial_id' => self::currentFilialId(),
      'strict' => self::strictEnabled(),
    ];
  }

  public static function strictEnabled(): bool {
    $cfg = class_exists('App') ? App::config() : [];
    return !empty($cfg['commercial']['tenant_scope_required']);
  }

  public static function requireScopeForOperationalRoute(string $page): void {
    if (!self::strictEnabled()) return;
    $operationalPrefixes = ['pedidos','produtos','estoque','fiscal','fila','reconciliacao'];
    $isOperational = false;
    foreach ($operationalPrefixes as $prefix) {
      if ($page === $prefix || str_starts_with($page, $prefix.'-')) { $isOperational = true; break; }
    }
    if (!$isOperational) return;
    if (!self::currentEmpresaId()) {
      Audit::event('tenant.escopo_ausente','alerta',['mensagem'=>'Rota operacional acessada sem empresa/tenant selecionado.','contexto'=>['page'=>$page]]);
      http_response_code(409);
      exit('Selecione uma empresa/filial antes de operar este módulo.');
    }
  }

  public static function appendWhereIfColumns(string $table, string &$sql, array &$params): void {
    $empresa = self::currentEmpresaId();
    $filial = self::currentFilialId();
    if ($empresa && Database::columnExists($table, 'empresa_id')) { $sql .= ' AND empresa_id=?'; $params[] = $empresa; }
    if ($filial && Database::columnExists($table, 'filial_id')) { $sql .= ' AND filial_id=?'; $params[] = $filial; }
  }
}
