<?php
/**
 * V42 - Controller modular planejado: Tiny V2/V3, webhooks e teste real.
 * As rotas legadas continuam em DashboardController para compatibilidade.
 * Este arquivo documenta a separação e serve como ponto de extração progressiva sem quebrar rotas existentes.
 */
class TinyController {
  public static function routes(): array {
    $map = RouteModuleRegistry::architectureControllers();
    return $map['TinyController'] ?? [];
  }
}
