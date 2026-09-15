<?php
class TwoFactorService {
  public static function generateSecret(int $length=20): string {
    $alphabet='ABCDEFGHIJKLMNOPQRSTUVWXYZ234567'; $s='';
    for($i=0;$i<$length;$i++) $s.=$alphabet[random_int(0,strlen($alphabet)-1)];
    return $s;
  }

  public static function encryptSecret(?string $secret): ?string {
    if ($secret === null || $secret === '') return $secret;
    return CryptoService::encrypt($secret);
  }

  public static function decryptSecret(?string $value): string {
    if ($value === null || $value === '') return '';
    try {
      return (string)CryptoService::decrypt($value);
    } catch (Throwable $e) {
      if (class_exists('SecurityAuditService')) {
        try { SecurityAuditService::record('auth.2fa_secret_decrypt_fail','alerta',['motivo'=>$e->getMessage()]); } catch(Throwable $ignored){ if (class_exists('BestEffortLogService')) BestEffortLogService::warning(__METHOD__, $ignored); }
      }
      return '';
    }
  }

  public static function provisioningUri(string $email, string $secret, string $issuer='Hub de Integração'): string {
    $label = rawurlencode($issuer.':'.$email);
    return 'otpauth://totp/'.$label.'?secret='.rawurlencode($secret).'&issuer='.rawurlencode($issuer).'&algorithm=SHA1&digits=6&period=30';
  }

  public static function totp(string $secret, ?int $timeSlice=null): string {
    $timeSlice = $timeSlice ?? (int)floor(time()/30);
    $key=self::base32Decode($secret);
    $bin=pack('N*',0).pack('N*',$timeSlice);
    $hash=hash_hmac('sha1',$bin,$key,true);
    $offset=ord(substr($hash,-1)) & 0x0F;
    $trunc=unpack('N',substr($hash,$offset,4))[1] & 0x7FFFFFFF;
    return str_pad((string)($trunc % 1000000),6,'0',STR_PAD_LEFT);
  }

  public static function verify(string $secret, string $code): bool {
    $code=preg_replace('/\D/','',$code);
    if (strlen($code)!==6) return false;
    $window = 2; // aceita até ~60s de diferença antes/depois para celulares com relógio levemente fora de sincronia
    try {
      if (class_exists('App')) {
        $sec = App::config()['security'] ?? [];
        $window = max(1, min(4, (int)($sec['two_factor_time_window_steps'] ?? $window)));
      }
    } catch (Throwable $e) { $window = 2; }
    $slice=(int)floor(time()/30);
    for($i=-$window;$i<=$window;$i++){
      if(hash_equals(self::totp($secret,$slice+$i),$code)) return true;
    }
    return false;
  }

  private static function base32Decode(string $b32): string {
    $alphabet='ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';
    $b32=strtoupper(preg_replace('/[^A-Z2-7]/','',$b32));
    $bits='';
    foreach(str_split($b32) as $c){ $v=strpos($alphabet,$c); if($v!==false) $bits.=str_pad(decbin($v),5,'0',STR_PAD_LEFT); }
    $out=''; foreach(str_split($bits,8) as $byte){ if(strlen($byte)===8) $out.=chr(bindec($byte)); }
    return $out;
  }
}
