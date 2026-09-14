<?php
class PermissionService {
  public static function can(string $modulo, string $acao): bool {
    $u=Auth::user(); if(!$u) return false;
    return self::canForProfile((string)($u['perfil'] ?? ''), $modulo, $acao);
  }

  /**
   * Reauditoria 2026-09-14 (achado A-02): autoriza por perfil explícito, sem depender da
   * sessão. Necessário no callback OAuth, que chega por navegação cross-site e portanto sem
   * o cookie SameSite=Strict - o perfil vem da transação OAuth assinada (OAuthStateService).
   * Não use isto onde houver sessão disponível: prefira can()/require().
   */
  public static function canForProfile(string $perfil, string $modulo, string $acao): bool {
    if ($perfil === '') return false;
    if ($perfil === 'admin') return true;
    $pdo=Database::forTable('permissoes_perfil');
    $st=$pdo->prepare('SELECT permitido FROM permissoes_perfil WHERE perfil=? AND modulo=? AND acao=? LIMIT 1');
    $st->execute([$perfil, $modulo, $acao]);
    $r=$st->fetch(); return $r ? (bool)$r['permitido'] : false;
  }
  public static function require(string $modulo, string $acao): void {
    Auth::requireLogin();
    if(!self::can($modulo,$acao)){
      Audit::event('permissao.negada','erro',['codigo_erro'=>'PERMISSION_DENIED','mensagem'=>'Usuário sem permissão para '.$modulo.'.'.$acao,'contexto'=>['usuario'=>Auth::user()]]);
      http_response_code(403); exit('Acesso negado para esta função.');
    }
  }
}
