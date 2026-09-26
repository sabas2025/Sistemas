<?php
declare(strict_types=1);
require __DIR__.'/_helpers.php';
$checks = [];

/**
 * F6-07 etapa 4 (2026-09-26) — PedidoMapper::tinyParaCadastroIntegradora monta o PedidoCadastroDTO.
 *
 * O teste é COMPORTAMENTAL (o mapper é puro) e cruza a saída contra os campos `required` do PRÓPRIO
 * contrato salvo (contracts/vsm/pedidos-integradora.openapi.json) — se o contrato mudar, o teste
 * cobra o mapper. Reprova sobre o código antigo: o método não existe em main.
 */

require_once hub_root().'/app/Services/PedidoMapper.php';
if (!class_exists('RequestContext')) { class RequestContext { public static function id(): string { return 'TESTE'; } } }

$pv = [
  'cliente'  => ['nome'=>'Fulano de Tal','documento'=>'12345678909','email'=>'f@x.com'],
  'endereco' => ['cep'=>'19806173','logradouro'=>'Rua A','bairro'=>'Centro','numero'=>'100','cidade'=>'Assis','uf'=>'SP'],
  'itens'    => [['sku'=>'SKU1','descricao'=>'Produto 1','quantidade'=>2,'valor_unitario'=>10.5]],
  'frete'    => 15.0,
  'valor_total' => 36.0,
  'payload_original' => ['dados'=>['cliente'=>['telefone'=>'(18) 99999-1234']]],
];
$dto = PedidoMapper::tinyParaCadastroIntegradora($pv);

// --- Cruza com os `required` do contrato ---
$contrato = json_decode(hub_read('contracts/vsm/pedidos-integradora.openapi.json'), true);
$schemas = $contrato['components']['schemas'] ?? [];
hub_check($checks, 'contrato pedidos-integradora legível', is_array($schemas) && isset($schemas['PedidoCadastroDTO']));

$reqPedido = $schemas['PedidoCadastroDTO']['required'] ?? [];
foreach ($reqPedido as $campo) {
    hub_check($checks, "DTO tem o campo obrigatório do pedido: {$campo}", array_key_exists($campo, $dto));
}
$reqCliente = $schemas['ClienteCadastroPedidoDTO']['required'] ?? [];
foreach ($reqCliente as $campo) {
    hub_check($checks, "cliente tem o campo obrigatório: {$campo}", array_key_exists($campo, $dto['cliente'] ?? []));
}
$reqEntrega = $schemas['PedidoEntregaCadastroDTO']['required'] ?? [];
foreach ($reqEntrega as $campo) {
    // cep|codigoIbge são alternativos; o mapper usa cep quando válido
    if ($campo === 'cep') { hub_check($checks, 'entrega tem cep OU codigoIbge', isset($dto['pedidoEntrega']['cep']) || isset($dto['pedidoEntrega']['codigoIbge'])); continue; }
    hub_check($checks, "entrega tem o campo obrigatório: {$campo}", array_key_exists($campo, $dto['pedidoEntrega'] ?? []));
}
$reqItem = $schemas['PedidoItemCadastroDTO']['required'] ?? [];
foreach ($reqItem as $campo) {
    hub_check($checks, "item tem o campo obrigatório: {$campo}", array_key_exists($campo, $dto['pedidoItem'][0] ?? []));
}
$reqPag = $schemas['PedidoPagamentoCadastroDTO']['required'] ?? [];
foreach ($reqPag as $campo) {
    hub_check($checks, "pagamento tem o campo obrigatório: {$campo}", array_key_exists($campo, $dto['pedidoPagamento'] ?? []));
}

// --- Regras de derivação ---
hub_check($checks, 'valorFinal do item = quantidade × valorUnitario', ($dto['pedidoItem'][0]['valorFinal'] ?? null) === 21.0);
hub_check($checks, 'DDD separado do telefone', ($dto['cliente']['ddd'] ?? '') === '18' && ($dto['cliente']['telefone'] ?? '') === '999991234');
hub_check($checks, 'tipoPessoa FÍSICA (0) para CPF de 11 dígitos', ($dto['cliente']['tipoPessoa'] ?? '') === '0');
hub_check($checks, 'enums de tipo/entrega/pagamento estão no domínio 0..2', in_array($dto['tipo'],['0','1','2'],true) && in_array($dto['pedidoEntrega']['tipo'],['0','1'],true) && in_array($dto['pedidoPagamento']['tipoPagamento'],['0','1'],true));
hub_check($checks, 'dataAprovacao no formato yyyy-MM-dd HH:mm:ss', (bool)preg_match('/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}$/', (string)($dto['dataAprovacao'] ?? '')));

// CNPJ (14 dígitos) => jurídica (1)
$dtoJ = PedidoMapper::tinyParaCadastroIntegradora(['cliente'=>['nome'=>'Empresa','documento'=>'11222333000181'],'itens'=>[],'valor_total'=>0]);
hub_check($checks, 'tipoPessoa JURÍDICA (1) para CNPJ de 14 dígitos', ($dtoJ['cliente']['tipoPessoa'] ?? '') === '1');

// --- Estrutural: o ApiController usa o mapper antes de enviar ---
$api = hub_read('app/Controllers/ApiController.php');
hub_check($checks, 'ApiController mapeia para o DTO antes de enviarPedido',
    str_contains($api, 'PedidoMapper::tinyParaCadastroIntegradora') &&
    strpos($api, 'PedidoMapper::tinyParaCadastroIntegradora') < strpos($api, 'enviarPedido($dtoPedido'));

hub_finish($checks);
