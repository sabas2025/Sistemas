<?php
/**
 * V42 - Controller modular planejado: Configurações, usuários, bancos e backups.
 * As rotas legadas continuam em DashboardController para compatibilidade.
 * Este arquivo documenta a separação e serve como ponto de extração progressiva sem quebrar rotas existentes.
 */
class ConfiguracaoController {
  public static function routes(): array {
    $map = RouteModuleRegistry::architectureControllers();
    return $map['ConfiguracaoController'] ?? [];
  }
}
