<?php require __DIR__.'/layout_top.php'; ?>
<div class="hero-sabas mb-4">
  <div class="signature">📘 Tutorial Operacional • Desenvolvido por Sabas</div>
  <h2>🚀 Tutorial do Hub de Integração Enterprise</h2>
  <p class="mb-0 mt-2">Entenda para que serve o sistema, quais módulos existem e quais rotas estão disponíveis.</p>
</div>

<div class="row g-3">
  <div class="col-lg-4"><div class="build-card h-100"><h4>🎯 Para que serve?</h4><p>O HUB é a ponte inteligente entre <b>Tiny ERP</b> e <b>VSM</b>. Ele recebe dados, valida, guarda cópias, audita, envia para o destino correto e permite reprocessar falhas.</p><div class="alert alert-primary mb-0"><b>Fluxo principal:</b><br>Tiny → Hub → VSM → Hub → Tiny</div></div></div>
  <div class="col-lg-4"><div class="build-card h-100"><h4>🛡️ Segurança</h4><ul class="mb-0"><li>Login e permissões</li><li>CSRF</li><li>Trace ID</li><li>Auditoria de status</li><li>Health check</li><li>Produção segura</li></ul></div></div>
  <div class="col-lg-4"><div class="build-card h-100"><h4>⚙️ Operação</h4><ul class="mb-0"><li>Pedidos</li><li>Produtos</li><li>Estoque</li><li>NF-e/XML</li><li>Filas</li><li>Webhooks</li></ul></div></div>
</div>

<div class="build-card mt-3">
  <h4>🔄 Ciclo do Pedido</h4>
  <div class="timeline-vsm">
    <div><span>1</span>📥 Recebido do Tiny</div>
    <div><span>2</span>🔎 Validado no Hub</div>
    <div><span>3</span>📤 Enviado para VSM</div>
    <div><span>4</span>📨 Retorno VSM com NF-e/XML</div>
    <div><span>5</span>✅ XML validado</div>
    <div><span>6</span>📦 Enviado para Tiny</div>
    <div><span>7</span>🏁 Concluído</div>
  </div>
</div>

<div class="build-card mt-3">
  <h4>🧭 Módulos do Sistema</h4>
  <div class="row g-2">
    <?php $mods = [
      '🏠 Dashboard'=>'Visão geral da operação, alertas e indicadores.',
      '📦 Pedidos'=>'Recebimento, validação e ciclo Tiny → VSM → Tiny.',
      '🧾 Produtos'=>'Produtos, pendências, aprovação manual/automática e categorias.',
      '📊 Estoque'=>'Baixas, divergências e reconciliação.',
      '🧾 XML / NF-e'=>'XML, chave NF-e, status fiscal e reenvio.',
      '🔗 Integrações'=>'Tiny, VSM, webhooks, endpoints, campos e testes.',
      '🧪 Central Técnica'=>'Homologação, health check, updates e produção segura.',
      'ℹ️ Sistema'=>'Sobre e tutorial operacional.'
    ]; foreach($mods as $m=>$d): ?>
    <div class="col-md-6"><div class="p-3 border rounded-3 h-100"><b><?=e($m)?></b><br><small><?=e($d)?></small></div></div>
    <?php endforeach; ?>
  </div>
</div>

<div class="build-card mt-3">
  <h4>🛣️ Rotas do Sistema</h4>
  <p class="text-muted">Lista gerada pela leitura dos controllers. Use estas rotas no formato abaixo quando precisar acessar uma tela específica.</p>
  <?php
  $routes = [
    ['grupo'=>'🔌 APIs Tiny / Webhooks','rota'=>'index.php?page=api/tiny/webhook/estoque','controller'=>'ApiTinyController.php'],
    ['grupo'=>'🔌 APIs Tiny / Webhooks','rota'=>'index.php?page=api/tiny/webhook/produto','controller'=>'ApiTinyController.php'],
    ['grupo'=>'🔌 APIs Tiny / Webhooks','rota'=>'index.php?page=api/tiny/webhook/nota-fiscal','controller'=>'ApiTinyController.php'],
    ['grupo'=>'🔌 APIs Tiny / Webhooks','rota'=>'index.php?page=api/tiny/webhook/situacao-pedido','controller'=>'ApiTinyController.php'],
    ['grupo'=>'🔌 APIs Tiny / Webhooks','rota'=>'index.php?page=api/tiny/webhook/pedido','controller'=>'ApiTinyController.php'],
    ['grupo'=>'🔌 APIs Tiny / Webhooks','rota'=>'index.php?page=api/webhook/tiny/evento','controller'=>'ApiTinyController.php'],
    ['grupo'=>'🔌 APIs VSM / Webhooks','rota'=>'index.php?page=api/webhook/vsm/pedido','controller'=>'ApiVsmWebhookController.php'],
    ['grupo'=>'🔌 APIs VSM / Webhooks','rota'=>'index.php?page=api/webhook/vsm/produto','controller'=>'ApiVsmWebhookController.php'],
    ['grupo'=>'🔌 APIs VSM / Webhooks','rota'=>'index.php?page=api/webhook/vsm/pedido-retorno','controller'=>'ApiVsmWebhookController.php'],
    ['grupo'=>'📦 Pedidos','rota'=>'index.php?page=pedidos','controller'=>'DashboardController.php'],
    ['grupo'=>'📦 Pedidos','rota'=>'index.php?page=pedido-detalhe','controller'=>'DashboardController.php'],
    ['grupo'=>'⏳ Filas','rota'=>'index.php?page=fila','controller'=>'DashboardController.php'],
    ['grupo'=>'⏳ Filas','rota'=>'index.php?page=fila-reprocessar','controller'=>'DashboardController.php'],
    ['grupo'=>'⏳ Filas','rota'=>'index.php?page=fila-criar-teste','controller'=>'DashboardController.php'],
    ['grupo'=>'🟨 Tiny','rota'=>'index.php?page=testar-tiny','controller'=>'DashboardController.php'],
    ['grupo'=>'🟦 VSM','rota'=>'index.php?page=testar-vsm','controller'=>'DashboardController.php'],
    ['grupo'=>'🧾 Produtos / Categorias','rota'=>'index.php?page=produtos','controller'=>'DashboardController.php'],
    ['grupo'=>'📊 Estoque','rota'=>'index.php?page=baixas-estoque','controller'=>'DashboardController.php'],
    ['grupo'=>'🟦 VSM','rota'=>'index.php?page=produtos-vsm','controller'=>'DashboardController.php'],
    ['grupo'=>'🧾 Produtos / Categorias','rota'=>'index.php?page=produtos-pendencias','controller'=>'DashboardController.php'],
    ['grupo'=>'🧾 Produtos / Categorias','rota'=>'index.php?page=produtos-pendentes-integracao','controller'=>'DashboardController.php'],
    ['grupo'=>'🧾 Produtos / Categorias','rota'=>'index.php?page=produto-pendente-integracao-comparar','controller'=>'DashboardController.php'],
    ['grupo'=>'🧾 Produtos / Categorias','rota'=>'index.php?page=produto-pendente-integracao-acao','controller'=>'DashboardController.php'],
    ['grupo'=>'🧾 Produtos / Categorias','rota'=>'index.php?page=categorias-mapeamento','controller'=>'DashboardController.php'],
    ['grupo'=>'🧾 Produtos / Categorias','rota'=>'index.php?page=categoria-mapeamento-salvar','controller'=>'DashboardController.php'],
    ['grupo'=>'🧾 Produtos / Categorias','rota'=>'index.php?page=produto-pendencia-acao','controller'=>'DashboardController.php'],
    ['grupo'=>'📊 Estoque','rota'=>'index.php?page=divergencia-estoque','controller'=>'DashboardController.php'],
    ['grupo'=>'📌 Outras rotas','rota'=>'index.php?page=divergencia-acao','controller'=>'DashboardController.php'],
    ['grupo'=>'🟨 Tiny','rota'=>'index.php?page=simular-baixa-tiny','controller'=>'DashboardController.php'],
    ['grupo'=>'🟦 VSM','rota'=>'index.php?page=simular-produto-vsm','controller'=>'DashboardController.php'],
    ['grupo'=>'🟦 VSM','rota'=>'index.php?page=simular-estoque-vsm','controller'=>'DashboardController.php'],
    ['grupo'=>'🟦 VSM','rota'=>'index.php?page=simular-status-vsm','controller'=>'DashboardController.php'],
    ['grupo'=>'📌 Outras rotas','rota'=>'index.php?page=salvar-fluxos','controller'=>'DashboardController.php'],
    ['grupo'=>'🏠 Operação / Integrações','rota'=>'index.php?page=integracoes','controller'=>'DashboardController.php'],
    ['grupo'=>'🟦 VSM','rota'=>'index.php?page=vsm-endpoints','controller'=>'VsmController.php'],
    ['grupo'=>'🟦 VSM','rota'=>'index.php?page=vsm-endpoint-salvar','controller'=>'VsmController.php'],
    ['grupo'=>'🟦 VSM','rota'=>'index.php?page=vsm-endpoint-testar','controller'=>'VsmController.php'],
    ['grupo'=>'🟦 VSM','rota'=>'index.php?page=vsm-campos','controller'=>'VsmController.php'],
    ['grupo'=>'🟦 VSM','rota'=>'index.php?page=vsm-campo-salvar','controller'=>'VsmController.php'],
    ['grupo'=>'🟦 VSM','rota'=>'index.php?page=vsm-saude','controller'=>'VsmController.php'],
    ['grupo'=>'🟦 VSM','rota'=>'index.php?page=vsm-logs','controller'=>'VsmController.php'],
    ['grupo'=>'🟦 VSM','rota'=>'index.php?page=vsm-testes','controller'=>'VsmController.php'],
    ['grupo'=>'🏠 Operação / Integrações','rota'=>'index.php?page=regras-sincronizacao','controller'=>'DashboardController.php'],
    ['grupo'=>'🏠 Operação / Integrações','rota'=>'index.php?page=orquestracao-integracoes','controller'=>'DashboardController.php'],
    ['grupo'=>'⚙️ Administração / Configurações','rota'=>'index.php?page=bancos-modulos','controller'=>'DashboardController.php'],
    ['grupo'=>'⚙️ Administração / Configurações','rota'=>'index.php?page=bancos-modulos-instalar','controller'=>'DashboardController.php'],
    ['grupo'=>'🏠 Operação / Integrações','rota'=>'index.php?page=central-tecnica','controller'=>'DashboardController.php'],
    ['grupo'=>'📌 Outras rotas','rota'=>'index.php?page=salvar-orquestracao-integracoes','controller'=>'DashboardController.php'],
    ['grupo'=>'🟨 Tiny','rota'=>'index.php?page=teste-real-tiny','controller'=>'DashboardController.php'],
    ['grupo'=>'🟨 Tiny','rota'=>'index.php?page=teste-real-tiny-executar','controller'=>'DashboardController.php'],
    ['grupo'=>'📌 Outras rotas','rota'=>'index.php?page=atualizador-seguro','controller'=>'DashboardController.php'],
    ['grupo'=>'📚 Auditoria / Logs / Métricas','rota'=>'index.php?page=auditoria-codigo','controller'=>'DashboardController.php'],
    ['grupo'=>'📌 Outras rotas','rota'=>'index.php?page=atualizador-seguro-executar','controller'=>'DashboardController.php'],
    ['grupo'=>'🟨 Tiny','rota'=>'index.php?page=oauth-v3-checklist','controller'=>'DashboardController.php'],
    ['grupo'=>'📌 Outras rotas','rota'=>'index.php?page=salvar-regras-sincronizacao','controller'=>'DashboardController.php'],
    ['grupo'=>'📚 Auditoria / Logs / Métricas','rota'=>'index.php?page=logs','controller'=>'DashboardController.php'],
    ['grupo'=>'📚 Auditoria / Logs / Métricas','rota'=>'index.php?page=notificacoes','controller'=>'DashboardController.php'],
    ['grupo'=>'ℹ️ Sistema','rota'=>'index.php?page=sobre','controller'=>'DashboardController.php'],
    ['grupo'=>'📌 Outras rotas','rota'=>'index.php?page=notificacao-lida','controller'=>'DashboardController.php'],
    ['grupo'=>'📚 Auditoria / Logs / Métricas','rota'=>'index.php?page=auditoria','controller'=>'DashboardController.php'],
    ['grupo'=>'📚 Auditoria / Logs / Métricas','rota'=>'index.php?page=auditoria-detalhe','controller'=>'DashboardController.php'],
    ['grupo'=>'🧪 Diagnóstico / Homologação','rota'=>'index.php?page=diagnostico','controller'=>'DashboardController.php'],
    ['grupo'=>'📌 Outras rotas','rota'=>'index.php?page=dashboard-integridade','controller'=>'DashboardController.php'],
    ['grupo'=>'📌 Outras rotas','rota'=>'index.php?page=dashboard-integridade-executar','controller'=>'DashboardController.php'],
    ['grupo'=>'🧪 Diagnóstico / Homologação','rota'=>'index.php?page=health-modulos','controller'=>'DashboardController.php'],
    ['grupo'=>'🧪 Diagnóstico / Homologação','rota'=>'index.php?page=menu-testes','controller'=>'DashboardController.php'],
    ['grupo'=>'🧾 XML / NF-e','rota'=>'index.php?page=fiscal-reenviar','controller'=>'DashboardController.php'],
    ['grupo'=>'🧾 XML / NF-e','rota'=>'index.php?page=fiscal','controller'=>'DashboardController.php'],
    ['grupo'=>'🟨 Tiny','rota'=>'index.php?page=tiny-webhooks','controller'=>'DashboardController.php'],
    ['grupo'=>'🟨 Tiny','rota'=>'index.php?page=tiny-v3-ficha','controller'=>'DashboardController.php'],
    ['grupo'=>'🟨 Tiny','rota'=>'index.php?page=tiny-v3-callback','controller'=>'DashboardController.php'],
    ['grupo'=>'📌 Outras rotas','rota'=>'index.php?page=ficha-tecnica-100','controller'=>'DashboardController.php'],
    ['grupo'=>'📌 Outras rotas','rota'=>'index.php?page=production-ready-v24','controller'=>'DashboardController.php'],
    ['grupo'=>'📌 Outras rotas','rota'=>'index.php?page=production-ready-v25','controller'=>'DashboardController.php'],
    ['grupo'=>'📌 Outras rotas','rota'=>'index.php?page=production-ready-v26','controller'=>'DashboardController.php'],
    ['grupo'=>'📌 Outras rotas','rota'=>'index.php?page=hosting-infinityfree','controller'=>'DashboardController.php'],
    ['grupo'=>'📚 Auditoria / Logs / Métricas','rota'=>'index.php?page=auditoria-hash-chain','controller'=>'DashboardController.php'],
    ['grupo'=>'🟦 VSM','rota'=>'index.php?page=vsm-ficha-tecnica','controller'=>'DashboardController.php'],
    ['grupo'=>'🟨 Tiny','rota'=>'index.php?page=tiny-v2-ficha-tecnica','controller'=>'DashboardController.php'],
    ['grupo'=>'⏳ Filas','rota'=>'index.php?page=fila-analytics-v24','controller'=>'DashboardController.php'],
    ['grupo'=>'📚 Auditoria / Logs / Métricas','rota'=>'index.php?page=auditoria-assinar-trace','controller'=>'DashboardController.php'],
    ['grupo'=>'📚 Auditoria / Logs / Métricas','rota'=>'index.php?page=auditoria-exportar-pdf','controller'=>'DashboardController.php'],
    ['grupo'=>'📚 Auditoria / Logs / Métricas','rota'=>'index.php?page=seguranca-auditoria','controller'=>'DashboardController.php'],
    ['grupo'=>'⚙️ Administração / Configurações','rota'=>'index.php?page=relatorio-prontidao-producao','controller'=>'DashboardController.php'],
    ['grupo'=>'📚 Auditoria / Logs / Métricas','rota'=>'index.php?page=auditoria-exportar-enterprise','controller'=>'DashboardController.php'],
    ['grupo'=>'🟨 Tiny','rota'=>'index.php?page=tiny-v3-token-salvar','controller'=>'DashboardController.php'],
    ['grupo'=>'🟨 Tiny','rota'=>'index.php?page=tiny-v3-token-renovar','controller'=>'DashboardController.php'],
    ['grupo'=>'🟨 Tiny','rota'=>'index.php?page=tiny-v3-token-revogar','controller'=>'DashboardController.php'],
    ['grupo'=>'🟨 Tiny','rota'=>'index.php?page=tiny-v3-testar','controller'=>'DashboardController.php'],
    ['grupo'=>'🟨 Tiny','rota'=>'index.php?page=tiny-v3-testar-modulo','controller'=>'DashboardController.php'],
    ['grupo'=>'🟨 Tiny','rota'=>'index.php?page=tiny-v3-endpoints-salvar','controller'=>'DashboardController.php'],
    ['grupo'=>'⏳ Filas','rota'=>'index.php?page=fila-morta','controller'=>'DashboardController.php'],
    ['grupo'=>'⏳ Filas','rota'=>'index.php?page=fila-morta-reprocessar','controller'=>'DashboardController.php'],
    ['grupo'=>'🧪 Diagnóstico / Homologação','rota'=>'index.php?page=laboratorio','controller'=>'DashboardController.php'],
    ['grupo'=>'🧪 Diagnóstico / Homologação','rota'=>'index.php?page=laboratorio-executar','controller'=>'DashboardController.php'],
    ['grupo'=>'🏠 Operação / Integrações','rota'=>'index.php?page=reconciliacao','controller'=>'DashboardController.php'],
    ['grupo'=>'📌 Outras rotas','rota'=>'index.php?page=reconciliacao-executar','controller'=>'DashboardController.php'],
    ['grupo'=>'📚 Auditoria / Logs / Métricas','rota'=>'index.php?page=metricas','controller'=>'DashboardController.php'],
    ['grupo'=>'🧪 Diagnóstico / Homologação','rota'=>'index.php?page=selftest','controller'=>'DashboardController.php'],
    ['grupo'=>'🧪 Diagnóstico / Homologação','rota'=>'index.php?page=selftest-executar','controller'=>'DashboardController.php'],
    ['grupo'=>'🧪 Diagnóstico / Homologação','rota'=>'index.php?page=homologacao-automatica','controller'=>'DashboardController.php'],
    ['grupo'=>'🧪 Diagnóstico / Homologação','rota'=>'index.php?page=homologacao-automatica-executar','controller'=>'DashboardController.php'],
    ['grupo'=>'🧪 Diagnóstico / Homologação','rota'=>'index.php?page=homologacao','controller'=>'DashboardController.php'],
    ['grupo'=>'🧪 Diagnóstico / Homologação','rota'=>'index.php?page=homologacao-acao','controller'=>'DashboardController.php'],
    ['grupo'=>'🧪 Diagnóstico / Homologação','rota'=>'index.php?page=homologacao-relatorio','controller'=>'DashboardController.php'],
    ['grupo'=>'🧪 Diagnóstico / Homologação','rota'=>'index.php?page=validar-banco','controller'=>'DashboardController.php'],
    ['grupo'=>'🧪 Diagnóstico / Homologação','rota'=>'index.php?page=relatorio-homologacao','controller'=>'DashboardController.php'],
    ['grupo'=>'⚙️ Administração / Configurações','rota'=>'index.php?page=usuarios','controller'=>'DashboardController.php'],
    ['grupo'=>'⚙️ Administração / Configurações','rota'=>'index.php?page=usuario-salvar','controller'=>'DashboardController.php'],
    ['grupo'=>'📌 Outras rotas','rota'=>'index.php?page=permissoes-salvar','controller'=>'DashboardController.php'],
    ['grupo'=>'⚙️ Administração / Configurações','rota'=>'index.php?page=trocar-senha','controller'=>'DashboardController.php'],
    ['grupo'=>'⚙️ Administração / Configurações','rota'=>'index.php?page=backup','controller'=>'DashboardController.php'],
    ['grupo'=>'⚙️ Administração / Configurações','rota'=>'index.php?page=backup-download','controller'=>'DashboardController.php'],
    ['grupo'=>'⚙️ Administração / Configurações','rota'=>'index.php?page=backup-excluir','controller'=>'DashboardController.php'],
    ['grupo'=>'⚙️ Administração / Configurações','rota'=>'index.php?page=backups','controller'=>'DashboardController.php'],
    ['grupo'=>'📚 Auditoria / Logs / Métricas','rota'=>'index.php?page=logs-exportar','controller'=>'DashboardController.php'],
    ['grupo'=>'⚙️ Administração / Configurações','rota'=>'index.php?page=configuracoes','controller'=>'DashboardController.php'],
    ['grupo'=>'⚙️ Administração / Configurações','rota'=>'index.php?page=salvar-configuracoes','controller'=>'DashboardController.php'],
    ['grupo'=>'🟨 Tiny','rota'=>'index.php?page=tiny-validacao','controller'=>'V50Controller.php'],
    ['grupo'=>'🟨 Tiny','rota'=>'index.php?page=tiny-validacao-executar','controller'=>'V50Controller.php'],
    ['grupo'=>'🟦 VSM','rota'=>'index.php?page=vsm-simulador','controller'=>'V50Controller.php'],
    ['grupo'=>'🟦 VSM','rota'=>'index.php?page=vsm-simulador-executar','controller'=>'V50Controller.php'],
    ['grupo'=>'⚙️ Administração / Configurações','rota'=>'index.php?page=producao-segura','controller'=>'V50Controller.php'],
    ['grupo'=>'⚙️ Administração / Configurações','rota'=>'index.php?page=producao-segura-executar','controller'=>'V50Controller.php'],
    ['grupo'=>'⚙️ Administração / Configurações','rota'=>'index.php?page=limpeza-retencao','controller'=>'V50Controller.php'],
    ['grupo'=>'⚙️ Administração / Configurações','rota'=>'index.php?page=limpeza-retencao-executar','controller'=>'V50Controller.php'],
    ['grupo'=>'🧾 Produtos / Categorias','rota'=>'index.php?page=produto-novo-politica','controller'=>'V51Controller.php'],
    ['grupo'=>'🧾 Produtos / Categorias','rota'=>'index.php?page=produto-novo-politica-salvar','controller'=>'V51Controller.php'],
    ['grupo'=>'📦 Pedidos','rota'=>'index.php?page=pedidos-validacao-vsm','controller'=>'V51Controller.php'],
    ['grupo'=>'📦 Pedidos','rota'=>'index.php?page=pedido-validacao-detalhe','controller'=>'V51Controller.php'],
    ['grupo'=>'📦 Pedidos','rota'=>'index.php?page=pedido-validacao-enfileirar','controller'=>'V51Controller.php'],
    ['grupo'=>'📦 Pedidos','rota'=>'index.php?page=pedido-ciclo-vida','controller'=>'V51Controller.php'],
    ['grupo'=>'📦 Pedidos','rota'=>'index.php?page=pedido-ciclo-detalhe','controller'=>'V51Controller.php'],
    ['grupo'=>'🟨 Tiny','rota'=>'index.php?page=pedido-ciclo-enviar-tiny','controller'=>'V51Controller.php'],
  ];
  $agrupadas = [];
  foreach ($routes as $r) { $agrupadas[$r['grupo']][] = $r; }
  foreach($agrupadas as $grupo=>$lista): ?>
    <h5 class="mt-3"><?=e($grupo)?></h5>
    <div class="table-responsive"><table class="table table-sm table-striped"><thead><tr><th>Rota</th><th>Controller</th></tr></thead><tbody>
    <?php foreach($lista as $r): ?>
      <tr><td><code><?=e($r['rota'])?></code></td><td><?=e($r['controller'])?></td></tr>
    <?php endforeach; ?>
    </tbody></table></div>
  <?php endforeach; ?>
</div>

<div class="build-card mt-3">
  <h4>✅ Checklist rápido</h4>
  <ol class="mb-0">
    <li>Rodar instalação/updates.</li>
    <li>Configurar Tiny V2/V3.</li>
    <li>Configurar VSM homologação/produção.</li>
    <li>Validar endpoints, webhooks e tokens.</li>
    <li>Testar ciclo completo de pedido.</li>
    <li>Ativar produção somente após Checklist Produção Segura.</li>
  </ol>
</div>

<?php require __DIR__.'/layout_bottom.php'; ?>
