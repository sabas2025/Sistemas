<?php
declare(strict_types=1);
require __DIR__.'/_helpers.php';
$checks = [];

/**
 * ErrorCatalog cataloga PEDIDO_STATUS_ERRO — melhoria de observabilidade (auditoria de
 * vídeo-demo, 2026-10-05).
 *
 * PedidoCicloVidaService emite codigo_erro='PEDIDO_STATUS_ERRO' quando uma etapa do ciclo de
 * vida do pedido falha (ver linha do Audit::event, abaixo). ErrorCatalog::explain() alimenta a
 * causa_provavel/acao_recomendada da tela de Auditoria pelo Trace ID. Sem o código no catálogo,
 * a tela forense mostrava o texto genérico "Erro não catalogado." para um dos erros mais úteis
 * de se rastrear — a etapa do pedido que falhou.
 *
 * Esta checagem ancora os DOIS lados: (a) o catálogo responde específico para o código, e
 * (b) o serviço continua emitindo o código. Se qualquer um mudar sem o outro, reprova.
 *
 * O QUE ELA NÃO PROVA: que o Audit::event grava de fato (medido em runtime contra banco), nem
 * que os demais códigos emitidos pelo produto estão catalogados (oportunidade futura registrada
 * no relatório desta melhoria).
 */

// (a) O catálogo responde específico. ErrorCatalog é classe folha (sem dependências); carregá-la
// isolada é seguro e dá uma asserção de runtime, não só de texto-fonte.
require_once hub_root().'/app/Services/ErrorCatalog.php';
hub_check($checks, 'Classe ErrorCatalog carregou', class_exists('ErrorCatalog'));

$generico = ErrorCatalog::explain('__CODIGO_INEXISTENTE_PARA_MEDIR_O_FALLBACK__');
$pedido   = ErrorCatalog::explain('PEDIDO_STATUS_ERRO');

// O fallback genérico é a régua: a entrada catalogada tem de DIFERIR dele.
hub_check($checks, 'Fallback genérico tem a forma esperada',
    ($generico['causa'] ?? '') !== '' && str_contains((string)($generico['causa'] ?? ''), 'não catalogado'),
    (string)($generico['causa'] ?? ''));

hub_check($checks, 'PEDIDO_STATUS_ERRO tem causa específica (não é o fallback genérico)',
    ($pedido['causa'] ?? '') !== '' && ($pedido['causa'] ?? '') !== ($generico['causa'] ?? ''),
    (string)($pedido['causa'] ?? ''));

hub_check($checks, 'PEDIDO_STATUS_ERRO tem ação recomendada específica (não é o fallback genérico)',
    ($pedido['acao'] ?? '') !== '' && ($pedido['acao'] ?? '') !== ($generico['acao'] ?? ''),
    (string)($pedido['acao'] ?? ''));

hub_check($checks, 'explain() devolve o código consultado em codigo',
    ($pedido['codigo'] ?? '') === 'PEDIDO_STATUS_ERRO');

hub_check($checks, 'PEDIDO_STATUS_ERRO tem gravidade declarada',
    in_array($pedido['gravidade'] ?? '', ['baixa','media','alta','critica'], true),
    (string)($pedido['gravidade'] ?? ''));

// (b) O serviço continua emitindo o código. Âncora no CÓDIGO (ternário do Audit::event), não em
// comentário — lição da "varredura que acusa a própria documentação".
$svc = hub_read('app/Services/PedidoCicloVidaService.php');
hub_check($checks, 'PedidoCicloVidaService foi lido', $svc !== '', strlen($svc).' bytes');
hub_check($checks, "PedidoCicloVidaService ainda emite 'PEDIDO_STATUS_ERRO'",
    $svc !== '' && str_contains($svc, "? 'PEDIDO_STATUS_ERRO'"));

hub_finish($checks);
