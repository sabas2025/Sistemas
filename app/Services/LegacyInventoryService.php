<?php
class LegacyInventoryService {
  public static function controllersServices(): array {
    $root = dirname(__DIR__, 2);
    $items = [];
    foreach (['app/Controllers'=>'controller','app/Legacy/Controllers'=>'controller_legado','app/Services'=>'service','app/Legacy/Services'=>'service_legado'] as $dir=>$tipo) {
      foreach (glob($root.'/'.$dir.'/*.php') ?: [] as $file) {
        $name = basename($file, '.php');
        $rel = str_replace($root.'/', '', $file);
        $status = self::classificar($name, $rel, $tipo);
        $items[] = ['nome'=>$name,'tipo'=>$tipo,'arquivo'=>$rel,'status'=>$status['status'],'motivo'=>$status['motivo'],'acao'=>$status['acao']];
      }
    }
    usort($items, fn($a,$b)=>strcmp($a['status'].$a['nome'], $b['status'].$b['nome']));
    return ['items'=>$items,'resumo'=>self::resumo($items),'gerado_em'=>date('Y-m-d H:i:s')];
  }

  private static function classificar(string $name, string $rel, string $tipo): array {
    if (str_contains($rel, 'app/Legacy/')) return ['status'=>'LEGADO','motivo'=>'Movido para /app/Legacy para reduzir superfície ativa.','acao'=>'Manter apenas enquanto houver rota compatível; remover após migração final.'];
    if (preg_match('/V\d{2,}/', $name)) return ['status'=>'LEGADO','motivo'=>'Nome histórico/versionado indica acúmulo de versões.','acao'=>'Mover para /app/Legacy ou absorver a lógica no módulo atual.'];
    if (preg_match('/Future|Roadmap|Experimental|Draft/i', $name)) return ['status'=>'FUTURO','motivo'=>'Classe experimental/futura.','acao'=>'Não expor em rota pública até homologar.'];
    if (preg_match('/Controller$/', $name) || preg_match('/Service$/', $name)) return ['status'=>'ATIVO','motivo'=>'Classe operacional sem marca histórica.','acao'=>'Manter monitorada por auditoria e permissões.'];
    return ['status'=>'ATIVO','motivo'=>'Arquivo PHP operacional.','acao'=>'Revisar em limpeza periódica.'];
  }

  private static function resumo(array $items): array {
    $r=['ATIVO'=>0,'LEGADO'=>0,'FUTURO'=>0];
    foreach ($items as $i) $r[$i['status']] = ($r[$i['status']] ?? 0) + 1;
    return $r;
  }
}
