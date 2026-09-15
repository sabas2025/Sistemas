<?php
/**
 * V42 - Controller modular planejado: Homologação e self-test.
 * As rotas legadas continuam em DashboardController para compatibilidade.
 * Este arquivo documenta a separação e serve como ponto de extração progressiva sem quebrar rotas existentes.
 */
class HomologacaoController {
  public static function routes(): array {
    $map = RouteModuleRegistry::architectureControllers();
    return $map['HomologacaoController'] ?? [];
  }
}
