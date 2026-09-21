<?php
declare(strict_types=1);
require __DIR__.'/_helpers.php';

/**
 * Achado L-01 (2026-09-21): ReconciliationService::reconciliarSku() dependia de Tiny e VSM;
 * VsmService::consultarEstoque() LANÇA quando o host está indisponível, e o controller chamava
 * sem try/catch — o operador via a tela de Recuperação genérica, sem registro nem mensagem.
 *
 * Correção: reconciliarSku captura a indisponibilidade do provedor, audita
 * (reconciliacao.sku.indisponivel) e retorna status 'indisponivel' SEM registrar comparação
 * (números inválidos gerariam falso 'divergente'); o controller redireciona com aviso amigável.
 *
 * Teste estrutural: extrai o corpo real do método por tokenização (sem comentários) e exige o
 * try/catch, o status 'indisponivel' e o redirect. Reprova sobre o código antigo, que não os tinha.
 * (A prova comportamental — reconciliarSku não lança e retorna 'indisponivel' sem rede — foi medida
 * em MariaDB real e registrada no relatório da sessão.)
 */
$checks = [];

$extrair = function(string $fonte, string $metodo): string {
  if ($fonte === '') return '';
  $tokens = token_get_all($fonte);
  for ($i=0; $i<count($tokens); $i++) {
    if (!is_array($tokens[$i]) || $tokens[$i][0] !== T_FUNCTION) continue;
    $j=$i+1; while (isset($tokens[$j]) && is_array($tokens[$j]) && $tokens[$j][0]===T_WHITESPACE) $j++;
    if (!isset($tokens[$j]) || !is_array($tokens[$j]) || $tokens[$j][0]!==T_STRING || $tokens[$j][1]!==$metodo) continue;
    $texto=''; $prof=0; $abriu=false;
    for ($k=$i; $k<count($tokens); $k++) {
      $t=$tokens[$k];
      if (is_array($t)) { if (in_array($t[0],[T_COMMENT,T_DOC_COMMENT],true)) continue; $texto.=$t[1]; }
      else { $texto.=$t; if ($t==='{'){$prof++;$abriu=true;} elseif($t==='}'){$prof--; if($abriu&&$prof===0) break;} }
    }
    return $texto;
  }
  return '';
};

$svc = hub_read('app/Services/ReconciliationService.php');
$m = $extrair($svc, 'reconciliarSku');
hub_check($checks, 'reconciliarSku localizado', $m !== '' && strlen($m) > 120);
hub_check($checks, 'reconciliarSku envolve a consulta externa em try/catch',
  str_contains($m,'try') && str_contains($m,'catch') && preg_match('/catch\s*\(\s*\\\\?Throwable/',$m)===1);
hub_check($checks, 'provedor fora → retorna status \'indisponivel\' (sem tela de Recuperação)',
  str_contains($m,'indisponivel'));
hub_check($checks, 'não registra comparação no caminho de indisponibilidade (evita falso divergente)',
  // registrarManual é chamado só DEPOIS do catch (no caminho de sucesso), não dentro dele.
  substr_count($m,'registrarManual') === 1
  && strpos($m,'indisponivel') < strpos($m,'registrarManual'));
hub_check($checks, 'caminho de sucesso preservado (registra + audita reconciliacao.sku.real)',
  str_contains($m,'registrarManual') && str_contains($m,'reconciliacao.sku.real'));

$ctrl = $extrair(hub_read('app/Controllers/DashboardController.php'), 'reconciliacaoExecutar');
hub_check($checks, 'controller redireciona com aviso amigável quando indisponível',
  $ctrl !== '' && str_contains($ctrl,'indisponivel') && str_contains($ctrl,'provedor_indisponivel'));

$view = hub_read('views/reconciliacao.php');
hub_check($checks, 'view exibe o aviso de provedor indisponível',
  $view !== '' && str_contains($view,'provedor_indisponivel'));

hub_finish($checks);
