<?php
/**
 * V42 - Controller modular planejado: Logs, auditoria e integridade.
 * As rotas legadas continuam em DashboardController para compatibilidade.
 * Este arquivo documenta a separação e serve como ponto de extração progressiva sem quebrar rotas existentes.
 */
class AuditoriaController {
  public static function routes(): array {
    $map = RouteModuleRegistry::architectureControllers();
    return $map['AuditoriaController'] ?? [];
  }

  public function dispatch(string $page): void {
    if ($page === 'auditoria-detalhe') { $this->detalhe(); return; }
    http_response_code(404); echo 'Rota de auditoria não encontrada';
  }

  /**
   * Detalhe de um evento da trilha, com a linha do tempo do Trace ID inteiro.
   *
   * Achado I-17 (2026-09-15): aceitava APENAS `?id=`, mas `auditoriaAssinarTrace()` redireciona
   * para cá com `?trace_id=` — então assinar um trace, que é ação forense, levava o operador a um
   * **404 "Evento não encontrado"**, sem confirmação de que a assinatura tinha funcionado. A tela
   * de Auditoria linka com `?id=` e sempre funcionou; só o retorno da assinatura quebrava.
   *
   * Mora aqui, e não no `DashboardController`, porque acrescentar as duas formas de busca lá
   * estourava em 339 bytes o teto de 160 KB que `v104_48_1_architecture_test.php` impõe — e a
   * resposta que a guarda pede é mover, não levantar o limite. É o mesmo caminho usado no I-12.
   */
  public function detalhe(): void {
    PermissionService::require('auditoria','visualizar');
    $id = (int)($_GET['id'] ?? 0);
    $trace = trim((string)($_GET['trace_id'] ?? ''));
    $pdo = Database::forTable('auditoria_eventos');
    if ($id > 0) {
      $st = $pdo->prepare('SELECT * FROM auditoria_eventos WHERE id=? LIMIT 1');
      $st->execute([$id]);
    } else {
      // Busca por trace cai no primeiro evento dele; a linha do tempo abaixo mostra o trace inteiro.
      $st = $pdo->prepare('SELECT * FROM auditoria_eventos WHERE trace_id=? ORDER BY id ASC LIMIT 1');
      $st->execute([$trace]);
    }
    $evento = $st->fetch();
    if (!$evento) { http_response_code(404); echo 'Evento não encontrado'; return; }
    $st = $pdo->prepare('SELECT * FROM auditoria_eventos WHERE trace_id=? ORDER BY id ASC');
    $st->execute([$evento['trace_id']]);
    $timeline = $st->fetchAll();
    $pageTitle = 'Detalhe da Auditoria';
    require __DIR__.'/../../views/auditoria_detalhe.php';
  }
}
