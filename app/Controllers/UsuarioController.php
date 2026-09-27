<?php
/**
 * Fase 3 (decomposição do controller-deus, 2026-09-27) — etapa Usuários & Sessão.
 *
 * Reúne a gestão de usuários e permissões e a troca de senha que viviam no DashboardController
 * (achado A3-01): usuarios, usuario-salvar, usuario-excluir, permissoes-salvar e trocar-senha.
 * Handlers movidos verbatim; PermissionService/Csrf/Auth e as revogações de sessão
 * (session_version) preservados. Nenhuma URL muda, só quem a atende
 * (FastRouteDispatcherService::$dispatchGroups). O gate de troca de senha obrigatória continua no
 * dispatcher (roda antes do roteamento), então trocar-senha segue alcançável com deve_trocar_senha=1.
 * Precisa de $pdo próprio (transação em permissoesSalvar, lastInsertId em usuarioSalvar) e do
 * helper db(), como o HomologacaoController.
 */
class UsuarioController extends BaseModuleController {
  private PDO $pdo;
  public function __construct(){ $this->pdo = Database::getConnection(); }

  public static function routes(): array {
    return ['usuarios','usuario-salvar','usuario-excluir','permissoes-salvar','trocar-senha'];
  }

  public function dispatch(string $page): void {
    switch ($page) {
      case 'usuario-salvar': $this->usuarioSalvar(); break;
      case 'usuario-excluir': $this->usuarioExcluir(); break;
      case 'permissoes-salvar': $this->permissoesSalvar(); break;
      case 'trocar-senha': $this->trocarSenha(); break;
      default: $this->usuarios(); break; // usuarios
    }
  }

  private function db(string $table): PDO { return Database::forTable($table); }

  private function usuarios(): void {
    PermissionService::require('usuarios','gerenciar');
    $usuarios = $this->db('usuarios')->query("SELECT id,nome,email,perfil,ativo,ultimo_login,deve_trocar_senha,tentativas_login,bloqueado_ate,two_factor_enabled,two_factor_secret,two_factor_created_at,two_factor_last_verified_at,empresa_id,criado_em FROM usuarios ORDER BY id DESC")->fetchAll();
    $empresas = EmpresaCatalogService::listar();
    $permissoes = $this->db('permissoes_perfil')->query("SELECT * FROM permissoes_perfil ORDER BY perfil,modulo,acao")->fetchAll();
    $senhaTemporaria=$_SESSION['senha_temporaria_ultima']??null;unset($_SESSION['senha_temporaria_ultima']);
    $pageTitle='Usuários e Permissões';
    require __DIR__.'/../../views/usuarios.php';
  }

  private function usuarioSalvar(): void {
    PermissionService::require('usuarios','gerenciar');
    Csrf::validate();
    $id=(int)($_POST['id'] ?? 0);
    $nome=trim($_POST['nome'] ?? '');
    $email=trim($_POST['email'] ?? '');
    $perfil=$_POST['perfil'] ?? 'operador';
    $ativo=(int)($_POST['ativo'] ?? 0);
    $trocar=(int)($_POST['deve_trocar_senha'] ?? 0);
    $senha=(string)($_POST['senha'] ?? '');
    $twoFactor=(int)($_POST['two_factor_enabled'] ?? 0);
    $twoSecretPlain=$twoFactor ? TwoFactorService::generateSecret() : null;
    $twoSecret=$twoSecretPlain ? TwoFactorService::encryptSecret($twoSecretPlain) : null;

    $empresaId = EmpresaCatalogService::idDoFormulario($_POST['empresa_id'] ?? '');
    if ($empresaId === false) { redirect('index.php?page=usuarios&erro=empresa'); }

    if($nome==='' || $email==='' || !filter_var($email,FILTER_VALIDATE_EMAIL)) {
      redirect('index.php?page=usuarios&erro=campos');
    }
    if(!in_array($perfil,['admin','gerente','operador'],true)) $perfil='operador';

    $stDup=$this->db('usuarios')->prepare('SELECT id FROM usuarios WHERE email=? AND id<>? LIMIT 1');
    $stDup->execute([$email,$id]);
    if($stDup->fetch()) { redirect('index.php?page=usuarios&erro=email'); }

    if($senha!==''&&!PasswordPolicyService::isValid($senha,$email)){redirect('index.php?page=usuarios&erro=senha');}

    // Proteção: não permitir remover o último admin ativo.
    if($id>0 && ($perfil!=='admin' || $ativo!==1)) {
      $st=$this->db('usuarios')->prepare("SELECT COUNT(*) c FROM usuarios WHERE perfil='admin' AND ativo=1 AND id<>?");
      $st->execute([$id]);
      if((int)($st->fetch()['c'] ?? 0) < 1) { redirect('index.php?page=usuarios&erro=ultimo_admin'); }
    }

    if($id>0){
      if($senha !== ''){
        $this->db('usuarios')->prepare('UPDATE usuarios SET nome=?,email=?,perfil=?,ativo=?,deve_trocar_senha=?,empresa_id=?,senha=?,session_version=COALESCE(session_version,0)+1,two_factor_enabled=?,two_factor_secret=COALESCE(?,two_factor_secret), two_factor_created_at=CASE WHEN ? IS NOT NULL THEN NOW() ELSE two_factor_created_at END, bloqueado_ate=NULL,tentativas_login=0 WHERE id=?')->execute([$nome,$email,$perfil,$ativo,$trocar,$empresaId,password_hash($senha,PASSWORD_DEFAULT),$twoFactor,$twoSecret,$twoSecret,$id]);
        $msg='Usuário salvo com alteração de senha pelo administrador';
      } else {
        // P1-01 (reauditoria 2026-08-23): este UPDATE (sem troca de senha) não bumpava
        // session_version, então desativar o usuário, rebaixar o perfil ou desligar o
        // 2FA não revogava a sessão já aberta dele - continuava válida até expirar por
        // tempo. Agora qualquer alteração de segurança do usuário revoga a sessão atual.
        $this->db('usuarios')->prepare("UPDATE usuarios SET nome=?,email=?,perfil=?,ativo=?,deve_trocar_senha=?,empresa_id=?,two_factor_enabled=?,two_factor_secret=CASE WHEN ?=1 AND (two_factor_secret IS NULL OR two_factor_secret='') THEN ? ELSE two_factor_secret END, two_factor_created_at=CASE WHEN ?=1 AND (two_factor_secret IS NULL OR two_factor_secret='') THEN NOW() ELSE two_factor_created_at END, session_version=COALESCE(session_version,0)+1 WHERE id=?")->execute([$nome,$email,$perfil,$ativo,$trocar,$empresaId,$twoFactor,$twoFactor,$twoSecret,$twoFactor,$id]);
        $msg='Usuário salvo';
      }
    } else {
      if($senha===''){ $senha=PasswordPolicyService::generateTemporary(); $_SESSION['senha_temporaria_ultima']=['email'=>$email,'senha'=>$senha]; }
      $this->db('usuarios')->prepare('INSERT INTO usuarios(nome,email,perfil,ativo,deve_trocar_senha,empresa_id,senha,two_factor_enabled,two_factor_secret,two_factor_created_at) VALUES(?,?,?,?,?,?,?,?,?,CASE WHEN ?=1 THEN NOW() ELSE NULL END)')->execute([$nome,$email,$perfil,$ativo,1,$empresaId,password_hash($senha,PASSWORD_DEFAULT),$twoFactor,$twoSecret,$twoFactor]);
      $id=(int)$this->pdo->lastInsertId();
      $msg='Usuário criado';
    }
    Audit::event('usuarios.salvar','sucesso',['mensagem'=>$msg,'entidade'=>'usuarios','entidade_id'=>$id,'contexto'=>['perfil'=>$perfil,'ativo'=>$ativo,'empresa_id'=>$empresaId,'senha_alterada'=>$senha!=='' || !empty($_SESSION['senha_temporaria_usuario_'.$email])]]);
    redirect('index.php?page=usuarios&salvo=1');
  }

  private function usuarioExcluir(): void {
    PermissionService::require('usuarios','gerenciar');
    Csrf::validate();
    $id=(int)($_POST['id'] ?? 0);
    $confirmar=trim($_POST['confirmar'] ?? '');
    if($id<=0 || $confirmar!=='EXCLUIR') { redirect('index.php?page=usuarios&erro=confirmacao'); }
    $logado=(int)(Auth::user()['id'] ?? 0);
    if($id===$logado) { redirect('index.php?page=usuarios&erro=self_delete'); }

    $st=$this->db('usuarios')->prepare('SELECT id,nome,email,perfil,ativo FROM usuarios WHERE id=? LIMIT 1');
    $st->execute([$id]);
    $u=$st->fetch();
    if(!$u) { redirect('index.php?page=usuarios&erro=nao_encontrado'); }

    if($u['perfil']==='admin' && (int)$u['ativo']===1) {
      $st=$this->db('usuarios')->prepare("SELECT COUNT(*) c FROM usuarios WHERE perfil='admin' AND ativo=1 AND id<>?");
      $st->execute([$id]);
      if((int)($st->fetch()['c'] ?? 0) < 1) { redirect('index.php?page=usuarios&erro=ultimo_admin'); }
    }

    // Exclusão lógica profissional: preserva auditoria e histórico de integrações.
    $this->db('usuarios')->prepare('UPDATE usuarios SET ativo=0, bloqueado_ate=NULL, tentativas_login=0 WHERE id=?')->execute([$id]);
    Audit::event('usuarios.excluir','sucesso',['mensagem'=>'Usuário inativado/excluído logicamente pelo administrador','entidade'=>'usuarios','entidade_id'=>$id,'contexto'=>['email'=>$u['email'],'perfil'=>$u['perfil']]]);
    redirect('index.php?page=usuarios&excluido=1');
  }

  private function permissoesSalvar(): void {
    PermissionService::require('usuarios','gerenciar');
    Csrf::validate();
    $permissoesPost = $_POST['permissoes'] ?? [];
    if (!is_array($permissoesPost)) $permissoesPost = [];
    $todas = $this->db('permissoes_perfil')->query("SELECT id FROM permissoes_perfil")->fetchAll();
    $permitidos = array_map('intval', array_keys($permissoesPost));
    $this->pdo->beginTransaction();
    try {
      $this->db('permissoes_perfil')->exec("UPDATE permissoes_perfil SET permitido=0");
      if ($permitidos) {
        $in = implode(',', array_fill(0, count($permitidos), '?'));
        $st = $this->db('permissoes_perfil')->prepare("UPDATE permissoes_perfil SET permitido=1 WHERE id IN ($in)");
        $st->execute($permitidos);
      }
      // Admin sempre preserva acesso total para evitar bloqueio administrativo.
      $this->db('permissoes_perfil')->exec("UPDATE permissoes_perfil SET permitido=1 WHERE perfil='admin'");
      $this->pdo->commit();
      Audit::event('permissoes.salvar','sucesso',['mensagem'=>'Matriz de permissões atualizada pelo painel.']);
      redirect('index.php?page=usuarios&permissoes=ok');
    } catch (Throwable $e) {
      if($this->pdo->inTransaction()) $this->pdo->rollBack();
      Audit::exception($e,'permissoes.salvar.erro',['codigo_erro'=>'PERMISSION_SAVE_ERROR']);
      redirect('index.php?page=usuarios&permissoes=erro');
    }
  }

  private function trocarSenha(): void {
    Auth::requireLogin();
    if($_SERVER['REQUEST_METHOD'] !== 'POST') { $pageTitle='Trocar senha'; require __DIR__.'/../../views/trocar_senha.php'; return; }
    Csrf::validate();
    $senha=(string)($_POST['senha'] ?? ''); $confirma=(string)($_POST['confirma'] ?? '');
    $policyError=PasswordPolicyService::message($senha,(string)(Auth::user()['email']??''));
    if($senha!==$confirma||$policyError!==''){ $erro=$senha!==$confirma?'A confirmação da senha não confere.':$policyError; $pageTitle='Trocar senha'; require __DIR__.'/../../views/trocar_senha.php'; return; }
    $id=Auth::user()['id'];
    $this->db('usuarios')->prepare('UPDATE usuarios SET senha=?, deve_trocar_senha=0, tentativas_login=0, bloqueado_ate=NULL, session_version=COALESCE(session_version,0)+1 WHERE id=?')->execute([password_hash($senha,PASSWORD_DEFAULT),$id]);
    $_SESSION['user']['deve_trocar_senha']=0;
    if(Database::columnExists('usuarios','session_version')){ $stVersion=$this->db('usuarios')->prepare('SELECT session_version FROM usuarios WHERE id=?');$stVersion->execute([$id]);$_SESSION['session_version']=(int)($stVersion->fetchColumn()?:0); }
    Audit::event('auth.trocar_senha','sucesso',['mensagem'=>'Senha alterada pelo usuário','entidade'=>'usuarios','entidade_id'=>$id]);
    redirect('index.php?page=dashboard&senha=ok');
  }
}
