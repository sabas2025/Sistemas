<?php require __DIR__.'/layout_top.php'; ?>
<div class="panel p-4">
  <h1>MyOuro GraphQL — Consultas</h1>
  <p>Produção, somente leitura. Esta conexão não altera a URL REST do Conecta Venda e não envia pedidos nem atualiza o Tiny.</p>
  <a class="btn btn-outline-secondary mb-3" href="index.php?page=configuracoes">Voltar às configurações</a>
  <?php if ($error): ?><div class="alert alert-warning" role="alert"><?=e($error)?></div><?php endif; ?>
  <?php if ($empresa): ?>
  <h2 class="h5">1. Confirmar a empresa das integrações</h2>
  <p>Empresa do HUB: <b><?=e($empresa)?></b>. Confirme apenas se as credenciais Tiny/VSM desta instalação pertencem a ela. Isto não classifica dados antigos e não altera tokens existentes.</p>
  <form method="post" action="index.php?page=integracao-vincular-empresa" class="mb-4">
    <?=Csrf::input()?>
    <label class="form-label" for="confirmacao">Digite CONFIRMO EMPRESA <?=e($empresa)?></label>
    <input id="confirmacao" name="confirmacao" class="form-control" required autocomplete="off">
    <button class="btn btn-outline-primary mt-2" type="submit">Confirmar vínculo</button>
  </form>
  <h2 class="h5">2. Configurar consulta de estoque</h2>
  <form method="post" action="index.php?page=myouro-salvar" class="mb-4">
    <?=Csrf::input()?>
    <label class="form-label" for="myouro-url">URL completa</label>
    <input id="myouro-url" name="url" class="form-control mb-3" value="<?=e(MyOuroConfigService::URL)?>" readonly>
    <label class="form-label" for="myouro-loja">Código da loja VSM autorizada para esta empresa</label>
    <input id="myouro-loja" name="codigo_loja" type="number" min="1" max="2147483647" class="form-control mb-3" value="<?=e($connection['codigo_loja'] ?? '')?>" required>
    <label class="form-label" for="myouro-token">Token Bearer (somente o valor, sem o prefixo Bearer)</label>
    <input id="myouro-token" name="token" type="password" class="form-control" autocomplete="new-password" value="" placeholder="<?=!empty($connection['token_encrypted'])?'Token cadastrado — deixe vazio para preservar':'Informe o token'?>">
    <p class="form-text">O token é criptografado e nunca devolvido ao navegador. Um campo vazio preserva o valor anterior.</p>
    <div class="form-check my-3"><input id="myouro-enabled" class="form-check-input" name="habilitado" type="checkbox" value="1" <?=!empty($connection['habilitado'])?'checked':''?>><label for="myouro-enabled" class="form-check-label">Habilitar consultas manuais</label></div>
    <button class="btn btn-primary" type="submit">Salvar conexão</button>
  </form>
  <h2 class="h5">3. Testar produto e loja conhecidos</h2>
  <p>Adicione <code>msx.vsm.api.br</code> à allowlist <code>security.vsm_allowed_hosts</code>, preservando os hosts existentes. Nenhum teste é disparado ao abrir ou salvar esta tela.</p>
  <form method="post" action="index.php?page=myouro-testar">
    <?=Csrf::input()?>
    <label for="myouro-produto" class="form-label">Código do produto VSM (não é o SKU Tiny)</label>
    <input id="myouro-produto" name="codigo_produto" type="number" min="1" max="2147483647" class="form-control" required>
    <button class="btn btn-outline-primary mt-2" type="submit">Consultar estoque — somente leitura</button>
  </form>
  <p class="mt-3">Último teste: <?=e($connection['ultimo_teste_em'] ?? 'não realizado')?> · <?=e($connection['ultimo_teste_codigo'] ?? 'pendente')?></p>
  <?php endif; ?>
  <?php if ($result): ?><div class="alert <?=$result['ok']?'alert-success':'alert-warning'?> mt-3" role="status"><pre class="mb-0"><?=e(json_encode($result,JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE))?></pre></div><?php endif; ?>
  <div class="alert alert-info mt-4">Conecta Venda / pedidos-integradora continua independente. Autenticação por clientToken/clientSecret, URL de produção e payload de criação dependem do contrato REST oficial. Nenhuma autenticação ou mutation foi presumida.</div>
</div>
<?php require __DIR__.'/layout_bottom.php'; ?>
