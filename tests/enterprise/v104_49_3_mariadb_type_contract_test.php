<?php
declare(strict_types=1);
require __DIR__.'/_helpers.php';
$checks=[];

/**
 * Reauditoria 2026-09-14: o gate pós-instalação do install.php reprovava TODA tabela com
 * coluna inteira UNSIGNED em MariaDB real (security_events.id, ips_bloqueados.id,
 * rate_limit_hits.id, integration_replay_guard.time_bucket), bloqueando a instalação.
 *
 * Causa: normalized_schema_sql() remove o espaço ao redor de parênteses, então o
 * COLUMN_TYPE do MariaDB ("bigint(20) unsigned") virava "bigint(20)unsigned"; ao remover
 * a largura de exibição sem repor o separador, o resultado era "bigintunsigned", que nunca
 * batia com o esperado extraído do DDL ("bigint unsigned"). O MySQL 8.0.19+ não expõe
 * largura de exibição, então o defeito só se manifestava em MariaDB - e a CI, que exercita
 * os scripts de regressão (normalizadores próprios, simétricos) e não o gate do install.php,
 * nunca o alcançou.
 *
 * Este teste exercita as funções REAIS do install.php, extraídas do arquivo, comparando o
 * que cada banco reporta em information_schema com o que o DDL declara.
 */
$source=hub_read('public/install.php');
$extracted='';
foreach(['normalized_schema_sql','normalized_column_type'] as $fn){
  if(preg_match('/^function '.preg_quote($fn,'/').'\(.*?^\}/ms',$source,$m))$extracted.=$m[0]."\n";
}
if($extracted===''||!str_contains($extracted,'normalized_column_type')){
  hub_check($checks,'Funções de contrato de tipo extraídas do install.php',false,'não foi possível extrair; verifique a formatação das funções');
  hub_finish($checks);
}
eval($extracted);
hub_check($checks,'Funções de contrato de tipo extraídas do install.php',function_exists('normalized_column_type'));

// [descrição, tipo declarado no DDL, COLUMN_TYPE do MariaDB, COLUMN_TYPE do MySQL 8]
$matriz=[
  ['bigint unsigned',   'BIGINT UNSIGNED', 'bigint(20) unsigned', 'bigint unsigned'],
  ['bigint',            'BIGINT',          'bigint(20)',          'bigint'],
  ['int unsigned',      'INT UNSIGNED',    'int(10) unsigned',    'int unsigned'],
  ['tinyint(1)',        'TINYINT(1)',      'tinyint(1)',          'tinyint(1)'],
  ['tinyint',           'TINYINT',         'tinyint(4)',          'tinyint'],
  ['varchar',           'VARCHAR(190)',    'varchar(190)',        'varchar(190)'],
  ['decimal',           'DECIMAL(10,2)',   'decimal(10,2)',       'decimal(10,2)'],
  ['enum',              "ENUM('a','b')",   "enum('a','b')",       "enum('a','b')"],
  ['datetime',          'DATETIME',        'datetime',            'datetime'],
  ['char',              'CHAR(64)',        'char(64)',            'char(64)'],
];
$divergentes=[];
foreach($matriz as [$desc,$ddl,$maria,$mysql]){
  $esperado=normalized_column_type($ddl);
  if(normalized_column_type($maria)!==$esperado||normalized_column_type($mysql)!==$esperado)$divergentes[]=$desc;
}
hub_check($checks,'Contrato de tipo do instalador confere em MariaDB e MySQL 8',$divergentes===[],$divergentes?'divergem: '.implode(', ',$divergentes):'');

// Guarda específica do defeito corrigido: o separador não pode sumir ao remover a largura.
hub_check($checks,'Largura de exibição removida preservando o separador antes de UNSIGNED',
  normalized_column_type('bigint(20) unsigned')==='bigint unsigned',
  'obtido: '.normalized_column_type('bigint(20) unsigned'));

hub_finish($checks);
