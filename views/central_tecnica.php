<?php require __DIR__.'/layout_top.php'; ?>
<div class="row g-3">
  <div class="col-12">
    <div class="card-soft central-hero">
      <div class="d-flex flex-wrap justify-content-between align-items-start gap-3">
        <div>
          <h3 class="mb-1"><i class="bi bi-tools"></i> Central Técnica</h3>
          <p class="text-muted mb-0">Ferramentas essenciais para diagnóstico, segurança, filas, banco e integrações.</p>
        </div>
        <span class="badge bg-success-subtle text-success border">Operação limpa</span>
      </div>
    </div>
  </div>

  <?php
    $blocos = [
      'Integrações' => [
        ['integracoes','bi-diagram-3','Central de Integrações','Fluxos Tiny/VSM, ordem de envio e regras operacionais.'],
        ['tiny-ambientes','bi-diagram-3','Tiny V2 / V3','Configurações e homologação separadas por versão.'],
        ['tiny-validacao','bi-check2-circle','Validação Tiny','Testes de autenticação, produto, estoque e operação.'],
        ['vsm-endpoints','bi-plug','VSM Endpoints','Endpoint, método, timeout, retry e teste individual.'],
        ['vsm-campos','bi-arrow-left-right','VSM Campos','Mapeamento de campos entre VSM e Hub.'],
        ['vsm-saude','bi-heart-pulse','Saúde VSM','Status dos endpoints e tempo de resposta.'],
        ['vsm-logs','bi-journal-text','Logs VSM','Chamadas e retornos da integração VSM.'],
        ['vsm-testes','bi-play-circle','Testes VSM','Execução controlada de endpoints.'],
      ],
      'Estoque' => [
        ['estoque-dashboard','bi-box-arrow-down','Dashboard de Estoque','Indicadores, divergências e sincronização VSM → Tiny.'],
        ['estoque-config','bi-sliders','Configuração de Estoque','Fonte real, consulta programada, limite e modo manual/automático.'],
        ['estoque-consultas-vsm','bi-clock-history','Consultas VSM','Histórico das consultas programadas.'],
        ['estoque-consulta-vsm-resultados','bi-list-check','Resultado por SKU','Detalhe do saldo consultado na VSM.'],
        ['estoque-alertas','bi-bell','Alertas de Estoque','Falhas, divergências, produto inativo e SKU sem mapeamento.'],
        ['estoque-sku-historico','bi-graph-up','Histórico por SKU','Movimentações e auditoria por produto.'],
      ],
      'XML/NF-e' => [
        ['fiscal-dashboard','bi-receipt','Dashboard XML/NF-e','NF-e recebidas, pendentes, erros e concluídas.'],
        ['fiscal','bi-receipt-cutoff','Central XML/NF-e','XML, NF-e, reenvio e controle VSM → HUB → Tiny.'],
        ['fiscal-xml','bi-filetype-xml','Visualizador XML','Consulta, validação e download de XML.'],
        ['fiscal-timeline','bi-diagram-3','Timeline XML/NF-e','Etapas do retorno VSM e envio ao Tiny.'],
        ['fiscal-reconciliacao','bi-arrow-left-right','Reconciliação XML/NF-e','Divergências Tiny x Hub x VSM.'],
        ['fiscal-health','bi-heart-pulse','Saúde XML/NF-e','Fila fiscal, XML, banco e auditoria.'],
      ],
      'Pedidos e Produtos' => [
        ['pedido-ciclo-vida','bi-diagram-3','Ciclo do Pedido','Pedido Tiny, envio VSM, retorno XML e status no Tiny.'],
        ['pedidos-validacao-vsm','bi-clipboard-check','Validação de Pedidos','Pedidos Tiny retidos e validados antes da VSM.'],
        ['produtos-pendentes-integracao','bi-shield-exclamation','Produtos Pendentes','Produto novo VSM aguardando aprovação ou vínculo.'],
        ['categorias-mapeamento','bi-tags','Categorias','Mapeamento de categorias VSM ↔ Tiny.'],
        ['produto-novo-politica','bi-toggles','Política Produto/Pedido','Aprovação automática/manual e validação de pedido.'],
      ],
      'Saúde e Segurança' => [
        ['dashboard-integridade','bi-speedometer','Integridade do Dashboard','Confere telas, tabelas, botões e rotas principais.'],
        ['health-modulos','bi-hdd-network','Health Check por Módulo','Valida bancos modulares e tabelas críticas.'],
        ['producao-segura','bi-shield-lock','Produção Segura','Checklist de segurança antes de produção.'],
        ['enterprise-core','bi-building-gear','Enterprise Core','Migrações versionadas, idempotência, event store e quality gates.'],
        ['enterprise-observabilidade','bi-activity','Observabilidade Enterprise','Filas, DLQ, workers, eventos e métricas operacionais.'],
        ['llm-governance','bi-robot','Governança LLM','Prompts, anti-injection, custos e auditoria de IA.'],
        ['enterprise-regression-tests','bi-check2-square','Testes de Regressão','Smoke tests pós-update para banco, PWA, filas e Enterprise Core.'],
        ['design-system-enterprise','bi-stars','Design Futurista','Guia visual das telas, cards, ações, badges e UX enterprise.'],
        ['menu-testes','bi-menu-button-wide','Teste de Menu e Botões','Confere rotas, views, botões e CSRF.'],
        ['diagnostico','bi-activity','Diagnóstico','Ambiente, serviços e integrações.'],
      ],
      'Administração' => [
        ['usuarios','bi-people','Usuários e Permissões','Controle de acesso por perfil.'],
        ['configuracoes','bi-sliders','Configurações Gerais','Credenciais, ambiente e parâmetros gerais.'],
        ['validar-banco','bi-database-check','Validar Banco','Confere tabelas, colunas e integridade mínima.'],
        ['mapa-banco','bi-diagram-3','Mapa do Banco','Mostra módulos, tabelas, bancos e colunas ausentes.'],
        ['migracoes-seguras','bi-shield-lock','Migrações Seguras','Bloqueia rotas atualizar-vXX e orienta aplicação controlada de SQL versionado.'],
        ['backups','bi-database-down','Backups','Gerar, baixar e excluir backups.'],
        ['limpeza-retencao','bi-trash3','Limpeza e Retenção','Limpa logs, payloads, cache e filas antigas.'],
      ],
      'Auditoria e Logs' => [
        ['logs','bi-journal-text','Logs','Eventos técnicos e erros de integração.'],
        ['auditoria','bi-shield-check','Auditoria','Ações, status e Trace ID.'],
        ['notificacoes','bi-bell','Notificações','Alertas operacionais do Hub.'],
        ['metricas','bi-graph-up','Métricas','Indicadores operacionais.'],
        ['integration-events','bi-diagram-3','Eventos de Integração','Event store Tiny/VSM com idempotência e Trace ID.'],
      ],
    ];
  ?>

  <?php foreach($blocos as $titulo=>$links): ?>
    <div class="col-12 col-xl-6">
      <div class="card-soft h-100">
        <h5 class="mb-3"><?=e($titulo)?></h5>
        <div class="central-grid">
          <?php foreach($links as $l): ?>
            <a class="central-card" href="index.php?page=<?=e($l[0])?>">
              <i class="bi <?=e($l[1])?>"></i>
              <span><b><?=e($l[2])?></b><small><?=e($l[3])?></small></span>
            </a>
          <?php endforeach; ?>
        </div>
      </div>
    </div>
  <?php endforeach; ?>
</div>
<?php require __DIR__.'/layout_bottom.php'; ?>
