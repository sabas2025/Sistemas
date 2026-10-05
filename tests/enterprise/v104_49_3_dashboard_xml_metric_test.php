<?php
declare(strict_types=1);
require __DIR__.'/_helpers.php';
$checks = [];

/**
 * Cartão "XML processados" do Dashboard — achado D-01 (auditoria de vídeo-demo, 2026-10-05).
 *
 * `DashboardMetricsService::fiscalResumo['xml_processados']` contava
 * `status_xml IN ('validado','enviado_tiny','concluido')`. Mas o escritor real
 * (`PedidoCicloVidaService::receberRetornoVsm`) grava `status_xml='xml_validado'` no sucesso e
 * `enviarXmlParaTiny` grava `'enviado_tiny'`. O token `'validado'` (sem prefixo) NUNCA é gravado,
 * então toda NF-e validada e ainda não enviada ao Tiny sumia do cartão — em produção, não só na
 * demo. É o padrão "indicador que mente é pior que indicador ausente" (F-01/F-02).
 *
 * Esta checagem ancora o cartão na VERDADE do escritor: o status de sucesso que o ciclo de vida
 * grava precisa estar na lista que o Dashboard conta. Não cravamos o literal dos dois lados — se
 * alguém renomear o status no escritor, a asserção (1) acusa e força atualizar o cartão junto.
 *
 * O QUE ELA NÃO PROVA: que o número exibido está certo em runtime (isso é medido com banco no job
 * E2E) — só que o vocabulário de status casa entre quem escreve e quem conta.
 */
$ciclo = hub_read('app/Services/PedidoCicloVidaService.php');
$dash  = hub_read('app/Services/DashboardMetricsService.php');

// Lição I-20: asserção sobre conjunto vazio passa por engano. Prove que leu os dois arquivos.
hub_check($checks, 'PedidoCicloVidaService.php foi lido (não vazio)', $ciclo !== '');
hub_check($checks, 'DashboardMetricsService.php foi lido (não vazio)', $dash !== '');

// (fonte da verdade) O escritor do ciclo de vida grava 'xml_validado' como status de sucesso do XML.
hub_check($checks, "Escritor grava status_xml='xml_validado' no sucesso (receberRetornoVsm)",
    $ciclo !== '' && str_contains($ciclo, "'xml_validado'"));

// Isola a linha/expressão do cartão xml_processados para não medir o arquivo inteiro.
$linhaCartao = '';
foreach (explode("\n", $dash) as $l) {
    if (str_contains($l, "'xml_processados'") && str_contains($l, 'status_xml')) { $linhaCartao = $l; break; }
}
hub_check($checks, 'Expressão do cartão xml_processados encontrada', $linhaCartao !== '');

// (1) O cartão conta o status REAL de sucesso que o escritor produz.
hub_check($checks, "Cartão xml_processados conta 'xml_validado'",
    $linhaCartao !== '' && str_contains($linhaCartao, "'xml_validado'"));

// (2) Regressão: o token morto 'validado' (sem prefixo) não pode voltar — ele nunca é gravado.
//     'xml_validado' NÃO contém o literal "'validado'" (a aspa de abertura casa com '_', não com a aspa),
//     então esta asserção não tem falso positivo com o token correto.
hub_check($checks, "Cartão não usa o token morto 'validado' (nunca gravado)",
    $linhaCartao !== '' && !str_contains($linhaCartao, "'validado'"));

// (3) O caminho de envio ao Tiny ('enviado_tiny') também conta como processado.
hub_check($checks, "Cartão xml_processados conta 'enviado_tiny'",
    $linhaCartao !== '' && str_contains($linhaCartao, "'enviado_tiny'"));

hub_finish($checks);
