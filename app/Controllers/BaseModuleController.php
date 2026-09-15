<?php
abstract class BaseModuleController {
  /**
   * Renderiza uma view com variáveis padrão para evitar warnings de variável indefinida.
   * Também padroniza mensagens comuns usadas pelos módulos.
   */
  protected function view(string $view, array $vars = []): void {
    $defaults = [
      'erro' => null,
      'erroFiscal' => null,
      'sucesso' => null,
      'mensagem' => null,
      'warning' => null,
      'info' => null,
      'status' => $_GET['status'] ?? '',
      'busca' => $_GET['busca'] ?? '',
    ];
    extract(array_merge($defaults, $vars), EXTR_SKIP);
    require __DIR__.'/../../views/'.$view.'.php';
  }

  protected function defaultViewData(array $extra = []): array {
    return array_merge([
      'erro' => null,
      'erroFiscal' => null,
      'sucesso' => null,
      'mensagem' => null,
      'warning' => null,
      'info' => null,
      'status' => $_GET['status'] ?? '',
      'busca' => $_GET['busca'] ?? '',
    ], $extra);
  }
}
