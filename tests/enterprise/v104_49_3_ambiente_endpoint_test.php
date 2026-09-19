<?php
declare(strict_types=1);
require __DIR__.'/_helpers.php';

/**
 * Achado I-23 (2026-09-15): instalar com Ambiente=Produção deixava o Hub apontando para o host de
 * HOMOLOGAÇÃO da VSM, e nenhuma checagem acusava.
 *
 * `public/install.php` semeia `{VSM_URL}` => 'https://conectavenda.homolog.vsm.com.br' como
 * CONSTANTE, qualquer que seja o ambiente, e `IntegrationConfig::get()` repete o mesmo host como
 * fallback quando a coluna está vazia — então nem apagar o valor resolve. As checagens que já
 * existiam só exigiam URL não-vazia e bem formada. Medido contra uma instalação de produção real:
 * `configuracoes_integracao.ambiente = 'producao'` com `vsm_url` de homologação, e o go-live
 * respondia apto.
 *
 * O teste exercita os helpers REAIS por reflexão, com cfg sintética — sem banco, sem rede. A
 * asserção é sobre COMPORTAMENTO, não sobre o texto do comentário que explica a correção (armadilha
 * que já apareceu três vezes nesta linha).
 */
$checks=[];
require_once hub_root().'/app/Services/ProductionGoLiveService.php';
hub_check($checks,'ProductionGoLiveService foi carregado', class_exists('ProductionGoLiveService'));

$rc = new ReflectionClass('ProductionGoLiveService');
$temHelpers = $rc->hasMethod('hostDeHomologacao') && $rc->hasMethod('endpointsForaDoAmbiente');
hub_check($checks,'Os helpers de coerência de ambiente existem', $temHelpers);

if ($temHelpers) {
    $host = $rc->getMethod('hostDeHomologacao'); $host->setAccessible(true);
    $ends = $rc->getMethod('endpointsForaDoAmbiente'); $ends->setAccessible(true);

    // Rótulo do HOST decide. Nunca substring na URL inteira — é a armadilha do B-06.
    $casos = [
        ['https://conectavenda.homolog.vsm.com.br', true,  'rótulo homolog no host'],
        ['https://sandbox.vsm.com.br',              true,  'rótulo sandbox'],
        ['https://conectavenda.vsm.com.br',         false, 'host sem rótulo de teste'],
        ['https://vsm.com.br/homologacao/api',      false, 'homologacao apenas no CAMINHO'],
        ['https://homologvsm.com.br',               false, 'homolog grudado, não é rótulo'],
        ['',                                        false, 'URL vazia'],
    ];
    $erros=[];
    foreach ($casos as [$url,$esperado,$desc]) {
        if ($host->invoke(null,$url) !== $esperado) $erros[] = $desc;
    }
    hub_check($checks,'hostDeHomologacao decide por rótulo do host nos 6 casos',
        $erros===[], $erros===[] ? '' : 'errou em: '.implode(', ',$erros));

    $prodComHomolog = $ends->invoke(null, ['ambiente'=>'producao','vsm_url'=>'https://conectavenda.homolog.vsm.com.br']);
    hub_check($checks,'Produção apontando para homologação é incoerente',
        $prodComHomolog['coerente']===false && $prodComHomolog['suspeitos']!==[],
        json_encode($prodComHomolog['suspeitos'], JSON_UNESCAPED_UNICODE));

    $prodLimpo = $ends->invoke(null, ['ambiente'=>'producao','vsm_url'=>'https://conectavenda.vsm.com.br','tiny_v2_url'=>'https://api.tiny.com.br/api2']);
    hub_check($checks,'Produção com hosts de produção é coerente', $prodLimpo['coerente']===true);

    $homolog = $ends->invoke(null, ['ambiente'=>'homologacao','vsm_url'=>'https://conectavenda.homolog.vsm.com.br']);
    hub_check($checks,'Homologação com host de homologação NÃO é acusada', $homolog['coerente']===true);

    $tiny = $ends->invoke(null, ['ambiente'=>'producao','tiny_v3_url'=>'https://homolog.tiny.com.br/v3']);
    hub_check($checks,'A coerência cobre Tiny, não só VSM', $tiny['coerente']===false);
}

// A checagem precisa estar ligada ao go-live, senão o helper existe e ninguém o chama.
$servico = hub_read('app/Services/ProductionGoLiveService.php');
hub_check($checks,'O go-live publica a checagem de endpoints',
    $servico!=='' && str_contains($servico,"'Endpoints coerentes com o ambiente'")
    && str_contains($servico,'endpointsForaDoAmbiente($cfg)'));

hub_finish($checks);
