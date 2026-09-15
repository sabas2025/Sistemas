<?php require __DIR__.'/layout_top.php'; ?>
<div class="d-flex flex-wrap justify-content-between align-items-start gap-2 mb-3">
  <div>
    <h2 class="h4 mb-1">Teste Real Tiny</h2>
    <p class="text-muted mb-0">Cria produto, atualiza estoque e inativa/ativa produto diretamente no Tiny configurado no Hub.</p>
  </div>
  <a class="btn btn-outline-secondary btn-sm" href="index.php?page=tiny-v3-ficha"><i class="bi bi-file-earmark-code"></i> Ficha Tiny V3</a>
</div>

<div class="alert alert-warning border-0 shadow-sm">
  <b>Atenção:</b> esta tela executa chamadas reais na API do Tiny. Por segurança, o Hub agora bloqueia qualquer SKU que não comece com <code><?=e(TesteRealTinyService::SKU_PREFIXO_SEGURO)?></code>. Exemplo seguro: <code><?=e(TesteRealTinyService::skuPadrao())?></code>.
</div>

<div class="row g-3">
  <div class="col-xl-5">
    <div class="card shadow-sm border-0">
      <div class="card-header bg-white"><b>Parâmetros do teste real</b></div>
      <div class="card-body">
        <form method="post" action="index.php?page=teste-real-tiny-executar">
          <?= Csrf::input() ?>
          <div class="mb-3">
            <label class="form-label">Modo Tiny</label>
            <select name="modo_tiny" class="form-select">
              <option value="auto">Automático — respeita Tiny ativo e fallback seguro</option>
              <option value="v2">Forçar Tiny V2</option>
              <option value="v3">Forçar Tiny V3 homologação</option>
            </select>
            <div class="form-text">Se V3 não estiver operacional, o modo automático usa fallback seguro para V2.</div>
          </div>
          <div class="mb-3">
            <label class="form-label">Ação</label>
            <select name="acao_teste" class="form-select">
              <option value="completo">Teste completo: criar produto → atualizar estoque → inativar</option>
              <option value="criar_produto">Somente criar produto</option>
              <option value="atualizar_estoque">Somente atualizar estoque</option>
              <option value="inativar_produto">Somente inativar produto</option>
              <option value="ativar_produto">Somente ativar produto</option>
            </select>
          </div>
          <div class="mb-3">
            <label class="form-label">SKU de teste</label>
            <input name="sku" class="form-control" value="<?=e(TesteRealTinyService::skuPadrao())?>" pattern="HUB-TESTE-.*" required>
            <div class="form-text text-danger">Obrigatório iniciar com <code><?=e(TesteRealTinyService::SKU_PREFIXO_SEGURO)?></code>. Produto comercial real será bloqueado.</div>
          </div>
          <div class="mb-3">
            <label class="form-label">Nome do produto</label>
            <input name="nome" class="form-control" value="Produto Teste Real Hub de Integração <?=date('d/m/Y H:i')?>">
          </div>
          <div class="row g-2">
            <div class="col-md-4"><label class="form-label">Preço</label><input name="preco" class="form-control" value="9.99"></div>
            <div class="col-md-4"><label class="form-label">Estoque inicial</label><input name="estoque_inicial" class="form-control" value="1"></div>
            <div class="col-md-4"><label class="form-label">Estoque novo</label><input name="estoque_novo" class="form-control" value="5"></div>
          </div>
          <div class="row g-2 mt-1">
            <div class="col-md-4"><label class="form-label">Unidade</label><input name="unidade" class="form-control" value="UN"></div>
            <div class="col-md-4"><label class="form-label">NCM</label><input name="ncm" class="form-control" placeholder="Opcional"></div>
            <div class="col-md-4"><label class="form-label">EAN/GTIN</label><input name="ean" class="form-control" placeholder="Opcional"></div>
          </div>
          <div class="row g-2 mt-1">
            <div class="col-md-6"><label class="form-label">Marca</label><input name="marca" class="form-control" value="HUB TESTE"></div>
            <div class="col-md-6"><label class="form-label">Categoria</label><input name="categoria" class="form-control" value="Teste Integração"></div>
          </div>
          <div class="mt-3 p-3 rounded border bg-light">
            <label class="form-label fw-bold">Confirmação de segurança</label>
            <input name="confirmacao_sku_teste" class="form-control" placeholder="Digite SIM para confirmar" required>
            <div class="form-text">Esta confirmação reduz risco de alteração acidental em produto real.</div>
          </div>
          <button class="btn btn-danger w-100 mt-3" data-confirm="Confirma executar chamada REAL na API do Tiny usando somente SKU HUB-TESTE?"><i class="bi bi-send-check"></i> Executar teste real no Tiny</button>
        </form>
      </div>
    </div>
  </div>
  <div class="col-xl-7">
    <div class="card shadow-sm border-0 mb-3">
      <div class="card-header bg-white"><b>Resultado da última execução</b></div>
      <div class="card-body">
        <?php if(!empty($resultadoTeste)): ?>
          <?php if(!empty($resultadoTeste['fallback_detectado'])): ?>
            <div class="alert alert-warning"><b>Fallback detectado:</b> o Tiny V3 não foi usado como operacional. O Hub executou em modo seguro/fallback. Não marque V3 como operacional até testar produto, estoque, pedido e logs sem fallback.</div>
          <?php endif; ?>
          <div class="alert <?=$resultadoTeste['success']?'alert-success':'alert-danger'?>">
            <b><?=$resultadoTeste['success']?'Sucesso':'Falha'?></b> — SKU <code><?=e($resultadoTeste['sku'])?></code> — Trace <code><?=e($resultadoTeste['trace_id'])?></code><br>
            Versão efetiva: <code><?=e($resultadoTeste['versao_efetiva'])?></code>
          </div>
          <div class="table-responsive">
            <table class="table table-sm align-middle">
              <thead><tr><th>Etapa</th><th>Status</th><th>Mensagem</th></tr></thead>
              <tbody>
                <?php foreach($resultadoTeste['resultados'] as $r): ?>
                  <tr>
                    <td><?=e($r['etapa'])?></td>
                    <td><span class="badge <?=$r['ok']?'bg-success':'bg-danger'?>"><?=$r['ok']?'OK':'ERRO'?></span></td>
                    <td><?=e($r['mensagem'])?></td>
                  </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          </div>
          <details><summary>JSON completo do resultado</summary><pre class="bg-light p-3 rounded small overflow-auto"><?=e(json_encode($resultadoTeste, JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES))?></pre></details>
        <?php else: ?>
          <div class="text-muted">Nenhum teste executado nesta tela ainda.</div>
        <?php endif; ?>
      </div>
    </div>

    <div class="card shadow-sm border-0">
      <div class="card-header bg-white d-flex justify-content-between align-items-center"><b>Últimos testes reais</b><a href="index.php?page=auditoria" class="btn btn-sm btn-outline-secondary">Abrir auditoria</a></div>
      <div class="table-responsive">
        <table class="table table-sm mb-0 align-middle">
          <thead><tr><th>Data</th><th>SKU</th><th>Ação</th><th>Tiny</th><th>Status</th><th>Trace</th></tr></thead>
          <tbody>
            <?php foreach(($historicoTestes ?? []) as $h): ?>
              <tr>
                <td><?=e($h['criado_em'])?></td>
                <td><code><?=e($h['sku'])?></code></td>
                <td><?=e($h['acao'])?></td>
                <td><?=e($h['versao_efetiva'])?></td>
                <td><span class="badge <?=$h['sucesso']?'bg-success':'bg-danger'?>"><?=$h['sucesso']?'sucesso':'erro'?></span></td>
                <td><a href="index.php?page=auditoria&trace=<?=urlencode($h['trace_id'])?>"><?=e($h['trace_id'])?></a></td>
              </tr>
            <?php endforeach; if(empty($historicoTestes)): ?>
              <tr><td colspan="6" class="text-muted">Nenhum histórico registrado.</td></tr>
            <?php endif; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>
</div>
<?php require __DIR__.'/layout_bottom.php'; ?>
