<?php
class QueueAnalyticsService {
  public static function resumo(): array {
    $pdo=Database::forTable('fila_integracao');
    $status=$pdo->query("SELECT status, COUNT(*) total FROM fila_integracao GROUP BY status")->fetchAll();
    $tipos=$pdo->query("SELECT tipo, status, COUNT(*) total FROM fila_integracao GROUP BY tipo,status ORDER BY total DESC")->fetchAll();
    $dlqErros=$pdo->query("SELECT COALESCE(codigo_erro,'SEM_CODIGO') codigo, COUNT(*) total FROM fila_morta GROUP BY COALESCE(codigo_erro,'SEM_CODIGO') ORDER BY total DESC LIMIT 10")->fetchAll();
    $dlqRefs=$pdo->query("SELECT COALESCE(referencia,'SEM_REF') referencia, COUNT(*) total FROM fila_morta GROUP BY COALESCE(referencia,'SEM_REF') ORDER BY total DESC LIMIT 10")->fetchAll();
    $throughput=$pdo->query("SELECT COUNT(*) total, AVG(TIMESTAMPDIFF(SECOND, criado_em, COALESCE(processado_em,NOW()))) tempo_medio_seg FROM fila_integracao WHERE criado_em >= DATE_SUB(NOW(), INTERVAL 24 HOUR)")->fetch() ?: [];
    return compact('status','tipos','dlqErros','dlqRefs','throughput');
  }
}
