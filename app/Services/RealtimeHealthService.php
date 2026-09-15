<?php
class RealtimeHealthService {
  public static function snapshot(): array {
    $checks=[];
    $checks[] = self::db();
    $checks[] = self::fila();
    $checks[] = self::tiny('Tiny V2','tiny_v2','tiny_v2_endpoint_logs');
    $checks[] = self::tiny('Tiny V3','tiny_v3','tiny_v3_endpoint_logs');
    $checks[] = self::vsm();
    $checks[] = self::fiscal();
    $ok=count(array_filter($checks, fn($c)=>($c['status'] ?? '')==='ok'));
    return ['status'=>$ok===count($checks)?'ok':($ok>=max(1,count($checks)-2)?'atencao':'erro'),'score'=>(int)round($ok*100/max(1,count($checks))),'checks'=>$checks,'gerado_em'=>date('Y-m-d H:i:s')];
  }
  private static function db(): array {
    $mods = class_exists('Database') ? Database::modules() : ['core'];
    $ok=[]; $fail=[];
    foreach ($mods as $m) {
      try { Database::connection($m)->query('SELECT 1'); $ok[]=$m; }
      catch(Throwable $e){ $fail[]=$m.': '.$e->getMessage(); }
    }
    if ($fail) return self::c('Banco','erro','Falha em módulo(s): '.implode(' | ', array_slice($fail,0,4)));
    return self::c('Banco','ok','MySQL respondeu SELECT 1 em '.count($ok).' módulo(s): '.implode(', ', $ok));
  }
  private static function fila(): array { try { $n=(int)(Database::forTable('fila_integracao')->query("SELECT COUNT(*) c FROM fila_integracao WHERE status IN ('pendente','processando','erro')")->fetch()['c'] ?? 0); return self::c('Fila',$n>100?'atencao':'ok',$n.' item(ns) pendente/processando/erro.'); } catch(Throwable $e){ return self::c('Fila','erro',$e->getMessage()); } }
  private static function tiny(string $label,string $cb,string $table): array { try { $row=Database::forTable('circuit_breakers')->prepare('SELECT status,ultima_falha FROM circuit_breakers WHERE sistema=? LIMIT 1'); $row->execute([$cb]); $c=$row->fetch() ?: ['status'=>'fechado']; $last=null; try{ $last=Database::forTable($table)->query('SELECT http_code,sucesso,criado_em FROM '.$table.' ORDER BY id DESC LIMIT 1')->fetch(); }catch(Throwable $e){ if (class_exists('BestEffortLogService')) BestEffortLogService::warning(__METHOD__, $e); } $st=($c['status']??'fechado')==='aberto'?'erro':'ok'; return self::c($label,$st,'Circuit breaker: '.($c['status'] ?? 'fechado').($last?' | último HTTP '.$last['http_code'].' em '.$last['criado_em']:' | sem chamada recente')); } catch(Throwable $e){ return self::c($label,'atencao','Sem dados suficientes: '.$e->getMessage()); } }
  // P2 (reauditoria 2026-08-23): esta consulta usava http_code, mas a tabela
  // vsm_endpoint_logs tem status_http - a query falhava sempre (silenciosamente,
  // via catch) e o painel de saúde mostrava "sem chamada recente" mesmo com tráfego VSM real.
  private static function vsm(): array { try { $row=Database::forTable('circuit_breakers')->prepare('SELECT status,ultima_falha FROM circuit_breakers WHERE sistema=? LIMIT 1'); $row->execute(['vsm']); $c=$row->fetch() ?: ['status'=>'fechado']; $last=null; try{ $last=Database::forTable('vsm_endpoint_logs')->query('SELECT status_http,sucesso,criado_em FROM vsm_endpoint_logs ORDER BY id DESC LIMIT 1')->fetch(); }catch(Throwable $e){ if (class_exists('BestEffortLogService')) BestEffortLogService::warning(__METHOD__, $e); } return self::c('VSM',($c['status']??'fechado')==='aberto'?'erro':'ok','Circuit breaker: '.($c['status'] ?? 'fechado').($last?' | último HTTP '.$last['status_http'].' em '.$last['criado_em']:' | sem chamada recente')); } catch(Throwable $e){ return self::c('VSM','atencao','Sem dados suficientes: '.$e->getMessage()); } }
  private static function fiscal(): array { try { $res=FiscalEnterpriseService::dashboard(); return self::c('Fiscal',$res['status']==='erro'?'erro':($res['status']==='atencao'?'atencao':'ok'),'Pendentes: '.$res['pendentes'].' | XML erro: '.$res['xml_erro']); } catch(Throwable $e){ return self::c('Fiscal','atencao','Módulo fiscal sem dados: '.$e->getMessage()); } }
  private static function c(string $nome,string $status,string $detalhe): array { return ['nome'=>$nome,'status'=>$status,'ok'=>$status==='ok','detalhe'=>$detalhe,'trace_id'=>RequestContext::id()]; }
}
