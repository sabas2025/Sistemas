<?php
declare(strict_types=1);
require __DIR__.'/_helpers.php';
$checks = [];

/**
 * Achado F6-06 (auditoria Fase 6, 2026-09-26) — o Hub não reconhecia o host de STAGE da VSM.
 *
 * O responsável informou os hosts reais da VSM:
 *   Stage (validação de integrações de parceiros): https://conectavenda.stage.vsm.com.br
 *   Produção:                                       https://conectavenda.vsm.com.br
 *
 * O vocabulário de "host de teste" do Hub tinha `homolog` e `staging`, mas NÃO `stage`. Efeito:
 *   1. VsmEnvironmentService::isHomologUrl() devolvia false para o host de Stage → o dashboard o
 *      classificava como PRODUÇÃO.
 *   2. ProductionGoLiveService (checagem I-23 "Endpoints coerentes com o ambiente") NÃO acusava
 *      Stage em produção — a mesma falha que o I-23 existia para pegar, para o rótulo `stage`.
 *
 * Correção: acrescentar o rótulo `stage` aos dois vocabulários, comparado por RÓTULO de DNS
 * (nunca substring — armadilha B-06). O host de PRODUÇÃO real (conectavenda.vsm.com.br) não tem
 * nenhum rótulo de teste e continua classificado como produção.
 *
 * Este teste é COMPORTAMENTAL: chama os métodos reais (o privado por reflexão) e reprova sobre o
 * código antigo, que devolvia false para o host de Stage.
 */

require_once hub_root().'/app/Services/VsmEnvironmentService.php';
require_once hub_root().'/app/Services/ProductionGoLiveService.php';

$stage = 'https://conectavenda.stage.vsm.com.br';
$prod  = 'https://conectavenda.vsm.com.br';
$homolog = 'https://conectavenda.homolog.vsm.com.br';

// --- 1) Classificação de ambiente (método público e puro) ---
hub_check($checks, 'isHomologUrl reconhece o host de STAGE da VSM como ambiente de teste',
    VsmEnvironmentService::isHomologUrl($stage) === true);
hub_check($checks, 'isHomologUrl continua reconhecendo o host de HOMOLOG antigo',
    VsmEnvironmentService::isHomologUrl($homolog) === true);
hub_check($checks, 'isHomologUrl NÃO trata o host de PRODUÇÃO como teste',
    VsmEnvironmentService::isHomologUrl($prod) === false);
hub_check($checks, 'normalizedEnv classifica o host de STAGE como homologacao',
    VsmEnvironmentService::normalizedEnv(['vsm_url'=>$stage, 'vsm_ambiente'=>'producao']) === 'homologacao');

// --- 2) Checagem de coerência do go-live (método privado e puro, via reflexão) ---
$m = new ReflectionMethod('ProductionGoLiveService', 'hostDeHomologacao');
$m->setAccessible(true);
hub_check($checks, 'go-live: hostDeHomologacao acusa o host de STAGE',
    $m->invoke(null, $stage) === true);
hub_check($checks, 'go-live: hostDeHomologacao NÃO acusa o host de PRODUÇÃO',
    $m->invoke(null, $prod) === false);

$e = new ReflectionMethod('ProductionGoLiveService', 'endpointsForaDoAmbiente');
$e->setAccessible(true);
$foraStage = $e->invoke(null, ['ambiente'=>'producao', 'vsm_url'=>$stage]);
hub_check($checks, 'go-live: produção apontando para STAGE é INCOERENTE (bloqueia)',
    isset($foraStage['coerente']) && $foraStage['coerente'] === false);
$foraProd = $e->invoke(null, ['ambiente'=>'producao', 'vsm_url'=>$prod]);
hub_check($checks, 'go-live: produção apontando para o host de PRODUÇÃO é coerente',
    isset($foraProd['coerente']) && $foraProd['coerente'] === true);

// --- 3) Âncora no fonte, para o rótulo não sumir num refactor futuro ---
$env = hub_read('app/Services/VsmEnvironmentService.php');
$golive = hub_read('app/Services/ProductionGoLiveService.php');
hub_check($checks, 'VsmEnvironmentService cita o rótulo stage', $env !== '' && str_contains($env, "'stage'"));
hub_check($checks, 'ProductionGoLiveService cita o rótulo stage', $golive !== '' && str_contains($golive, "'stage'"));

hub_finish($checks);
