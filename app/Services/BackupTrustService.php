<?php
class BackupTrustService {
  public static function score(?string $file=null): array {
    $dir=dirname(__DIR__,2).'/storage/backups';
    $files=[];
    if ($file) $files[]=$dir.'/'.basename($file); else $files=array_merge(glob($dir.'/backup_*.sql')?:[], glob($dir.'/backup_*.zip')?:[], glob($dir.'/importado_*.sql')?:[], glob($dir.'/importado_*.zip')?:[]);
    rsort($files);
    $items=[]; $totalScore=0; $count=0;
    foreach (array_slice($files,0,20) as $f) {
      if (!is_file($f)) continue;
      $checks=[]; $score=0; $max=0;
      $add=function($label,$ok,$peso,$acao='') use (&$checks,&$score,&$max){ $max+=$peso; if($ok)$score+=$peso; $checks[]=['label'=>$label,'ok'=>(bool)$ok,'peso'=>$peso,'acao'=>$acao]; };
      $sig=BackupSignatureService::signaturePath($f);
      $add('Arquivo existe', true, 10);
      $add('Assinatura .sig.json existe', is_file($sig), 25, 'Gerar novo backup pelo painel para obter assinatura.');
      $verified=false; try { BackupSignatureService::verify($f); $verified=true; } catch(Throwable $e){ if (class_exists('BestEffortLogService')) BestEffortLogService::warning(__METHOD__, $e); }
      $add('HMAC/SHA-256 conferem', $verified, 35, 'Não restaurar backup sem HMAC válido.');
      $add('Nome segue padrão permitido', (bool)preg_match('/^(backup_|importado_).+\.(sql|zip)$/i', basename($f)), 10);
      $add('Arquivo não está vazio', filesize($f) > 0, 10);
      $add('Backup recente ou importado controlado', filemtime($f) >= time()-(30*86400) || str_starts_with(basename($f),'importado_'), 10, 'Gerar backup recente antes de alterações críticas.');
      $percent=$max?(int)round($score*100/$max):0; $totalScore += $percent; $count++;
      $items[]=['arquivo'=>basename($f),'score'=>$percent,'status'=>$percent>=90?'confiavel':($percent>=70?'atencao':'bloqueado'),'tamanho'=>filesize($f),'modificado_em'=>date('Y-m-d H:i:s', filemtime($f)),'checks'=>$checks];
    }
    return ['score'=>$count?(int)round($totalScore/$count):0,'items'=>$items,'gerado_em'=>date('Y-m-d H:i:s')];
  }
}
