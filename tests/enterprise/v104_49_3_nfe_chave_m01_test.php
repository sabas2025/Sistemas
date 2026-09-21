<?php
declare(strict_types=1);
require __DIR__.'/_helpers.php';
require_once hub_root().'/app/Services/XmlNfeHomologationService.php';

/**
 * Achado M-01 (2026-09-21): a validação de chave NF-e (44 dígitos + dígito verificador módulo-11)
 * existia em XmlNfeHomologationService::validarChaveNfe(), mas NÃO era chamada no intake real
 * (PedidoCicloVidaService::receberRetornoVsm) — uma chave com DV inválido era gravada validado=1.
 *
 * Correção: receberRetornoVsm valida a chave QUANDO ela está presente (chave ausente segue a regra
 * de exigir_chave_nfe, para não bloquear retorno legítimo sem chave).
 */
$checks = [];

// gera uma chave válida (43 díg + DV módulo-11) e uma inválida
$b = substr(str_pad('35240111222333000181550010000009991', 43, '0'), 0, 43);
$peso=2; $soma=0; for($i=strlen($b)-1;$i>=0;$i--){ $soma+=intval($b[$i])*$peso; $peso=($peso==9)?2:$peso+1; }
$r=$soma%11; $dv=($r==0||$r==1)?0:11-$r; $chaveOk=$b.$dv; $chaveBad=$b.(($dv+1)%10);

// 1) o validador em si (comportamental, sem banco)
$vok = XmlNfeHomologationService::validarChaveNfe($chaveOk);
$vbad = XmlNfeHomologationService::validarChaveNfe($chaveBad);
hub_check($checks, 'validarChaveNfe aceita chave com DV correto', !empty($vok['ok']));
hub_check($checks, 'validarChaveNfe rejeita chave com DV incorreto', empty($vbad['ok']));
hub_check($checks, 'validarChaveNfe rejeita tamanho != 44', empty(XmlNfeHomologationService::validarChaveNfe('123')['ok']));

// 2) o intake real agora CHAMA o validador (guarda estrutural, tokenizada sem comentários)
$src = hub_read('app/Services/PedidoCicloVidaService.php');
$extrair = function(string $fonte, string $metodo): string {
  if ($fonte==='') return '';
  $t=token_get_all($fonte);
  for($i=0;$i<count($t);$i++){ if(!is_array($t[$i])||$t[$i][0]!==T_FUNCTION) continue;
    $j=$i+1; while(isset($t[$j])&&is_array($t[$j])&&$t[$j][0]===T_WHITESPACE)$j++;
    if(!isset($t[$j])||!is_array($t[$j])||$t[$j][0]!==T_STRING||$t[$j][1]!==$metodo) continue;
    $txt='';$p=0;$a=false;
    for($k=$i;$k<count($t);$k++){ $x=$t[$k];
      if(is_array($x)){ if(in_array($x[0],[T_COMMENT,T_DOC_COMMENT],true)) continue; $txt.=$x[1]; }
      else{ $txt.=$x; if($x==='{'){$p++;$a=true;} elseif($x==='}'){$p--; if($a&&$p===0) break;} } }
    return $txt; }
  return '';
};
$m = $extrair($src, 'receberRetornoVsm');
hub_check($checks, 'receberRetornoVsm localizado', $m!=='' && strlen($m)>200);
hub_check($checks, 'receberRetornoVsm chama validarChaveNfe (intake real valida a chave)',
  str_contains($m,'validarChaveNfe'));
hub_check($checks, 'a validação da chave é guardada por presença (não bloqueia chave ausente)',
  preg_match('/\$chave\s*!==\s*\x27\x27[^;]*validarChaveNfe|if\s*\(\s*\$chave[^)]*\)[^;]*\{?[^}]*validarChaveNfe/s', $m) === 1
  || (strpos($m,"\$chave !== ''") !== false && strpos($m,"\$chave !== ''") < strpos($m,'validarChaveNfe')));

hub_finish($checks);
