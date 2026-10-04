<?php
/**
 * Correlação de eventos entre telas, logs, filas e integrações (Fase 11 — O11-1).
 *
 * A tabela `evento_correlacao` existe desde o início do Hub com as colunas desenhadas para
 * correlacionar trace↔webhook↔fila↔pedido↔sku↔nf, mas até 2026-10-04 NENHUM componente a
 * escrevia: o painel de correlação (SecurityController) ficava sempre vazio e o score de
 * prontidão (ProductionReadinessV24Service) premiava uma tabela que não trabalhava — o
 * "indicador que mente" do CLAUDE.md. Este serviço liga a escrita.
 *
 * Desenho:
 *  - É CHAMADO dos chokepoints centrais do ciclo de fila (IntegrationEventService), então os
 *    6 sites de enfileiramento não precisam mudar.
 *  - O `trace_id` gravado é o da ORIGEM (o `trace_id` carimbado no item da fila quando o webhook
 *    o enfileirou — T1). O trace do processo que processa (o worker — T2) vai em `detalhes`,
 *    amarrado pelo `fila_id`. Assim a linha única da correlação carrega as DUAS pontas do fluxo
 *    assíncrono (resolve O11-2/O11-3: o trace de origem, antes capturado e nunca usado, passa a
 *    ser o eixo da correlação).
 *  - Escrita best-effort dentro de `catch(Throwable)`, como os demais pontos de observabilidade:
 *    falhar aqui NUNCA pode derrubar o processamento da fila.
 *  - A tabela não tem `empresa_id` e não está no catálogo do TenantScopeService; coerente com a
 *    decisão de empresa única (o painel de segurança já a lê globalmente). Não é inventada coluna
 *    nem alterado schema.
 */
class EventCorrelationService {
  public static function registrar(array $d): void {
    try {
      if (!class_exists('Database') || !Database::tableExists('evento_correlacao')) return;
      $trace = (string)($d['trace_id'] ?? (class_exists('RequestContext') ? RequestContext::id() : ''));
      if ($trace === '') return;
      $detalhes = $d['detalhes'] ?? null;
      if ($detalhes !== null && !is_string($detalhes)) {
        $detalhes = json_encode($detalhes, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_PARTIAL_OUTPUT_ON_ERROR);
      }
      Database::forTable('evento_correlacao')->prepare(
        'INSERT INTO evento_correlacao(trace_id,origem,tipo_evento,pedido_id,produto_sku,nf_chave,webhook_id,fila_id,detalhes) VALUES(?,?,?,?,?,?,?,?,?)'
      )->execute([
        substr($trace, 0, 80),
        self::clip($d['origem'] ?? null, 60),
        self::clip($d['tipo_evento'] ?? null, 80),
        self::clip($d['pedido_id'] ?? null, 120),
        self::clip($d['produto_sku'] ?? null, 120),
        self::clip($d['nf_chave'] ?? null, 120),
        isset($d['webhook_id']) && $d['webhook_id'] !== null ? (int)$d['webhook_id'] : null,
        isset($d['fila_id']) && $d['fila_id'] !== null ? (int)$d['fila_id'] : null,
        $detalhes,
      ]);
    } catch (Throwable $e) {
      if (class_exists('BestEffortLogService')) BestEffortLogService::warning(__METHOD__, $e, ['operation'=>'event_correlation']);
    }
  }

  private static function clip($value, int $max): ?string {
    if ($value === null) return null;
    $s = (string)$value;
    if ($s === '') return null;
    return substr($s, 0, $max);
  }
}
