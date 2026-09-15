<?php
/**
 * V104.17 - catálogo de consultas pesadas para orientar refatoração progressiva.
 * Evita quebrar views antigas: sugere colunas leves e permite uso gradual.
 */
class HeavyQueryOptimizerService {
  private static array $lightColumns = [
    'auditoria_eventos' => 'id, trace_id, acao, status, nivel, codigo_erro, mensagem, ip, criado_em',
    'logs_integracao' => 'id, trace_id, tipo, nivel, codigo_erro, mensagem, criado_em',
    'fila_integracao' => 'id, trace_id, tipo, referencia, status, prioridade, tentativas, proxima_tentativa, criado_em, atualizado_em',
    'pedidos_integracao' => 'id, pedido_origem_id, pedido_tiny_id, cliente_nome, status, trace_id, criado_em, atualizado_em',
    'tiny_webhooks' => 'id, evento, status, trace_id, criado_em',
    'webhook_requisicoes' => 'id, origem, status, trace_id, ip, criado_em',
  ];

  public static function columns(string $table, string $fallback='*'): string {
    return self::$lightColumns[$table] ?? $fallback;
  }

  public static function listHeavyTables(): array { return self::$lightColumns; }

  // Reauditoria 2026-09-14 (achado A-12): selectLight() foi removido. Ele validava a tabela,
  // mas interpolava diretamente na consulta os fragmentos $where e $order recebidos do
  // chamador - uma injeção de SQL esperando por um primeiro uso com entrada não confiável.
  // Não havia nenhum chamador no produto, então a remoção elimina o risco sem perda de
  // funcionalidade. Quem precisar de leitura filtrada deve usar prepared statements com
  // filtros estruturados, como fazem DashboardController e DashboardMetricsService.
}
