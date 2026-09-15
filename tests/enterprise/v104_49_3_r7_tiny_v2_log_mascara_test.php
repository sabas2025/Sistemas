<?php
declare(strict_types=1);
require __DIR__.'/_helpers.php';
$checks=[];

/**
 * Auditoria final 2026-09-14 — achados G-01 e G-02.
 *
 * G-01: TinyV2Service::logEndpoint() declara `array $request` e passava esse array para
 * SensitiveDataService::maskJson(), que declara `string $body`. Array não é coercível para
 * string em PHP 8, então TODA chamada lançava TypeError, o catch(Throwable) do próprio método
 * engolia, e tiny_v2_endpoint_logs nunca recebia um registro. Efeito visível: a tela de
 * observabilidade do Tiny V2 ficava vazia para sempre e RealtimeHealthService mostrava
 * "sem chamada recente" com o status verde, mesmo sob tráfego real.
 *
 * G-02: dos três clientes de integração, o V2 era o único que gravava requisição e resposta de
 * sucesso em auditoria_eventos sem SensitiveDataService. Audit::event() não mascara nada - a
 * máscara é responsabilidade de quem chama.
 *
 * Este teste falha se qualquer um dos dois voltar.
 */

/** Código sem comentários: os comentários da correção citam maskJson() pelo nome. */
function hub_exec_g(string $rel): string {
    $fonte = hub_read($rel); if ($fonte === '') return '';
    $o='';
    foreach (token_get_all($fonte) as $t) {
        if (is_array($t) && in_array($t[0],[T_COMMENT,T_DOC_COMMENT],true)) continue;
        $o .= is_array($t) ? $t[1] : $t;
    }
    return $o;
}
function hub_corpo_metodo(string $codigo, string $assinatura): string {
    $i = strpos($codigo, $assinatura);
    if ($i === false) return '';
    $c = substr($codigo, $i);
    $j = strpos($c, "\n  }\n");
    return $j === false ? $c : substr($c, 0, $j + 4);
}

$v2 = hub_exec_g('app/Services/TinyV2Service.php');
hub_check($checks,'TinyV2Service foi lido', $v2 !== '');

// ------------------------------------------------------------------ G-01 (estático)
$log = hub_corpo_metodo($v2, 'private static function logEndpoint');
hub_check($checks,'G-01: logEndpoint() foi localizado', $log !== '');
hub_check($checks,'G-01: logEndpoint() não chama maskJson() (exige string, recebe array)',
    $log !== '' && !str_contains($log,'maskJson('));
hub_check($checks,'G-01: logEndpoint() usa sanitizeForStorage() nas duas colunas',
    substr_count($log,'sanitizeForStorage(') === 2);
hub_check($checks,'G-01: o INSERT grava as variáveis já sanitizadas',
    str_contains($log,'$req, $resp, $erro'));

// ------------------------------------------------------------------ G-02 (estático)
hub_check($checks,'G-02: tiny.v2.request mascara o corpo enviado',
    (bool)preg_match("/tiny\.v2\.request'.*SensitiveDataService::mask\(\\\$safeData\)/s", $v2));
hub_check($checks,'G-02: tiny.v2.response mascara a resposta de sucesso',
    (bool)preg_match("/tiny\.v2\.response'.*SensitiveDataService::mask\(\\\$json\)/s", $v2));
hub_check($checks,'G-02: Audit::event continua sem mascarar sozinho (a máscara é do chamador)',
    !str_contains(hub_exec_g('app/Services/Audit.php'),'SensitiveDataService'));

// ------------------------------------------------- paridade com os irmãos já corretos
foreach (['TinyV3Service','VsmService'] as $irmao) {
    hub_check($checks,"paridade: $irmao mascara requisição e resposta",
        substr_count(hub_exec_g("app/Services/$irmao.php"),'SensitiveDataService::mask(') >= 2);
}

// ------------------------------------------------------------------ comportamento real
require_once hub_root().'/app/Services/SensitiveDataService.php';

$lancou = false;
try { SensitiveDataService::maskJson(['token'=>'x']); } catch (TypeError $e) { $lancou = true; }
hub_check($checks,'a causa raiz de G-01 continua existindo (maskJson(array) lança TypeError)', $lancou,
    'se isto virar falso, a assinatura mudou e este teste precisa ser revisto');

$pedido = json_encode(['cliente'=>['cpf_cnpj'=>'12345678901','email'=>'joao@exemplo.com','fone'=>'11999998888','endereco'=>'Rua X, 100']], JSON_UNESCAPED_UNICODE);
$tipos = [
    'array (request dos 3 call sites)' => ['token'=>'t','formato'=>'json','pedido'=>$pedido],
    'string (response dos caminhos de erro)' => '<html>erro</html>',
    'array (response do caminho de sucesso)' => ['retorno'=>['pedido'=>['cliente'=>['cpf_cnpj'=>'12345678901','email'=>'joao@exemplo.com']]]],
];
foreach ($tipos as $rotulo => $valor) {
    $ok = false; $out = '';
    try { $out = SensitiveDataService::sanitizeForStorage($valor); $ok = is_string($out); } catch (Throwable $e) { $ok = false; }
    hub_check($checks,"sanitizeForStorage aceita $rotulo e devolve string gravável", $ok);
}

$gravado = SensitiveDataService::sanitizeForStorage($tipos['array (request dos 3 call sites)'])
         . SensitiveDataService::sanitizeForStorage($tipos['array (response do caminho de sucesso)']);
// PHP converte chave de array numérica em int, e str_contains exige string - por isso a lista
// é de pares, não um mapa por chave.
foreach ([['12345678901','CPF'],['joao@exemplo.com','e-mail'],['11999998888','telefone'],['Rua X, 100','endereço']] as [$claro,$rotulo]) {
    hub_check($checks,"$rotulo não é gravado em claro", !str_contains($gravado,(string)$claro));
}

hub_finish($checks);
