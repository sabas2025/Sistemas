<?php
/**
 * V47 - Camadas extras de aprovação de produto VSM -> Tiny.
 * Centraliza validações para evitar cadastro duplicado, incompleto ou fiscalmente inseguro.
 */
class ProdutoVsmApprovalGuardService {
  public static function payloadFromPending(array $p): array {
    $payload = json_decode((string)($p['payload_json'] ?? '{}'), true);
    return is_array($payload) ? $payload : [];
  }

  public static function ean(array $p, array $payload): string {
    return trim((string)($p['ean'] ?? $payload['ean'] ?? $payload['gtin'] ?? $payload['codigo_gtin'] ?? $payload['codigoBarras'] ?? ''));
  }

  public static function ncm(array $payload): string {
    return trim((string)($payload['ncm'] ?? $payload['NCM'] ?? $payload['codigo_ncm'] ?? $payload['tributacao']['ncm'] ?? ''));
  }

  public static function name(array $p, array $payload): string {
    return trim((string)($p['nome'] ?? $payload['nome'] ?? $payload['descricao'] ?? $payload['descricao_produto'] ?? ''));
  }

  public static function categoryMapped(array $p): bool {
    return trim((string)($p['categoria_tiny_id_sugerida'] ?? '')) !== '' || trim((string)($p['categoria_tiny_nome_sugerida'] ?? '')) !== '';
  }

  public static function duplicateCandidates(array $p, array $payload): array {
    $sku = trim((string)($p['sku'] ?? $payload['sku'] ?? $payload['codigo'] ?? ''));
    $ean = self::ean($p, $payload);
    $nome = self::name($p, $payload);
    $candidates = [];

    // 1) Mapeamento existente por SKU/EAN aproximado.
    try {
      $pdo = Database::forTable('produtos_mapeamento');
      $params = [];
      $where = [];
      if ($sku !== '') { $where[] = '(sku_vsm=? OR sku_tiny=?)'; $params[]=$sku; $params[]=$sku; }
      if ($ean !== '') { $where[] = '(descricao LIKE ?)'; $params[]='%'.$ean.'%'; }
      if ($where) {
        $st = TenantScopeService::run('produtos_mapeamento', 'SELECT "produtos_mapeamento" AS fonte, id, sku_vsm, sku_tiny, produto_tiny_id, produto_vsm_id, descricao FROM produtos_mapeamento WHERE '.implode(' OR ', $where).' LIMIT 10', $params);
        foreach($st->fetchAll(PDO::FETCH_ASSOC) as $r){ $candidates[] = $r + ['motivo'=>'SKU/EAN já mapeado']; }
      }
    } catch(Throwable $e) { Audit::exception($e, 'produto_vsm.aprovacao.duplicidade_mapeamento'); }

    // 2) Catálogo local Tiny, se existir.
    try {
      $pdo = Database::forTable('produtos_tiny');
      $tests = [];
      $params = [];
      if ($sku !== '') { $tests[]='(sku=? OR codigo=? OR codigo_sku=?)'; $params[]=$sku; $params[]=$sku; $params[]=$sku; }
      if ($ean !== '') { $tests[]='(ean=? OR gtin=? OR codigo_barras=?)'; $params[]=$ean; $params[]=$ean; $params[]=$ean; }
      if ($nome !== '') { $tests[]='(nome LIKE ? OR descricao LIKE ?)'; $like='%'.mb_substr($nome,0,40).'%'; $params[]=$like; $params[]=$like; }
      if ($tests) {
        $st = TenantScopeService::run('produtos_tiny', 'SELECT "produtos_tiny" AS fonte, id, sku, codigo, ean, gtin, nome, descricao FROM produtos_tiny WHERE '.implode(' OR ', $tests).' LIMIT 10', $params);
        foreach($st->fetchAll(PDO::FETCH_ASSOC) as $r){ $candidates[] = $r + ['motivo'=>'Possível produto existente no Tiny local']; }
      }
    } catch(Throwable $e) { if(class_exists('BestEffortLogService')) BestEffortLogService::warning(__METHOD__, $e, ['compat_table'=>'produtos_tiny']); }

    return array_slice($candidates, 0, 10);
  }

  public static function checklist(array $p): array {
    $payload = self::payloadFromPending($p);
    $ean = self::ean($p, $payload);
    $ncm = self::ncm($payload);
    $cat = self::categoryMapped($p);
    $dup = self::duplicateCandidates($p, $payload);
    $sku = trim((string)($p['sku'] ?? ''));

    $items = [
      'sku' => ['ok'=>$sku !== '', 'titulo'=>'SKU informado', 'mensagem'=>$sku !== '' ? 'SKU recebido.' : 'SKU ausente.'],
      'ean' => ['ok'=>$ean !== '', 'titulo'=>'EAN/GTIN informado', 'mensagem'=>$ean !== '' ? 'EAN/GTIN recebido.' : 'EAN/GTIN obrigatório para evitar duplicidade e erro fiscal.'],
      'ncm' => ['ok'=>$ncm !== '', 'titulo'=>'NCM informado', 'mensagem'=>$ncm !== '' ? 'NCM recebido no payload.' : 'NCM obrigatório para produto de farmácia antes de criar no Tiny.'],
      'categoria' => ['ok'=>$cat, 'titulo'=>'Categoria VSM ↔ Tiny mapeada', 'mensagem'=>$cat ? 'Categoria Tiny sugerida disponível.' : 'Sem categoria Tiny mapeada.'],
      'duplicidade' => ['ok'=>count($dup) === 0, 'titulo'=>'Sem duplicidade provável', 'mensagem'=>count($dup) === 0 ? 'Nenhuma duplicidade local encontrada.' : 'Encontramos possível duplicidade; prefira vincular ao Tiny existente.'],
      'confirmacao' => ['ok'=>true, 'titulo'=>'Confirmação por botão', 'mensagem'=>'Ação confirmada por botão protegido com CSRF, permissão e validações do checklist.'],
    ];
    $bloqueios = [];
    foreach($items as $k=>$item){ if(!$item['ok'] && $k !== 'confirmacao') $bloqueios[] = $item['mensagem']; }
    return ['items'=>$items, 'bloqueios'=>$bloqueios, 'duplicidades'=>$dup, 'payload'=>$payload, 'ean'=>$ean, 'ncm'=>$ncm, 'pode_aprovar'=>empty($bloqueios)];
  }

  public static function registrarHistorico(int $pendenciaId, string $acao, string $resultado, string $mensagem, array $contexto=[]): void {
    try {
      TenantScopeService::run('produtos_aprovacao_historico', 'INSERT INTO produtos_aprovacao_historico(produto_pendente_id, acao, resultado, mensagem, contexto_json, usuario_id, trace_id) VALUES(?,?,?,?,?,?,?)', [$pendenciaId, $acao, $resultado, $mensagem, json_encode($contexto,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES), Auth::user()['id'] ?? null, RequestContext::id()]);
    } catch(Throwable $e) { Audit::exception($e, 'produto_vsm.aprovacao.historico_error', ['produto_pendente_id'=>$pendenciaId]); }
  }
}
