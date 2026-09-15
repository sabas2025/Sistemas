<?php
/** Política única para novas senhas. Senhas existentes continuam válidas até serem alteradas. */
class PasswordPolicyService {
  public static function minLength(): int {
    $cfg=class_exists('App')?App::config():[];
    return max(10,min(64,(int)($cfg['security']['password_min_length']??10)));
  }

  /** @return list<string> */
  public static function validate(string $password, string $email=''): array {
    $errors=[];$len=strlen($password);$min=self::minLength();
    if($len<$min)$errors[]='mínimo de '.$min.' caracteres';
    if($len>256)$errors[]='máximo de 256 caracteres';
    if(!preg_match('/[A-Z]/',$password))$errors[]='uma letra maiúscula';
    if(!preg_match('/[a-z]/',$password))$errors[]='uma letra minúscula';
    if(!preg_match('/[0-9]/',$password))$errors[]='um número';
    $normalized=strtolower(trim($password));
    $blocked=['admin123','admin1234','123456','12345678','123456789','password','password1','senha123','senha1234','qwerty123'];
    if(in_array($normalized,$blocked,true))$errors[]='senha comum/bloqueada';
    $local=strtolower((string)strtok($email,'@'));
    if($local!==''&&strlen($local)>=4&&str_contains($normalized,$local))$errors[]='não conter o nome do e-mail';
    return array_values(array_unique($errors));
  }

  public static function isValid(string $password, string $email=''): bool { return self::validate($password,$email)===[]; }

  public static function message(string $password, string $email=''): string {
    $errors=self::validate($password,$email);
    return $errors?'A senha deve ter '.implode(', ',$errors).'.':'';
  }

  public static function generateTemporary(int $length=16): string {
    $length=max(12,min(64,$length));
    $upper='ABCDEFGHJKLMNPQRSTUVWXYZ';$lower='abcdefghijkmnopqrstuvwxyz';$digits='23456789';$symbols='!@#$%*-_';
    $all=$upper.$lower.$digits.$symbols;
    $chars=[self::pick($upper),self::pick($lower),self::pick($digits),self::pick($symbols)];
    while(count($chars)<$length)$chars[]=self::pick($all);
    for($i=count($chars)-1;$i>0;$i--){$j=random_int(0,$i);[$chars[$i],$chars[$j]]=[$chars[$j],$chars[$i]];}
    return implode('',$chars);
  }

  private static function pick(string $chars): string { return $chars[random_int(0,strlen($chars)-1)]; }
}
