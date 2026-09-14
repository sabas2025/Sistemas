<?php require __DIR__.'/layout_top.php'; ?>
<?php $formError=$_SESSION['form_error'] ?? null; unset($_SESSION['form_error']); ?>
<?php $canEditVsm=PermissionService::can('configuracoes','editar'); ?>
<?php if(isset($_GET['salvo'])): ?><div class="alert alert-success">Endpoint VSM salvo com sucesso.</div><?php endif; ?>
<?php if($formError): ?><div class="alert alert-danger"><?=e($formError)?></div><?php endif; ?>
<?php if($erro): ?><div class="alert alert-danger">Erro ao carregar endpoints: <?=e($erro)?></div><?php endif; ?>
<?php if(!empty($resultado)): ?>
<div class="alert <?=$resultado['ok']?'alert-success':'alert-danger'?>">
  <b>Teste seguro executado:</b> <?=e($resultado['metodo_executado'] ?? 'GET')?> (definição: <?=e($resultado['metodo_solicitado'] ?? '-')?>) • HTTP <?=e($resultado['status'] ?? '-')?> em <?=e($resultado['tempo_ms'] ?? '-')?>ms<br>
  <small><?=e($resultado['url'] ?? '')?></small>
  <?php if(!empty($resultado['erro'])): ?><div>Erro: <?=e($resultado['erro'])?></div><?php endif; ?>
  <details class="mt-2"><summary>Ver metadados (corpo não armazenado)</summary><pre class="json-box"><?=e($resultado['resposta'] ?? '')?></pre></details>
</div>
<?php endif; ?>
<div class="panel">
  <div class="panel-header"><h2><i class="bi bi-plug"></i> Mapeamento de Endpoints VSM</h2><span class="text-muted">Configure URLs sem alterar PHP</span></div>
  <div class="p-4">
    <div class="alert alert-warning"><b>Este catálogo é diagnóstico, não é a fonte da verdade operacional:</b> os fluxos reais de baixa de estoque e envio de pedido usam a configuração fixa de URL/endpoint em Configurações (<code>VsmService</code>), não os registros abaixo. Testar e confirmar um endpoint aqui não altera o que o Hub realmente chama em produção — use esta tela para inventário/homologação do contrato, não como prova de que o fluxo operacional está correto.</div>
    <div class="alert alert-info"><b>Governança VSM:</b> use apenas caminhos relativos, com bloqueio SSRF, fixação de DNS, teste seguro e logs por Trace ID. Edições manuais ficam pendentes e inativas até a confirmação explícita com uma fonte auditável do contrato.</div>
    <div class="d-flex flex-wrap gap-2 mb-3">
      <a class="btn btn-outline-primary btn-sm" href="index.php?page=vsm-endpoints">Todos</a>
      <?php foreach(['pedidos','produtos','estoque','fiscal','webhooks'] as $cat): ?><a class="btn btn-outline-primary btn-sm" href="index.php?page=vsm-endpoints&categoria=<?=$cat?>"><?=ucfirst($cat)?></a><?php endforeach; ?>
      <a class="btn btn-outline-success btn-sm" href="index.php?page=vsm-campos"><i class="bi bi-arrow-left-right"></i> Campos</a>
      <a class="btn btn-outline-info btn-sm" href="index.php?page=vsm-saude"><i class="bi bi-heart-pulse"></i> Saúde</a>
      <a class="btn btn-outline-dark btn-sm" href="index.php?page=vsm-logs"><i class="bi bi-journal-text"></i> Logs</a>
    </div>
    <div class="table-responsive"><table class="table table-sm align-middle">
      <thead><tr><th>Ordem</th><th>Categoria</th><th>Função</th><th>Método</th><th>Endpoint</th><th>Status</th><th>Último teste</th><th>Ações</th></tr></thead>
      <tbody>
      <?php foreach($endpoints as $ep): ?>
        <tr>
          <td><?=e($ep['ordem_execucao'])?></td>
          <td><span class="badge bg-light text-dark border"><?=e($ep['categoria'])?></span></td>
          <td><b><?=e($ep['nome'])?></b><br><small class="text-muted"><?=e($ep['chave'])?></small></td>
          <td><span class="badge bg-secondary"><?=e($ep['metodo_http'])?></span></td>
          <td><code><?=e($ep['endpoint'])?></code><br><small class="text-muted"><?=e($ep['descricao'])?></small></td>
          <td><span class="badge <?=$ep['ativo']?'bg-success':'bg-secondary'?>"><?=$ep['ativo']?'Ativo':'Inativo'?></span><br><?php if(!empty($ep['contract_verified'])): ?><span class="badge bg-info text-dark mt-1">Confirmado: <?=e($ep['contract_source'] ?? 'fonte não informada')?></span><?php elseif(!empty($ep['is_template'])): ?><span class="badge bg-warning text-dark mt-1">Modelo não verificado</span><?php else: ?><span class="badge bg-warning text-dark mt-1">Manual pendente</span><?php endif; ?></td>
          <td class="small">HTTP <?=e($ep['ultimo_status_http'] ?? '-')?> • <?=e($ep['ultimo_tempo_ms'] ?? '-')?>ms<br><?=e($ep['ultima_execucao_em'] ?? 'Nunca')?></td>
          <td>
            <?php if($canEditVsm): ?>
            <?php $temPlaceholders=(bool)preg_match('/\{[a-zA-Z][a-zA-Z0-9_]*\}/',(string)$ep['endpoint']); ?>
            <?php if($temPlaceholders): ?><a class="btn btn-sm btn-outline-success <?=empty($ep['contract_verified'])?'disabled':''?>" href="index.php?page=vsm-testes" <?=empty($ep['contract_verified'])?'aria-disabled="true" title="Valide o contrato com uma fonte auditável antes de testar"':'title="Informe os parâmetros obrigatórios"'?>> <i class="bi bi-shield-check"></i> Informar parâmetros</a><?php else: ?><form method="post" action="index.php?page=vsm-endpoint-testar" class="d-inline"><?=Csrf::input()?><input type="hidden" name="id" value="<?=e($ep['id'])?>"><input type="hidden" name="modo_seguro" value="1"><button class="btn btn-sm btn-outline-success" <?=empty($ep['contract_verified'])?'disabled title="Valide o contrato com uma fonte auditável antes de testar"':''?>> <i class="bi bi-shield-check"></i> Testar seguro</button></form><?php endif; ?>
            <button class="btn btn-sm btn-outline-primary" type="button" data-bs-toggle="collapse" data-bs-target="#editEp<?=e($ep['id'])?>">Editar</button>
            <?php else: ?><span class="badge bg-light text-dark border">Somente leitura</span><?php endif; ?>
          </td>
        </tr>
        <?php if($canEditVsm): ?>
        <tr class="collapse" id="editEp<?=e($ep['id'])?>"><td colspan="8">
          <form method="post" action="index.php?page=vsm-endpoint-salvar" class="row g-2 bg-light p-3 rounded">
            <?=Csrf::input()?><input type="hidden" name="id" value="<?=e($ep['id'])?>">
            <div class="col-md-2"><label class="form-label">Chave</label><input class="form-control" name="chave" value="<?=e($ep['chave'])?>"></div>
            <div class="col-md-3"><label class="form-label">Nome</label><input class="form-control" name="nome" value="<?=e($ep['nome'])?>"></div>
            <div class="col-md-2"><label class="form-label">Categoria</label><input class="form-control" name="categoria" value="<?=e($ep['categoria'])?>"></div>
            <div class="col-md-1"><label class="form-label">Método</label><select class="form-select" name="metodo_http"><?php foreach(['GET','POST','PUT','PATCH','DELETE'] as $m): ?><option <?=$ep['metodo_http']===$m?'selected':''?>><?=$m?></option><?php endforeach; ?></select></div>
            <div class="col-md-4"><label class="form-label">Endpoint relativo</label><input class="form-control" name="endpoint" value="<?=e($ep['endpoint'])?>" pattern="^/.*" title="Use apenas caminho relativo. Ex: /pedidos/{id}"><div class="form-text">Bloqueado: http://, https://, localhost e IP interno.</div></div>
            <div class="col-md-2"><label class="form-label">Timeout</label><input type="number" class="form-control" name="timeout_segundos" value="<?=e($ep['timeout_segundos'])?>"></div>
            <div class="col-md-2"><label class="form-label">Retry</label><input type="number" class="form-control" name="retry_maximo" value="<?=e($ep['retry_maximo'])?>"></div>
            <div class="col-md-2"><label class="form-label">Ordem</label><input type="number" class="form-control" name="ordem_execucao" value="<?=e($ep['ordem_execucao'])?>"></div>
            <div class="col-md-2 d-flex align-items-end"><div><div class="form-check form-switch"><input class="form-check-input" type="checkbox" name="ativo" value="1" <?=$ep['ativo']?'checked':''?>><label class="form-check-label">Ativo</label></div><div class="form-check form-switch"><input class="form-check-input" type="checkbox" name="modo_teste_seguro" value="1" <?=($ep['modo_teste_seguro'] ?? 1)?'checked':''?>><label class="form-check-label">Teste seguro</label></div></div></div>
            <div class="col-md-12"><label class="form-label">Descrição</label><input class="form-control" name="descricao" value="<?=e($ep['descricao'])?>"></div>
            <div class="col-md-8"><label class="form-label">Fonte auditável do contrato</label><input class="form-control" name="contract_source" maxlength="255" value="<?=e($ep['contract_source'] ?? '')?>" placeholder="https://... | sha256:... | openapi:arquivo.json | contrato:referencia"><div class="form-text">Referências OpenAPI locais são validadas contra método e caminho. URLs externas não são buscadas pelo servidor.</div></div>
            <div class="col-md-4 d-flex align-items-end"><label class="form-check mb-2"><input class="form-check-input" type="checkbox" name="confirmar_contrato" value="1"> <span class="form-check-label">Reconfirmo esta definição completa na fonte informada</span></label></div>
            <div class="col-12"><div class="form-text mb-2">A confirmação nunca é herdada: salvar sem reconfirmar torna o endpoint pendente, inativo e sem data de validação.</div><button class="btn btn-primary btn-sm"><i class="bi bi-save"></i> Salvar endpoint</button></div>
          </form>
        </td></tr>
        <?php endif; ?>
      <?php endforeach; ?>
      </tbody>
    </table></div>
  </div>
</div>
<?php if($canEditVsm): ?>
<div class="panel mt-3"><div class="panel-header"><h2>Novo endpoint</h2><span class="text-muted">Use placeholders como {id} ou {sku}</span></div><div class="p-4">
<form method="post" action="index.php?page=vsm-endpoint-salvar" class="row g-2"><?=Csrf::input()?>
  <div class="col-md-2"><input class="form-control" name="chave" placeholder="chave_unica"></div>
  <div class="col-md-3"><input class="form-control" name="nome" placeholder="Nome da função"></div>
  <div class="col-md-2"><input class="form-control" name="categoria" placeholder="pedidos"></div>
  <div class="col-md-1"><select class="form-select" name="metodo_http"><option>GET</option><option>POST</option><option>PUT</option><option>PATCH</option><option>DELETE</option></select></div>
  <div class="col-md-4"><input class="form-control" name="endpoint" placeholder="/pedidos/{id}" pattern="^/.*" title="Use apenas caminho relativo"><div class="form-text">Somente caminho relativo. Nunca cole URL completa.</div></div>
  <div class="col-md-2"><input type="number" class="form-control" name="timeout_segundos" value="30"></div>
  <div class="col-md-2"><input type="number" class="form-control" name="retry_maximo" value="3"></div>
  <div class="col-md-2"><input type="number" class="form-control" name="ordem_execucao" value="0"></div>
  <div class="col-md-2 d-flex align-items-center"><div><div class="form-check"><input class="form-check-input" type="checkbox" name="ativo"> <label class="form-check-label">Ativo após confirmação</label></div><div class="form-check"><input class="form-check-input" type="checkbox" name="modo_teste_seguro" checked> <label class="form-check-label">Teste seguro</label></div></div></div>
  <div class="col-md-12"><input class="form-control" name="descricao" placeholder="Descrição operacional"></div>
  <div class="col-md-8"><input class="form-control" name="contract_source" maxlength="255" placeholder="Fonte: https://... | sha256:... | openapi:arquivo.json | contrato:referencia"><div class="form-text">Sem confirmação e fonte auditável, o endpoint será salvo como manual pendente e inativo.</div></div>
  <div class="col-md-4 d-flex align-items-center"><label class="form-check"><input class="form-check-input" type="checkbox" name="confirmar_contrato" value="1"> <span class="form-check-label">Confirmo que validei esta definição na fonte informada</span></label></div>
  <div class="col-12"><button class="btn btn-success"><i class="bi bi-plus-circle"></i> Adicionar</button></div>
</form></div></div>
<?php endif; ?>
<?php require __DIR__.'/layout_bottom.php'; ?>
