<?php
declare(strict_types=1);
require __DIR__.'/_helpers.php';

/**
 * Achado I-22 (2026-09-15): o preflight de public/install.php abortava TODA instalação limpa.
 *
 * `split_schema_definitions()` respeita aspas e parênteses, mas NÃO descartava comentário SQL.
 * Uma vírgula dentro de um `--` partia a lista de definições, e o pedaço seguinte virava coluna
 * fantasma: o parser via colunas chamadas `e`, `os`, `por` e `UPDATE`. A primeira delas fazia
 * `expected_column_contract()` lançar "Definição de coluna SQL não reconhecida no contrato
 * canônico", dentro de `assert_fresh_install_targets()` — antes de qualquer DDL. Medido contra
 * MariaDB real: o instalador respondia "Erro técnico na instalação", consumia a autorização de 15
 * minutos e deixava o banco com ZERO tabelas.
 *
 * Nenhum portão pegava porque nenhum deles roda o parser do INSTALADOR: a CI aplica os módulos
 * pelo cliente mysql (`mysql-modular-runtime-regression.php`) e o E2E usa o consolidado. Os dois
 * caminhos pulam public/install.php.
 *
 * Este teste roda as funções REAIS do instalador sobre os módulos REAIS. Ele não procura o texto
 * da correção: exige que 135 tabelas e 1.550 colunas sejam reconhecidas e que nenhuma seja
 * rejeitada. Com o defeito reposto, reprova.
 */
$checks=[];
$root=dirname(__DIR__,2);
$instaladorFonte=hub_read('public/install.php');
hub_check($checks,'public/install.php foi lido', $instaladorFonte!=='' && strlen($instaladorFonte)>1000);

/** Extrai as funções do instalador por tokenização — nomear é preciso, regex sobre código não é. */
$necessarias=['split_schema_definitions','sql_schema_contract','schema_index_columns',
  'normalized_schema_sql','expected_column_contract','normalized_column_type','normalized_schema_default'];
$tokens=token_get_all($instaladorFonte);
$fontes=[];
for($i=0;$i<count($tokens);$i++){
    if(!is_array($tokens[$i])||$tokens[$i][0]!==T_FUNCTION) continue;
    $j=$i+1; while(isset($tokens[$j])&&is_array($tokens[$j])&&$tokens[$j][0]===T_WHITESPACE) $j++;
    if(!isset($tokens[$j])||!is_array($tokens[$j])||$tokens[$j][0]!==T_STRING) continue;
    $nome=$tokens[$j][1];
    if(!in_array($nome,$necessarias,true)) continue;
    $texto=''; $profundidade=0; $abriu=false;
    for($k=$i;$k<count($tokens);$k++){
        $t=$tokens[$k];
        $texto.=is_array($t)?$t[1]:$t;
        if($t==='{'){ $profundidade++; $abriu=true; }
        elseif($t==='}'){ $profundidade--; if($abriu&&$profundidade===0) break; }
    }
    $fontes[$nome]=$texto;
}
$faltando=array_values(array_diff($necessarias,array_keys($fontes)));
hub_check($checks,'As 7 funções de contrato do instalador foram localizadas', $faltando===[],
    $faltando===[] ? '' : 'ausentes: '.implode(', ',$faltando));

if($faltando===[]){
    foreach($fontes as $nome=>$codigo){ if(!function_exists($nome)) eval($codigo); }

    $modulos=glob($root.'/database/modules/*.sql') ?: [];
    hub_check($checks,'Os módulos SQL foram encontrados', count($modulos)>0, count($modulos).' arquivo(s)');

    $tabelas=0; $colunas=0; $rejeitadas=[];
    foreach($modulos as $arquivo){
        $sql=(string)file_get_contents($arquivo);
        // Mesmo regex de sql_schema_contract(), para isolar a coluna que estoura em vez de
        // parar no primeiro erro como o instalador faz.
        preg_match_all('/CREATE\s+TABLE\s+(?:IF\s+NOT\s+EXISTS\s+)?`?([a-zA-Z0-9_]+)`?\s*\((.*?)\)\s*ENGINE\s*=\s*([a-zA-Z0-9_]+)([^;]*);/is',$sql,$m,PREG_SET_ORDER);
        foreach($m as $t){
            $tabelas++;
            foreach(split_schema_definitions($t[2]) as $def){
                if(preg_match('/^PRIMARY\s+KEY\s*\(/is',$def)) continue;
                if(preg_match('/^(UNIQUE\s+)?(?:KEY|INDEX)\s+`?[a-zA-Z0-9_]+`?\s*\(/is',$def)) continue;
                if(preg_match('/^(?:CONSTRAINT|FOREIGN\s+KEY|CHECK)\b/i',$def)) continue;
                if(!preg_match('/^`?([a-zA-Z0-9_]+)`?\s+(.+)$/is',$def,$c)) continue;
                $colunas++;
                try { expected_column_contract($c[2]); }
                catch (Throwable $e) { $rejeitadas[]=basename($arquivo).':'.$t[1].'.'.$c[1]; }
            }
        }
    }

    // I-20: afirmar o N do conjunto antes de afirmar o vazio. Verde sobre nada não é verde.
    hub_check($checks,'O contrato reconhece as 135 tabelas dos módulos', $tabelas===135, $tabelas.' tabela(s)');
    hub_check($checks,'O contrato reconhece as 1.550 colunas dos módulos', $colunas===1550, $colunas.' coluna(s)');
    hub_check($checks,'Nenhuma coluna é rejeitada pelo contrato canônico do instalador',
        $rejeitadas===[], $rejeitadas===[] ? '' : count($rejeitadas).' rejeitada(s): '.implode(', ',array_slice($rejeitadas,0,6)));
}

hub_finish($checks);
