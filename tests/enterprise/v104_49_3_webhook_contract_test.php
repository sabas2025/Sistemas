<?php
declare(strict_types=1);
require __DIR__.'/_helpers.php';
$checks = [];

/**
 * Contrato de resposta dos webhooks de ENTRADA — achado I-14 (auditoria de 2026-09-15).
 *
 * `responderTiny(bool $success, array $data, int $httpCode)` monta o corpo com `success` e define o
 * status HTTP. Na resposta do webhook de PEDIDO o primeiro argumento era `true` FIXO enquanto o
 * código variava 202/422: um pedido bloqueado respondia `HTTP 422` com `"success": true`, dois
 * sinais do mesmo corpo dizendo o contrário. Quem integra lê um dos dois e erra.
 *
 * Que era inconsistência, e não contrato deliberado, ficou provado pelo próprio arquivo: a resposta
 * de estoque sem itens já fazia `responderTiny(false, …, 422)`. Aquela linha era a única a divergir.
 *
 * Esta verificação generaliza a regra: status de erro (>= 400) exige `success` falso no corpo.
 *
 * O QUE ELA NÃO PROVA: não diz se o CÓDIGO HTTP escolhido é o certo para o fluxo — trocar 422 por
 * 200 mudaria quando o Tiny reenvia, e isso é decisão de produto, não de correção.
 */
$arquivo = 'app/Controllers/ApiController.php';
$codigo = hub_read($arquivo);
$linhas = explode("\n", $codigo);

/**
 * A varredura precisa ler a chamada INTEIRA, não a linha: a resposta do webhook de pedido — a que
 * carregava o defeito — abre em `responderTiny(true,[` e só fecha três linhas abaixo, com o código
 * vindo de um ternário (`$validacao['ok']?202:422`). Uma primeira versão desta checagem casava só
 * dentro de uma linha e deixava passar exatamente o caso que motivou o teste.
 */
$divergentes = [];
$conferidas = 0;
$pos = 0;
while (($achou = strpos($codigo, 'responderTiny(', $pos)) !== false) {
    $abre = $achou + strlen('responderTiny(');
    // percorre até fechar o parêntese da chamada, ignorando aninhamentos e strings
    $nivel = 1; $i = $abre; $len = strlen($codigo); $aspas = '';
    $virgulasTopo = [];
    while ($i < $len && $nivel > 0) {
        $c = $codigo[$i];
        if ($aspas !== '') {
            if ($c === '\\') { $i += 2; continue; }
            if ($c === $aspas) $aspas = '';
        } elseif ($c === "'" || $c === '"') { $aspas = $c; }
        elseif ($c === '(' || $c === '[') { $nivel++; }
        elseif ($c === ')' || $c === ']') { $nivel--; if ($nivel === 0) break; }
        elseif ($c === ',' && $nivel === 1) { $virgulasTopo[] = $i; }
        $i++;
    }
    $pos = $i + 1;
    if ($nivel !== 0 || $virgulasTopo === []) continue;
    $chamada = substr($codigo, $abre, $i - $abre);
    $primeiro = trim(substr($codigo, $abre, $virgulasTopo[0] - $abre));
    // o código HTTP é o último argumento de topo; pode ser literal ou ternário
    $ultimo = trim(substr($codigo, end($virgulasTopo) + 1, $i - end($virgulasTopo) - 1));
    if (!preg_match_all('/\b(\d{3})\b/', $ultimo, $cs)) continue;
    $conferidas++;
    foreach ($cs[1] as $bruto) {
        $http = (int)$bruto;
        if ($http >= 400 && $primeiro !== 'false' && !str_contains($primeiro, "['ok']")) {
            $linha = substr_count(substr($codigo, 0, $achou), "\n") + 1;
            $divergentes[] = $arquivo.':'.$linha."  HTTP {$http} com success={$primeiro}";
        }
    }
}

hub_check($checks, "Respostas responderTiny com código explícito conferidas: {$conferidas}", $conferidas >= 5);
hub_check($checks, 'Status de erro sempre com success=false: '.(implode(' | ', $divergentes) ?: 'nenhuma divergência'), $divergentes === []);

// O ponto exato do I-14, travado por nome: o argumento passou a seguir a validação.
hub_check($checks, 'O webhook de pedido Tiny faz o corpo seguir a validação',
    str_contains($codigo, "\$this->responderTiny(\$validacao['ok'],["));
hub_check($checks, 'O código HTTP do webhook de pedido NÃO foi alterado (202/422 preservados)',
    str_contains($codigo, "\$validacao['ok']?202:422"));

// As seis rotas de webhook Tiny continuam declaradas e classificadas como superfície de entrada —
// o achado C-01 mostrou que tratá-las como rota de painel derruba o rate limit para 20/min.
$catalogo = hub_read('app/Services/RouteCatalogService.php');
foreach ([
    'api/tiny/webhook/estoque', 'api/tiny/webhook/produto', 'api/tiny/webhook/nota-fiscal',
    'api/tiny/webhook/situacao-pedido', 'api/tiny/webhook/pedido', 'api/webhook/tiny/evento',
] as $rota) {
    hub_check($checks, "Rota de webhook de entrada declarada no catálogo: {$rota}", str_contains($catalogo, "'{$rota}'"));
}

// A autenticação do Tiny é por SEGREDO em header, não por HMAC como a da VSM. Não confunda as duas.
$seg = hub_read('app/Services/TinyWebhookSecurityService.php');
hub_check($checks, 'O segredo do Tiny é comparado com hash_equals (nunca ==)', str_contains($seg, 'hash_equals($secret, $received)'));
hub_check($checks, 'A trava de CNPJ autorizado existe e audita o bloqueio',
    str_contains($seg, 'TINY_WEBHOOK_CNPJ_NOT_ALLOWED'));
// Compara com a CHECAGEM `if ($requireSecret)`, não com a atribuição da variável, que fica lá no
// topo do método — a primeira versão desta asserção comparava com a atribuição e acusava um
// defeito que não existe.
hub_check($checks, 'O limitador de tentativas roda ANTES da autenticação (achado A-09)',
    strpos($seg, "RateLimitService::hit('tiny_webhook'") < strpos($seg, 'if ($requireSecret)'));

hub_finish($checks);
