<?php
class RetentionService {
  /**
   * Expurgo por idade das tabelas operacionais que o `DataRetentionService` NÃO cobre.
   *
   * Os dois serviços se complementam e não se sobrepõem: aquele trata `fila_integracao` e
   * `sessoes` (achados C-04 e D-01, pelo `workers/worker_retencao.php`); este trata as seis
   * tabelas abaixo, pelo `workers/worker_backup.php`.
   *
   * Achado I-15 (2026-09-15): a variável `$pdo` usada no laço **nunca era definida**. Cada uma das
   * seis deleções morria com `Call to a member function prepare() on null`, o `catch(Throwable)`
   * transformava o erro numa string dentro do resultado, e logo abaixo `Audit::event()` registrava
   * o conjunto como **'sucesso'** — um evento de sucesso carregando seis erros. Efeito: essas seis
   * tabelas nunca foram expurgadas em nenhuma instalação, e o indicador dizia o contrário.
   *
   * O desenho agora é o mesmo do serviço que funciona: conexão resolvida POR TABELA (elas vivem em
   * módulos diferentes), guarda de existência de tabela e coluna, e `LIMIT` por execução — sem ele
   * a primeira passada numa instalação antiga tentaria apagar anos de uma vez e prenderia a tabela.
   *
   * @return array<string,mixed> contagem por alvo, mais os backups removidos
   */
  public static function limparOperacional(int $diasLogs=90, int $diasAuditoria=180, int $diasWebhooks=180): array {
    // Teto por execução, igual ao de DataRetentionService::cleanupFilaConcluida(). O worker roda
    // periodicamente: o que sobrar sai na próxima passada.
    $limite = 5000;
    $map = [
      'logs'        => ['logs_integracao',     $diasLogs],
      'auditoria'   => ['auditoria_eventos',   $diasAuditoria],
      'webhooks'    => ['tiny_webhooks',       $diasWebhooks],
      'metricas'    => ['metricas_api',        $diasLogs],
      'diagnostico' => ['diagnostico_api',     $diasLogs],
      'selftests'   => ['selftest_relatorios', $diasLogs],
    ];
    $result = [];
    $erros = [];
    foreach ($map as $chave => [$tabela, $dias]) {
      try {
        if (!Database::tableExists($tabela) || !Database::columnExists($tabela, 'criado_em')) {
          $result[$chave] = 'ignorado: tabela ou coluna ausente';
          continue;
        }
        $st = Database::forTable($tabela)->prepare(
          'DELETE FROM `'.$tabela.'` WHERE criado_em < DATE_SUB(NOW(), INTERVAL ? DAY) LIMIT '.$limite
        );
        $st->execute([(int)$dias]);
        $result[$chave] = (int)$st->rowCount();
      } catch (Throwable $e) {
        $result[$chave] = 'erro: '.$e->getMessage();
        $erros[] = $chave;
        if (class_exists('BestEffortLogService')) BestEffortLogService::warning(__METHOD__, $e, ['tabela'=>$tabela]);
      }
    }
    $backups = BackupService::limparAntigos(30);
    // O status do evento segue o que aconteceu. Antes era 'sucesso' fixo — foi assim que o defeito
    // sobreviveu: quem olhasse a Auditoria via sucesso.
    Audit::event('retencao.limpeza', $erros === [] ? 'sucesso' : 'alerta', [
      'codigo_erro'=>$erros === [] ? null : 'RETENTION_PARTIAL_FAILURE',
      'mensagem'=>$erros === []
        ? 'Retenção operacional executada.'
        : 'Retenção operacional concluída com falha em: '.implode(', ', $erros).'.',
      'contexto'=>array_merge($result, ['backups_removidos'=>$backups, 'limite_por_execucao'=>$limite]),
      'causa_provavel'=>$erros === [] ? null : 'Tabela indisponível, permissão de DELETE ausente ou conexão do módulo com problema.',
      'acao_recomendada'=>'Mantenha backup externo antes de reduzir os prazos de retenção.'
    ]);
    return array_merge($result, ['backups_removidos'=>$backups]);
  }
}
