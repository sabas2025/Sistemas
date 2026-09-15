<?php
declare(strict_types=1);
require __DIR__.'/_helpers.php';

$checks=[];
$service=hub_read('app/Services/VsmEndpointService.php');
$security=hub_read('app/Services/VsmEndpointSecurityService.php');
$controller=hub_read('app/Controllers/VsmController.php');
$endpointView=hub_read('views/vsm_endpoints.php');
$campoView=hub_read('views/vsm_campos.php');
$testView=hub_read('views/vsm_testes.php');
$layout=hub_read('views/layout_bottom.php');
$openApiCi=hub_read('scripts/ci/vsm-openapi-check.php');

hub_check($checks,'Leituras VSM não executam seeds',
    !str_contains($service,'seedDefaults') && !str_contains($service,'INSERT IGNORE INTO vsm_'));

hub_check($checks,'Confirmação contratual nunca vem pré-marcada',
    !preg_match('/name="confirmar_contrato"[^>]*contract_verified/', $endpointView)
    && !preg_match('/name="confirmar_contrato"[^>]*contract_verified/', $campoView)
    && str_contains($endpointView,'A confirmação nunca é herdada'));

hub_check($checks,'OpenAPI local valida método e caminho e registra hash da definição',
    str_contains($service,'VsmOpenApiContractService::validateFile')
    && str_contains($service,'VsmOpenApiContractService::compareCatalog')
    && str_contains($service,'definition_sha256'));

hub_check($checks,'CI aceita catálogo vazio sem fabricar contrato e reprova OpenAPI presente inválido',
    str_contains($openApiCi,'Nenhum contrato VSM foi empacotado')
    && str_contains($openApiCi,'exit(0)')
    && str_contains($openApiCi,'exit($failed?1:0)'));

hub_check($checks,'Teste administrativo exige edição e força sonda segura',
    str_contains($controller,"PermissionService::require('configuracoes','editar')")
    && str_contains($controller,'VsmEndpointService::testEndpoint($id,$params,true)')
    && str_contains($service,'return self::executeTest($ep, $params, true)')
    && str_contains($endpointView,"PermissionService::can('configuracoes','editar')")
    && str_contains($testView,"PermissionService::can('configuracoes','editar')")
    && str_contains($testView,'nunca envia POST, PUT, PATCH ou DELETE real'));

hub_check($checks,'Placeholders são extraídos dinamicamente e exigidos na UI',
    str_contains($service,'public static function placeholders')
    && str_contains($testView,'VsmEndpointService::placeholders')
    && str_contains($testView,'required'));

hub_check($checks,'Logs VSM não persistem corpo e registram somente metadados e método executado',
    str_contains($service,"'body_sha256'")
    && str_contains($service,"'body_bytes'")
    && !str_contains($service,'sanitizeForStorage($body')
    && str_contains($service,'VsmEndpointSecurityService::redactUrlForLog')
    && str_contains($service,'$sendMethod')
    && str_contains($service,"'metodo_executado'=>\$sendMethod"));

hub_check($checks,'Fonte HTTPS de contrato rejeita segredos e é persistida como host mais hash',
    str_contains($service,'normalizeHttpsContractSource')
    && str_contains($service,"isset(\$parts['user'])")
    && str_contains($service,"isset(\$parts['query'])")
    && str_contains($service,"'/contract-ref-sha256-'.hash('sha256',\$source)"));

require_once hub_root().'/app/Services/VsmEndpointService.php';
$normalizer=new ReflectionMethod(VsmEndpointService::class,'normalizeHttpsContractSource');
$normalizer->setAccessible(true);
$maliciousSources=[
    'https://user:secret@example.com/contract-ref-sha256-'.str_repeat('a',64),
    'https://example.com/contract.json?token=secret',
    'https://example.com/contract.json#private-fragment',
];
$rejected=0;
foreach($maliciousSources as $source){
    try{$normalizer->invoke(null,$source);}catch(Throwable $e){$rejected++;}
}
$normalized=(string)$normalizer->invoke(null,'https://Example.com/contracts/customer/openapi.json');
hub_check($checks,'Matriz comportamental rejeita userinfo/query/fragment e oculta o caminho HTTPS',
    $rejected===count($maliciousSources)
    && preg_match('#^https://example\.com/contract-ref-sha256-[a-f0-9]{64}$#',$normalized)===1
    && !str_contains($normalized,'customer'));

hub_check($checks,'Allowlist VSM falha fechada em produção e IPv6 é fixado com colchetes',
    str_contains($security,'Allowlist security.vsm_allowed_hosts é obrigatória em produção')
    && str_contains($security,"\$ip='['.\$ip.']'"));

hub_check($checks,'Confirmações críticas são compatíveis com CSP',
    !preg_match('/\sonsubmit\s*=/', implode("\n",array_map('hub_read',[
        'views/validar_banco.php','views/enterprise_regression_tests.php','views/enterprise_core.php','views/mapa_banco.php'
    ])))
    && str_contains($layout,'ev.submitter')
    && str_contains($layout,"form.matches('[data-confirm]')"));

hub_finish($checks);
