<?php
declare(strict_types=1);
require __DIR__.'/_helpers.php';
require_once hub_root().'/app/Services/SensitiveDataService.php';

/**
 * Achado K-01 (2026-09-21): SensitiveDataService::mask() chamava maskScalar() com um ARRAY quando
 * a chave era sensível e o valor era aninhado (ex.: `endereco`, `itens`). `(string)$array` disparava
 * "Array to string conversion" no worker e mascarava tudo como a string literal "Array", perdendo a
 * estrutura. Medido durante o teste E2E de fluxos (worker_fila mascarando o payload do pedido).
 *
 * Este teste é COMPORTAMENTAL: captura E_WARNING e exercita o mascaramento real. Reprova sobre o
 * código antigo (que emitia o warning e devolvia string no lugar do array).
 */
$checks = [];

// Payload com CHAVE SENSÍVEL guardando um ARRAY aninhado, com folha sensível dentro.
$payload = [
  'numero'   => 'PED-1',
  'endereco' => ['bairro' => 'Centro', 'cep' => '01000000', 'logradouro' => 'Rua das Flores 123'],
  'itens'    => [['sku' => 'S1', 'cpf_cnpj' => '11222333000181']],
];

// Captura qualquer warning "Array to string conversion" durante o mascaramento.
$warnings = [];
set_error_handler(function($no, $str) use (&$warnings) { $warnings[] = $str; return true; });
$masked = SensitiveDataService::mask($payload);
restore_error_handler();

hub_check($checks, 'mascarar array sob chave sensível NÃO emite "Array to string conversion"',
  !array_filter($warnings, fn($w) => stripos($w, 'Array to string') !== false),
  implode(' | ', $warnings));

hub_check($checks, 'a estrutura do array aninhado é PRESERVADA (não vira string "Array")',
  is_array($masked['endereco'] ?? null) && is_array($masked['itens'] ?? null));

hub_check($checks, 'as folhas sob a chave sensível foram mascaradas (não em claro)',
  isset($masked['endereco']['logradouro'])
  && $masked['endereco']['logradouro'] !== 'Rua das Flores 123'
  && str_contains((string)$masked['endereco']['logradouro'], '*'));

hub_check($checks, 'campo não-sensível fora de chave sensível permanece legível',
  ($masked['numero'] ?? null) === 'PED-1');

// Guarda de regressão do código: mask() deve rotear array sensível pelo helper recursivo,
// não por maskScalar direto (âncora no código, sem depender do texto do comentário).
$src = hub_read('app/Services/SensitiveDataService.php');
hub_check($checks, 'mask() usa maskSensitiveValue() para chave sensível',
  $src !== '' && str_contains($src, 'maskSensitiveValue($v) : self::mask($v)'));
hub_check($checks, 'maskSensitiveValue trata array recursivamente',
  $src !== '' && preg_match('/function\s+maskSensitiveValue.*is_array\(\$v\)/s', $src) === 1);

hub_finish($checks);
