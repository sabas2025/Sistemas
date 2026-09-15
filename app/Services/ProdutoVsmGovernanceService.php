<?php
class ProdutoVsmGovernanceService {
  public static function isNewProductEvent(string $tipoFila): bool {
    return $tipoFila === 'produto_vsm_para_tiny';
  }

  public static function sku(string $sku): string { return trim($sku); }

  public static function mappingForSku(string $sku): ?array {
    $sku = self::sku($sku);
    if ($sku === '') return null;
    try {
      $pdo = Database::forTable('produtos_mapeamento');
      $st = TenantScopeService::run('produtos_mapeamento', 'SELECT * FROM produtos_mapeamento WHERE sku_vsm=? OR sku_tiny=? LIMIT 1', [$sku,$sku]);
      $row = $st->fetch(PDO::FETCH_ASSOC);
      return $row ?: null;
    } catch (Throwable $e) {
      Audit::exception($e,'produto_vsm.governance.mapping_lookup_error',['sku'=>$sku]);
      return null;
    }
  }

  public static function categoryMappingForPayload(array $payload): ?array {
    $id = trim((string)($payload['categoria_id'] ?? $payload['id_categoria'] ?? $payload['categoriaVsmId'] ?? $payload['categoria_vsm_id'] ?? ''));
    $nome = trim((string)($payload['categoria'] ?? $payload['nome_categoria'] ?? $payload['categoria_nome'] ?? ''));
    try {
      $pdo = Database::forTable('categorias_mapeamento');
      if ($id !== '') {
        $st = TenantScopeService::run('categorias_mapeamento', 'SELECT * FROM categorias_mapeamento WHERE id_categoria_vsm=? AND ativo=1 ORDER BY prioridade DESC,id DESC LIMIT 1', [$id]);
        $row = $st->fetch(PDO::FETCH_ASSOC);
        if ($row) return $row;
      }
      if ($nome !== '') {
        $st = TenantScopeService::run('categorias_mapeamento', 'SELECT * FROM categorias_mapeamento WHERE nome_categoria_vsm=? AND ativo=1 ORDER BY prioridade DESC,id DESC LIMIT 1', [$nome]);
        $row = $st->fetch(PDO::FETCH_ASSOC);
        if ($row) return $row;
      }
    } catch (Throwable $e) {
      Audit::exception($e,'produto_vsm.governance.category_lookup_error',['payload'=>$payload]);
    }
    return null;
  }

  public static function isAutoCreateAllowed(array $config): bool {
    $modo = $config['produto_novo_aprovacao_modo'] ?? 'manual';
    return !empty($config['fluxo_vsm_tiny_produto'])
      && $modo === 'automatico'
      && empty($config['sync_bloquear_produto_novo_vsm'])
      && !empty($config['sync_criar_produto_tiny'])
      && !empty($config['sync_criar_produto_se_nao_existir']);
  }

  public static function createPending(array $payload, string $sku, string $tipoEvento, string $motivo, string $mensagem, ?int $filaId=null, ?string $traceId=null, array $extras=[]): int {
    $traceId = $traceId ?: RequestContext::id();
    try {
      $pdo = Database::forTable('produtos_pendentes_integracao');
      $hash = hash('sha256', json_encode($payload, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES));
      $categoria = self::categoryMappingForPayload($payload);
      $catVsmId = (string)($payload['categoria_id'] ?? $payload['id_categoria'] ?? $payload['categoriaVsmId'] ?? $payload['categoria_vsm_id'] ?? '');
      $catVsmNome = (string)($payload['categoria'] ?? $payload['nome_categoria'] ?? $payload['categoria_nome'] ?? '');
      $catTinyId = $categoria['id_categoria_tiny'] ?? null;
      $catTinyNome = $categoria['nome_categoria_tiny'] ?? null;
      $st = TenantScopeService::run('produtos_pendentes_integracao', 'INSERT INTO produtos_pendentes_integracao(origem, sku, ean, nome, categoria_vsm_id, categoria_vsm_nome, categoria_tiny_id_sugerida, categoria_tiny_nome_sugerida, payload_json, payload_hash, acao_recomendada, status, motivo, mensagem, fila_id, trace_id) VALUES(?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?) ON DUPLICATE KEY UPDATE payload_json=VALUES(payload_json), acao_recomendada=VALUES(acao_recomendada), motivo=VALUES(motivo), mensagem=VALUES(mensagem), fila_id=COALESCE(VALUES(fila_id), fila_id), trace_id=VALUES(trace_id), atualizado_em=NOW()', [
        'vsm', $sku, (string)($payload['ean'] ?? $payload['gtin'] ?? ''), (string)($payload['nome'] ?? $payload['descricao'] ?? ''),
        $catVsmId ?: null, $catVsmNome ?: null, $catTinyId, $catTinyNome,
        json_encode($payload,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES), $hash,
        $extras['acao_recomendada'] ?? 'Aprovar manualmente, vincular a produto Tiny existente ou rejeitar.',
        'pendente', $motivo, $mensagem, $filaId, $traceId
      ]);
      $id = (int)$pdo->lastInsertId();
      if ($id === 0) {
        $st2 = TenantScopeService::run('produtos_pendentes_integracao', 'SELECT id FROM produtos_pendentes_integracao WHERE origem=? AND sku=? AND payload_hash=? LIMIT 1', ['vsm',$sku,$hash]);
        $id = (int)($st2->fetchColumn() ?: 0);
      }
      // compatibilidade com tela antiga de pendências
      try {
        TenantScopeService::run('produto_pendencias', 'INSERT INTO produto_pendencias(sku,tipo_evento,motivo,mensagem,payload,fila_id,trace_id,status) VALUES(?,?,?,?,?,?,?,?)', [$sku,$tipoEvento,$motivo,$mensagem,json_encode($payload,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES),$filaId,$traceId,'aberto']);
      } catch(Throwable $e) { if(class_exists('BestEffortLogService')) BestEffortLogService::warning(__METHOD__, $e, ['compat_table'=>'produto_pendencias']); }
      Audit::event('produto_vsm.pendente.criado','alerta',['entidade'=>'produtos_pendentes_integracao','entidade_id'=>$id,'mensagem'=>$mensagem,'payload'=>$payload,'contexto'=>['sku'=>$sku,'motivo'=>$motivo,'tipo_evento'=>$tipoEvento]]);
      return $id;
    } catch (Throwable $e) {
      Audit::exception($e,'produto_vsm.governance.pending_create_error',['sku'=>$sku,'payload'=>$payload]);
      return 0;
    }
  }

  public static function evaluate(array $payload, string $sku, string $tipoFila, array $config): array {
    $sku = self::sku($sku);
    $mapping = self::mappingForSku($sku);
    $category = self::categoryMappingForPayload($payload);
    if (!empty($payload['hub_aprovado_manual']) && !empty($payload['hub_aprovado_por'])) {
      return ['action'=>'queue','reason'=>'MANUALLY_APPROVED','message'=>'Produto novo aprovado manualmente no Hub.','mapping'=>$mapping,'category'=>$category];
    }
    $isNew = self::isNewProductEvent($tipoFila) && !$mapping;

    if (!$isNew && !empty($config['sync_exigir_mapeamento_sku']) && !$mapping) {
      return ['action'=>'pending','reason'=>'MAPPING_REQUIRED','message'=>'SKU recebido da VSM não possui vínculo aprovado no Hub. Atualização bloqueada até mapear SKU VSM ↔ Tiny.','mapping'=>$mapping,'category'=>$category];
    }

    if ($isNew) {
      $cfgV51 = class_exists('ProductApprovalPolicyService') ? ProductApprovalPolicyService::config() : $config;
      $config = array_merge($config, $cfgV51);
      if (($config['produto_novo_aprovacao_modo'] ?? 'manual') === 'manual') {
        return ['action'=>'pending','reason'=>'MANUAL_APPROVAL_MODE','message'=>'Produto novo da VSM está em modo manual e precisa de aprovação no Hub.','mapping'=>$mapping,'category'=>$category];
      }
      if (!empty($config['sync_bloquear_produto_novo_vsm']) || !self::isAutoCreateAllowed($config)) {
        return ['action'=>'pending','reason'=>'NEW_PRODUCT_BLOCKED','message'=>'Produto novo da VSM recebido, mas criação automática no Tiny está bloqueada por segurança.','mapping'=>$mapping,'category'=>$category];
      }
      if (!empty($config['sync_exigir_categoria_mapeada_vsm']) && !$category) {
        return ['action'=>'pending','reason'=>'CATEGORY_MAPPING_REQUIRED','message'=>'Produto novo da VSM sem categoria VSM ↔ Tiny mapeada.','mapping'=>$mapping,'category'=>$category];
      }
      if (!empty($config['sync_aprovacao_manual_produto_novo_vsm'])) {
        return ['action'=>'pending','reason'=>'MANUAL_APPROVAL_REQUIRED','message'=>'Produto novo da VSM exige aprovação manual antes de criar no Tiny.','mapping'=>$mapping,'category'=>$category];
      }
      if (class_exists('ProductApprovalPolicyService')) {
        $auto = ProductApprovalPolicyService::autoProductAllowed($payload, $sku, $tipoFila, $config);
        if (empty($auto['ok'])) return ['action'=>'pending','reason'=>'AUTO_CHECKLIST_FAILED','message'=>'Produto novo automático bloqueado: '.($auto['reason'] ?? 'checklist inválido'),'mapping'=>$mapping,'category'=>$category,'auto'=>$auto];
      }
    }
    return ['action'=>'queue','reason'=>'OK','message'=>'Evento liberado para fila.','mapping'=>$mapping,'category'=>$category];
  }
}
