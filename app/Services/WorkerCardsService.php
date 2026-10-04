<?php
/**
 * Fonte única dos "cards" de monitor de workers: status derivado da existência do shim público
 * (public/worker_*.php). Consolida o laço antes duplicado em DashboardMetricsService e
 * OperationCenterService (achado A3-N3 da auditoria de arquitetura 2026-10-04).
 *
 * A LISTA de workers é de cada chamador — os painéis mostram conjuntos diferentes de propósito
 * (o dashboard mostra os essenciais; o centro de operações mostra mais). Aqui mora só o laço
 * is_file()→card, para não haver duas cópias que divirjam ao incluir/remover um worker.
 */
class WorkerCardsService {
  /**
   * @param array<array{arquivo:string,titulo:string}> $workers
   * @return array<array{titulo:string,arquivo:string,status:string,detalhe:string}>
   */
  public static function build(array $workers): array {
    $out = [];
    foreach ($workers as $w) {
      $arquivo = (string)($w['arquivo'] ?? '');
      $titulo  = (string)($w['titulo'] ?? $arquivo);
      $exists  = $arquivo !== '' && is_file(__DIR__.'/../../public/'.$arquivo);
      $out[] = [
        'titulo'  => $titulo,
        'arquivo' => $arquivo,
        'status'  => $exists ? 'online' : 'erro',
        'detalhe' => $exists ? 'Arquivo disponível para cron/agendamento' : 'Arquivo não encontrado',
      ];
    }
    return $out;
  }
}
