<?php
class LoginController {
  public function form(){ require __DIR__.'/../../views/login.php'; }
  public function login(){
    Csrf::validate();
    $email = trim($_POST['email']??'');
    $res = Auth::login($email, $_POST['senha']??'', $_POST['codigo_2fa'] ?? null);
    if($res === '2fa_required' || $res === '2fa_setup_required') { $precisa2fa=true; $twoFactorInfo=Auth::pendingTwoFactorInfo(); require __DIR__.'/../../views/login.php'; return; }
    if($res === true) redirect('index.php');
    if(!empty($_SESSION['pending_2fa_ok_password'])) {
      $precisa2fa=true;
      $twoFactorInfo=Auth::pendingTwoFactorInfo();
      $erro='Código 2FA inválido. Confira se o celular está com data/hora automáticas e tente novamente. Se acabou de escanear o QR Code, recarregue a tela de login e refaça o pareamento.';
      require __DIR__.'/../../views/login.php';
      return;
    }
    $erro='Usuário ou senha inválidos, ou usuário temporariamente bloqueado por excesso de tentativas.';
    require __DIR__.'/../../views/login.php';
  }
  public function logout(){ Audit::event('auth.logout','sucesso',['mensagem'=>'Logout realizado']); Auth::logout(); }
}
