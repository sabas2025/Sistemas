<?php require __DIR__.'/layout_top.php'; ?>
<div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
  <div>
    <h2 class="h5 m-0">Usuários e permissões</h2>
    <p class="text-muted small mb-0">Administração segura de usuários, alteração de senha, perfis e matriz de permissões.</p>
  </div>
  <a class="btn btn-outline-dark btn-sm" href="index.php?page=relatorio-prontidao-producao" target="_blank">Relatório de prontidão</a>
</div>

<?php if(isset($_GET['salvo'])): ?><div class="alert alert-success">Usuário salvo com sucesso.</div><?php endif; ?>
<?php if(!empty($senhaTemporaria['senha'])): ?><div class="alert alert-warning"><b>Senha temporária exibida uma única vez:</b> <code><?= e($senhaTemporaria['senha']) ?></code><br><span>Usuário: <?= e($senhaTemporaria['email'] ?? '') ?>. Copie agora e entregue por canal seguro; o usuário deverá trocar no primeiro acesso.</span></div><?php endif; ?>
<?php if(isset($_GET['excluido'])): ?><div class="alert alert-warning">Usuário inativado/excluído logicamente. O histórico de auditoria foi preservado.</div><?php endif; ?>
<?php if(isset($_GET['erro'])): ?>
  <?php $erros=[
    'campos'=>'Informe nome e e-mail válido.',
    'email'=>'Este e-mail já está cadastrado em outro usuário.',
    'senha'=>'A senha deve ter no mínimo 10 caracteres, letra maiúscula, minúscula e número, sem conter o nome do e-mail.',
    'ultimo_admin'=>'Não é permitido remover, inativar ou trocar o perfil do último administrador ativo.',
    'self_delete'=>'O administrador logado não pode excluir/inativar o próprio usuário.',
    'confirmacao'=>'Confirmação inválida. Digite EXCLUIR para inativar o usuário.',
    'nao_encontrado'=>'Usuário não encontrado.'
  ]; ?>
  <div class="alert alert-danger"><?= e($erros[$_GET['erro']] ?? 'Erro ao processar usuário.') ?></div>
<?php endif; ?>

<div class="row g-3 mb-4">
  <div class="col-md-3"><div class="card pro-card"><div class="card-body"><div class="text-muted small">Total</div><div class="h4 mb-0"><?= count($usuarios) ?></div></div></div></div>
  <div class="col-md-3"><div class="card pro-card"><div class="card-body"><div class="text-muted small">Ativos</div><div class="h4 mb-0"><?= count(array_filter($usuarios, fn($u)=>!empty($u['ativo']))) ?></div></div></div></div>
  <div class="col-md-3"><div class="card pro-card"><div class="card-body"><div class="text-muted small">Admins ativos</div><div class="h4 mb-0"><?= count(array_filter($usuarios, fn($u)=>$u['perfil']==='admin' && !empty($u['ativo']))) ?></div></div></div></div>
  <div class="col-md-3"><div class="card pro-card"><div class="card-body"><div class="text-muted small">2FA ativo</div><div class="h4 mb-0"><?= count(array_filter($usuarios, fn($u)=>!empty($u['two_factor_enabled']))) ?></div></div></div></div>
</div>

<div class="card pro-card mb-4"><div class="card-body">
  <h3 class="h6" id="form-title">Cadastrar usuário</h3>
  <form method="post" action="index.php?page=usuario-salvar" class="row g-2" id="usuario-form">
    <?= Csrf::input() ?>
    <input type="hidden" name="id" id="usuario-id" value="">
    <div class="col-md-3"><label class="form-label small">Nome</label><input name="nome" id="usuario-nome" class="form-control" placeholder="Nome" required></div>
    <div class="col-md-3"><label class="form-label small">E-mail</label><input name="email" id="usuario-email" type="email" class="form-control" placeholder="E-mail" required></div>
    <div class="col-md-2"><label class="form-label small">Perfil</label><select name="perfil" id="usuario-perfil" class="form-select"><option value="operador">Operador</option><option value="gerente">Gerente</option><option value="admin">Admin</option></select></div>
    <div class="col-md-2"><label class="form-label small">Senha</label><input name="senha" id="usuario-senha" type="password" class="form-control" placeholder="Vazio mantém atual"></div>
    <div class="col-md-1"><label class="form-label small">Status</label><select name="ativo" id="usuario-ativo" class="form-select"><option value="1">Ativo</option><option value="0">Inativo</option></select></div>
    <div class="col-md-1 d-grid"><label class="form-label small">&nbsp;</label><button class="btn btn-primary">Salvar</button></div>
    <div class="col-md-4">
      <label class="form-label small">Empresa</label>
      <select name="empresa_id" id="usuario-empresa" class="form-select">
        <option value="">— Sem empresa (vê todas) —</option>
        <?php foreach(($empresas ?? []) as $emp): ?>
        <option value="<?= (int)$emp['id'] ?>"><?= e($emp['nome']) ?><?= empty($emp['ativo']) ? ' (inativa)' : '' ?></option>
        <?php endforeach; ?>
      </select>
      <div class="form-text">
        <?php if(empty($empresas)): ?>
          Nenhuma empresa cadastrada. Sem empresa, o usuário enxerga os dados de todas.
        <?php else: ?>
          Define o que o usuário enxerga. <b>Sem empresa</b> ele vê os dados de todas — mantenha assim só para administração geral.
        <?php endif; ?>
      </div>
    </div>
    <div class="col-12 d-flex flex-wrap gap-4 mt-2">
      <label class="small"><input type="checkbox" name="deve_trocar_senha" id="usuario-trocar" value="1" checked> Exigir troca de senha no próximo login</label>
      <label class="small"><input type="checkbox" name="two_factor_enabled" id="usuario-2fa" value="1"> Ativar 2FA/TOTP</label>
      <button type="button" class="btn btn-sm btn-outline-secondary js-usuario-novo">Novo usuário</button>
    </div>
  </form>
</div></div>

<div class="alert alert-info small">
  <b>Regras de segurança:</b> senha mínima de 10 caracteres, com maiúscula, minúscula e número, e-mail único, CSRF obrigatório, auditoria por Trace ID, bloqueio contra exclusão do próprio usuário e proteção do último administrador ativo.<br>
  <b>2FA/TOTP:</b> quando o 2FA for ativado para um usuário, o cadastro no Google Authenticator aparece no próximo login com QR Code local e chave manual. O segredo não é enviado para API externa de QR Code.
</div>

<div class="card pro-card mb-4"><div class="table-responsive"><table class="table table-hover align-middle mb-0"><thead><tr><th>ID</th><th>Nome</th><th>E-mail</th><th>Perfil</th><th>Empresa</th><th>Status</th><th>Último login</th><th>Trocar senha</th><th>2FA</th><th>Ações</th></tr></thead><tbody>
<?php foreach($usuarios as $u): ?>
<tr>
  <td><?= e($u['id']) ?></td>
  <td><?= e($u['nome']) ?></td>
  <td><?= e($u['email']) ?></td>
  <td><span class="badge bg-secondary"><?= e($u['perfil']) ?></span></td>
  <td><?php
    $empNome = null;
    foreach(($empresas ?? []) as $emp) { if((int)$emp['id'] === (int)($u['empresa_id'] ?? 0)) { $empNome = $emp['nome']; break; } }
    if ($empNome !== null) { echo '<span class="badge bg-info text-dark">'.e($empNome).'</span>'; }
    elseif (!empty($u['empresa_id'])) { echo '<span class="badge bg-warning text-dark">#'.(int)$u['empresa_id'].' (não encontrada)</span>'; }
    else { echo '<span class="badge bg-secondary" title="Enxerga os dados de todas as empresas">todas</span>'; }
  ?></td>
  <td><?= $u['ativo']?'<span class="badge bg-success">Ativo</span>':'<span class="badge bg-danger">Inativo</span>' ?></td>
  <td><?= e($u['ultimo_login'] ?: '—') ?></td>
  <td><?= $u['deve_trocar_senha']?'Sim':'Não' ?></td>
  <td><?= !empty($u['two_factor_enabled'])?'<span class="badge bg-success">Ativo</span>':'<span class="badge bg-secondary">Inativo</span>' ?><?php if(!empty($u['two_factor_last_verified_at'])): ?><br><small class="text-muted">verificado: <?= e($u['two_factor_last_verified_at']) ?></small><?php endif; ?></td>
  <td class="text-nowrap">
    <button type="button" class="btn btn-sm btn-outline-primary js-usuario-editar" data-user='<?=e(json_encode([
      'id'=>(int)$u['id'], 'nome'=>$u['nome'], 'email'=>$u['email'], 'perfil'=>$u['perfil'], 'ativo'=>(int)$u['ativo'], 'trocar'=>(int)$u['deve_trocar_senha'], 'twofa'=>(int)$u['two_factor_enabled'], 'empresa'=>($u['empresa_id']===null?'':(int)$u['empresa_id'])
    ], JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES))?>'>Editar</button>
    <button type="button" class="btn btn-sm btn-outline-warning js-usuario-senha" data-user='<?=e(json_encode([
      'id'=>(int)$u['id'], 'nome'=>$u['nome'], 'email'=>$u['email'], 'perfil'=>$u['perfil'], 'ativo'=>(int)$u['ativo'], 'trocar'=>1, 'twofa'=>(int)$u['two_factor_enabled'], 'empresa'=>($u['empresa_id']===null?'':(int)$u['empresa_id'])
    ], JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES))?>'>Alterar senha</button>
    <?php if((int)($u['id']) !== (int)(Auth::user()['id'] ?? 0)): ?>
    <form method="post" action="index.php?page=usuario-excluir" class="d-inline js-usuario-excluir">
      <?= Csrf::input() ?>
      <input type="hidden" name="id" value="<?= (int)$u['id'] ?>">
      <input type="hidden" name="confirmar" value="">
      <button class="btn btn-sm btn-outline-danger">Excluir</button>
    </form>
    <?php endif; ?>
  </td>
</tr>
<?php endforeach; ?>
</tbody></table></div></div>

<?php if((($_GET['erro'] ?? '')==='empresa')): ?><div class="alert alert-danger">Empresa inválida: selecione uma empresa cadastrada ou deixe em branco.</div><?php endif; ?>
<?php if(isset($_GET['permissoes'])): ?><div class="alert alert-info">Matriz de permissões atualizada.</div><?php endif; ?>
<div class="card pro-card"><div class="card-body"><h3 class="h6">Matriz de permissões editável</h3><p class="text-muted small">Admin sempre mantém acesso total para evitar bloqueio do sistema.</p><form method="post" action="index.php?page=permissoes-salvar"><?= Csrf::input() ?><div class="table-responsive"><table class="table table-sm"><thead><tr><th>Perfil</th><th>Módulo</th><th>Ação</th><th>Permitido</th></tr></thead><tbody><?php foreach($permissoes as $p): ?><tr><td><?= e($p['perfil']) ?></td><td><?= e($p['modulo']) ?></td><td><?= e($p['acao']) ?></td><td><label class="form-check form-switch"><input class="form-check-input" type="checkbox" name="permissoes[<?= (int)$p['id'] ?>]" value="1" <?= $p['permitido']?'checked':'' ?> <?= $p['perfil']==='admin'?'disabled':'' ?>><span class="form-check-label"><?= $p['permitido']?'Sim':'Não' ?></span></label><?php if($p['perfil']==='admin'): ?><input type="hidden" name="permissoes[<?= (int)$p['id'] ?>]" value="1"><?php endif; ?></td></tr><?php endforeach; ?></tbody></table></div><button class="btn btn-primary"><i class="bi bi-save"></i> Salvar permissões</button></form></div></div>

<script nonce="<?=App::cspNonce()?>">
function editarUsuario(u){
  document.getElementById('form-title').innerText='Editar usuário #' + u.id;
  document.getElementById('usuario-id').value=u.id;
  document.getElementById('usuario-nome').value=u.nome || '';
  document.getElementById('usuario-email').value=u.email || '';
  document.getElementById('usuario-perfil').value=u.perfil || 'operador';
  document.getElementById('usuario-ativo').value=String(u.ativo ?? 1);
  document.getElementById('usuario-empresa').value=(u.empresa === undefined || u.empresa === null) ? '' : String(u.empresa);
  document.getElementById('usuario-trocar').checked=!!Number(u.trocar);
  document.getElementById('usuario-2fa').checked=!!Number(u.twofa);
  document.getElementById('usuario-senha').value='';
  document.getElementById('usuario-senha').placeholder='Vazio mantém a senha atual';
  document.getElementById('usuario-form').scrollIntoView({behavior:'smooth', block:'center'});
}
function alterarSenhaUsuario(u){
  editarUsuario(u);
  document.getElementById('form-title').innerText='Alterar senha de ' + (u.nome || ('#'+u.id));
  document.getElementById('usuario-trocar').checked=true;
  document.getElementById('usuario-senha').placeholder='Digite a nova senha';
  document.getElementById('usuario-senha').focus();
}
function limparUsuarioForm(){
  document.getElementById('form-title').innerText='Cadastrar usuário';
  document.getElementById('usuario-id').value='';
  document.getElementById('usuario-form').reset();
  document.getElementById('usuario-ativo').value='1';
  document.getElementById('usuario-empresa').value='';
  document.getElementById('usuario-trocar').checked=true;
}
function confirmarExclusaoUsuario(form){
  const ok=prompt('Para excluir/inativar este usuário, digite EXCLUIR:');
  if(ok!=='EXCLUIR') return false;
  form.querySelector('input[name="confirmar"]').value='EXCLUIR';
  return true;
}
document.querySelector('.js-usuario-novo')?.addEventListener('click', limparUsuarioForm);
document.querySelectorAll('.js-usuario-editar').forEach((btn)=>btn.addEventListener('click', ()=>editarUsuario(JSON.parse(btn.dataset.user || '{}'))));
document.querySelectorAll('.js-usuario-senha').forEach((btn)=>btn.addEventListener('click', ()=>alterarSenhaUsuario(JSON.parse(btn.dataset.user || '{}'))));
document.querySelectorAll('.js-usuario-excluir').forEach((form)=>form.addEventListener('submit', (ev)=>{ if(!confirmarExclusaoUsuario(form)) ev.preventDefault(); }));
</script>
<?php require __DIR__.'/layout_bottom.php'; ?>
