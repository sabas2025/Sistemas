<?php
class Csrf {
  public static function token(): string {
    if (empty($_SESSION['csrf_token'])) {
      $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
  }
  public static function input(): string {
    return '<input type="hidden" name="csrf_token" value="'.e(self::token()).'">';
  }
  public static function field(): string {
    return self::input();
  }
  public static function validate(): void {
    $sent = $_POST['csrf_token'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
    if (!$sent || empty($_SESSION['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $sent)) {
      Audit::event('seguranca.csrf_invalido','erro',[
        'codigo_erro'=>'CSRF_INVALID',
        'mensagem'=>'Token CSRF ausente ou inválido. A ação foi bloqueada por segurança.',
        'causa_provavel'=>'Formulário expirado, aba antiga aberta ou tentativa externa de enviar dados para o painel.',
        'acao_recomendada'=>'Recarregue a página e tente novamente. Se persistir, limpe o cache do navegador.'
      ]);
      http_response_code(403);
      exit('Ação bloqueada: token de segurança inválido. Recarregue a página e tente novamente.');
    }
  }
}
