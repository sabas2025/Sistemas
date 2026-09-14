<?php
/**
 * V82 - Homologação XML/NF-e do fluxo real VSM → HUB → Tiny.
 * Não homologa emissão fiscal pelo Tiny; valida recebimento, chave, vínculo, envio e auditoria.
 */
class XmlNfeHomologationService {
  public static function responsabilidades(): array {
    return [
      'origem' => 'VSM',
      'hub' => ['receber_xml','validar_chave','armazenar_xml','vincular_pedido','auditar_trace','reenviar_tiny_quando_aplicavel'],
      'tiny' => ['receber_xml_ou_retorno_quando_api_disponivel','vincular_informacao_ao_pedido'],
      'fora_do_fluxo_tiny' => ['emitir_nfe','autorizar_sefaz','certificado_digital','carta_correcao','inutilizacao','cancelamento_fiscal']
    ];
  }
  public static function resumoOperacional(): array {
    $base = ['total'=>0,'pendentes'=>0,'erros'=>0,'xml_sem_envio'=>0,'erro_integracao'=>0,'ultimo_trace'=>null,'status'=>'pendente','checks'=>self::checksBase()];
    try { if (class_exists('FiscalIntegrationService')) $base = array_merge($base, FiscalIntegrationService::resumo() ?: []); } catch (Throwable $e) { $base['erros']++; }
    try { if (class_exists('FiscalEnterpriseService')) $base = array_merge($base, FiscalEnterpriseService::reconciliacao() ?: []); } catch (Throwable $e) { $base['erros']++; }
    $base['status'] = ((int)($base['erros'] ?? 0) > 0 || (int)($base['erro_integracao'] ?? 0) > 0) ? 'atencao' : (((int)($base['total'] ?? 0) > 0) ? 'ok' : 'pendente');
    return $base;
  }
  public static function checksBase(): array {
    return [
      ['key'=>'recebimento_vsm','label'=>'Recebimento VSM','status'=>self::tableExists('notas_fiscais') ? 'ok' : 'pendente'],
      ['key'=>'xml_armazenado','label'=>'XML armazenado','status'=>self::tableExists('nfe_xml') ? 'ok' : 'pendente'],
      ['key'=>'fila_envio_tiny','label'=>'Fila/Reenvio Tiny','status'=>self::tableExists('nfe_integracao') ? 'ok' : 'pendente'],
      ['key'=>'auditoria','label'=>'Auditoria Trace ID','status'=>self::tableExists('logs_integracao') ? 'ok' : 'pendente'],
    ];
  }
  public static function validarChaveNfe(?string $chave): array {
    $chave = preg_replace('/\D+/', '', (string)$chave);
    $ok = strlen($chave) === 44 && self::dvNfeValido($chave);
    return ['ok'=>$ok,'chave'=>$chave,'tamanho'=>strlen($chave),'mensagem'=>$ok ? 'Chave NF-e válida.' : 'Chave NF-e inválida ou incompleta.'];
  }
  public static function localizarPedidoRelacionado(array $nota): array {
    foreach (['pedido_id','id_pedido','pedido_numero','numero_pedido','pedido'] as $campo) {
      if (!empty($nota[$campo])) return ['ok'=>true,'campo'=>$campo,'valor'=>(string)$nota[$campo]];
    }
    return ['ok'=>false,'campo'=>null,'valor'=>null,'mensagem'=>'Nota sem campo de pedido identificado.'];
  }
  public static function compararPedidoNota(array $pedido, array $nota): array {
    $valorPedido = (float)($pedido['valor_total'] ?? $pedido['total'] ?? 0);
    $valorNota = (float)($nota['valor_total'] ?? $nota['total'] ?? 0);
    $dif = round($valorNota - $valorPedido, 2);
    return ['ok'=>abs($dif) <= 0.01,'valor_pedido'=>$valorPedido,'valor_nota'=>$valorNota,'diferenca'=>$dif];
  }
  public static function registrarEvidencia(string $etapa, array $contexto=[]): void {
    Audit::event('xml_nfe.homologacao.'.$etapa, 'info', ['mensagem'=>'Evidência XML/NF-e registrada.', 'contexto'=>$contexto]);
  }
  private static function tableExists(string $table): bool {
    try { Database::forTable($table)->query('SELECT 1 FROM '.$table.' LIMIT 1'); return true; } catch (Throwable $e) { return false; }
  }
  private static function dvNfeValido(string $chave): bool {
    if (strlen($chave) !== 44) return false;
    $base = substr($chave, 0, 43); $dv = (int)substr($chave, 43, 1); $peso=2; $soma=0;
    for ($i=42; $i>=0; $i--) { $soma += ((int)$base[$i]) * $peso; $peso = ($peso === 9) ? 2 : $peso + 1; }
    $calc = 11 - ($soma % 11); if ($calc >= 10) $calc = 0;
    return $calc === $dv;
  }
}
