<?php
/** V104.19 - recomendações analíticas para produção comercial. */
class CommercialReadinessAdvisorService {
  public static function analyze(): array {
    $checks = [];
    $license = class_exists('LicenseEnforcementService') ? LicenseEnforcementService::status() : ['status'=>'erro','mensagem'=>'LicenseEnforcementService ausente'];
    $connectors = class_exists('ConnectorRegistryService') ? ConnectorRegistryService::all() : [];
    $schema = class_exists('Database') ? ['status'=>'ok','tabelas'=>count(Database::knownTables())] : ['status'=>'erro'];
    $ddl = class_exists('SchemaRuntimePolicyService') ? SchemaRuntimePolicyService::report() : ['revisar'=>999];
    $checks[] = self::check('Licenciamento HMAC', ($license['status']??'')==='ok', $license['mensagem'] ?? 'Verifique licença');
    $checks[] = self::check('Conectores plugáveis', count($connectors) >= 2, count($connectors).' conector(es) registrado(s)');
    $checks[] = self::check('Schema oficial', ($schema['status']??'')==='ok' && ($schema['tabelas']??0) >= 100, 'Tabelas conhecidas: '.($schema['tabelas']??0));
    $checks[] = self::check('DDL runtime controlado', (int)($ddl['revisar']??0) === 0, 'Itens para revisar: '.($ddl['revisar']??0));
    $score = 0; foreach($checks as $c){ $score += $c['ok'] ? 25 : 0; }
    return ['score'=>$score,'status'=>$score>=90?'pronto':($score>=70?'atencao':'critico'),'checks'=>$checks,'sugestoes'=>self::suggestions($checks)];
  }
  private static function check(string $titulo, bool $ok, string $detalhe): array { return ['titulo'=>$titulo,'ok'=>$ok,'status'=>$ok?'ok':'atencao','detalhe'=>$detalhe]; }
  private static function suggestions(array $checks): array {
    $out=[]; foreach($checks as $c){ if(!$c['ok']) $out[]='Corrigir: '.$c['titulo'].' — '.$c['detalhe']; }
    if(!$out) $out[]='Checklist comercial sem pendências críticas neste ambiente. Execute homologação Tiny/VSM real antes de produção.';
    return $out;
  }
}
