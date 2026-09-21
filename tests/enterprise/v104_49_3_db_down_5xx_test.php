<?php
declare(strict_types=1);
require __DIR__.'/_helpers.php';

/**
 * Teste de caos medido (2026-09-21): quando o BANCO CAI, toda rota — inclusive os webhooks de
 * entrada — respondia HTTP 409 com o SQLSTATE cru no corpo, em vez de 5xx.
 *
 * Causa raiz: IntegrationTenantService::enforceRequest() (chamado em FastRouteDispatcherService
 * a cada requisição) resolve o tenant tocando o banco (singleEmpresaId/boundEmpresaId). Na queda,
 * o PDOException — que É subclasse de RuntimeException — caía no `catch (RuntimeException)`, que
 * fazia `http_response_code(409); echo $e->getMessage(); exit;`. Efeito medido em MariaDB real
 * derrubado: webhook 401→409, corpo "SQLSTATE[HY000] [2002] Connection refused".
 *
 * Consequência: 409 (Conflict) é lido por Tiny/VSM como duplicado/definitivo — não reenviam — então
 * um pedido de entrada seria PERDIDO numa queda de banco; e o SQLSTATE cru vazava ao chamador.
 *
 * Correção: capturar PDOException ANTES e deixá-la propagar para o tratador global de
 * public/index.php, que mapeia a queda de conexão para 503 + tela de Recuperação (sem vazar).
 *
 * Este teste extrai o corpo REAL do método por tokenização e remove comentários antes de casar
 * (a documentação da correção não pode satisfazer a asserção). Reprovaria sobre o código antigo,
 * que não tinha o catch de PDOException.
 */
$checks = [];

// A armadilha da linguagem, documentada: um erro de infraestrutura é um RuntimeException.
hub_check($checks, 'PDOException É subclasse de RuntimeException (a raiz do defeito)',
  is_subclass_of('PDOException', 'RuntimeException'));

/** Extrai o corpo de um método por tokens, SEM comentários. Retorna '' se não achar. */
$extrairMetodo = function(string $fonte, string $metodo): string {
  if ($fonte === '') return '';
  $tokens = token_get_all($fonte);
  for ($i=0; $i<count($tokens); $i++) {
    if (!is_array($tokens[$i]) || $tokens[$i][0] !== T_FUNCTION) continue;
    $j=$i+1; while (isset($tokens[$j]) && is_array($tokens[$j]) && $tokens[$j][0]===T_WHITESPACE) $j++;
    if (!isset($tokens[$j]) || !is_array($tokens[$j]) || $tokens[$j][0]!==T_STRING || $tokens[$j][1]!==$metodo) continue;
    $texto=''; $prof=0; $abriu=false;
    for ($k=$i; $k<count($tokens); $k++) {
      $t=$tokens[$k];
      if (is_array($t)) {
        if (in_array($t[0], [T_COMMENT, T_DOC_COMMENT], true)) continue; // fora comentários
        $texto .= $t[1];
      } else {
        $texto .= $t;
        if ($t==='{') { $prof++; $abriu=true; }
        elseif ($t==='}') { $prof--; if ($abriu && $prof===0) break; }
      }
    }
    return $texto;
  }
  return '';
};

$src = hub_read('app/Services/IntegrationTenantService.php');
hub_check($checks, 'IntegrationTenantService foi lido', $src !== '' && strlen($src) > 500);

$m = $extrairMetodo($src, 'enforceRequest');
hub_check($checks, 'método enforceRequest localizado', $m !== '' && strlen($m) > 80, strlen($m).' bytes');

$posPdo  = strpos($m, 'catch(PDOException') !== false ? strpos($m, 'catch(PDOException') : strpos($m, 'catch (PDOException');
$posRun  = strpos($m, 'catch(RuntimeException') !== false ? strpos($m, 'catch(RuntimeException') : strpos($m, 'catch (RuntimeException');

hub_check($checks, 'enforceRequest captura PDOException (infra) explicitamente', $posPdo !== false);
hub_check($checks, 'enforceRequest ainda captura RuntimeException (tenant → 409 preservado)', $posRun !== false);
hub_check($checks, 'o catch de PDOException vem ANTES do de RuntimeException (senão nunca é alcançado)',
  $posPdo !== false && $posRun !== false && $posPdo < $posRun);

// O ramo de PDOException deve PROPAGAR (throw) e NÃO devolver 409 nem ecoar mensagem.
$ramoPdo = ($posPdo !== false && $posRun !== false && $posPdo < $posRun) ? substr($m, $posPdo, $posRun - $posPdo) : '';
hub_check($checks, 'o ramo de PDOException propaga (throw), não devolve 409',
  $ramoPdo !== '' && str_contains($ramoPdo, 'throw') && !str_contains($ramoPdo, '409') && !str_contains($ramoPdo, 'echo'));

// O ramo de tenant (negócio) permanece 409 — não removemos o comportamento legítimo.
$ramoRun = $posRun !== false ? substr($m, $posRun) : '';
hub_check($checks, 'o ramo de tenant (RuntimeException) permanece 409',
  $ramoRun !== '' && str_contains($ramoRun, '409'));

// Referência: MyOuroController já seguia o padrão correto (catch PDOException → 503 ANTES do
// catch RuntimeException → 409). Ancorar no `catch`, não em qualquer menção a RuntimeException
// (o método também LANÇA RuntimeException antes, o que enganaria um strpos ingênuo).
$myouro = hub_read('app/Controllers/MyOuroController.php');
$acha = function(string $h, string $needle): int {
  $a = strpos($h, 'catch ('.$needle); $b = strpos($h, 'catch('.$needle);
  if ($a === false) return $b === false ? -1 : $b;
  if ($b === false) return $a;
  return min($a, $b);
};
$mp = $acha($myouro, 'PDOException');
$mr = $acha($myouro, 'RuntimeException');
hub_check($checks, 'MyOuroController também captura PDOException (503) antes do catch de 409',
  $myouro !== '' && $mp >= 0 && $mr >= 0 && $mp < $mr && str_contains($myouro, "http_response_code(503)"));

hub_finish($checks);
