<?php
declare(strict_types=1);
if (PHP_SAPI!=='cli') { http_response_code(403); exit; }
require_once dirname(__DIR__).'/app/Core/Autoload.php';
require_once dirname(__DIR__).'/app/Core/Helpers.php';
$options=getopt('', ['help','dry-run','apply','phase:','maintenance-confirmed','restore-tested','backfill-empresa:','legacy-ownership-confirmed']);
if (isset($options['help'])) {
  echo "Uso: php scripts/upgrade-r7.php --dry-run [--phase=standard|bigint|all]\n";
  echo "Aplicar: --apply --restore-tested --maintenance-confirmed\nBackfill opcional: --backfill-empresa=N --legacy-ownership-confirmed\nLeia UPGRADE-R7-20260917.md. DDL pode fazer commit implícito; não há rollback global.\n"; exit;
}
try {
  if (!is_file(dirname(__DIR__).'/config/config.php')) throw new RuntimeException('config/config.php ausente; --help explica o uso sem banco.');
  if (isset($options['apply'],$options['dry-run'])) throw new InvalidArgumentException('Escolha simulação OU aplicação.');
  $phase=(string)($options['phase'] ?? 'standard');
  $backfill=null;
  if (isset($options['backfill-empresa'])) {
    $backfill=filter_var($options['backfill-empresa'],FILTER_VALIDATE_INT,['options'=>['min_range'=>1,'max_range'=>2147483647]]);
    if ($backfill===false || !isset($options['legacy-ownership-confirmed'])) throw new InvalidArgumentException('Backfill exige empresa válida e confirmação da propriedade do legado.');
  }
  $legacy=R7UpgradeService::preflight($backfill);
  $plan=R7UpgradeService::plan($phase,$backfill);
  foreach ($plan as $step) echo $step['id'].' '.$step['file'].' → '.$step['module'].' / '.$step['database'].' ('.count($step['statements'])." comandos)\n";
  echo 'Legado sem empresa: '.json_encode($legacy,JSON_UNESCAPED_UNICODE)."\n";
  if (!isset($options['apply'])) { echo "SIMULAÇÃO: nenhum DDL/DML executado. BIGINT excluído da fase standard.\n"; exit; }
  if (!isset($options['restore-tested'],$options['maintenance-confirmed'])) throw new RuntimeException('Aplicação exige backup restaurado em teste, manutenção e workers/webhooks parados; informe --restore-tested --maintenance-confirmed somente após verificar.');
  R7UpgradeService::apply($plan,$backfill);
  echo "Migrações da fase selecionada aplicadas e pós-condições verificadas. Homologação Tiny/VSM continua obrigatória.\n";
} catch (Throwable $e) { fwrite(STDERR,"[FALHA] ".$e->getMessage()."\n"); exit(1); }
