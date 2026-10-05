<?php
declare(strict_types=1);
require __DIR__.'/_helpers.php';
$checks = [];

/**
 * MenuActionTestService resolve a rota do menu nas DUAS fontes — correção do indicador que mente
 * da tela Integridade do Dashboard (auditoria de vídeo-demo, 2026-10-05).
 *
 * A tela mostrava 7 "erros": 6 itens de menu (Produtos, Estoque, XML/NF-e, Reconciliação,
 * Configurações, Central Técnica) marcados "rota ausente" e o botão órfão "Aplicar estrutura
 * fiscal". Causa: MenuActionTestService::run() resolvia a rota olhando SÓ o DashboardController,
 * mas a campanha Fase 3 moveu esses domínios para controllers dedicados despachados pelo
 * FastRouteDispatcherService::$dispatchGroups. Rotas que funcionam viravam falso "rota ausente" —
 * exatamente a classe F-01/F-02 ("indicador que mente é pior que indicador ausente"). O serviço
 * irmão DashboardIntegrityService já consultava as duas fontes; este ficou para trás.
 *
 * Esta checagem EXERCITA o serviço real (sem banco — run() só lê arquivos e OperationalRouteService)
 * e exige zero erro; e documenta a regressão provando que a fonte única (DashboardController)
 * ainda deixaria itens de menu sem "case".
 *
 * O QUE ELA NÃO PROVA: que as rotas realmente respondem em runtime (medido por HTTP) — só que o
 * checador de saúde não mente mais sobre elas.
 */
require_once hub_root().'/app/Services/OperationalRouteService.php';
require_once hub_root().'/app/Services/MenuActionTestService.php';
hub_check($checks, 'Classes do checador carregaram',
    class_exists('OperationalRouteService') && class_exists('MenuActionTestService'));

$itens = MenuActionTestService::run();
// I-20: prove que exercitou algo antes de afirmar o vazio de erros.
hub_check($checks, 'MenuActionTestService::run() devolveu itens', count($itens) > 0, count($itens).' item(ns)');

$erros = array_values(array_filter($itens, fn($i) => ($i['status'] ?? '') === 'erro'));
$rotulos = array_map(fn($i) => ($i['tipo'] ?? '').': '.($i['item'] ?? '').' ('.($i['mensagem'] ?? '').')', $erros);
hub_check($checks, 'Nenhum item do menu/botão fica "erro" no checador de saúde',
    $erros === [], $erros ? implode(' | ', $rotulos) : 'todos ok');

// O botão órfão "Aplicar estrutura fiscal" não pode voltar ao conjunto testado (feature removida).
$temBotaoOrfao = (bool)array_filter($itens, fn($i) => ($i['page'] ?? '') === 'atualizar-v43-fiscal-dashboard-install');
hub_check($checks, "Botão órfão 'Aplicar estrutura fiscal' não é mais testado", !$temBotaoOrfao);

// Regressão: a fonte ÚNICA (só DashboardController) deixaria itens de menu sem "case" — é o defeito.
// Se um dia alguém reduzir run() a uma fonte, este número cai a 0 e o teste perde sentido, então
// afirmamos que a fonte única É insuficiente hoje (há rota de menu fora do DashboardController).
$ctrl = hub_read('app/Controllers/DashboardController.php');
hub_check($checks, 'DashboardController foi lido', $ctrl !== '', strlen($ctrl).' bytes');
$semCaseNoDashboard = 0;
foreach (OperationalRouteService::menuRoutes() as $r) {
    $p = $r['page'];
    $caseDash = ($p === 'dashboard') || str_contains($ctrl, "case '$p'") || str_contains($ctrl, 'case "'.$p.'"');
    if (!$caseDash) $semCaseNoDashboard++;
}
hub_check($checks, 'Fonte única (DashboardController) seria insuficiente — a 2ª fonte é necessária',
    $semCaseNoDashboard > 0, $semCaseNoDashboard.' rota(s) de menu fora do switch do Dashboard');

// E a 2ª fonte precisa estar realmente ligada em run(): o arquivo cita o dispatcher.
$svc = hub_read('app/Services/MenuActionTestService.php');
hub_check($checks, 'run() consulta o FastRouteDispatcherService como 2ª fonte',
    $svc !== '' && str_contains($svc, 'FastRouteDispatcherService.php') && str_contains($svc, '$dispatcher'));

hub_finish($checks);
