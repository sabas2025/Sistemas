<?php
declare(strict_types=1);
require __DIR__ . '/_helpers.php';

/**
 * VSM duas APIs ativas (2026-09-20): consulta e gravação em bases URL distintas, sem
 * principal/secundária. Prova a seleção pura de base (VsmService::baseConsulta), a paridade
 * do schema (coluna nos dois caminhos) e o wiring de config/controller/serviço.
 */
$checks = [];
require_once hub_root() . '/app/Services/VsmService.php';

// ---- seleção pura de base (sem rede/banco) -------------------------------------------------
hub_check($checks, 'consulta vazia usa a base de GRAVAÇÃO (retrocompatível)',
  VsmService::baseConsulta('https://grava.vsm', '') === 'https://grava.vsm');
hub_check($checks, 'consulta preenchida usa a base de CONSULTA (as duas ativas)',
  VsmService::baseConsulta('https://grava.vsm', 'https://consulta.vsm') === 'https://consulta.vsm');
hub_check($checks, 'barra final é normalizada em ambas',
  VsmService::baseConsulta('https://grava.vsm/', 'https://consulta.vsm/') === 'https://consulta.vsm'
  && VsmService::baseConsulta('https://grava.vsm/', '') === 'https://grava.vsm');
hub_check($checks, 'espaços em branco não viram base falsa (cai na gravação)',
  VsmService::baseConsulta('https://grava.vsm', '   ') === 'https://grava.vsm');

// ---- wiring do serviço --------------------------------------------------------------------
$vsm = hub_read('app/Services/VsmService.php');
hub_check($checks, 'consultarEstoque roteia pela base de consulta', $vsm !== '' && str_contains($vsm, '$this->urlConsulta'));
hub_check($checks, 'request aceita base por operação', $vsm !== '' && str_contains($vsm, '?string $baseUrl=null'));
hub_check($checks, 'gravação (enviarPedido/enviarBaixaEstoque) NÃO injeta base → usa gravação',
  $vsm !== '' && str_contains($vsm, 'return $this->post($this->endpointPedido')
  && str_contains($vsm, 'return $this->post($this->endpointBaixa'));

// ---- paridade do schema (coluna nos DOIS caminhos) ----------------------------------------
$modulo = hub_read('database/modules/core.sql');
$consolidado = hub_read('database/install_final_current.sql');
hub_check($checks, 'coluna vsm_url_consulta no módulo core.sql', $modulo !== '' && str_contains($modulo, 'vsm_url_consulta'));
hub_check($checks, 'coluna vsm_url_consulta no consolidado', $consolidado !== '' && str_contains($consolidado, 'vsm_url_consulta'));
$mig = hub_read('app/Services/SchemaMigrationService.php');
$guard = hub_read('app/Services/DatabaseSchemaGuardService.php');
hub_check($checks, 'update path (SchemaMigrationService) adiciona a coluna', $mig !== '' && str_contains($mig, "'vsm_url_consulta'"));
hub_check($checks, 'schema guard cobre a coluna', $guard !== '' && str_contains($guard, "'vsm_url_consulta'"));

// ---- wiring config/controller -------------------------------------------------------------
$cfg = hub_read('app/Services/IntegrationConfig.php');
hub_check($checks, 'IntegrationConfig traz vsm_url_consulta no default', $cfg !== '' && str_contains($cfg, "'vsm_url_consulta'"));
$ctrl = hub_read('app/Controllers/DashboardController.php');
hub_check($checks, 'DashboardController salva vsm_url_consulta (UPDATE dinâmico compatível)',
  $ctrl !== '' && str_contains($ctrl, "'vsm_url_consulta' => trim(")
  && str_contains($ctrl, "['vsm_url_consulta','vsm_api_principal'"));
$view = hub_read('views/configuracoes.php');
hub_check($checks, 'tela nomeia as duas APIs (gravação e consulta)',
  $view !== '' && str_contains($view, 'API de gravação') && str_contains($view, 'API de consulta')
  && str_contains($view, 'name="vsm_url_consulta"'));

hub_finish($checks);
