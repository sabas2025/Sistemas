# RELATÓRIO V104.3 — Funções do sistema, fluxo de pedido Tiny e correção dos fluxos ativos
Gerado em: 2026-06-25
## 1. Resumo da auditoria
- Arquivos PHP verificados: **288**.
- Métodos/funções encontrados por varredura: **998**.
- Classes/agrupamentos encontrados: **156**.
- Rotas mapeadas no `public/index.php`: **70**.
- Tabelas detectadas nos SQLs principais: **102**.
- Fluxos granulares em Integrações > Escolher fluxos ativos: **11**.
- Erro de sintaxe PHP após correção: **0**.
## 2. Correção aplicada em “Integrações > Escolher fluxos ativos”
### Problema encontrado
A página `views/orquestracao_integracoes.php` usava `onclick` inline nos botões. Como o HUB usa CSP forte com nonce, navegadores modernos podem bloquear JavaScript inline. Com isso, botões como **Testar regra deste fluxo**, **Ativar todos**, **Desativar todos** e **Modelo seguro recomendado** podiam não responder.
### Correção aplicada
- Removido `onclick` da tela de orquestração.
- O botão **Testar regra deste fluxo** agora envia `POST` diretamente para `index.php?page=testar-orquestracao-fluxo` usando formulário seguro com CSRF, sem depender de JavaScript inline.
- Os botões **Ativar todos**, **Desativar todos** e **Modelo seguro recomendado** agora usam `addEventListener` dentro de `<script nonce="...">`.
- A página de orquestração ficou compatível com CSP forte.
### Validação dos checkboxes
- Todos os fluxos do catálogo aparecem na tela.
- Todos os fluxos possuem coluna/chave em `database/install.sql`, `database/modules/core.sql` e `database/install_final_v104_2.sql`.
- A função de salvar usa `IntegrationOrchestratorService::keys()` e preserva macros não editáveis, evitando desligar configuração invisível por engano.
- `onclick` inline na tela de orquestração: **não encontrado**.
## 3. Fluxo de pedido quando gerado no Tiny
Este é o fluxo correto quando o pedido nasce no Tiny/Olist e precisa passar pelo HUB antes de chegar à VSM.
1. **Tiny gera ou atualiza o pedido** e envia webhook para o HUB pela rota de pedido Tiny.
2. **HUB recebe o webhook** em `ApiController::webhookTinyPedidoVsm()`. Antes de aceitar o conteúdo, valida segurança do webhook Tiny: secret, payload, tamanho, CNPJ autorizado quando configurado e Trace ID.
3. **HUB valida o pedido** com `PedidoTinyVsmValidationService::validar()`. A validação confere dados mínimos do cliente, documento, endereço, itens, SKU mapeado, quantidade, valor e regras de aprovação.
4. **HUB registra o pedido validado** em `pedidos_validacao` com status como `aprovado_para_vsm` ou bloqueado por erro/pendência. Também cria histórico em `pedidos_validacao_historico`.
5. **HUB cria o ciclo de vida do pedido** com `PedidoCicloVidaService::fromTinyPedido()`, gravando cópia do payload, status `recebido_tiny`, snapshots e vínculo com o pedido original do Tiny.
6. **Se a política exigir aprovação manual**, o pedido fica aguardando na tela de validação. O operador aprova em `pedido-validacao-detalhe` e aciona `PedidoTinyVsmValidationService::enfileirar()`.
7. **Se a política permitir envio automático**, o HUB enfileira diretamente o payload convertido para VSM com tipo `pedido_tiny_para_vsm`.
8. **Worker/processador de fila** pega o item e chama `VsmService::enviarPedido()`. O envio usa retry, timeout, auditoria, Trace ID e circuit breaker conforme configuração.
9. **Retorno da VSM** atualiza `pedidos_validacao` e `pedidos_hub` por `PedidoCicloVidaService::marcarEnviadoVsmPorTinyId()`. Se falhar, grava erro e mantém rastreabilidade.
10. **NF-e autorizada da VSM para Tiny**, quando chegar, passa por `PedidoCicloVidaService::receberRetornoVsm()`, valida XML/chave e só depois `PedidoCicloVidaService::enviarXmlParaTiny()` envia para o Tiny se o fluxo `nfe_vsm_enviar_tiny` estiver ativo.
### Observação operacional
O fluxo **Tiny → VSM pedido** está desligado por padrão no catálogo (`pedido_tiny_enviar_vsm = 0`) porque, em muitas operações, o fluxo seguro é Tiny gerar pedido/venda e o HUB enviar baixa/estoque para VSM. Se você realmente quer enviar pedido completo do Tiny para VSM, ative conscientemente o checkbox **Enviar pedidos originados no Tiny para a VSM** e valide a política de aprovação.
## 4. Checkboxes/fluxos existentes e função de cada um
| Fluxo | Direção | Função | Regra técnica | Risco/controle |
|---|---|---|---|---|
| `pedido_vsm_receber` | VSM → Hub | Receber pedidos da VSM: Importa pedidos da VSM para o Hub, mantendo idempotência por código externo. | `sync_receber_pedidos_vsm` | Se desligado, o Hub não receberá pedidos novos da VSM. |
| `pedido_vsm_enviar_tiny` | Hub → Tiny | Enviar pedidos da VSM para o Tiny: Cria/envia no Tiny os pedidos recebidos da VSM após validação de cliente, itens e pagamento. | `sync_enviar_pedido_tiny` | Pode duplicar pedido se não houver chave idempotente. |
| `pedido_tiny_enviar_vsm` | Tiny → Hub → VSM | Enviar pedidos originados no Tiny para a VSM: Espelha pedidos nascidos no Tiny para a VSM quando Tiny também for origem operacional. | `sync_tiny_enviar_pedido_vsm` | Normalmente deve ficar desligado quando a VSM é a origem principal do pedido. |
| `nfe_vsm_enviar_tiny` | VSM → Hub → Tiny | Enviar NF-e autorizada do VSM para a Tiny: Recebe a NF-e/XML autorizada pela VSM, valida no Hub e envia para a Tiny vinculando ao pedido correto. | `sync_vsm_enviar_nota_tiny` | Nunca enviar NF-e sem autorização, XML inválido ou sem vínculo de pedido para a Tiny. |
| `nfe_tiny_enviar_vsm` | Tiny → Hub → VSM | Enviar NF-e autorizada do Tiny para a VSM: Envia chave, XML/PDF e status fiscal quando a nota estiver autorizada. | `sync_tiny_enviar_nota_vsm` | Nunca enviar NF-e rejeitada/cancelada como se estivesse autorizada. |
| `estoque_tiny_enviar_vsm` | Tiny → Hub → VSM | Tiny atualiza estoque na VSM: Propaga saldo alterado no Tiny para a VSM com fila, retry e reconciliação. | `sync_tiny_enviar_estoque_vsm` | Evitar loop de estoque quando VSM também envia estoque para Tiny. |
| `estoque_vsm_enviar_tiny` | VSM → Hub → Tiny | VSM atualiza estoque no Tiny: Atualiza saldo no Tiny quando o estoque oficial vier da VSM. | `sync_vsm_enviar_estoque_tiny` | Definir fonte mestre para evitar ida e volta infinita. |
| `produto_status_tiny_enviar_vsm` | Tiny → Hub → VSM | Tiny envia status ativo/inativo para VSM: Atualiza na VSM se o produto ficou ativo ou inativo no Tiny. | `sync_tiny_status_produto_vsm` | Não criar produto novo automaticamente se SKU não existir. |
| `produto_status_vsm_enviar_tiny` | VSM → Hub → Tiny | VSM envia status ativo/inativo para Tiny: Atualiza no Tiny se o produto ficou ativo ou inativo na VSM. | `sync_vsm_status_produto_tiny` | Inativação deve respeitar pedido pendente e estoque positivo. |
| `produto_novo_vsm_bloquear` | VSM → Hub | Bloquear produto novo vindo da VSM: Produto sem mapeamento vira pendência manual em vez de ser criado no Tiny. | `sync_bloquear_produto_novo_vsm` | Protege contra cadastro incompleto, EAN errado, NCM ausente e duplicidade. |
| `produto_novo_tiny_bloquear` | Tiny → Hub | Bloquear produto novo vindo do Tiny para VSM: Produto sem SKU mapeado não é criado automaticamente na VSM. | `sync_tiny_bloquear_produto_novo_vsm` | Protege a VSM contra produto não homologado. |

## 5. Travamentos obrigatórios e função de cada checkbox
- `sync_exigir_mapeamento_sku` — Exige SKU mapeado antes de qualquer envio Tiny/VSM. Evita criação ou baixa em produto errado.
- `sync_exigir_nfe_autorizada` — Bloqueia envio de NF-e sem autorização/validação.
- `sync_bloquear_produto_novo_vsm` — Bloqueia produto novo vindo da VSM para não criar cadastro automaticamente no Tiny.
- `sync_tiny_bloquear_produto_novo_vsm` — Bloqueia produto novo vindo do Tiny para não criar cadastro automaticamente na VSM.
- `sync_permitir_produto_novo_vsm_manual` — Permite receber produto novo como pendência manual em vez de descartar.
- `sync_aprovacao_manual_produto_novo_vsm` — Exige aprovação humana para produto novo.
- `sync_exigir_categoria_mapeada_vsm` — Exige vínculo de categoria VSM ↔ Tiny antes de integrar produto.
- `sync_permitir_atualizar_produto_existente_vsm` — Permite atualizar cadastro de produto que já está mapeado.
- `sync_permitir_estoque_vsm_tiny` — Permite estoque VSM → Tiny somente para SKU aceito.
- `sync_permitir_status_vsm_tiny` — Permite status ativo/inativo VSM → Tiny somente para SKU aceito.

## 6. Funções principais do sistema por módulo
### Login e 2FA
Autentica usuário, aplica CSRF, rate limit, sessão segura, troca obrigatória de senha e QR Code TOTP para administradores.
### Dashboard Executivo
Mostra saúde geral do HUB, status Tiny/VSM, filas, alertas, auditoria e indicadores.
### Central de Integrações
Centraliza configuração Tiny V2/V3, VSM, webhooks, fluxos ativos e regras operacionais.
### Orquestração de fluxos
Permite ligar/desligar fluxos específicos: pedidos, NF-e, estoque, produtos e status.
### Tiny V2/V3
Executa chamadas ao Tiny, homologação, token/OAuth, retry, circuit breaker e auditoria.
### VSM
Executa chamadas à VSM, valida webhooks, HMAC/secret, anti-replay, retry, timeout e circuit breaker.
### Pedidos
Valida pedido Tiny/VSM, grava ciclo de vida, snapshots, histórico e fila de envio.
### Fiscal/NF-e/XML
Recebe XML/NF-e, valida chave/XML, registra hash, envia NF-e autorizada para Tiny quando o fluxo está ativo.
### Estoque
Processa baixa, consulta programada, divergências, reconciliação e histórico de movimentos.
### Produtos
Mapeia SKU Tiny/VSM, bloqueia produto novo inseguro, controla status ativo/inativo e aprovação manual.
### Backups
Gera ZIP/SQL, assinatura HMAC, validação de restore e score de confiança.
### Segurança/SOC
Eventos, IPs bloqueados, WAF painel, rate limit, FIM, hash chain, auditoria diária e checklist de produção.
### Banco/migrações
Valida tabelas/colunas por módulo, aplica migrações seguras e bloqueia rotas antigas sem controle.
### Workers
Processam filas fora de `/public`, impedindo execução por navegador.
### PWA
Manifest, service worker e status de instalação/offline.
### Usuários/permissões
Gerencia perfis, permissões, troca de senha, sessão e auditoria de ações.

## 7. Inventário técnico completo de métodos/funções
Abaixo está o inventário de todos os métodos/funções PHP encontrados. Para uso em planilha, também foi gerado `docs/INVENTARIO-FUNCOES-V104.3.csv`.
### `app/Controllers/ApiController.php` — `ApiController`
- Linha 3: `private configIntegracao()` — Função auxiliar do domínio de controle de telas/rotas; usada para apoiar regras internas da classe ApiController.
- Linha 11: `private validarSegurancaTiny(string $raw, array $payload)` — Valida dados/regras antes de concluir operação de controle de telas/rotas.
- Linha 16: `private registrarEventoIdempotente(string $origem, string $referencia, string $tipoEvento, string $raw, array $payload)` — Função auxiliar do domínio de controle de telas/rotas; usada para apoiar regras internas da classe ApiController.
- Linha 39: `public webhookVsmPedido()` — Recebe, valida e registra webhook relacionado a controle de telas/rotas.
- Linha 102: `public webhookVsmProduto()` — Recebe, valida e registra webhook relacionado a controle de telas/rotas.
- Linha 200: `public webhookTinyEvento()` — Recebe, valida e registra webhook relacionado a controle de telas/rotas.
- Linha 254: `public webhookTinyPedidoVsm()` — Recebe, valida e registra webhook relacionado a controle de telas/rotas.
- Linha 286: `public webhookVsmRetornoPedido()` — Recebe, valida e registra webhook relacionado a controle de telas/rotas.
- Linha 328: `public webhookVsmEstoque()` — Recebe, valida e registra webhook relacionado a controle de telas/rotas.
- Linha 359: `private responderTiny(bool $success, array $data=[], int $httpCode=200)` — Função auxiliar do domínio de controle de telas/rotas; usada para apoiar regras internas da classe ApiController.
- Linha 364: `public webhookTinyEstoque()` — Recebe, valida e registra webhook relacionado a controle de telas/rotas.
- Linha 416: `public webhookTinyProduto()` — Recebe, valida e registra webhook relacionado a controle de telas/rotas.
- Linha 436: `public webhookTinyNotaFiscal()` — Recebe, valida e registra webhook relacionado a controle de telas/rotas.
- Linha 457: `public webhookTinySituacaoPedido()` — Recebe, valida e registra webhook relacionado a controle de telas/rotas.
- Linha 480: `public processarFila()` — Função auxiliar do domínio de controle de telas/rotas; usada para apoiar regras internas da classe ApiController.
- Linha 719: `public notificacoesRecentes()` — Função auxiliar do domínio de controle de telas/rotas; usada para apoiar regras internas da classe ApiController.
- Linha 726: `public marcarNotificacaoLidaApi()` — Função auxiliar do domínio de controle de telas/rotas; usada para apoiar regras internas da classe ApiController.
- Linha 736: `public statusJson()` — Função auxiliar do domínio de controle de telas/rotas; usada para apoiar regras internas da classe ApiController.

### `app/Controllers/ApiTinyController.php` — `ApiTinyController`
- Linha 8: `public dispatch(string $page)` — Recebe a rota solicitada e direciona para a ação correta do controller.

### `app/Controllers/ApiVsmWebhookController.php` — `ApiVsmWebhookController`
- Linha 7: `public dispatch(string $page)` — Recebe a rota solicitada e direciona para a ação correta do controller.

### `app/Controllers/AuditoriaController.php` — `AuditoriaController`
- Linha 8: `public static routes()` — Função auxiliar do domínio de auditoria; usada para apoiar regras internas da classe AuditoriaController.

### `app/Controllers/BackupController.php` — `BackupController`
- Linha 3: `public dispatch(string $page)` — Recebe a rota solicitada e direciona para a ação correta do controller.
- Linha 14: `public static routes()` — Função auxiliar do domínio de backups; usada para apoiar regras internas da classe BackupController.
- Linha 15: `public index()` — Função auxiliar do domínio de backups; usada para apoiar regras internas da classe BackupController.
- Linha 22: `private gerar()` — Gera arquivo, relatório, token, código ou saída operacional.
- Linha 29: `private download()` — Função auxiliar do domínio de backups; usada para apoiar regras internas da classe BackupController.
- Linha 39: `private excluir()` — Remove ou inativa um registro.
- Linha 46: `private importar()` — Função auxiliar do domínio de backups; usada para apoiar regras internas da classe BackupController.
- Linha 52: `private restaurar()` — Função auxiliar do domínio de backups; usada para apoiar regras internas da classe BackupController.

### `app/Controllers/BaseModuleController.php` — `BaseModuleController`
- Linha 7: `protected view(string $view, array $vars = [])` — Renderiza conteúdo visual do módulo de controle de telas/rotas.
- Linha 22: `protected defaultViewData(array $extra = [])` — Renderiza conteúdo visual do módulo de controle de telas/rotas.

### `app/Controllers/CentralHomologacaoController.php` — `CentralHomologacaoController`
- Linha 3: `public dispatch(string $page)` — Recebe a rota solicitada e direciona para a ação correta do controller.
- Linha 10: `public static routes()` — Função auxiliar do domínio de homologação; usada para apoiar regras internas da classe CentralHomologacaoController.
- Linha 11: `public index()` — Função auxiliar do domínio de homologação; usada para apoiar regras internas da classe CentralHomologacaoController.

### `app/Controllers/CentralTecnicaController.php` — `CentralTecnicaController`
- Linha 8: `public static routes()` — Função auxiliar do domínio de controle de telas/rotas; usada para apoiar regras internas da classe CentralTecnicaController.

### `app/Controllers/ConfiguracaoController.php` — `ConfiguracaoController`
- Linha 8: `public static routes()` — Função auxiliar do domínio de controle de telas/rotas; usada para apoiar regras internas da classe ConfiguracaoController.

### `app/Controllers/DashboardController.php` — `DashboardController`
- Linha 4: `public __construct()` — Inicializa dependências internas de DashboardController.
- Linha 6: `private appBaseUrl()` — Função auxiliar do domínio de dashboard; usada para apoiar regras internas da classe DashboardController.
- Linha 15: `private absolutePublicBaseUrl()` — Função auxiliar do domínio de dashboard; usada para apoiar regras internas da classe DashboardController.
- Linha 25: `private normalizeTinyV3RedirectUri(string $uri = '')` — Padroniza formato de dados usado por dashboard.
- Linha 42: `public dispatch(string $page)` — Recebe a rota solicitada e direciona para a ação correta do controller.
- Linha 228: `public index()` — Função auxiliar do domínio de dashboard; usada para apoiar regras internas da classe DashboardController.
- Linha 249: `private safeIdentifier(string $identifier)` — Função auxiliar do domínio de dashboard; usada para apoiar regras internas da classe DashboardController.
- Linha 256: `private safeColumn(string $table, string $column)` — Função auxiliar do domínio de dashboard; usada para apoiar regras internas da classe DashboardController.
- Linha 268: `private safeSqlFragment(string $table, string $fragment)` — Função auxiliar do domínio de dashboard; usada para apoiar regras internas da classe DashboardController.
- Linha 289: `private safeOrderBy(string $table, string $orderBy)` — Função auxiliar do domínio de dashboard; usada para apoiar regras internas da classe DashboardController.
- Linha 302: `private count(string $table, string $where='1=1')` — Função auxiliar do domínio de dashboard; usada para apoiar regras internas da classe DashboardController.
- Linha 310: `private safeCount(string $table, string $where='1=1')` — Função auxiliar do domínio de dashboard; usada para apoiar regras internas da classe DashboardController.
- Linha 314: `private statusDot(string $status)` — Função auxiliar do domínio de dashboard; usada para apoiar regras internas da classe DashboardController.
- Linha 318: `private workerCards()` — Função auxiliar do domínio de dashboard; usada para apoiar regras internas da classe DashboardController.
- Linha 339: `private operationalSummary(array $cfgInt=[])` — Função auxiliar do domínio de dashboard; usada para apoiar regras internas da classe DashboardController.
- Linha 369: `private tableRows(string $table, string $orderBy='id DESC', int $limit=10, string $where='1=1')` — Função auxiliar do domínio de dashboard; usada para apoiar regras internas da classe DashboardController.
- Linha 378: `private tableOne(string $table, string $orderBy='id DESC', string $where='1=1')` — Função auxiliar do domínio de dashboard; usada para apoiar regras internas da classe DashboardController.
- Linha 383: `private dashboard()` — Função auxiliar do domínio de dashboard; usada para apoiar regras internas da classe DashboardController.
- Linha 520: `private alertasOperacionais()` — Função auxiliar do domínio de dashboard; usada para apoiar regras internas da classe DashboardController.
- Linha 528: `private centroOperacoes()` — Função auxiliar do domínio de dashboard; usada para apoiar regras internas da classe DashboardController.
- Linha 558: `private dashboardIntegridade()` — Função auxiliar do domínio de dashboard; usada para apoiar regras internas da classe DashboardController.
- Linha 566: `private pedidos()` — Função auxiliar do domínio de dashboard; usada para apoiar regras internas da classe DashboardController.
- Linha 580: `private pedidoDetalhe()` — Função auxiliar do domínio de dashboard; usada para apoiar regras internas da classe DashboardController.
- Linha 597: `private fila()` — Função auxiliar do domínio de dashboard; usada para apoiar regras internas da classe DashboardController.
- Linha 608: `private filaReprocessar()` — Função auxiliar do domínio de dashboard; usada para apoiar regras internas da classe DashboardController.
- Linha 616: `private filaCriarTeste()` — Executa teste/diagnóstico controlado do módulo de dashboard.
- Linha 647: `private produtos()` — Função auxiliar do domínio de dashboard; usada para apoiar regras internas da classe DashboardController.
- Linha 656: `private auditoriaCodigo()` — Registra ou consulta auditoria/histórico de dashboard.
- Linha 663: `private testeRealTiny()` — Executa teste/diagnóstico controlado do módulo de dashboard.
- Linha 672: `private testeRealTinyExecutar()` — Executa teste/diagnóstico controlado do módulo de dashboard.
- Linha 704: `private logs()` — Função auxiliar do domínio de dashboard; usada para apoiar regras internas da classe DashboardController.
- Linha 716: `private auditoria()` — Registra ou consulta auditoria/histórico de dashboard.
- Linha 731: `private auditoriaDetalhe()` — Registra ou consulta auditoria/histórico de dashboard.
- Linha 742: `private tinyWebhooks()` — Recebe, valida e registra webhook relacionado a dashboard.
- Linha 757: `private notificacoes()` — Função auxiliar do domínio de dashboard; usada para apoiar regras internas da classe DashboardController.
- Linha 771: `private marcarNotificacaoLida()` — Função auxiliar do domínio de dashboard; usada para apoiar regras internas da classe DashboardController.
- Linha 783: `private diagnostico()` — Função auxiliar do domínio de dashboard; usada para apoiar regras internas da classe DashboardController.
- Linha 791: `private configuracoes()` — Função auxiliar do domínio de dashboard; usada para apoiar regras internas da classe DashboardController.
- Linha 810: `private salvarConfiguracoes()` — Salva/atualiza dados do módulo de dashboard.
- Linha 903: `private testarTiny()` — Executa teste/diagnóstico controlado do módulo de dashboard.
- Linha 920: `private testarVsm()` — Executa teste/diagnóstico controlado do módulo de dashboard.
- Linha 947: `private tinyV3Callback()` — Função auxiliar do domínio de dashboard; usada para apoiar regras internas da classe DashboardController.
- Linha 1003: `private tinyV3Ficha()` — Função auxiliar do domínio de dashboard; usada para apoiar regras internas da classe DashboardController.
- Linha 1028: `private tinyV3TokenSalvar()` — Gere/valida/consulta token seguro no módulo de dashboard.
- Linha 1038: `private tinyV3TokenRenovar()` — Gere/valida/consulta token seguro no módulo de dashboard.
- Linha 1046: `private tinyV3TokenRevogar()` — Gere/valida/consulta token seguro no módulo de dashboard.
- Linha 1067: `private tinyV3Testar()` — Função auxiliar do domínio de dashboard; usada para apoiar regras internas da classe DashboardController.
- Linha 1078: `private tinyV3TestarModulo()` — Função auxiliar do domínio de dashboard; usada para apoiar regras internas da classe DashboardController.
- Linha 1099: `private tinyV3EndpointsSalvar()` — Função auxiliar do domínio de dashboard; usada para apoiar regras internas da classe DashboardController.
- Linha 1124: `private atualizarV17TinyV3()` — Atualiza cadastro, status ou estrutura no módulo de dashboard.
- Linha 1146: `private atualizarV18TinyV3Final()` — Atualiza cadastro, status ou estrutura no módulo de dashboard.
- Linha 1169: `private atualizarV19TinyV3Operacional()` — Atualiza cadastro, status ou estrutura no módulo de dashboard.
- Linha 1193: `private atualizarV20TinyV3Seguranca()` — Atualiza cadastro, status ou estrutura no módulo de dashboard.
- Linha 1217: `private tinyV3OperationalReady(array $dados = [])` — Função auxiliar do domínio de dashboard; usada para apoiar regras internas da classe DashboardController.
- Linha 1246: `private atualizarV21TinyV3Final()` — Atualiza cadastro, status ou estrutura no módulo de dashboard.
- Linha 1280: `private usuarios()` — Função auxiliar do domínio de dashboard; usada para apoiar regras internas da classe DashboardController.
- Linha 1288: `private usuarioSalvar()` — Função auxiliar do domínio de dashboard; usada para apoiar regras internas da classe DashboardController.
- Linha 1338: `private usuarioExcluir()` — Função auxiliar do domínio de dashboard; usada para apoiar regras internas da classe DashboardController.
- Linha 1364: `private permissoesSalvar()` — Função auxiliar do domínio de dashboard; usada para apoiar regras internas da classe DashboardController.
- Linha 1391: `private trocarSenha()` — Função auxiliar do domínio de dashboard; usada para apoiar regras internas da classe DashboardController.
- Linha 1404: `private backup()` — Função auxiliar do domínio de dashboard; usada para apoiar regras internas da classe DashboardController.
- Linha 1413: `private backups()` — Função auxiliar do domínio de dashboard; usada para apoiar regras internas da classe DashboardController.
- Linha 1421: `private backupDownload()` — Função auxiliar do domínio de dashboard; usada para apoiar regras internas da classe DashboardController.
- Linha 1437: `private backupExcluir()` — Função auxiliar do domínio de dashboard; usada para apoiar regras internas da classe DashboardController.
- Linha 1454: `private backupImportar()` — Função auxiliar do domínio de dashboard; usada para apoiar regras internas da classe DashboardController.
- Linha 1467: `private backupRestaurar()` — Função auxiliar do domínio de dashboard; usada para apoiar regras internas da classe DashboardController.
- Linha 1482: `private logsExportar()` — Função auxiliar do domínio de dashboard; usada para apoiar regras internas da classe DashboardController.
- Linha 1495: `private baixasEstoque()` — Função auxiliar do domínio de dashboard; usada para apoiar regras internas da classe DashboardController.
- Linha 1509: `private produtosVsm()` — Função auxiliar do domínio de dashboard; usada para apoiar regras internas da classe DashboardController.
- Linha 1525: `private produtosPendencias()` — Função auxiliar do domínio de dashboard; usada para apoiar regras internas da classe DashboardController.
- Linha 1538: `private produtoPendenciaAcao()` — Função auxiliar do domínio de dashboard; usada para apoiar regras internas da classe DashboardController.
- Linha 1573: `private divergenciaEstoque()` — Função auxiliar do domínio de dashboard; usada para apoiar regras internas da classe DashboardController.
- Linha 1586: `private divergenciaAcao()` — Função auxiliar do domínio de dashboard; usada para apoiar regras internas da classe DashboardController.
- Linha 1606: `private simularBaixaTiny()` — Função auxiliar do domínio de dashboard; usada para apoiar regras internas da classe DashboardController.
- Linha 1632: `private simularProdutoVsm()` — Função auxiliar do domínio de dashboard; usada para apoiar regras internas da classe DashboardController.
- Linha 1665: `private simularEstoqueVsm()` — Função auxiliar do domínio de dashboard; usada para apoiar regras internas da classe DashboardController.
- Linha 1689: `private simularStatusVsm()` — Função auxiliar do domínio de dashboard; usada para apoiar regras internas da classe DashboardController.
- Linha 1714: `private salvarFluxos()` — Salva/atualiza dados do módulo de dashboard.
- Linha 1724: `private atualizarV12ProdutosVsm()` — Atualiza cadastro, status ou estrutura no módulo de dashboard.
- Linha 1756: `private atualizarV14HomologacaoReal()` — Atualiza cadastro, status ou estrutura no módulo de dashboard.
- Linha 1782: `private filaMorta()` — Função auxiliar do domínio de dashboard; usada para apoiar regras internas da classe DashboardController.
- Linha 1793: `private filaMortaReprocessar()` — Função auxiliar do domínio de dashboard; usada para apoiar regras internas da classe DashboardController.
- Linha 1799: `private laboratorio()` — Função auxiliar do domínio de dashboard; usada para apoiar regras internas da classe DashboardController.
- Linha 1805: `private laboratorioExecutar()` — Função auxiliar do domínio de dashboard; usada para apoiar regras internas da classe DashboardController.
- Linha 1816: `private reconciliacao()` — Função auxiliar do domínio de dashboard; usada para apoiar regras internas da classe DashboardController.
- Linha 1823: `private reconciliacaoExecutar()` — Função auxiliar do domínio de dashboard; usada para apoiar regras internas da classe DashboardController.
- Linha 1831: `private metricas()` — Função auxiliar do domínio de dashboard; usada para apoiar regras internas da classe DashboardController.
- Linha 1839: `private selftest()` — Função auxiliar do domínio de dashboard; usada para apoiar regras internas da classe DashboardController.
- Linha 1846: `private selftestExecutar()` — Executa teste/diagnóstico controlado do módulo de dashboard.
- Linha 1856: `private centralHomologacao()` — Função auxiliar do domínio de dashboard; usada para apoiar regras internas da classe DashboardController.
- Linha 1869: `private tinyV2Homologacao()` — Função auxiliar do domínio de dashboard; usada para apoiar regras internas da classe DashboardController.
- Linha 1881: `private tinyV2HomologacaoExecutar()` — Função auxiliar do domínio de dashboard; usada para apoiar regras internas da classe DashboardController.
- Linha 1894: `private tinyV3Homologacao()` — Função auxiliar do domínio de dashboard; usada para apoiar regras internas da classe DashboardController.
- Linha 1906: `private tinyV3HomologacaoExecutar()` — Função auxiliar do domínio de dashboard; usada para apoiar regras internas da classe DashboardController.
- Linha 1918: `private tinyAmbientes()` — Função auxiliar do domínio de dashboard; usada para apoiar regras internas da classe DashboardController.
- Linha 1929: `private homologacao()` — Função auxiliar do domínio de dashboard; usada para apoiar regras internas da classe DashboardController.
- Linha 1938: `private homologacaoAcao()` — Função auxiliar do domínio de dashboard; usada para apoiar regras internas da classe DashboardController.
- Linha 1951: `private atualizarV16CorrecoesFinais()` — Atualiza cadastro, status ou estrutura no módulo de dashboard.
- Linha 1977: `private atualizarV15HomologacaoFinal()` — Atualiza cadastro, status ou estrutura no módulo de dashboard.
- Linha 2002: `private validarBanco()` — Valida dados/regras antes de concluir operação de dashboard.
- Linha 2009: `private homologacaoRelatorio()` — Função auxiliar do domínio de dashboard; usada para apoiar regras internas da classe DashboardController.
- Linha 2021: `private columnExistsForUpdate(string $table, string $column)` — Função auxiliar do domínio de dashboard; usada para apoiar regras internas da classe DashboardController.
- Linha 2028: `private safeAddColumn(array &$mensagens, string $table, string $column, string $definition)` — Função auxiliar do domínio de dashboard; usada para apoiar regras internas da classe DashboardController.
- Linha 2034: `private atualizarV16Final()` — Atualiza cadastro, status ou estrutura no módulo de dashboard.
- Linha 2060: `private fichaTecnica100()` — Função auxiliar do domínio de dashboard; usada para apoiar regras internas da classe DashboardController.
- Linha 2071: `private segurancaAuditoria()` — Registra ou consulta auditoria/histórico de dashboard.
- Linha 2080: `private segurancaExtrema()` — Função auxiliar do domínio de dashboard; usada para apoiar regras internas da classe DashboardController.
- Linha 2087: `private atualizarV22AuditoriaSeguranca()` — Atualiza cadastro, status ou estrutura no módulo de dashboard.
- Linha 2112: `private auditoriaExportarEnterprise()` — Registra ou consulta auditoria/histórico de dashboard.
- Linha 2132: `private productionReadyV25()` — Função auxiliar do domínio de dashboard; usada para apoiar regras internas da classe DashboardController.
- Linha 2144: `private atualizarV25Final()` — Atualiza cadastro, status ou estrutura no módulo de dashboard.
- Linha 2167: `private productionReadyV24()` — Função auxiliar do domínio de dashboard; usada para apoiar regras internas da classe DashboardController.
- Linha 2176: `private vsmFichaTecnica()` — Função auxiliar do domínio de dashboard; usada para apoiar regras internas da classe DashboardController.
- Linha 2186: `private tinyV2FichaTecnica()` — Função auxiliar do domínio de dashboard; usada para apoiar regras internas da classe DashboardController.
- Linha 2195: `private filaAnalyticsV24()` — Função auxiliar do domínio de dashboard; usada para apoiar regras internas da classe DashboardController.
- Linha 2202: `private atualizarV24ProductionReady()` — Atualiza cadastro, status ou estrutura no módulo de dashboard.
- Linha 2225: `private auditoriaAssinarTrace()` — Registra ou consulta auditoria/histórico de dashboard.
- Linha 2237: `private auditoriaExportarPdf()` — Registra ou consulta auditoria/histórico de dashboard.
- Linha 2253: `private productionReadyV26()` — Função auxiliar do domínio de dashboard; usada para apoiar regras internas da classe DashboardController.
- Linha 2260: `private hostingInfinityFree()` — Função auxiliar do domínio de dashboard; usada para apoiar regras internas da classe DashboardController.
- Linha 2268: `private atualizarV26Ultimate()` — Atualiza cadastro, status ou estrutura no módulo de dashboard.
- Linha 2291: `private auditoriaHashChain()` — Gera ou valida hash para integridade/idempotência em dashboard.
- Linha 2309: `private integracoes()` — Função auxiliar do domínio de dashboard; usada para apoiar regras internas da classe DashboardController.
- Linha 2320: `private regrasSincronizacao()` — Função auxiliar do domínio de dashboard; usada para apoiar regras internas da classe DashboardController.
- Linha 2328: `private salvarRegrasSincronizacao()` — Salva/atualiza dados do módulo de dashboard.
- Linha 2361: `private atualizarV31RegrasSincronizacao()` — Atualiza cadastro, status ou estrutura no módulo de dashboard.
- Linha 2387: `private orquestracaoIntegracoes()` — Função auxiliar do domínio de dashboard; usada para apoiar regras internas da classe DashboardController.
- Linha 2398: `private salvarOrquestracaoIntegracoes()` — Salva/atualiza dados do módulo de dashboard.
- Linha 2479: `private testarOrquestracaoFluxo()` — Executa teste/diagnóstico controlado do módulo de dashboard.
- Linha 2493: `private garantirEstruturaOrquestracao()` — Função auxiliar do domínio de dashboard; usada para apoiar regras internas da classe DashboardController.
- Linha 2516: `private registrarHistoricoOrquestracao(string $acao, string $status, string $mensagem, array $contexto = [], ?string $trace = null)` — Registra ou consulta auditoria/histórico de dashboard.
- Linha 2525: `private historicoOrquestracao(int $limit = 10)` — Registra ou consulta auditoria/histórico de dashboard.
- Linha 2534: `private diffOrquestracao(array $antes, array $depois)` — Função auxiliar do domínio de dashboard; usada para apoiar regras internas da classe DashboardController.
- Linha 2544: `private atualizarV38Orquestracao()` — Atualiza cadastro, status ou estrutura no módulo de dashboard.
- Linha 2563: `private homologacaoAutomatica()` — Função auxiliar do domínio de dashboard; usada para apoiar regras internas da classe DashboardController.
- Linha 2573: `private homologacaoAutomaticaExecutar()` — Função auxiliar do domínio de dashboard; usada para apoiar regras internas da classe DashboardController.
- Linha 2589: `private atualizadorSeguro()` — Função auxiliar do domínio de dashboard; usada para apoiar regras internas da classe DashboardController.
- Linha 2597: `private atualizadorSeguroExecutar()` — Função auxiliar do domínio de dashboard; usada para apoiar regras internas da classe DashboardController.
- Linha 2605: `private oauthV3Checklist()` — Função auxiliar do domínio de dashboard; usada para apoiar regras internas da classe DashboardController.
- Linha 2614: `private atualizarV23Seguranca2FA()` — Atualiza cadastro, status ou estrutura no módulo de dashboard.
- Linha 2629: `private relatorioProntidaoProducao()` — Função auxiliar do domínio de dashboard; usada para apoiar regras internas da classe DashboardController.
- Linha 2637: `private fiscal()` — Função auxiliar do domínio de dashboard; usada para apoiar regras internas da classe DashboardController.
- Linha 2647: `private dashboardIntegridadeExecutar()` — Função auxiliar do domínio de dashboard; usada para apoiar regras internas da classe DashboardController.
- Linha 2657: `private healthModulos()` — Executa verificação de saúde/disponibilidade em dashboard.
- Linha 2664: `private menuTestes()` — Executa teste/diagnóstico controlado do módulo de dashboard.
- Linha 2671: `private fiscalReenviar()` — Envia dados para integração/destino externo no módulo de dashboard.
- Linha 2680: `private atualizarV43FiscalDashboardInstall()` — Atualiza cadastro, status ou estrutura no módulo de dashboard.
- Linha 2697: `private centralTecnica()` — Função auxiliar do domínio de dashboard; usada para apoiar regras internas da classe DashboardController.
- Linha 2703: `private bancosModulos()` — Função auxiliar do domínio de dashboard; usada para apoiar regras internas da classe DashboardController.
- Linha 2711: `private bancosModulosInstalar()` — Função auxiliar do domínio de dashboard; usada para apoiar regras internas da classe DashboardController.
- Linha 2722: `private produtosPendentesIntegracao()` — Função auxiliar do domínio de dashboard; usada para apoiar regras internas da classe DashboardController.
- Linha 2741: `private produtoPendenteIntegracaoComparar()` — Converte/mapeia dados entre formatos do módulo de dashboard.
- Linha 2754: `private produtoPendenteIntegracaoAcao()` — Função auxiliar do domínio de dashboard; usada para apoiar regras internas da classe DashboardController.
- Linha 2805: `private categoriasMapeamento()` — Função auxiliar do domínio de dashboard; usada para apoiar regras internas da classe DashboardController.
- Linha 2820: `private categoriaMapeamentoSalvar()` — Função auxiliar do domínio de dashboard; usada para apoiar regras internas da classe DashboardController.
- Linha 2846: `private atualizarV45GovernancaProdutoVsm()` — Atualiza cadastro, status ou estrutura no módulo de dashboard.
- Linha 2873: `private vsmEndpoints()` — Função auxiliar do domínio de dashboard; usada para apoiar regras internas da classe DashboardController.
- Linha 2883: `private vsmEndpointSalvar()` — Função auxiliar do domínio de dashboard; usada para apoiar regras internas da classe DashboardController.
- Linha 2890: `private vsmEndpointTestar()` — Função auxiliar do domínio de dashboard; usada para apoiar regras internas da classe DashboardController.
- Linha 2900: `private vsmCampos()` — Função auxiliar do domínio de dashboard; usada para apoiar regras internas da classe DashboardController.
- Linha 2908: `private vsmCampoSalvar()` — Função auxiliar do domínio de dashboard; usada para apoiar regras internas da classe DashboardController.
- Linha 2915: `private vsmSaude()` — Função auxiliar do domínio de dashboard; usada para apoiar regras internas da classe DashboardController.
- Linha 2922: `private vsmLogs()` — Função auxiliar do domínio de dashboard; usada para apoiar regras internas da classe DashboardController.
- Linha 2929: `private vsmTestes()` — Executa teste/diagnóstico controlado do módulo de dashboard.
- Linha 2936: `private atualizarV48VsmConfiguravel()` — Atualiza cadastro, status ou estrutura no módulo de dashboard.
- Linha 2945: `private sobre()` — Função auxiliar do domínio de dashboard; usada para apoiar regras internas da classe DashboardController.
- Linha 2953: `private atualizarV53EnterpriseStabilization()` — Atualiza cadastro, status ou estrutura no módulo de dashboard.
- Linha 2969: `private producaoReady()` — Função auxiliar do domínio de dashboard; usada para apoiar regras internas da classe DashboardController.
- Linha 2977: `private securityCenter()` — Função auxiliar do domínio de dashboard; usada para apoiar regras internas da classe DashboardController.
- Linha 3023: `private securityFim()` — Função auxiliar do domínio de dashboard; usada para apoiar regras internas da classe DashboardController.
- Linha 3029: `private securityFimGerar()` — Função auxiliar do domínio de dashboard; usada para apoiar regras internas da classe DashboardController.
- Linha 3037: `private securityScore()` — Função auxiliar do domínio de dashboard; usada para apoiar regras internas da classe DashboardController.
- Linha 3044: `private securityAuditSign()` — Registra ou consulta auditoria/histórico de dashboard.

### `app/Controllers/DatabaseMaintenanceController.php` — `DatabaseMaintenanceController`
- Linha 3: `public dispatch(string $page)` — Recebe a rota solicitada e direciona para a ação correta do controller.
- Linha 10: `public static routes()` — Função auxiliar do domínio de banco de dados; usada para apoiar regras internas da classe DatabaseMaintenanceController.
- Linha 11: `private validarBanco()` — Valida dados/regras antes de concluir operação de banco de dados.
- Linha 17: `private healthModulos()` — Executa verificação de saúde/disponibilidade em banco de dados.
- Linha 23: `private updateLegadoBloqueado(string $page)` — Função auxiliar do domínio de banco de dados; usada para apoiar regras internas da classe DatabaseMaintenanceController.

### `app/Controllers/DivergenceMonitorController.php` — `DivergenceMonitorController`
- Linha 3: `public dispatch(string $page)` — Recebe a rota solicitada e direciona para a ação correta do controller.
- Linha 12: `public static routes()` — Função auxiliar do domínio de controle de telas/rotas; usada para apoiar regras internas da classe DivergenceMonitorController.
- Linha 13: `public index()` — Função auxiliar do domínio de controle de telas/rotas; usada para apoiar regras internas da classe DivergenceMonitorController.
- Linha 32: `private acao()` — Função auxiliar do domínio de controle de telas/rotas; usada para apoiar regras internas da classe DivergenceMonitorController.

### `app/Controllers/EstoqueController.php` — `EstoqueController`
- Linha 3: `public dispatch(string $page)` — Recebe a rota solicitada e direciona para a ação correta do controller.
- Linha 21: `private dashboard()` — Função auxiliar do domínio de estoque; usada para apoiar regras internas da classe EstoqueController.
- Linha 38: `private config()` — Função auxiliar do domínio de estoque; usada para apoiar regras internas da classe EstoqueController.
- Linha 45: `private salvarConfig()` — Salva/atualiza dados do módulo de estoque.
- Linha 79: `private alertas()` — Função auxiliar do domínio de estoque; usada para apoiar regras internas da classe EstoqueController.
- Linha 91: `private skuHistorico()` — Registra ou consulta auditoria/histórico de estoque.
- Linha 103: `private executarConsultaVsm()` — Função auxiliar do domínio de estoque; usada para apoiar regras internas da classe EstoqueController.
- Linha 118: `private consultasVsm()` — Função auxiliar do domínio de estoque; usada para apoiar regras internas da classe EstoqueController.
- Linha 127: `private consultaVsmResultados()` — Função auxiliar do domínio de estoque; usada para apoiar regras internas da classe EstoqueController.
- Linha 147: `private testarConsultaVsmSku()` — Executa teste/diagnóstico controlado do módulo de estoque.
- Linha 164: `private reconciliarAgora()` — Função auxiliar do domínio de estoque; usada para apoiar regras internas da classe EstoqueController.

### `app/Controllers/EvidenceController.php` — `EvidenceController`
- Linha 3: `public dispatch(string $page)` — Recebe a rota solicitada e direciona para a ação correta do controller.
- Linha 11: `public static routes()` — Função auxiliar do domínio de controle de telas/rotas; usada para apoiar regras internas da classe EvidenceController.
- Linha 12: `public index()` — Função auxiliar do domínio de controle de telas/rotas; usada para apoiar regras internas da classe EvidenceController.
- Linha 22: `public trace()` — Função auxiliar do domínio de controle de telas/rotas; usada para apoiar regras internas da classe EvidenceController.

### `app/Controllers/FilaController.php` — `FilaController`
- Linha 3: `public dispatch(string $page)` — Recebe a rota solicitada e direciona para a ação correta do controller.
- Linha 4: `public static routes()` — Função auxiliar do domínio de controle de telas/rotas; usada para apoiar regras internas da classe FilaController.
- Linha 5: `public index()` — Função auxiliar do domínio de controle de telas/rotas; usada para apoiar regras internas da classe FilaController.
- Linha 6: `public reprocessar()` — Função auxiliar do domínio de controle de telas/rotas; usada para apoiar regras internas da classe FilaController.
- Linha 7: `public criarTeste()` — Executa teste/diagnóstico controlado do módulo de controle de telas/rotas.

### `app/Controllers/FiscalController.php` — `FiscalController`
- Linha 3: `public dispatch(string $page)` — Recebe a rota solicitada e direciona para a ação correta do controller.
- Linha 14: `public static routes()` — Função auxiliar do domínio de fiscal/NF-e; usada para apoiar regras internas da classe FiscalController.
- Linha 15: `public index()` — Função auxiliar do domínio de fiscal/NF-e; usada para apoiar regras internas da classe FiscalController.
- Linha 29: `public dashboardFiscal()` — Função auxiliar do domínio de fiscal/NF-e; usada para apoiar regras internas da classe FiscalController.
- Linha 30: `public xml()` — Função auxiliar do domínio de fiscal/NF-e; usada para apoiar regras internas da classe FiscalController.
- Linha 31: `public timeline()` — Função auxiliar do domínio de fiscal/NF-e; usada para apoiar regras internas da classe FiscalController.
- Linha 32: `public reconciliacao()` — Função auxiliar do domínio de fiscal/NF-e; usada para apoiar regras internas da classe FiscalController.
- Linha 33: `public health()` — Executa verificação de saúde/disponibilidade em fiscal/NF-e.
- Linha 34: `public reenviar()` — Envia dados para integração/destino externo no módulo de fiscal/NF-e.

### `app/Controllers/HomologacaoController.php` — `HomologacaoController`
- Linha 8: `public static routes()` — Função auxiliar do domínio de homologação; usada para apoiar regras internas da classe HomologacaoController.

### `app/Controllers/LoginController.php` — `LoginController`
- Linha 3: `public form()` — Renderiza o formulário/tela correspondente.
- Linha 4: `public login()` — Processa autenticação do usuário com senha, sessão, rate limit e 2FA quando aplicável.
- Linha 20: `public logout()` — Encerra a sessão atual do usuário.

### `app/Controllers/MigrationController.php` — `MigrationController`
- Linha 3: `public dispatch(string $page)` — Recebe a rota solicitada e direciona para a ação correta do controller.
- Linha 11: `private index()` — Função auxiliar do domínio de migrações; usada para apoiar regras internas da classe MigrationController.
- Linha 20: `private apply()` — Função auxiliar do domínio de migrações; usada para apoiar regras internas da classe MigrationController.
- Linha 44: `private legacyBlocked(string $page)` — Bloqueia operação insegura ou não autorizada em migrações.

### `app/Controllers/OperationCenterController.php` — `OperationCenterController`
- Linha 3: `public dispatch(string $page)` — Recebe a rota solicitada e direciona para a ação correta do controller.
- Linha 12: `public static routes()` — Função auxiliar do domínio de operação; usada para apoiar regras internas da classe OperationCenterController.
- Linha 13: `public centro()` — Função auxiliar do domínio de operação; usada para apoiar regras internas da classe OperationCenterController.
- Linha 24: `public alertas()` — Função auxiliar do domínio de operação; usada para apoiar regras internas da classe OperationCenterController.
- Linha 30: `public dashboardExecutivo()` — Função auxiliar do domínio de operação; usada para apoiar regras internas da classe OperationCenterController.

### `app/Controllers/PedidoController.php` — `PedidoController`
- Linha 3: `public dispatch(string $page)` — Recebe a rota solicitada e direciona para a ação correta do controller.
- Linha 4: `public static routes()` — Função auxiliar do domínio de pedidos; usada para apoiar regras internas da classe PedidoController.
- Linha 5: `public index()` — Função auxiliar do domínio de pedidos; usada para apoiar regras internas da classe PedidoController.
- Linha 15: `public detalhe()` — Carrega os detalhes completos de um registro.

### `app/Controllers/ProductionGoLiveController.php` — `ProductionGoLiveController`
- Linha 3: `public dispatch(string $page)` — Recebe a rota solicitada e direciona para a ação correta do controller.
- Linha 12: `private index()` — Função auxiliar do domínio de controle de telas/rotas; usada para apoiar regras internas da classe ProductionGoLiveController.
- Linha 21: `private validar()` — Valida payload, banco, regra, arquivo ou configuração.
- Linha 28: `private lockInstall()` — Função auxiliar do domínio de controle de telas/rotas; usada para apoiar regras internas da classe ProductionGoLiveController.

### `app/Controllers/ProdutoController.php` — `ProdutoController`
- Linha 3: `public dispatch(string $page)` — Recebe a rota solicitada e direciona para a ação correta do controller.
- Linha 4: `public static routes()` — Função auxiliar do domínio de produtos; usada para apoiar regras internas da classe ProdutoController.
- Linha 5: `public index()` — Função auxiliar do domínio de produtos; usada para apoiar regras internas da classe ProdutoController.
- Linha 6: `public pendencias()` — Função auxiliar do domínio de produtos; usada para apoiar regras internas da classe ProdutoController.

### `app/Controllers/PwaController.php` — `PwaController`
- Linha 3: `public dispatch(string $page)` — Recebe a rota solicitada e direciona para a ação correta do controller.

### `app/Controllers/SistemaController.php` — `SistemaController`
- Linha 7: `public dispatch(string $page)` — Recebe a rota solicitada e direciona para a ação correta do controller.
- Linha 16: `private sobre()` — Função auxiliar do domínio de controle de telas/rotas; usada para apoiar regras internas da classe SistemaController.
- Linha 24: `private tutorialSistema()` — Função auxiliar do domínio de controle de telas/rotas; usada para apoiar regras internas da classe SistemaController.

### `app/Controllers/TinyController.php` — `TinyController`
- Linha 8: `public static routes()` — Função auxiliar do domínio de Tiny/Olist; usada para apoiar regras internas da classe TinyController.

### `app/Controllers/TinyHomologacaoController.php` — `TinyHomologacaoController`
- Linha 3: `public dispatch(string $page)` — Recebe a rota solicitada e direciona para a ação correta do controller.
- Linha 13: `public static routes()` — Função auxiliar do domínio de Tiny/Olist; usada para apoiar regras internas da classe TinyHomologacaoController.
- Linha 14: `private v2()` — Função auxiliar do domínio de Tiny/Olist; usada para apoiar regras internas da classe TinyHomologacaoController.
- Linha 26: `private v2Executar()` — Função auxiliar do domínio de Tiny/Olist; usada para apoiar regras internas da classe TinyHomologacaoController.
- Linha 36: `private v3()` — Função auxiliar do domínio de Tiny/Olist; usada para apoiar regras internas da classe TinyHomologacaoController.
- Linha 48: `private v3Executar()` — Função auxiliar do domínio de Tiny/Olist; usada para apoiar regras internas da classe TinyHomologacaoController.

### `app/Controllers/VsmController.php` — `VsmController`
- Linha 8: `public dispatch(string $page)` — Recebe a rota solicitada e direciona para a ação correta do controller.
- Linha 25: `private endpoints()` — Função auxiliar do domínio de VSM; usada para apoiar regras internas da classe VsmController.
- Linha 35: `private endpointSalvar()` — Função auxiliar do domínio de VSM; usada para apoiar regras internas da classe VsmController.
- Linha 45: `private endpointTestar()` — Função auxiliar do domínio de VSM; usada para apoiar regras internas da classe VsmController.
- Linha 56: `private campos()` — Função auxiliar do domínio de VSM; usada para apoiar regras internas da classe VsmController.
- Linha 66: `private campoSalvar()` — Função auxiliar do domínio de VSM; usada para apoiar regras internas da classe VsmController.
- Linha 73: `private saude()` — Função auxiliar do domínio de VSM; usada para apoiar regras internas da classe VsmController.
- Linha 79: `private logs()` — Função auxiliar do domínio de VSM; usada para apoiar regras internas da classe VsmController.
- Linha 85: `private testes()` — Executa teste/diagnóstico controlado do módulo de VSM.
- Linha 91: `private atualizarV48()` — Atualiza cadastro, status ou estrutura no módulo de VSM.
- Linha 93: `private atualizarV49()` — Atualiza cadastro, status ou estrutura no módulo de VSM.

### `app/Core/App.php` — `App`
- Linha 3: `public static cspNonce()` — Função auxiliar do domínio de utilitário/core; usada para apoiar regras internas da classe App.
- Linha 7: `public static config()` — Função auxiliar do domínio de utilitário/core; usada para apoiar regras internas da classe App.
- Linha 14: `public static env()` — Função auxiliar do domínio de utilitário/core; usada para apoiar regras internas da classe App.
- Linha 15: `public static isLocal()` — Função auxiliar do domínio de utilitário/core; usada para apoiar regras internas da classe App.
- Linha 16: `public static isProduction()` — Função auxiliar do domínio de utilitário/core; usada para apoiar regras internas da classe App.
- Linha 17: `public static isPublicHost()` — Função auxiliar do domínio de utilitário/core; usada para apoiar regras internas da classe App.
- Linha 22: `public static enforceProductionSafety()` — Função auxiliar do domínio de utilitário/core; usada para apoiar regras internas da classe App.
- Linha 30: `public static sendSecurityHeaders()` — Função auxiliar do domínio de utilitário/core; usada para apoiar regras internas da classe App.
- Linha 51: `public static setupErrors()` — Função auxiliar do domínio de utilitário/core; usada para apoiar regras internas da classe App.

### `app/Core/Auth.php` — `Auth`
- Linha 5: `public static check()` — Verifica se uma condição de autenticação, permissão ou saúde está válida.
- Linha 6: `public static user()` — Retorna dados do usuário logado ou relacionado à sessão.
- Linha 8: `public static requireLogin()` — Função auxiliar do domínio de autenticação/sessão; usada para apoiar regras internas da classe Auth.
- Linha 40: `private static fingerprintHash()` — Gera ou valida hash para integridade/idempotência em autenticação/sessão.
- Linha 47: `private static tooManyAttempts(string $email, ?string $ip)` — Função auxiliar do domínio de autenticação/sessão; usada para apoiar regras internas da classe Auth.
- Linha 67: `public static requirePerfil(array $perfis)` — Função auxiliar do domínio de autenticação/sessão; usada para apoiar regras internas da classe Auth.
- Linha 75: `public static pendingTwoFactorInfo()` — Função auxiliar do domínio de autenticação/sessão; usada para apoiar regras internas da classe Auth.
- Linha 92: `private static startPending2fa(array $u, string $secret, bool $setupRequired=false)` — Função auxiliar do domínio de autenticação/sessão; usada para apoiar regras internas da classe Auth.
- Linha 102: `public static completeTwoFactor(string $codigo2fa)` — Função auxiliar do domínio de autenticação/sessão; usada para apoiar regras internas da classe Auth.
- Linha 119: `public static login(string $email, string $senha, ?string $codigo2fa=null)` — Processa autenticação do usuário com senha, sessão, rate limit e 2FA quando aplicável.
- Linha 179: `private static finalizeLogin(array $u)` — Função auxiliar do domínio de autenticação/sessão; usada para apoiar regras internas da classe Auth.
- Linha 193: `public static logout()` — Encerra a sessão atual do usuário.

### `app/Core/Csrf.php` — `Csrf`
- Linha 3: `public static token()` — Gere/valida/consulta token seguro no módulo de CSRF.
- Linha 9: `public static input()` — Função auxiliar do domínio de CSRF; usada para apoiar regras internas da classe Csrf.
- Linha 12: `public static field()` — Função auxiliar do domínio de CSRF; usada para apoiar regras internas da classe Csrf.
- Linha 15: `public static validate()` — Valida dados recebidos antes de executar a regra de negócio.

### `app/Core/Database.php` — `Database`
- Linha 6: `public static getConnection(?string $module = null)` — Função auxiliar do domínio de banco de dados; usada para apoiar regras internas da classe Database.
- Linha 10: `public static connection(string $module = 'core')` — Abre/retorna conexão com banco de dados.
- Linha 30: `public static serverConnection(?string $module = null)` — Função auxiliar do domínio de banco de dados; usada para apoiar regras internas da classe Database.
- Linha 40: `public static moduleConfig(array $cfg, string $module = 'core')` — Função auxiliar do domínio de banco de dados; usada para apoiar regras internas da classe Database.
- Linha 54: `public static modules()` — Função auxiliar do domínio de banco de dados; usada para apoiar regras internas da classe Database.
- Linha 61: `public static tableModule(string $table)` — Função auxiliar do domínio de banco de dados; usada para apoiar regras internas da classe Database.
- Linha 75: `public static forTable(string $table)` — Função auxiliar do domínio de banco de dados; usada para apoiar regras internas da classe Database.
- Linha 80: `public static tableExists(string $table)` — Função auxiliar do domínio de banco de dados; usada para apoiar regras internas da classe Database.
- Linha 85: `public static columnExists(string $table, string $column)` — Função auxiliar do domínio de banco de dados; usada para apoiar regras internas da classe Database.
- Linha 90: `public static tableConnectionForSql(string $sql)` — Função auxiliar do domínio de banco de dados; usada para apoiar regras internas da classe Database.
- Linha 97: `private static normalizeModule(string $module)` — Padroniza formato de dados usado por banco de dados.
- Linha 102: `private static handleConnectionError(PDOException $e, array $db, string $module)` — Função auxiliar do domínio de banco de dados; usada para apoiar regras internas da classe Database.

### `app/Core/Helpers.php` — `global`
- Linha 2: `public/global e($v)` — Função auxiliar do domínio de utilitário/core; usada para apoiar regras internas da classe global.
- Linha 3: `public/global redirect($url)` — Função auxiliar do domínio de utilitário/core; usada para apoiar regras internas da classe global.
- Linha 4: `public/global cfg($key=null)` — Função auxiliar do domínio de utilitário/core; usada para apoiar regras internas da classe global.

### `app/Core/RequestContext.php` — `RequestContext`
- Linha 5: `public static id()` — Função auxiliar do domínio de utilitário/core; usada para apoiar regras internas da classe RequestContext.
- Linha 13: `public static clientTraceId()` — Função auxiliar do domínio de utilitário/core; usada para apoiar regras internas da classe RequestContext.
- Linha 20: `public static userId()` — Função auxiliar do domínio de utilitário/core; usada para apoiar regras internas da classe RequestContext.
- Linha 27: `public static ip()` — Função auxiliar do domínio de utilitário/core; usada para apoiar regras internas da classe RequestContext.
- Linha 33: `public static ipForAudit()` — Registra ou consulta auditoria/histórico de utilitário/core.
- Linha 35: `public static ipPrefix(?string $ip=null)` — Função auxiliar do domínio de utilitário/core; usada para apoiar regras internas da classe RequestContext.
- Linha 46: `public static userAgent()` — Função auxiliar do domínio de utilitário/core; usada para apoiar regras internas da classe RequestContext.
- Linha 47: `public static route()` — Função auxiliar do domínio de utilitário/core; usada para apoiar regras internas da classe RequestContext.
- Linha 48: `public static method()` — Função auxiliar do domínio de utilitário/core; usada para apoiar regras internas da classe RequestContext.

### `app/Legacy/Controllers/V50Controller.php` — `V50Controller`
- Linha 3: `public dispatch(string $page)` — Recebe a rota solicitada e direciona para a ação correta do controller.
- Linha 18: `private tinyValidacao()` — Função auxiliar do domínio de controle de telas/rotas; usada para apoiar regras internas da classe V50Controller.
- Linha 25: `private tinyValidacaoExecutar()` — Função auxiliar do domínio de controle de telas/rotas; usada para apoiar regras internas da classe V50Controller.
- Linha 31: `private vsmSimulador()` — Função auxiliar do domínio de controle de telas/rotas; usada para apoiar regras internas da classe V50Controller.
- Linha 37: `private vsmSimuladorExecutar()` — Função auxiliar do domínio de controle de telas/rotas; usada para apoiar regras internas da classe V50Controller.
- Linha 43: `private producaoSegura()` — Função auxiliar do domínio de controle de telas/rotas; usada para apoiar regras internas da classe V50Controller.
- Linha 48: `private producaoSeguraExecutar()` — Função auxiliar do domínio de controle de telas/rotas; usada para apoiar regras internas da classe V50Controller.
- Linha 54: `private limpezaRetencao()` — Função auxiliar do domínio de controle de telas/rotas; usada para apoiar regras internas da classe V50Controller.
- Linha 59: `private limpezaRetencaoExecutar()` — Função auxiliar do domínio de controle de telas/rotas; usada para apoiar regras internas da classe V50Controller.
- Linha 66: `private atualizarV50()` — Atualiza cadastro, status ou estrutura no módulo de controle de telas/rotas.

### `app/Legacy/Controllers/V51Controller.php` — `V51Controller`
- Linha 3: `public dispatch(string $page)` — Recebe a rota solicitada e direciona para a ação correta do controller.
- Linha 19: `private produtoNovoPolitica()` — Função auxiliar do domínio de controle de telas/rotas; usada para apoiar regras internas da classe V51Controller.
- Linha 20: `private produtoNovoPoliticaSalvar()` — Função auxiliar do domínio de controle de telas/rotas; usada para apoiar regras internas da classe V51Controller.
- Linha 21: `private pedidosValidacao()` — Função auxiliar do domínio de controle de telas/rotas; usada para apoiar regras internas da classe V51Controller.
- Linha 22: `private pedidoValidacaoDetalhe()` — Função auxiliar do domínio de controle de telas/rotas; usada para apoiar regras internas da classe V51Controller.
- Linha 23: `private pedidoValidacaoEnfileirar()` — Função auxiliar do domínio de controle de telas/rotas; usada para apoiar regras internas da classe V51Controller.
- Linha 24: `private pedidoCicloVida()` — Função auxiliar do domínio de controle de telas/rotas; usada para apoiar regras internas da classe V51Controller.
- Linha 25: `private pedidoCicloDetalhe()` — Função auxiliar do domínio de controle de telas/rotas; usada para apoiar regras internas da classe V51Controller.
- Linha 26: `private pedidoCicloEnviarTiny()` — Envia dados para integração/destino externo no módulo de controle de telas/rotas.
- Linha 27: `private atualizarV52()` — Atualiza cadastro, status ou estrutura no módulo de controle de telas/rotas.
- Linha 28: `private atualizarV51()` — Atualiza cadastro, status ou estrutura no módulo de controle de telas/rotas.

### `app/Services/Audit.php` — `Audit`
- Linha 3: `public static event(string $acao, string $status='sucesso', array $data=[])` — Função auxiliar do domínio de auditoria; usada para apoiar regras internas da classe Audit.
- Linha 30: `public static exception(Throwable $e, string $acao='exception', array $extra=[])` — Função auxiliar do domínio de auditoria; usada para apoiar regras internas da classe Audit.
- Linha 37: `public static json($v)` — Função auxiliar do domínio de auditoria; usada para apoiar regras internas da classe Audit.

### `app/Services/AuditDailySignatureService.php` — `AuditDailySignatureService`
- Linha 3: `public static ensureSchema()` — Garante que tabelas/colunas necessárias existam antes da operação.
- Linha 20: `public static sign(?string $date=null)` — Função auxiliar do domínio de auditoria; usada para apoiar regras internas da classe AuditDailySignatureService.
- Linha 45: `public static latest(int $limit=10)` — Função auxiliar do domínio de auditoria; usada para apoiar regras internas da classe AuditDailySignatureService.
- Linha 50: `private static key()` — Função auxiliar do domínio de auditoria; usada para apoiar regras internas da classe AuditDailySignatureService.

### `app/Services/AuditIntegrityService.php` — `AuditIntegrityService`
- Linha 8: `public static assinarEvento(int $auditoriaId)` — Função auxiliar do domínio de auditoria; usada para apoiar regras internas da classe AuditIntegrityService.
- Linha 26: `public static assinarTrace(string $traceId)` — Função auxiliar do domínio de auditoria; usada para apoiar regras internas da classe AuditIntegrityService.
- Linha 35: `public static verificarTrace(string $traceId)` — Função auxiliar do domínio de auditoria; usada para apoiar regras internas da classe AuditIntegrityService.
- Linha 50: `private static ultimoHashAntes(int $eventoId, string $traceId)` — Gera ou valida hash para integridade/idempotência em auditoria.
- Linha 60: `private static canonicalize(array $ev)` — Função auxiliar do domínio de auditoria; usada para apoiar regras internas da classe AuditIntegrityService.
- Linha 66: `private static ensureChainColumns()` — Função auxiliar do domínio de auditoria; usada para apoiar regras internas da classe AuditIntegrityService.
- Linha 80: `private static tableExists(string $table)` — Função auxiliar do domínio de auditoria; usada para apoiar regras internas da classe AuditIntegrityService.

### `app/Services/AuditTimelineService.php` — `AuditTimelineService`
- Linha 3: `public static registrar(string $fase, string $status='info', array $dados=[])` — Registra evento, histórico, auditoria ou snapshot no banco.
- Linha 6: `public static porTrace(string $trace)` — Função auxiliar do domínio de auditoria; usada para apoiar regras internas da classe AuditTimelineService.

### `app/Services/AuthRepository.php` — `AuthRepository`
- Linha 3: `public static pdo()` — Função auxiliar do domínio de autenticação/sessão; usada para apoiar regras internas da classe AuthRepository.
- Linha 5: `public static userByEmail(string $email)` — Função auxiliar do domínio de autenticação/sessão; usada para apoiar regras internas da classe AuthRepository.
- Linha 12: `public static userById(int $id)` — Função auxiliar do domínio de autenticação/sessão; usada para apoiar regras internas da classe AuthRepository.
- Linha 19: `public static sessionVersion(int $id)` — Função auxiliar do domínio de autenticação/sessão; usada para apoiar regras internas da classe AuthRepository.
- Linha 26: `public static countFailedByIp(string $ip, string $intervalSql)` — Função auxiliar do domínio de autenticação/sessão; usada para apoiar regras internas da classe AuthRepository.
- Linha 32: `public static countFailedByEmail(string $email, string $intervalSql)` — Função auxiliar do domínio de autenticação/sessão; usada para apoiar regras internas da classe AuthRepository.
- Linha 38: `public static insertAttempt(string $email, ?string $ip, int $success, string $message)` — Função auxiliar do domínio de autenticação/sessão; usada para apoiar regras internas da classe AuthRepository.
- Linha 42: `public static save2faSecret(int $id, string $encryptedSecret, bool $enable=true)` — Função auxiliar do domínio de autenticação/sessão; usada para apoiar regras internas da classe AuthRepository.
- Linha 50: `public static mark2faVerified(int $id)` — Função auxiliar do domínio de autenticação/sessão; usada para apoiar regras internas da classe AuthRepository.
- Linha 54: `public static markSuccess(int $id)` — Função auxiliar do domínio de autenticação/sessão; usada para apoiar regras internas da classe AuthRepository.
- Linha 58: `public static markFailure(array $u, int $maxTentativas, int $lockSeconds)` — Função auxiliar do domínio de autenticação/sessão; usada para apoiar regras internas da classe AuthRepository.

### `app/Services/AutoHomologationService.php` — `AutoHomologationService`
- Linha 7: `public __construct(?PDO $pdo = null)` — Inicializa dependências internas de AutoHomologationService.
- Linha 13: `public executar(bool $liberarSeAprovado = false)` — Executa processo operacional solicitado pelo painel ou worker.
- Linha 68: `private garantirEstrutura()` — Função auxiliar do domínio de homologação; usada para apoiar regras internas da classe AutoHomologationService.
- Linha 90: `private salvarRelatorio(array $relatorio)` — Salva/atualiza dados do módulo de homologação.
- Linha 101: `private htmlRelatorio(array $r)` — Função auxiliar do domínio de homologação; usada para apoiar regras internas da classe AutoHomologationService.
- Linha 109: `private item(string $nome, string $status, string $mensagem, string $acao = '', array $contexto = [])` — Função auxiliar do domínio de homologação; usada para apoiar regras internas da classe AutoHomologationService.
- Linha 113: `private testeBanco()` — Executa teste/diagnóstico controlado do módulo de homologação.
- Linha 124: `private testeConfiguracoesTinyV3()` — Executa teste/diagnóstico controlado do módulo de homologação.
- Linha 133: `private testeOAuthTinyV3()` — Executa teste/diagnóstico controlado do módulo de homologação.
- Linha 142: `private testeProdutoTinyV3()` — Executa teste/diagnóstico controlado do módulo de homologação.
- Linha 153: `private testeEstoqueTinyV3()` — Executa teste/diagnóstico controlado do módulo de homologação.
- Linha 164: `private testePedidoTinyV3()` — Executa teste/diagnóstico controlado do módulo de homologação.
- Linha 175: `private testeLogsTinyV3()` — Executa teste/diagnóstico controlado do módulo de homologação.
- Linha 183: `private testeConfiguracoesVsm()` — Executa teste/diagnóstico controlado do módulo de homologação.
- Linha 192: `private testeFila()` — Executa teste/diagnóstico controlado do módulo de homologação.
- Linha 199: `private testeAuditoria()` — Executa teste/diagnóstico controlado do módulo de homologação.
- Linha 208: `private tabelaExiste(string $t)` — Função auxiliar do domínio de homologação; usada para apoiar regras internas da classe AutoHomologationService.
- Linha 213: `private marcarChecklistBase()` — Função auxiliar do domínio de homologação; usada para apoiar regras internas da classe AutoHomologationService.
- Linha 219: `public static diagnosticarErroOAuth(array $query)` — Função auxiliar do domínio de homologação; usada para apoiar regras internas da classe AutoHomologationService.

### `app/Services/BackupService.php` — `BackupService`
- Linha 16: `private static buildSql()` — Função auxiliar do domínio de backups; usada para apoiar regras internas da classe BackupService.
- Linha 38: `public static gerarSql()` — Gera artefato, relatório, chave ou arquivo no módulo de backups.
- Linha 49: `public static gerarZip()` — Gera artefato, relatório, chave ou arquivo no módulo de backups.
- Linha 74: `private static registrar(string $file, string $msg)` — Registra evento, histórico, auditoria ou snapshot no banco.
- Linha 81: `public static importarUpload(array $file)` — Função auxiliar do domínio de backups; usada para apoiar regras internas da classe BackupService.
- Linha 108: `public static restaurarPorId(int $id, string $confirmacao)` — Função auxiliar do domínio de backups; usada para apoiar regras internas da classe BackupService.
- Linha 132: `private static extrairSqlDoBackup(string $file)` — Função auxiliar do domínio de backups; usada para apoiar regras internas da classe BackupService.
- Linha 154: `private static validarManifestoRestore(string $file, string $sql)` — Valida dados/regras antes de concluir operação de backups.
- Linha 168: `private static executarSqlSeguro(PDO $pdo, string $sql)` — Função auxiliar do domínio de backups; usada para apoiar regras internas da classe BackupService.
- Linha 200: `private static validarStatementRestore(string $stmt)` — Valida dados/regras antes de concluir operação de backups.
- Linha 207: `public static limparAntigos(int $dias=30)` — Converte/mapeia dados entre formatos do módulo de backups.

### `app/Services/BackupSignatureService.php` — `BackupSignatureService`
- Linha 3: `private static key()` — Função auxiliar do domínio de backups; usada para apoiar regras internas da classe BackupSignatureService.
- Linha 13: `public static signaturePath(string $file)` — Função auxiliar do domínio de backups; usada para apoiar regras internas da classe BackupSignatureService.
- Linha 14: `public static sign(string $file)` — Função auxiliar do domínio de backups; usada para apoiar regras internas da classe BackupSignatureService.
- Linha 28: `public static verify(string $file)` — Função auxiliar do domínio de backups; usada para apoiar regras internas da classe BackupSignatureService.

### `app/Services/BackupTrustService.php` — `BackupTrustService`
- Linha 3: `public static score(?string $file=null)` — Função auxiliar do domínio de backups; usada para apoiar regras internas da classe BackupTrustService.

### `app/Services/CircuitBreakerService.php` — `CircuitBreakerService`
- Linha 3: `private static policy(string $sistema)` — Função auxiliar do domínio de regra de negócio/serviço; usada para apoiar regras internas da classe CircuitBreakerService.
- Linha 12: `public static permitir(string $sistema)` — Autoriza operação conforme configuração/regra de regra de negócio/serviço.
- Linha 27: `public static sucesso(string $sistema)` — Função auxiliar do domínio de regra de negócio/serviço; usada para apoiar regras internas da classe CircuitBreakerService.
- Linha 31: `public static falha(string $sistema, string $mensagem)` — Função auxiliar do domínio de regra de negócio/serviço; usada para apoiar regras internas da classe CircuitBreakerService.
- Linha 53: `private static isTransientFailure(string $mensagem)` — Função auxiliar do domínio de regra de negócio/serviço; usada para apoiar regras internas da classe CircuitBreakerService.

### `app/Services/CodeAuditService.php` — `CodeAuditService`
- Linha 3: `public static analisar()` — Função auxiliar do domínio de auditoria; usada para apoiar regras internas da classe CodeAuditService.

### `app/Services/CodeExecutionAuditService.php` — `CodeExecutionAuditService`
- Linha 9: `public static scan()` — Função auxiliar do domínio de auditoria; usada para apoiar regras internas da classe CodeExecutionAuditService.
- Linha 62: `public static enforceNoOsExecution()` — Função auxiliar do domínio de auditoria; usada para apoiar regras internas da classe CodeExecutionAuditService.
- Linha 70: `private static phpFiles(string $root)` — Função auxiliar do domínio de auditoria; usada para apoiar regras internas da classe CodeExecutionAuditService.
- Linha 82: `private static shellDisabledOk()` — Função auxiliar do domínio de auditoria; usada para apoiar regras internas da classe CodeExecutionAuditService.
- Linha 88: `private static shouldKeepDetail(string $rel)` — Função auxiliar do domínio de auditoria; usada para apoiar regras internas da classe CodeExecutionAuditService.
- Linha 89: `private static hit(string $tipo, string $classe, string $arquivo, int $linha, string $codigo, string $analise)` — Função auxiliar do domínio de auditoria; usada para apoiar regras internas da classe CodeExecutionAuditService.

### `app/Services/CryptoService.php` — `CryptoService`
- Linha 5: `private static key()` — Função auxiliar do domínio de regra de negócio/serviço; usada para apoiar regras internas da classe CryptoService.
- Linha 15: `public static isEncrypted(?string $value)` — Função auxiliar do domínio de regra de negócio/serviço; usada para apoiar regras internas da classe CryptoService.
- Linha 19: `private static aad(?string $context=null)` — Função auxiliar do domínio de regra de negócio/serviço; usada para apoiar regras internas da classe CryptoService.
- Linha 21: `public static encrypt(?string $plain, ?string $context=null)` — Função auxiliar do domínio de regra de negócio/serviço; usada para apoiar regras internas da classe CryptoService.
- Linha 38: `public static decrypt(?string $value, ?string $context=null)` — Função auxiliar do domínio de regra de negócio/serviço; usada para apoiar regras internas da classe CryptoService.

### `app/Services/CspReportService.php` — `CspReportService`
- Linha 3: `public static handle()` — Função auxiliar do domínio de regra de negócio/serviço; usada para apoiar regras internas da classe CspReportService.
- Linha 12: `public static ensureSchema()` — Garante que tabelas/colunas necessárias existam antes da operação.

### `app/Services/DashboardIntegrityService.php` — `DashboardIntegrityService`
- Linha 3: `public static checks(bool $deep = false)` — Função auxiliar do domínio de dashboard; usada para apoiar regras internas da classe DashboardIntegrityService.
- Linha 47: `private static deepQuery(string $item, string $table, string $sql)` — Função auxiliar do domínio de dashboard; usada para apoiar regras internas da classe DashboardIntegrityService.
- Linha 51: `public static summary(array $checks)` — Função auxiliar do domínio de dashboard; usada para apoiar regras internas da classe DashboardIntegrityService.

### `app/Services/DatabaseInventoryService.php` — `DatabaseInventoryService`
- Linha 7: `public static classify()` — Função auxiliar do domínio de banco de dados; usada para apoiar regras internas da classe DatabaseInventoryService.
- Linha 23: `private static tables()` — Função auxiliar do domínio de banco de dados; usada para apoiar regras internas da classe DatabaseInventoryService.
- Linha 36: `private static moduleOf(string $table)` — Função auxiliar do domínio de banco de dados; usada para apoiar regras internas da classe DatabaseInventoryService.
- Linha 37: `private static resumo(array $items)` — Função auxiliar do domínio de banco de dados; usada para apoiar regras internas da classe DatabaseInventoryService.

### `app/Services/DatabaseValidationService.php` — `DatabaseValidationService`
- Linha 6: `public __construct(?PDO $pdo=null)` — Inicializa dependências internas de DatabaseValidationService.
- Linha 11: `public executar()` — Executa processo operacional solicitado pelo painel ou worker.
- Linha 39: `private checkConexao(array &$checks)` — Função auxiliar do domínio de banco de dados; usada para apoiar regras internas da classe DatabaseValidationService.
- Linha 48: `private checkTabelas(array &$checks)` — Função auxiliar do domínio de banco de dados; usada para apoiar regras internas da classe DatabaseValidationService.
- Linha 57: `private checkColunasCriticas(array &$checks)` — Função auxiliar do domínio de banco de dados; usada para apoiar regras internas da classe DatabaseValidationService.
- Linha 85: `private checkIndicesCriticos(array &$checks)` — Função auxiliar do domínio de banco de dados; usada para apoiar regras internas da classe DatabaseValidationService.
- Linha 101: `private checkPermissoesCriticas(array &$checks)` — Função auxiliar do domínio de banco de dados; usada para apoiar regras internas da classe DatabaseValidationService.
- Linha 113: `private checkTinyV3(array &$checks)` — Função auxiliar do domínio de banco de dados; usada para apoiar regras internas da classe DatabaseValidationService.
- Linha 124: `private tableExists(string $table)` — Função auxiliar do domínio de banco de dados; usada para apoiar regras internas da classe DatabaseValidationService.
- Linha 132: `private columnExists(string $table,string $column)` — Função auxiliar do domínio de banco de dados; usada para apoiar regras internas da classe DatabaseValidationService.
- Linha 140: `private indexExists(string $table,string $index)` — Função auxiliar do domínio de banco de dados; usada para apoiar regras internas da classe DatabaseValidationService.
- Linha 148: `private item(string $status,string $titulo,string $mensagem,string $acao)` — Função auxiliar do domínio de banco de dados; usada para apoiar regras internas da classe DatabaseValidationService.
- Linha 149: `private statusFinal(array $checks)` — Função auxiliar do domínio de banco de dados; usada para apoiar regras internas da classe DatabaseValidationService.

### `app/Services/DeadLetterQueueService.php` — `DeadLetterQueueService`
- Linha 3: `public static enviar(array $item, array $retorno=[], ?string $codigoErro=null, ?string $motivo=null)` — Envia dados para integração/destino externo no módulo de regra de negócio/serviço.
- Linha 23: `public static reprocessar(int $dlqId)` — Função auxiliar do domínio de regra de negócio/serviço; usada para apoiar regras internas da classe DeadLetterQueueService.

### `app/Services/DiagnosticoApiService.php` — `DiagnosticoApiService`
- Linha 3: `public static registrar(string $sistema, ?string $endpoint, string $status, ?int $httpCode, ?int $tempoMs, string $mensagem, $detalhes=null)` — Registra evento, histórico, auditoria ou snapshot no banco.
- Linha 23: `public static ultimos(int $limite=30)` — Função auxiliar do domínio de regra de negócio/serviço; usada para apoiar regras internas da classe DiagnosticoApiService.
- Linha 33: `public static ultimoPorSistema(string $sistema)` — Função auxiliar do domínio de regra de negócio/serviço; usada para apoiar regras internas da classe DiagnosticoApiService.

### `app/Services/EnterpriseAuditHashChainService.php` — `EnterpriseAuditHashChainService`
- Linha 3: `public static assinarProximos(int $limite=500)` — Função auxiliar do domínio de auditoria; usada para apoiar regras internas da classe EnterpriseAuditHashChainService.
- Linha 20: `public static validar()` — Valida payload, banco, regra, arquivo ou configuração.
- Linha 32: `private static garantirTabela(PDO $pdo)` — Função auxiliar do domínio de auditoria; usada para apoiar regras internas da classe EnterpriseAuditHashChainService.

### `app/Services/EnterpriseV26ReadinessService.php` — `EnterpriseV26ReadinessService`
- Linha 3: `public static resumo()` — Função auxiliar do domínio de regra de negócio/serviço; usada para apoiar regras internas da classe EnterpriseV26ReadinessService.

### `app/Services/ErrorCatalog.php` — `ErrorCatalog`
- Linha 3: `public static explain(string $code, string $message='')` — Função auxiliar do domínio de regra de negócio/serviço; usada para apoiar regras internas da classe ErrorCatalog.

### `app/Services/EstoqueEnterpriseService.php` — `EstoqueEnterpriseService`
- Linha 3: `public static config()` — Função auxiliar do domínio de estoque; usada para apoiar regras internas da classe EstoqueEnterpriseService.
- Linha 24: `public static salvarConfig(array $dados)` — Salva/atualiza dados do módulo de estoque.
- Linha 30: `public static registrarAlerta(string $sku, string $tipo, string $mensagem, string $severidade='alerta', array $ctx=[])` — Função auxiliar do domínio de estoque; usada para apoiar regras internas da classe EstoqueEnterpriseService.
- Linha 36: `public static enfileirarEstoque(string $origem, string $destino, string $sku, float $quantidade, array $payload=[])` — Função auxiliar do domínio de estoque; usada para apoiar regras internas da classe EstoqueEnterpriseService.
- Linha 52: `public static extrairEstoque(array $payload, string $origem)` — Função auxiliar do domínio de estoque; usada para apoiar regras internas da classe EstoqueEnterpriseService.
- Linha 62: `public static eventoJaProcessado(string $origem, string $referencia, string $sku, string $tipo)` — Função auxiliar do domínio de estoque; usada para apoiar regras internas da classe EstoqueEnterpriseService.
- Linha 74: `public static receberAtualizacao(string $origem, array $payload, string $raw='')` — Recebe e normaliza dados externos no módulo de estoque.
- Linha 95: `public static auditarSku(string $sku, string $evento, ?string $statusAnterior, ?string $statusNovo, ?float $saldoAnterior, ?float $saldoNovo, string $origem, string $mensagem, array $ctx=[])` — Registra ou consulta auditoria/histórico de estoque.
- Linha 102: `public static criarReconciliacaoManual()` — Cria registro, payload ou recurso do módulo de estoque.
- Linha 113: `public static proximaTentativa(int $tentativa)` — Função auxiliar do domínio de estoque; usada para apoiar regras internas da classe EstoqueEnterpriseService.

### `app/Services/EstoqueMapper.php` — `EstoqueMapper`
- Linha 3: `private static pick(array $arr, array $keys, $default='')` — Função auxiliar do domínio de estoque; usada para apoiar regras internas da classe EstoqueMapper.
- Linha 5: `public static extrairItensBaixa(array $payload)` — Função auxiliar do domínio de estoque; usada para apoiar regras internas da classe EstoqueMapper.
- Linha 33: `public static tinyEventoParaVsmBaixa(array $payload)` — Converte/mapeia dados entre formatos do módulo de estoque.

### `app/Services/EstoqueVsmSchedulerService.php` — `EstoqueVsmSchedulerService`
- Linha 8: `public static config()` — Função auxiliar do domínio de VSM; usada para apoiar regras internas da classe EstoqueVsmSchedulerService.
- Linha 28: `public static deveExecutar()` — Função auxiliar do domínio de VSM; usada para apoiar regras internas da classe EstoqueVsmSchedulerService.
- Linha 52: `public static criarExecucao(string $modo='automatico')` — Cria registro, payload ou recurso do módulo de VSM.
- Linha 60: `public static produtosParaConsulta(int $limite)` — Converte/mapeia dados entre formatos do módulo de VSM.
- Linha 86: `public static executarConsultaProgramada(bool $forcar=false, ?int $limiteOverride=null, string $modo='automatico')` — Função auxiliar do domínio de VSM; usada para apoiar regras internas da classe EstoqueVsmSchedulerService.
- Linha 154: `private static adquirirLock()` — Função auxiliar do domínio de VSM; usada para apoiar regras internas da classe EstoqueVsmSchedulerService.
- Linha 162: `private static liberarLock()` — Função auxiliar do domínio de VSM; usada para apoiar regras internas da classe EstoqueVsmSchedulerService.
- Linha 167: `private static consultarEstoqueVSM($client, string $sku)` — Função auxiliar do domínio de VSM; usada para apoiar regras internas da classe EstoqueVsmSchedulerService.
- Linha 172: `public static extrairSaldo(array $ret)` — Função auxiliar do domínio de VSM; usada para apoiar regras internas da classe EstoqueVsmSchedulerService.
- Linha 188: `private static getPath($arr, array $path)` — Função auxiliar do domínio de VSM; usada para apoiar regras internas da classe EstoqueVsmSchedulerService.
- Linha 194: `private static findNumericByKeys($data, array $keys)` — Função auxiliar do domínio de VSM; usada para apoiar regras internas da classe EstoqueVsmSchedulerService.
- Linha 203: `private static saldoCache(string $sku)` — Função auxiliar do domínio de VSM; usada para apoiar regras internas da classe EstoqueVsmSchedulerService.
- Linha 208: `private static atualizarCacheVSM(string $sku, float $saldo, array $ret)` — Atualiza cadastro, status ou estrutura no módulo de VSM.
- Linha 218: `private static registrarResultado(int $execId, string $sku, string $status, ?float $saldo, $saldoAnterior, bool $alterou, array $ret, ?string $erro)` — Função auxiliar do domínio de VSM; usada para apoiar regras internas da classe EstoqueVsmSchedulerService.

### `app/Services/FileIntegrityService.php` — `FileIntegrityService`
- Linha 3: `public static manifestPath()` — Função auxiliar do domínio de regra de negócio/serviço; usada para apoiar regras internas da classe FileIntegrityService.
- Linha 4: `public static criticalFiles()` — Função auxiliar do domínio de regra de negócio/serviço; usada para apoiar regras internas da classe FileIntegrityService.
- Linha 12: `public static buildManifest()` — Função auxiliar do domínio de regra de negócio/serviço; usada para apoiar regras internas da classe FileIntegrityService.
- Linha 20: `private static hmacKey()` — Função auxiliar do domínio de regra de negócio/serviço; usada para apoiar regras internas da classe FileIntegrityService.
- Linha 27: `private static signFiles(array $files)` — Função auxiliar do domínio de regra de negócio/serviço; usada para apoiar regras internas da classe FileIntegrityService.
- Linha 32: `public static saveManifest()` — Função auxiliar do domínio de regra de negócio/serviço; usada para apoiar regras internas da classe FileIntegrityService.
- Linha 36: `public static check()` — Verifica se uma condição de autenticação, permissão ou saúde está válida.

### `app/Services/FiscalEnterpriseService.php` — `FiscalEnterpriseService`
- Linha 3: `public static dashboard()` — Função auxiliar do domínio de fiscal/NF-e; usada para apoiar regras internas da classe FiscalEnterpriseService.
- Linha 18: `public static timeline(int $notaId)` — Função auxiliar do domínio de fiscal/NF-e; usada para apoiar regras internas da classe FiscalEnterpriseService.
- Linha 32: `public static xmls(string $status='')` — Função auxiliar do domínio de fiscal/NF-e; usada para apoiar regras internas da classe FiscalEnterpriseService.
- Linha 40: `public static reconciliacao()` — Função auxiliar do domínio de fiscal/NF-e; usada para apoiar regras internas da classe FiscalEnterpriseService.
- Linha 51: `public static health()` — Executa verificação de saúde/disponibilidade em fiscal/NF-e.
- Linha 62: `public static proximaTentativa(int $tentativa)` — Função auxiliar do domínio de fiscal/NF-e; usada para apoiar regras internas da classe FiscalEnterpriseService.
- Linha 74: `public static registrarStatus(int $notaId, string $novo, ?string $anterior=null, string $motivo='')` — Função auxiliar do domínio de fiscal/NF-e; usada para apoiar regras internas da classe FiscalEnterpriseService.
- Linha 82: `public static reprocessar(int $integracaoId)` — Função auxiliar do domínio de fiscal/NF-e; usada para apoiar regras internas da classe FiscalEnterpriseService.

### `app/Services/FiscalIntegrationService.php` — `FiscalIntegrationService`
- Linha 3: `public static resumo()` — Função auxiliar do domínio de fiscal/NF-e; usada para apoiar regras internas da classe FiscalIntegrationService.
- Linha 20: `public static registrarEvento(int $notaId, string $tipo, string $mensagem = '', array $payload = [])` — Função auxiliar do domínio de fiscal/NF-e; usada para apoiar regras internas da classe FiscalIntegrationService.
- Linha 26: `public static listar(string $status = '', int $limit = 100)` — Lista registros para tela, relatório ou API.
- Linha 33: `public static pendenciarEnvioVsm(int $notaId, string $chave = '')` — Função auxiliar do domínio de fiscal/NF-e; usada para apoiar regras internas da classe FiscalIntegrationService.
- Linha 41: `public static marcarReenvio(int $integracaoId)` — Função auxiliar do domínio de fiscal/NF-e; usada para apoiar regras internas da classe FiscalIntegrationService.

### `app/Services/HealthCheckService.php` — `HealthCheckService`
- Linha 3: `public static run()` — Função auxiliar do domínio de health check; usada para apoiar regras internas da classe HealthCheckService.
- Linha 25: `public static testarVsm(array $cfg)` — Executa teste/diagnóstico controlado do módulo de health check.
- Linha 90: `private static check(string $nome, bool $ok, string $detalhe, string $acao)` — Verifica se uma condição de autenticação, permissão ou saúde está válida.

### `app/Services/HomologationReportService.php` — `HomologationReportService`
- Linha 3: `public static gerarHtml(PDO $pdo)` — Gera artefato, relatório, chave ou arquivo no módulo de homologação.

### `app/Services/HostingCompatibilityService.php` — `HostingCompatibilityService`
- Linha 3: `public static ambiente()` — Função auxiliar do domínio de regra de negócio/serviço; usada para apoiar regras internas da classe HostingCompatibilityService.

### `app/Services/InstallationPrecheckService.php` — `InstallationPrecheckService`
- Linha 3: `public static run()` — Função auxiliar do domínio de regra de negócio/serviço; usada para apoiar regras internas da classe InstallationPrecheckService.
- Linha 21: `private static dirWritable(string $path)` — Função auxiliar do domínio de regra de negócio/serviço; usada para apoiar regras internas da classe InstallationPrecheckService.
- Linha 22: `public static score()` — Função auxiliar do domínio de regra de negócio/serviço; usada para apoiar regras internas da classe InstallationPrecheckService.

### `app/Services/InstallationRecoveryService.php` — `InstallationRecoveryService`
- Linha 3: `public static status()` — Função auxiliar do domínio de regra de negócio/serviço; usada para apoiar regras internas da classe InstallationRecoveryService.

### `app/Services/IntegrationConfig.php` — `IntegrationConfig`
- Linha 3: `public static get()` — Lê configuração, registro ou valor persistido.

### `app/Services/IntegrationOrchestratorService.php` — `IntegrationOrchestratorService`
- Linha 35: `public static catalogoFluxos()` — Função auxiliar do domínio de regra de negócio/serviço; usada para apoiar regras internas da classe IntegrationOrchestratorService.
- Linha 95: `public static keys()` — Retorna a lista de chaves oficiais aceitas pelo serviço.
- Linha 101: `public static all()` — Retorna coleção completa de configurações, regras ou registros.
- Linha 110: `public static normalizarOrdem(string $ordem)` — Padroniza formato de dados usado por regra de negócio/serviço.
- Linha 119: `public static ordemLista()` — Função auxiliar do domínio de regra de negócio/serviço; usada para apoiar regras internas da classe IntegrationOrchestratorService.
- Linha 121: `public static catalogoOrdem()` — Função auxiliar do domínio de regra de negócio/serviço; usada para apoiar regras internas da classe IntegrationOrchestratorService.
- Linha 127: `public static label(string $key)` — Converte chave técnica em rótulo legível para a interface.
- Linha 158: `public static gruposFluxos(array $cfg=[])` — Função auxiliar do domínio de regra de negócio/serviço; usada para apoiar regras internas da classe IntegrationOrchestratorService.
- Linha 170: `public static fluxos(array $rules = [])` — Função auxiliar do domínio de regra de negócio/serviço; usada para apoiar regras internas da classe IntegrationOrchestratorService.
- Linha 179: `public static podeExecutar(string $regra, array $payload=[])` — Função auxiliar do domínio de regra de negócio/serviço; usada para apoiar regras internas da classe IntegrationOrchestratorService.

### `app/Services/IntegrationReplayGuardService.php` — `IntegrationReplayGuardService`
- Linha 3: `public static ensureSchema()` — Garante que tabelas/colunas necessárias existam antes da operação.
- Linha 31: `private static safeAlter(PDO $pdo, string $sql)` — Função auxiliar do domínio de regra de negócio/serviço; usada para apoiar regras internas da classe IntegrationReplayGuardService.
- Linha 35: `public static guard(string $origem, string $raw, int $windowSeconds = 600, array $context = [])` — Função auxiliar do domínio de regra de negócio/serviço; usada para apoiar regras internas da classe IntegrationReplayGuardService.
- Linha 73: `private static cleanup(PDO $pdo, int $windowSeconds)` — Função auxiliar do domínio de regra de negócio/serviço; usada para apoiar regras internas da classe IntegrationReplayGuardService.
- Linha 80: `private static hashKey()` — Gera ou valida hash para integridade/idempotência em regra de negócio/serviço.

### `app/Services/IntegrationSecurityService.php` — `IntegrationSecurityService`
- Linha 3: `public static traceHeaders()` — Função auxiliar do domínio de segurança; usada para apoiar regras internas da classe IntegrationSecurityService.
- Linha 7: `public static vsmHmacHeaders(string $rawBody)` — Função auxiliar do domínio de segurança; usada para apoiar regras internas da classe IntegrationSecurityService.
- Linha 23: `public static maskHeaders(array $headers)` — Função auxiliar do domínio de segurança; usada para apoiar regras internas da classe IntegrationSecurityService.

### `app/Services/IpBlockService.php` — `IpBlockService`
- Linha 3: `public static enforce()` — Função auxiliar do domínio de regra de negócio/serviço; usada para apoiar regras internas da classe IpBlockService.
- Linha 18: `public static block(string $ip, string $motivo, int $minutes=60, string $severity='alto')` — Bloqueia operação insegura ou não autorizada em regra de negócio/serviço.

### `app/Services/JsonLogger.php` — `JsonLogger`
- Linha 3: `public static write(string $canal, string $nivel, string $mensagem, array $contexto=[])` — Função auxiliar do domínio de regra de negócio/serviço; usada para apoiar regras internas da classe JsonLogger.

### `app/Services/LegacyInventoryService.php` — `LegacyInventoryService`
- Linha 3: `public static controllersServices()` — Função auxiliar do domínio de regra de negócio/serviço; usada para apoiar regras internas da classe LegacyInventoryService.
- Linha 18: `private static classificar(string $name, string $rel, string $tipo)` — Função auxiliar do domínio de regra de negócio/serviço; usada para apoiar regras internas da classe LegacyInventoryService.
- Linha 26: `private static resumo(array $items)` — Função auxiliar do domínio de regra de negócio/serviço; usada para apoiar regras internas da classe LegacyInventoryService.

### `app/Services/Logger.php` — `Logger`
- Linha 3: `public static log(string $tipo, string $mensagem, $payload=null, string $nivel='info', ?string $codigoErro=null)` — Função auxiliar do domínio de regra de negócio/serviço; usada para apoiar regras internas da classe Logger.
- Linha 37: `public static info(string $tipo, string $mensagem, $payload=null)` — Função auxiliar do domínio de regra de negócio/serviço; usada para apoiar regras internas da classe Logger.
- Linha 38: `public static alerta(string $tipo, string $mensagem, $payload=null, ?string $codigoErro=null)` — Função auxiliar do domínio de regra de negócio/serviço; usada para apoiar regras internas da classe Logger.
- Linha 39: `public static erro(string $tipo, string $mensagem, $payload=null, ?string $codigoErro=null)` — Função auxiliar do domínio de regra de negócio/serviço; usada para apoiar regras internas da classe Logger.
- Linha 40: `public static critico(string $tipo, string $mensagem, $payload=null, ?string $codigoErro=null)` — Função auxiliar do domínio de regra de negócio/serviço; usada para apoiar regras internas da classe Logger.
- Linha 42: `private static writeFile(string $name, ?string $trace, string $nivel, string $tipo, string $mensagem, $payload=null)` — Função auxiliar do domínio de regra de negócio/serviço; usada para apoiar regras internas da classe Logger.
- Linha 53: `private static hasTraceColumn(PDO $pdo)` — Função auxiliar do domínio de regra de negócio/serviço; usada para apoiar regras internas da classe Logger.

### `app/Services/MenuActionTestService.php` — `MenuActionTestService`
- Linha 3: `public static run()` — Função auxiliar do domínio de regra de negócio/serviço; usada para apoiar regras internas da classe MenuActionTestService.

### `app/Services/MetricsService.php` — `MetricsService`
- Linha 3: `public static registrar(string $sistema, ?string $endpoint, ?int $httpCode, ?int $tempoMs, bool $sucesso, ?string $codigoErro=null)` — Registra evento, histórico, auditoria ou snapshot no banco.

### `app/Services/ModularDatabaseAuditService.php` — `ModularDatabaseAuditService`
- Linha 3: `public static resumo()` — Função auxiliar do domínio de auditoria; usada para apoiar regras internas da classe ModularDatabaseAuditService.

### `app/Services/ModuleDatabaseService.php` — `ModuleDatabaseService`
- Linha 3: `public static modulos()` — Função auxiliar do domínio de banco de dados; usada para apoiar regras internas da classe ModuleDatabaseService.
- Linha 34: `public static status(string $module)` — Função auxiliar do domínio de banco de dados; usada para apoiar regras internas da classe ModuleDatabaseService.
- Linha 45: `public static tabelasDoModulo(string $module)` — Função auxiliar do domínio de banco de dados; usada para apoiar regras internas da classe ModuleDatabaseService.
- Linha 59: `public static relatorio()` — Função auxiliar do domínio de banco de dados; usada para apoiar regras internas da classe ModuleDatabaseService.

### `app/Services/ModuleHealthService.php` — `ModuleHealthService`
- Linha 3: `public static checks()` — Função auxiliar do domínio de health check; usada para apoiar regras internas da classe ModuleHealthService.

### `app/Services/MultiDbMigrationService.php` — `MultiDbMigrationService`
- Linha 3: `public static moduleSqlFiles()` — Função auxiliar do domínio de migrações; usada para apoiar regras internas da classe MultiDbMigrationService.
- Linha 10: `public static instalarModulo(string $module)` — Função auxiliar do domínio de migrações; usada para apoiar regras internas da classe MultiDbMigrationService.
- Linha 16: `public static instalarTodos()` — Função auxiliar do domínio de migrações; usada para apoiar regras internas da classe MultiDbMigrationService.
- Linha 17: `public static runSqlFile(PDO $pdo, string $file)` — Função auxiliar do domínio de migrações; usada para apoiar regras internas da classe MultiDbMigrationService.
- Linha 23: `private static splitSql(string $sql)` — Função auxiliar do domínio de migrações; usada para apoiar regras internas da classe MultiDbMigrationService.

### `app/Services/NotificationService.php` — `NotificationService`
- Linha 3: `public static criar(string $tipo, string $titulo, string $mensagem, string $severidade='info', array $opts=[])` — Cria registro, payload ou recurso do módulo de notificações.
- Linha 28: `public static novoPedido(string $pedido, array $payload=[])` — Função auxiliar do domínio de notificações; usada para apoiar regras internas da classe NotificationService.
- Linha 38: `public static erroIntegracao(string $titulo, string $mensagem, array $opts=[])` — Função auxiliar do domínio de notificações; usada para apoiar regras internas da classe NotificationService.
- Linha 43: `public static fila(string $titulo, string $mensagem, string $severidade='alerta', array $opts=[])` — Função auxiliar do domínio de notificações; usada para apoiar regras internas da classe NotificationService.
- Linha 47: `public static listar(int $limit=100, bool $somenteNaoLidas=false)` — Lista registros para tela, relatório ou API.
- Linha 53: `public static recentes(int $ultimoId=0, int $limit=15)` — Função auxiliar do domínio de notificações; usada para apoiar regras internas da classe NotificationService.
- Linha 60: `public static totalNaoLidas()` — Função auxiliar do domínio de notificações; usada para apoiar regras internas da classe NotificationService.
- Linha 65: `public static marcarLida(int $id)` — Função auxiliar do domínio de notificações; usada para apoiar regras internas da classe NotificationService.
- Linha 71: `public static marcarTodasLidas()` — Função auxiliar do domínio de notificações; usada para apoiar regras internas da classe NotificationService.

### `app/Services/OAuthV3ChecklistService.php` — `OAuthV3ChecklistService`
- Linha 3: `public static passos()` — Função auxiliar do domínio de autenticação/sessão; usada para apoiar regras internas da classe OAuthV3ChecklistService.
- Linha 23: `public static score()` — Função auxiliar do domínio de autenticação/sessão; usada para apoiar regras internas da classe OAuthV3ChecklistService.

### `app/Services/OperationCenterService.php` — `OperationCenterService`
- Linha 8: `public static tableExists(string $table)` — Função auxiliar do domínio de operação; usada para apoiar regras internas da classe OperationCenterService.
- Linha 17: `public static columnExists(string $table, string $column)` — Função auxiliar do domínio de operação; usada para apoiar regras internas da classe OperationCenterService.
- Linha 26: `public static safeCount(string $table, string $where='1=1')` — Função auxiliar do domínio de operação; usada para apoiar regras internas da classe OperationCenterService.
- Linha 34: `public static safeCountInfo(string $table, string $where='1=1', string $label='')` — Função auxiliar do domínio de operação; usada para apoiar regras internas da classe OperationCenterService.
- Linha 47: `public static workerCards()` — Função auxiliar do domínio de operação; usada para apoiar regras internas da classe OperationCenterService.
- Linha 65: `public static ultimoHomologacao(string $table)` — Função auxiliar do domínio de operação; usada para apoiar regras internas da classe OperationCenterService.
- Linha 88: `public static summary(?array $cfgInt=null)` — Função auxiliar do domínio de operação; usada para apoiar regras internas da classe OperationCenterService.
- Linha 131: `public static estoqueResumo()` — Função auxiliar do domínio de operação; usada para apoiar regras internas da classe OperationCenterService.
- Linha 141: `public static xmlResumo()` — Função auxiliar do domínio de operação; usada para apoiar regras internas da classe OperationCenterService.
- Linha 150: `public static pedidosResumo()` — Função auxiliar do domínio de operação; usada para apoiar regras internas da classe OperationCenterService.
- Linha 161: `public static fontesDashboard()` — Função auxiliar do domínio de operação; usada para apoiar regras internas da classe OperationCenterService.
- Linha 174: `public static scoreDetalhado()` — Função auxiliar do domínio de operação; usada para apoiar regras internas da classe OperationCenterService.
- Linha 194: `public static score()` — Função auxiliar do domínio de operação; usada para apoiar regras internas da classe OperationCenterService.

### `app/Services/OperationalRouteService.php` — `OperationalRouteService`
- Linha 3: `public static menuRoutes()` — Função auxiliar do domínio de operação; usada para apoiar regras internas da classe OperationalRouteService.
- Linha 17: `public static technicalRoutes()` — Função auxiliar do domínio de operação; usada para apoiar regras internas da classe OperationalRouteService.

### `app/Services/PayloadSnapshotService.php` — `PayloadSnapshotService`
- Linha 3: `public static registrar(?int $filaId, string $etapa, $conteudo, array $extra=[])` — Registra evento, histórico, auditoria ou snapshot no banco.

### `app/Services/PedidoCicloVidaService.php` — `PedidoCicloVidaService`
- Linha 3: `public static ensureSchema()` — Garante que tabelas/colunas necessárias existam antes da operação.
- Linha 12: `private static pdo()` — Função auxiliar do domínio de pedidos; usada para apoiar regras internas da classe PedidoCicloVidaService.
- Linha 13: `private static json($v)` — Função auxiliar do domínio de pedidos; usada para apoiar regras internas da classe PedidoCicloVidaService.
- Linha 14: `private static hashPayload($payload)` — Gera ou valida hash para integridade/idempotência em pedidos.
- Linha 16: `public static fromTinyPedido(array $payload, array $validacao, ?int $pedidoValidacaoId=null)` — Função auxiliar do domínio de pedidos; usada para apoiar regras internas da classe PedidoCicloVidaService.
- Linha 37: `public static snapshot(int $pedidoHubId, string $origem, ?string $destino, string $tipo, $payload)` — Função auxiliar do domínio de pedidos; usada para apoiar regras internas da classe PedidoCicloVidaService.
- Linha 44: `public static status(int $pedidoHubId, ?string $statusAnterior, string $statusNovo, string $origem, string $mensagem, ?string $erro=null, array $ctx=[])` — Função auxiliar do domínio de pedidos; usada para apoiar regras internas da classe PedidoCicloVidaService.
- Linha 59: `public static marcarEnviadoVsmPorTinyId(string $tinyId, array $retorno, bool $erro=false)` — Função auxiliar do domínio de pedidos; usada para apoiar regras internas da classe PedidoCicloVidaService.
- Linha 68: `private static parseXmlInfo(?string $xml)` — Função auxiliar do domínio de pedidos; usada para apoiar regras internas da classe PedidoCicloVidaService.
- Linha 82: `private static findPedidoByRetorno(array $payload)` — Função auxiliar do domínio de pedidos; usada para apoiar regras internas da classe PedidoCicloVidaService.
- Linha 95: `public static receberRetornoVsm(array $payload, ?string $xmlRaw=null)` — Recebe e normaliza dados externos no módulo de pedidos.
- Linha 130: `public static enviarXmlParaTiny(int $pedidoHubId)` — Envia dados para integração/destino externo no módulo de pedidos.
- Linha 157: `public static listar(string $status='', string $busca='')` — Lista registros para tela, relatório ou API.
- Linha 164: `public static detalhe(int $id)` — Carrega os detalhes completos de um registro.

### `app/Services/PedidoMapper.php` — `PedidoMapper`
- Linha 3: `private static onlyDigits($v)` — Função auxiliar do domínio de pedidos; usada para apoiar regras internas da classe PedidoMapper.
- Linha 4: `private static money($v)` — Função auxiliar do domínio de pedidos; usada para apoiar regras internas da classe PedidoMapper.
- Linha 5: `private static pick(array $arr, array $keys, $default='')` — Função auxiliar do domínio de pedidos; usada para apoiar regras internas da classe PedidoMapper.
- Linha 7: `private static cliente(array $vsm)` — Função auxiliar do domínio de pedidos; usada para apoiar regras internas da classe PedidoMapper.
- Linha 27: `public static vsmParaTiny(array $vsm)` — Converte/mapeia dados entre formatos do módulo de pedidos.
- Linha 71: `public static valorTotal(array $vsm)` — Função auxiliar do domínio de pedidos; usada para apoiar regras internas da classe PedidoMapper.
- Linha 82: `public static extrairPedidoTinyId(array $ret)` — Função auxiliar do domínio de pedidos; usada para apoiar regras internas da classe PedidoMapper.

### `app/Services/PedidoTinyVsmValidationService.php` — `PedidoTinyVsmValidationService`
- Linha 3: `public static ensureSchema()` — Garante que tabelas/colunas necessárias existam antes da operação.
- Linha 10: `private static digits($v)` — Função auxiliar do domínio de Tiny/Olist; usada para apoiar regras internas da classe PedidoTinyVsmValidationService.
- Linha 11: `private static data(array $payload)` — Função auxiliar do domínio de Tiny/Olist; usada para apoiar regras internas da classe PedidoTinyVsmValidationService.
- Linha 12: `public static pedidoId(array $payload)` — Função auxiliar do domínio de Tiny/Olist; usada para apoiar regras internas da classe PedidoTinyVsmValidationService.
- Linha 13: `public static toVsmPayload(array $payload)` — Função auxiliar do domínio de Tiny/Olist; usada para apoiar regras internas da classe PedidoTinyVsmValidationService.
- Linha 38: `public static validar(array $payload, ?array $cfg=null)` — Valida payload, banco, regra, arquivo ou configuração.
- Linha 52: `public static registrar(array $payload, array $validacao, string $origem='tiny')` — Registra evento, histórico, auditoria ou snapshot no banco.
- Linha 64: `public static historico(int $id,string $acao,string $resultado,string $mensagem,array $ctx=[])` — Registra ou consulta auditoria/histórico de Tiny/Olist.
- Linha 65: `public static enfileirar(int $id)` — Função auxiliar do domínio de Tiny/Olist; usada para apoiar regras internas da classe PedidoTinyVsmValidationService.

### `app/Services/PedidoValidator.php` — `PedidoValidator`
- Linha 3: `private static digits($v)` — Função auxiliar do domínio de pedidos; usada para apoiar regras internas da classe PedidoValidator.
- Linha 4: `private static cpfValido(string $cpf)` — Função auxiliar do domínio de pedidos; usada para apoiar regras internas da classe PedidoValidator.
- Linha 9: `private static cnpjValido(string $cnpj)` — Função auxiliar do domínio de pedidos; usada para apoiar regras internas da classe PedidoValidator.
- Linha 14: `private static documentoValido(string $doc)` — Função auxiliar do domínio de pedidos; usada para apoiar regras internas da classe PedidoValidator.
- Linha 15: `private static emailValido(?string $email)` — Função auxiliar do domínio de pedidos; usada para apoiar regras internas da classe PedidoValidator.
- Linha 17: `public static validarVsm(array $vsm)` — Valida dados/regras antes de concluir operação de pedidos.

### `app/Services/PermissionService.php` — `PermissionService`
- Linha 3: `public static can(string $modulo, string $acao)` — Função auxiliar do domínio de regra de negócio/serviço; usada para apoiar regras internas da classe PermissionService.
- Linha 11: `public static require(string $modulo, string $acao)` — Exige permissão/autenticação antes de permitir a ação.

### `app/Services/PostInstallTestService.php` — `PostInstallTestService`
- Linha 3: `public static run()` — Função auxiliar do domínio de regra de negócio/serviço; usada para apoiar regras internas da classe PostInstallTestService.
- Linha 12: `public static score(array $tests)` — Função auxiliar do domínio de regra de negócio/serviço; usada para apoiar regras internas da classe PostInstallTestService.

### `app/Services/ProductApprovalPolicyService.php` — `ProductApprovalPolicyService`
- Linha 3: `public static ensureSchema()` — Garante que tabelas/colunas necessárias existam antes da operação.
- Linha 24: `public static config()` — Função auxiliar do domínio de regra de negócio/serviço; usada para apoiar regras internas da classe ProductApprovalPolicyService.
- Linha 37: `public static salvar(array $post)` — Persiste dados enviados pelo formulário.
- Linha 69: `public static autoProductAllowed(array $payload, string $sku, string $tipoFila, array $baseConfig)` — Autoriza operação conforme configuração/regra de regra de negócio/serviço.

### `app/Services/ProductionGoLiveService.php` — `ProductionGoLiveService`
- Linha 3: `public static ensureSchema()` — Garante que tabelas/colunas necessárias existam antes da operação.
- Linha 19: `public static executar()` — Executa processo operacional solicitado pelo painel ou worker.
- Linha 105: `public static historico(int $limit=5)` — Registra ou consulta auditoria/histórico de regra de negócio/serviço.
- Linha 113: `public static bloquearInstalador()` — Bloqueia operação insegura ou não autorizada em regra de negócio/serviço.
- Linha 122: `private static httpsAtivo()` — Função auxiliar do domínio de regra de negócio/serviço; usada para apoiar regras internas da classe ProductionGoLiveService.
- Linha 126: `private static tinyResumo(string $versao, array $cfg)` — Função auxiliar do domínio de regra de negócio/serviço; usada para apoiar regras internas da classe ProductionGoLiveService.
- Linha 139: `private static xmlNfeResumo()` — Função auxiliar do domínio de regra de negócio/serviço; usada para apoiar regras internas da classe ProductionGoLiveService.
- Linha 147: `private static backupResumo()` — Função auxiliar do domínio de regra de negócio/serviço; usada para apoiar regras internas da classe ProductionGoLiveService.
- Linha 153: `private static resumoExecutivo(string $status, int $bloqueios, int $alertas, int $score)` — Função auxiliar do domínio de regra de negócio/serviço; usada para apoiar regras internas da classe ProductionGoLiveService.

### `app/Services/ProductionReadinessV24Service.php` — `ProductionReadinessV24Service`
- Linha 8: `public static checks()` — Função auxiliar do domínio de regra de negócio/serviço; usada para apoiar regras internas da classe ProductionReadinessV24Service.
- Linha 22: `public static scores()` — Função auxiliar do domínio de regra de negócio/serviço; usada para apoiar regras internas da classe ProductionReadinessV24Service.
- Linha 47: `public static checklist()` — Função auxiliar do domínio de regra de negócio/serviço; usada para apoiar regras internas da classe ProductionReadinessV24Service.
- Linha 58: `public static gerarSnapshotUpgrade(string $versao='V24')` — Gera artefato, relatório, chave ou arquivo no módulo de regra de negócio/serviço.
- Linha 71: `private static hasConfig(array $cfg, string $key)` — Função auxiliar do domínio de regra de negócio/serviço; usada para apoiar regras internas da classe ProductionReadinessV24Service.
- Linha 72: `private static tableExists(string $table)` — Função auxiliar do domínio de regra de negócio/serviço; usada para apoiar regras internas da classe ProductionReadinessV24Service.
- Linha 75: `private static hasColumn(string $table,string $column)` — Função auxiliar do domínio de regra de negócio/serviço; usada para apoiar regras internas da classe ProductionReadinessV24Service.
- Linha 78: `private static tableList()` — Função auxiliar do domínio de regra de negócio/serviço; usada para apoiar regras internas da classe ProductionReadinessV24Service.

### `app/Services/ProductionReadinessV50Service.php` — `ProductionReadinessV50Service`
- Linha 3: `public static ensureSchema()` — Garante que tabelas/colunas necessárias existam antes da operação.
- Linha 14: `public static executar()` — Executa processo operacional solicitado pelo painel ou worker.

### `app/Services/ProductionReadyService.php` — `ProductionReadyService`
- Linha 3: `public static checks()` — Função auxiliar do domínio de regra de negócio/serviço; usada para apoiar regras internas da classe ProductionReadyService.

### `app/Services/ProdutoMapper.php` — `ProdutoMapper`
- Linha 3: `private static pick(array $arr, array $keys, $default='')` — Função auxiliar do domínio de produtos; usada para apoiar regras internas da classe ProdutoMapper.
- Linha 7: `private static onlyDigits($v)` — Função auxiliar do domínio de produtos; usada para apoiar regras internas da classe ProdutoMapper.
- Linha 8: `private static money($v)` — Função auxiliar do domínio de produtos; usada para apoiar regras internas da classe ProdutoMapper.
- Linha 9: `public static sku(array $vsm)` — Função auxiliar do domínio de produtos; usada para apoiar regras internas da classe ProdutoMapper.
- Linha 10: `public static nome(array $vsm)` — Função auxiliar do domínio de produtos; usada para apoiar regras internas da classe ProdutoMapper.
- Linha 12: `public static normalizarAtivo($valor)` — Padroniza formato de dados usado por produtos.
- Linha 21: `public static situacaoTiny(array $vsm)` — Função auxiliar do domínio de produtos; usada para apoiar regras internas da classe ProdutoMapper.
- Linha 28: `public static estoque(array $vsm)` — Função auxiliar do domínio de produtos; usada para apoiar regras internas da classe ProdutoMapper.
- Linha 36: `public static determinarEvento(array $vsm)` — Função auxiliar do domínio de produtos; usada para apoiar regras internas da classe ProdutoMapper.
- Linha 57: `public static referenciaEvento(array $vsm, string $tipoEvento)` — Função auxiliar do domínio de produtos; usada para apoiar regras internas da classe ProdutoMapper.
- Linha 67: `public static validarVsmProduto(array $vsm)` — Valida dados/regras antes de concluir operação de produtos.
- Linha 90: `public static vsmParaTiny(array $vsm)` — Converte/mapeia dados entre formatos do módulo de produtos.
- Linha 115: `public static vsmStatusParaTiny(array $vsm)` — Converte/mapeia dados entre formatos do módulo de produtos.
- Linha 123: `public static extrairProdutoTinyId(array $ret)` — Função auxiliar do domínio de produtos; usada para apoiar regras internas da classe ProdutoMapper.

### `app/Services/ProdutoTinyPreflightService.php` — `ProdutoTinyPreflightService`
- Linha 3: `private static normalizarSku(string $sku)` — Padroniza formato de dados usado por Tiny/Olist.
- Linha 5: `public static analisarRetornoPesquisa(array $ret)` — Função auxiliar do domínio de Tiny/Olist; usada para apoiar regras internas da classe ProdutoTinyPreflightService.
- Linha 34: `public static consultarOuPendenciar(TinyClientInterface $tiny, string $sku, string $tipoEvento, array $payload, int $filaId, string $trace)` — Função auxiliar do domínio de Tiny/Olist; usada para apoiar regras internas da classe ProdutoTinyPreflightService.
- Linha 61: `public static registrarPendencia(string $sku, string $tipoEvento, string $motivo, string $mensagem, array $payload, array $retorno, int $filaId, string $trace)` — Função auxiliar do domínio de Tiny/Olist; usada para apoiar regras internas da classe ProdutoTinyPreflightService.
- Linha 74: `public static registrarAlertaInativoComEstoque(string $sku, float $estoque, array $payload, int $filaId, string $trace)` — Função auxiliar do domínio de Tiny/Olist; usada para apoiar regras internas da classe ProdutoTinyPreflightService.

### `app/Services/ProdutoVsmApprovalGuardService.php` — `ProdutoVsmApprovalGuardService`
- Linha 7: `public static payloadFromPending(array $p)` — Função auxiliar do domínio de VSM; usada para apoiar regras internas da classe ProdutoVsmApprovalGuardService.
- Linha 12: `public static ean(array $p, array $payload)` — Função auxiliar do domínio de VSM; usada para apoiar regras internas da classe ProdutoVsmApprovalGuardService.
- Linha 16: `public static ncm(array $payload)` — Função auxiliar do domínio de VSM; usada para apoiar regras internas da classe ProdutoVsmApprovalGuardService.
- Linha 20: `public static name(array $p, array $payload)` — Função auxiliar do domínio de VSM; usada para apoiar regras internas da classe ProdutoVsmApprovalGuardService.
- Linha 24: `public static categoryMapped(array $p)` — Função auxiliar do domínio de VSM; usada para apoiar regras internas da classe ProdutoVsmApprovalGuardService.
- Linha 28: `public static duplicateCandidates(array $p, array $payload)` — Função auxiliar do domínio de VSM; usada para apoiar regras internas da classe ProdutoVsmApprovalGuardService.
- Linha 66: `public static checklist(array $p)` — Função auxiliar do domínio de VSM; usada para apoiar regras internas da classe ProdutoVsmApprovalGuardService.
- Linha 87: `public static registrarHistorico(int $pendenciaId, string $acao, string $resultado, string $mensagem, array $contexto=[])` — Registra ou consulta auditoria/histórico de VSM.

### `app/Services/ProdutoVsmGovernanceService.php` — `ProdutoVsmGovernanceService`
- Linha 3: `public static isNewProductEvent(string $tipoFila)` — Função auxiliar do domínio de VSM; usada para apoiar regras internas da classe ProdutoVsmGovernanceService.
- Linha 7: `public static sku(string $sku)` — Função auxiliar do domínio de VSM; usada para apoiar regras internas da classe ProdutoVsmGovernanceService.
- Linha 9: `public static mappingForSku(string $sku)` — Converte/mapeia dados entre formatos do módulo de VSM.
- Linha 24: `public static categoryMappingForPayload(array $payload)` — Função auxiliar do domínio de VSM; usada para apoiar regras internas da classe ProdutoVsmGovernanceService.
- Linha 47: `public static isAutoCreateAllowed(array $config)` — Autoriza operação conforme configuração/regra de VSM.
- Linha 56: `public static createPending(array $payload, string $sku, string $tipoEvento, string $motivo, string $mensagem, ?int $filaId=null, ?string $traceId=null, array $extras=[])` — Função auxiliar do domínio de VSM; usada para apoiar regras internas da classe ProdutoVsmGovernanceService.
- Linha 93: `public static evaluate(array $payload, string $sku, string $tipoFila, array $config)` — Função auxiliar do domínio de VSM; usada para apoiar regras internas da classe ProdutoVsmGovernanceService.

### `app/Services/QrCodeService.php` — `QrCodeService`
- Linha 21: `public static svg(string $text, int $scale = 5, int $border = 4)` — Função auxiliar do domínio de QR Code/2FA; usada para apoiar regras internas da classe QrCodeService.
- Linha 27: `public static dataUri(string $text, int $scale = 5, int $border = 4)` — Função auxiliar do domínio de QR Code/2FA; usada para apoiar regras internas da classe QrCodeService.
- Linha 31: `private encode(string $text)` — Função auxiliar do domínio de QR Code/2FA; usada para apoiar regras internas da classe QrCodeService.
- Linha 62: `private chooseVersion(int $byteLength)` — Função auxiliar do domínio de QR Code/2FA; usada para apoiar regras internas da classe QrCodeService.
- Linha 73: `private createDataCodewords(array $bytes, int $version)` — Função auxiliar do domínio de QR Code/2FA; usada para apoiar regras internas da classe QrCodeService.
- Linha 95: `private appendBits(array &$bits, int $value, int $length)` — Função auxiliar do domínio de QR Code/2FA; usada para apoiar regras internas da classe QrCodeService.
- Linha 99: `private numDataCodewords(int $version)` — Função auxiliar do domínio de QR Code/2FA; usada para apoiar regras internas da classe QrCodeService.
- Linha 103: `private addErrorCorrectionAndInterleave(array $data, int $version)` — Função auxiliar do domínio de QR Code/2FA; usada para apoiar regras internas da classe QrCodeService.
- Linha 135: `private reedSolomonRemainder(array $data, int $degree)` — Função auxiliar do domínio de QR Code/2FA; usada para apoiar regras internas da classe QrCodeService.
- Linha 149: `private reedSolomonDivisor(int $degree)` — Função auxiliar do domínio de QR Code/2FA; usada para apoiar regras internas da classe QrCodeService.
- Linha 163: `private gfMultiply(int $x, int $y)` — Função auxiliar do domínio de QR Code/2FA; usada para apoiar regras internas da classe QrCodeService.
- Linha 172: `private drawFunctionPatterns()` — Função auxiliar do domínio de QR Code/2FA; usada para apoiar regras internas da classe QrCodeService.
- Linha 197: `private drawFinderPattern(int $cx, int $cy)` — Função auxiliar do domínio de QR Code/2FA; usada para apoiar regras internas da classe QrCodeService.
- Linha 209: `private drawAlignmentPattern(int $cx, int $cy)` — Função auxiliar do domínio de QR Code/2FA; usada para apoiar regras internas da classe QrCodeService.
- Linha 218: `private setFunctionModule(int $x, int $y, bool $black)` — Função auxiliar do domínio de QR Code/2FA; usada para apoiar regras internas da classe QrCodeService.
- Linha 224: `private drawFormatBits(int $mask)` — Função auxiliar do domínio de QR Code/2FA; usada para apoiar regras internas da classe QrCodeService.
- Linha 241: `private drawVersionBits()` — Função auxiliar do domínio de QR Code/2FA; usada para apoiar regras internas da classe QrCodeService.
- Linha 254: `private drawCodewords(array $codewords)` — Função auxiliar do domínio de QR Code/2FA; usada para apoiar regras internas da classe QrCodeService.
- Linha 277: `private applyMask(int $mask)` — Função auxiliar do domínio de QR Code/2FA; usada para apoiar regras internas da classe QrCodeService.
- Linha 286: `private maskBit(int $mask, int $x, int $y)` — Função auxiliar do domínio de QR Code/2FA; usada para apoiar regras internas da classe QrCodeService.
- Linha 300: `private penaltyScore()` — Função auxiliar do domínio de QR Code/2FA; usada para apoiar regras internas da classe QrCodeService.
- Linha 335: `private toSvg(int $scale, int $border)` — Função auxiliar do domínio de QR Code/2FA; usada para apoiar regras internas da classe QrCodeService.

### `app/Services/QueueAnalyticsService.php` — `QueueAnalyticsService`
- Linha 3: `public static resumo()` — Função auxiliar do domínio de regra de negócio/serviço; usada para apoiar regras internas da classe QueueAnalyticsService.

### `app/Services/QueueService.php` — `QueueService`
- Linha 5: `public static liberarTravados()` — Função auxiliar do domínio de regra de negócio/serviço; usada para apoiar regras internas da classe QueueService.
- Linha 12: `public static pegarProximo()` — Função auxiliar do domínio de regra de negócio/serviço; usada para apoiar regras internas da classe QueueService.
- Linha 30: `public static marcarResultado(int $id, bool $sucesso, array $retorno, ?string $codigoErro=null)` — Função auxiliar do domínio de regra de negócio/serviço; usada para apoiar regras internas da classe QueueService.
- Linha 51: `public static reprocessar(int $id)` — Função auxiliar do domínio de regra de negócio/serviço; usada para apoiar regras internas da classe QueueService.

### `app/Services/QueueV24AnalyticsService.php` — `QueueV24AnalyticsService`
- Linha 3: `public static resumoAvancado()` — Função auxiliar do domínio de regra de negócio/serviço; usada para apoiar regras internas da classe QueueV24AnalyticsService.
- Linha 12: `public static snapshot()` — Função auxiliar do domínio de regra de negócio/serviço; usada para apoiar regras internas da classe QueueV24AnalyticsService.
- Linha 19: `private static tableExists(string $table)` — Função auxiliar do domínio de regra de negócio/serviço; usada para apoiar regras internas da classe QueueV24AnalyticsService.
- Linha 20: `private static hasColumn(string $table,string $column)` — Função auxiliar do domínio de regra de negócio/serviço; usada para apoiar regras internas da classe QueueV24AnalyticsService.

### `app/Services/RealtimeHealthService.php` — `RealtimeHealthService`
- Linha 3: `public static snapshot()` — Função auxiliar do domínio de health check; usada para apoiar regras internas da classe RealtimeHealthService.
- Linha 14: `private static db()` — Função auxiliar do domínio de health check; usada para apoiar regras internas da classe RealtimeHealthService.
- Linha 24: `private static fila()` — Função auxiliar do domínio de health check; usada para apoiar regras internas da classe RealtimeHealthService.
- Linha 25: `private static tiny(string $label,string $cb,string $table)` — Função auxiliar do domínio de health check; usada para apoiar regras internas da classe RealtimeHealthService.
- Linha 26: `private static vsm()` — Função auxiliar do domínio de health check; usada para apoiar regras internas da classe RealtimeHealthService.
- Linha 27: `private static fiscal()` — Função auxiliar do domínio de health check; usada para apoiar regras internas da classe RealtimeHealthService.
- Linha 28: `private static c(string $nome,string $status,string $detalhe)` — Função auxiliar do domínio de health check; usada para apoiar regras internas da classe RealtimeHealthService.

### `app/Services/ReconciliationService.php` — `ReconciliationService`
- Linha 3: `public static registrarManual(string $sku, float $tiny, float $vsm, string $origem='manual', array $detalhesExtra=[])` — Função auxiliar do domínio de regra de negócio/serviço; usada para apoiar regras internas da classe ReconciliationService.
- Linha 12: `public static reconciliarSku(string $sku)` — Função auxiliar do domínio de regra de negócio/serviço; usada para apoiar regras internas da classe ReconciliationService.
- Linha 28: `public static resumo()` — Função auxiliar do domínio de regra de negócio/serviço; usada para apoiar regras internas da classe ReconciliationService.

### `app/Services/RetentionCleanupService.php` — `RetentionCleanupService`
- Linha 3: `public static executar(int $dias=90, bool $dryRun=true)` — Executa processo operacional solicitado pelo painel ou worker.

### `app/Services/RetentionService.php` — `RetentionService`
- Linha 3: `public static limparOperacional(int $diasLogs=90, int $diasAuditoria=180, int $diasWebhooks=180)` — Função auxiliar do domínio de regra de negócio/serviço; usada para apoiar regras internas da classe RetentionService.

### `app/Services/RetryPolicyService.php` — `RetryPolicyService`
- Linha 3: `public static attempts()` — Função auxiliar do domínio de regra de negócio/serviço; usada para apoiar regras internas da classe RetryPolicyService.
- Linha 7: `public static baseDelayMs()` — Função auxiliar do domínio de regra de negócio/serviço; usada para apoiar regras internas da classe RetryPolicyService.
- Linha 11: `public static shouldRetry(?int $http, ?string $curlError, $decoded=null)` — Função auxiliar do domínio de regra de negócio/serviço; usada para apoiar regras internas da classe RetryPolicyService.
- Linha 17: `public static sleep(int $attempt)` — Função auxiliar do domínio de regra de negócio/serviço; usada para apoiar regras internas da classe RetryPolicyService.

### `app/Services/RouteModuleRegistry.php` — `RouteModuleRegistry`
- Linha 3: `public static groups()` — Função auxiliar do domínio de regra de negócio/serviço; usada para apoiar regras internas da classe RouteModuleRegistry.
- Linha 13: `public static technicalPages()` — Função auxiliar do domínio de regra de negócio/serviço; usada para apoiar regras internas da classe RouteModuleRegistry.
- Linha 14: `public static moduleForPage(string $page)` — Função auxiliar do domínio de regra de negócio/serviço; usada para apoiar regras internas da classe RouteModuleRegistry.
- Linha 15: `public static architectureControllers()` — Função auxiliar do domínio de regra de negócio/serviço; usada para apoiar regras internas da classe RouteModuleRegistry.

### `app/Services/RouteRateLimiterService.php` — `RouteRateLimiterService`
- Linha 4: `public static ensureSchema()` — Garante que tabelas/colunas necessárias existam antes da operação.
- Linha 20: `public static enforce(?string $route=null)` — Função auxiliar do domínio de rate limit; usada para apoiar regras internas da classe RouteRateLimiterService.

### `app/Services/SafeDb.php` — `SafeDb`
- Linha 3: `public static assertIdentifier(string $value, string $label='identificador')` — Função auxiliar do domínio de regra de negócio/serviço; usada para apoiar regras internas da classe SafeDb.
- Linha 10: `public static assertTable(string $table)` — Função auxiliar do domínio de regra de negócio/serviço; usada para apoiar regras internas da classe SafeDb.
- Linha 18: `public static assertColumn(string $table, string $column)` — Função auxiliar do domínio de regra de negócio/serviço; usada para apoiar regras internas da classe SafeDb.
- Linha 25: `public static quoteIdent(string $ident)` — Função auxiliar do domínio de regra de negócio/serviço; usada para apoiar regras internas da classe SafeDb.
- Linha 28: `public static selectAll(string $table, ?string $orderBy=null, string $direction='DESC', int $limit=100)` — Função auxiliar do domínio de regra de negócio/serviço; usada para apoiar regras internas da classe SafeDb.
- Linha 41: `public static countWhere(string $table, array $filters=[])` — Função auxiliar do domínio de regra de negócio/serviço; usada para apoiar regras internas da classe SafeDb.
- Linha 60: `public static updateAllowed(string $table, array $data, array $allowed, array $where)` — Autoriza operação conforme configuração/regra de regra de negócio/serviço.

### `app/Services/SafeSqlUpgradeService.php` — `SafeSqlUpgradeService`
- Linha 4: `public static ensureMigrationsTable(PDO $pdo)` — Função auxiliar do domínio de regra de negócio/serviço; usada para apoiar regras internas da classe SafeSqlUpgradeService.
- Linha 17: `public static migrationApplied(PDO $pdo, string $migration)` — Função auxiliar do domínio de regra de negócio/serviço; usada para apoiar regras internas da classe SafeSqlUpgradeService.
- Linha 24: `public static recordMigration(PDO $pdo, string $migration, string $checksum='', string $status='aplicada', string $mensagem='')` — Função auxiliar do domínio de regra de negócio/serviço; usada para apoiar regras internas da classe SafeSqlUpgradeService.
- Linha 30: `public static addColumnIfMissing(PDO $pdo, string $table, string $column, string $definition)` — Função auxiliar do domínio de regra de negócio/serviço; usada para apoiar regras internas da classe SafeSqlUpgradeService.
- Linha 36: `public static tableExists(PDO $pdo, string $table)` — Função auxiliar do domínio de regra de negócio/serviço; usada para apoiar regras internas da classe SafeSqlUpgradeService.
- Linha 37: `public static runStatements(PDO $pdo, string $sql)` — Função auxiliar do domínio de regra de negócio/serviço; usada para apoiar regras internas da classe SafeSqlUpgradeService.

### `app/Services/Secrets.php` — `Secrets`
- Linha 3: `public static mask(?string $value)` — Função auxiliar do domínio de regra de negócio/serviço; usada para apoiar regras internas da classe Secrets.
- Linha 10: `public static keepIfMasked(string $posted, ?string $current)` — Função auxiliar do domínio de regra de negócio/serviço; usada para apoiar regras internas da classe Secrets.

### `app/Services/SecurityAuditService.php` — `SecurityAuditService`
- Linha 3: `public static record(string $evento, string $nivel='info', array $data=[])` — Função auxiliar do domínio de segurança; usada para apoiar regras internas da classe SecurityAuditService.
- Linha 10: `public static recentes(int $limit=100)` — Função auxiliar do domínio de segurança; usada para apoiar regras internas da classe SecurityAuditService.

### `app/Services/SecurityEventService.php` — `SecurityEventService`
- Linha 3: `public static ensureSchema()` — Garante que tabelas/colunas necessárias existam antes da operação.
- Linha 38: `public static ip()` — Função auxiliar do domínio de segurança; usada para apoiar regras internas da classe SecurityEventService.
- Linha 43: `public static log(string $tipo, string $severidade='medio', string $detalhe='', array $contexto=[])` — Função auxiliar do domínio de segurança; usada para apoiar regras internas da classe SecurityEventService.

### `app/Services/SecurityHardeningService.php` — `SecurityHardeningService`
- Linha 3: `public static status()` — Função auxiliar do domínio de segurança; usada para apoiar regras internas da classe SecurityHardeningService.
- Linha 34: `private static strongKey(string $key)` — Função auxiliar do domínio de segurança; usada para apoiar regras internas da classe SecurityHardeningService.
- Linha 39: `public static maskSecretsInArray(array $data)` — Função auxiliar do domínio de segurança; usada para apoiar regras internas da classe SecurityHardeningService.

### `app/Services/SecurityScoreService.php` — `SecurityScoreService`
- Linha 3: `public static evaluate()` — Função auxiliar do domínio de segurança; usada para apoiar regras internas da classe SecurityScoreService.

### `app/Services/SelfTestService.php` — `SelfTestService`
- Linha 3: `public static executar()` — Executa processo operacional solicitado pelo painel ou worker.

### `app/Services/SensitiveDataService.php` — `SensitiveDataService`
- Linha 9: `public static mask(mixed $value)` — Função auxiliar do domínio de regra de negócio/serviço; usada para apoiar regras internas da classe SensitiveDataService.
- Linha 23: `private static isSensitiveKey(string $key)` — Função auxiliar do domínio de regra de negócio/serviço; usada para apoiar regras internas da classe SensitiveDataService.
- Linha 30: `private static maskScalar(mixed $v)` — Função auxiliar do domínio de regra de negócio/serviço; usada para apoiar regras internas da classe SensitiveDataService.
- Linha 38: `private static maskJsonString(string $body)` — Função auxiliar do domínio de regra de negócio/serviço; usada para apoiar regras internas da classe SensitiveDataService.

### `app/Services/ShellCommandService.php` — `ShellCommandService`
- Linha 3: `public static disabled()` — Função auxiliar do domínio de regra de negócio/serviço; usada para apoiar regras internas da classe ShellCommandService.
- Linha 10: `public static assertNoShellUse()` — Função auxiliar do domínio de regra de negócio/serviço; usada para apoiar regras internas da classe ShellCommandService.
- Linha 14: `public static detailedScan()` — Função auxiliar do domínio de regra de negócio/serviço; usada para apoiar regras internas da classe ShellCommandService.

### `app/Services/SimpleCache.php` — `SimpleCache`
- Linha 3: `private static dir()` — Função auxiliar do domínio de regra de negócio/serviço; usada para apoiar regras internas da classe SimpleCache.
- Linha 8: `private static file(string $key)` — Função auxiliar do domínio de regra de negócio/serviço; usada para apoiar regras internas da classe SimpleCache.
- Linha 11: `public static remember(string $key, int $ttlSeconds, callable $callback)` — Função auxiliar do domínio de regra de negócio/serviço; usada para apoiar regras internas da classe SimpleCache.
- Linha 21: `public static forget(string $prefix='')` — Função auxiliar do domínio de regra de negócio/serviço; usada para apoiar regras internas da classe SimpleCache.

### `app/Services/SyncRulesService.php` — `SyncRulesService`
- Linha 16: `public static all()` — Retorna coleção completa de configurações, regras ou registros.
- Linha 25: `public static enabled(string $key)` — Função auxiliar do domínio de regra de negócio/serviço; usada para apoiar regras internas da classe SyncRulesService.
- Linha 30: `public static explain(string $key)` — Função auxiliar do domínio de regra de negócio/serviço; usada para apoiar regras internas da classe SyncRulesService.
- Linha 46: `public static skipped(string $key, array $payload = [])` — Função auxiliar do domínio de regra de negócio/serviço; usada para apoiar regras internas da classe SyncRulesService.

### `app/Services/TesteRealTinyService.php` — `TesteRealTinyService`
- Linha 10: `public static skuPadrao()` — Função auxiliar do domínio de Tiny/Olist; usada para apoiar regras internas da classe TesteRealTinyService.
- Linha 14: `public static cliente(string $modo='auto')` — Função auxiliar do domínio de Tiny/Olist; usada para apoiar regras internas da classe TesteRealTinyService.
- Linha 21: `public static versaoEfetiva(string $modo='auto')` — Função auxiliar do domínio de Tiny/Olist; usada para apoiar regras internas da classe TesteRealTinyService.
- Linha 31: `public static skuSeguro(string $sku)` — Função auxiliar do domínio de Tiny/Olist; usada para apoiar regras internas da classe TesteRealTinyService.
- Linha 35: `public static validarEntradaSegura(array $input)` — Valida dados/regras antes de concluir operação de Tiny/Olist.
- Linha 47: `public static payloadProduto(array $input)` — Função auxiliar do domínio de Tiny/Olist; usada para apoiar regras internas da classe TesteRealTinyService.
- Linha 75: `public static executar(array $input)` — Executa processo operacional solicitado pelo painel ou worker.
- Linha 158: `private static registrarHistorico(array $resumo, array $produtoVsm, array $produtoTiny)` — Registra ou consulta auditoria/histórico de Tiny/Olist.
- Linha 174: `public static ultimos(int $limit=20)` — Função auxiliar do domínio de Tiny/Olist; usada para apoiar regras internas da classe TesteRealTinyService.
- Linha 183: `public static ensureTable(PDO $pdo)` — Função auxiliar do domínio de Tiny/Olist; usada para apoiar regras internas da classe TesteRealTinyService.
- Linha 203: `public static retornoOk(array $ret)` — Função auxiliar do domínio de Tiny/Olist; usada para apoiar regras internas da classe TesteRealTinyService.
- Linha 211: `public static resumoRetorno(array $ret)` — Função auxiliar do domínio de Tiny/Olist; usada para apoiar regras internas da classe TesteRealTinyService.

### `app/Services/TinyClientInterface.php` — `global`
- Linha 3: `public criarPedido(array $pedido)` — Cria registro, payload ou recurso do módulo de Tiny/Olist.
- Linha 4: `public consultarPedido(string $id)` — Função auxiliar do domínio de Tiny/Olist; usada para apoiar regras internas da classe global.
- Linha 5: `public consultarProduto(string $sku)` — Função auxiliar do domínio de Tiny/Olist; usada para apoiar regras internas da classe global.
- Linha 6: `public atualizarEstoque(string $sku, float $qtd)` — Atualiza cadastro, status ou estrutura no módulo de Tiny/Olist.
- Linha 7: `public criarProduto(array $produto)` — Cria registro, payload ou recurso do módulo de Tiny/Olist.
- Linha 8: `public atualizarProduto(array $produto)` — Atualiza cadastro, status ou estrutura no módulo de Tiny/Olist.
- Linha 9: `public atualizarStatusProduto(string $sku, string $situacao)` — Atualiza cadastro, status ou estrutura no módulo de Tiny/Olist.

### `app/Services/TinyEnvironmentReadinessService.php` — `TinyEnvironmentReadinessService`
- Linha 3: `public static analisar(array $cfg, array $checklist=[])` — Função auxiliar do domínio de Tiny/Olist; usada para apoiar regras internas da classe TinyEnvironmentReadinessService.

### `app/Services/TinyErrorCatalog.php` — `TinyErrorCatalog`
- Linha 3: `public static detectar(array $retorno)` — Função auxiliar do domínio de Tiny/Olist; usada para apoiar regras internas da classe TinyErrorCatalog.
- Linha 24: `public static info(string $code)` — Função auxiliar do domínio de Tiny/Olist; usada para apoiar regras internas da classe TinyErrorCatalog.

### `app/Services/TinyFactory.php` — `TinyFactory`
- Linha 3: `public static make()` — Função auxiliar do domínio de Tiny/Olist; usada para apoiar regras internas da classe TinyFactory.

### `app/Services/TinyHomologationDiagnosisService.php` — `TinyHomologationDiagnosisService`
- Linha 3: `public static montar(array $checks, ?array $ultimo=null, string $versao='Tiny')` — Função auxiliar do domínio de Tiny/Olist; usada para apoiar regras internas da classe TinyHomologationDiagnosisService.
- Linha 42: `public static statusEtapa(array $etapa, bool $pedidoOpcional=false)` — Função auxiliar do domínio de Tiny/Olist; usada para apoiar regras internas da classe TinyHomologationDiagnosisService.

### `app/Services/TinyV2ErrorCatalogService.php` — `TinyV2ErrorCatalogService`
- Linha 3: `public static all()` — Retorna coleção completa de configurações, regras ou registros.

### `app/Services/TinyV2HomologationService.php` — `TinyV2HomologationService`
- Linha 3: `public static resumo(array $cfg)` — Função auxiliar do domínio de Tiny/Olist; usada para apoiar regras internas da classe TinyV2HomologationService.
- Linha 26: `private static checksBase(array $cfg, ?array $ultimo)` — Função auxiliar do domínio de Tiny/Olist; usada para apoiar regras internas da classe TinyV2HomologationService.
- Linha 41: `private static etapaStatus(array $etapas, string $key)` — Função auxiliar do domínio de Tiny/Olist; usada para apoiar regras internas da classe TinyV2HomologationService.
- Linha 48: `public static executar(array $input)` — Executa processo operacional solicitado pelo painel ou worker.
- Linha 141: `private static extrairSaldoTiny(array $ret, array $produtoRet=[])` — Função auxiliar do domínio de Tiny/Olist; usada para apoiar regras internas da classe TinyV2HomologationService.
- Linha 162: `private static extrairSaldoGenerico(array $ret)` — Função auxiliar do domínio de Tiny/Olist; usada para apoiar regras internas da classe TinyV2HomologationService.
- Linha 170: `private static retornoOk(array $ret)` — Função auxiliar do domínio de Tiny/Olist; usada para apoiar regras internas da classe TinyV2HomologationService.
- Linha 177: `private static resumoRetorno(array $ret)` — Função auxiliar do domínio de Tiny/Olist; usada para apoiar regras internas da classe TinyV2HomologationService.
- Linha 185: `private static registrar(array $res)` — Registra evento, histórico, auditoria ou snapshot no banco.
- Linha 192: `public static historico(int $limit=10)` — Registra ou consulta auditoria/histórico de Tiny/Olist.
- Linha 201: `public static ensureTable()` — Função auxiliar do domínio de Tiny/Olist; usada para apoiar regras internas da classe TinyV2HomologationService.

### `app/Services/TinyV2ObservabilityService.php` — `TinyV2ObservabilityService`
- Linha 3: `public static metricas()` — Função auxiliar do domínio de Tiny/Olist; usada para apoiar regras internas da classe TinyV2ObservabilityService.
- Linha 8: `public static erros()` — Função auxiliar do domínio de Tiny/Olist; usada para apoiar regras internas da classe TinyV2ObservabilityService.
- Linha 15: `public static retryPolicy(string $codigo)` — Função auxiliar do domínio de Tiny/Olist; usada para apoiar regras internas da classe TinyV2ObservabilityService.
- Linha 22: `private static tableExists(string $table)` — Função auxiliar do domínio de Tiny/Olist; usada para apoiar regras internas da classe TinyV2ObservabilityService.

### `app/Services/TinyV2Service.php` — `TinyV2Service`
- Linha 4: `public __construct(?array $cfg=null)` — Inicializa dependências internas de TinyV2Service.
- Linha 6: `private post(string $endpoint, array $data)` — Função auxiliar do domínio de Tiny/Olist; usada para apoiar regras internas da classe TinyV2Service.
- Linha 56: `private static logEndpoint(string $endpoint, string $metodo, int $http, bool $sucesso, int $tempoMs, array $request, $response, ?string $erro=null)` — Função auxiliar do domínio de Tiny/Olist; usada para apoiar regras internas da classe TinyV2Service.
- Linha 68: `public criarPedido(array $pedido)` — Cria registro, payload ou recurso do módulo de Tiny/Olist.
- Linha 69: `public criarProduto(array $produto)` — Cria registro, payload ou recurso do módulo de Tiny/Olist.
- Linha 70: `public atualizarProduto(array $produto)` — Atualiza cadastro, status ou estrutura no módulo de Tiny/Olist.
- Linha 71: `public atualizarStatusProduto(string $sku, string $situacao)` — Atualiza cadastro, status ou estrutura no módulo de Tiny/Olist.
- Linha 79: `public consultarPedido(string $id)` — Função auxiliar do domínio de Tiny/Olist; usada para apoiar regras internas da classe TinyV2Service.
- Linha 80: `public consultarProduto(string $sku)` — Função auxiliar do domínio de Tiny/Olist; usada para apoiar regras internas da classe TinyV2Service.
- Linha 82: `public consultarEstoquePorSku(string $sku)` — Função auxiliar do domínio de Tiny/Olist; usada para apoiar regras internas da classe TinyV2Service.
- Linha 94: `public atualizarEstoque(string $sku, float $qtd)` — Atualiza cadastro, status ou estrutura no módulo de Tiny/Olist.
- Linha 96: `public enviarNfeXmlPedido(string $pedidoId, array $dados)` — Envia dados para integração/destino externo no módulo de Tiny/Olist.

### `app/Services/TinyV3EndpointCatalog.php` — `TinyV3EndpointCatalog`
- Linha 3: `public static defaults()` — Função auxiliar do domínio de Tiny/Olist; usada para apoiar regras internas da classe TinyV3EndpointCatalog.
- Linha 17: `public static get(string $key)` — Lê configuração, registro ou valor persistido.
- Linha 22: `public static fill(string $template, array $vars)` — Função auxiliar do domínio de Tiny/Olist; usada para apoiar regras internas da classe TinyV3EndpointCatalog.

### `app/Services/TinyV3FichaTecnicaService.php` — `TinyV3FichaTecnicaService`
- Linha 3: `public static itens()` — Função auxiliar do domínio de Tiny/Olist; usada para apoiar regras internas da classe TinyV3FichaTecnicaService.
- Linha 25: `public static score()` — Função auxiliar do domínio de Tiny/Olist; usada para apoiar regras internas da classe TinyV3FichaTecnicaService.

### `app/Services/TinyV3HomologationService.php` — `TinyV3HomologationService`
- Linha 3: `public static resumo(array $cfg)` — Função auxiliar do domínio de Tiny/Olist; usada para apoiar regras internas da classe TinyV3HomologationService.
- Linha 26: `private static checksBase(array $cfg, ?array $ultimo)` — Função auxiliar do domínio de Tiny/Olist; usada para apoiar regras internas da classe TinyV3HomologationService.
- Linha 45: `private static etapaStatus(array $etapas, string $key)` — Função auxiliar do domínio de Tiny/Olist; usada para apoiar regras internas da classe TinyV3HomologationService.
- Linha 52: `public static executar(array $input)` — Executa processo operacional solicitado pelo painel ou worker.
- Linha 171: `private static extrairSaldoTiny(array $estoqueRet, array $produtoRet=[])` — Função auxiliar do domínio de Tiny/Olist; usada para apoiar regras internas da classe TinyV3HomologationService.
- Linha 183: `private static extrairSaldoGenerico(array $ret)` — Função auxiliar do domínio de Tiny/Olist; usada para apoiar regras internas da classe TinyV3HomologationService.
- Linha 191: `private static acaoEstoque(array $tinyRet, array $vsmRet, $dif)` — Função auxiliar do domínio de Tiny/Olist; usada para apoiar regras internas da classe TinyV3HomologationService.
- Linha 199: `private static retornoOk(array $ret)` — Função auxiliar do domínio de Tiny/Olist; usada para apoiar regras internas da classe TinyV3HomologationService.
- Linha 206: `private static resumoRetorno(array $ret)` — Função auxiliar do domínio de Tiny/Olist; usada para apoiar regras internas da classe TinyV3HomologationService.
- Linha 214: `private static registrar(array $res)` — Registra evento, histórico, auditoria ou snapshot no banco.
- Linha 221: `public static historico(int $limit=10)` — Registra ou consulta auditoria/histórico de Tiny/Olist.
- Linha 230: `public static ensureTable()` — Função auxiliar do domínio de Tiny/Olist; usada para apoiar regras internas da classe TinyV3HomologationService.

### `app/Services/TinyV3Service.php` — `TinyV3Service`
- Linha 6: `public __construct(?array $cfg=null)` — Inicializa dependências internas de TinyV3Service.
- Linha 11: `private token()` — Gere/valida/consulta token seguro no módulo de Tiny/Olist.
- Linha 15: `private request(string $method, string $path, array $payload=[], array $query=[])` — Função auxiliar do domínio de Tiny/Olist; usada para apoiar regras internas da classe TinyV3Service.
- Linha 66: `private logEndpoint(string $endpoint, string $metodo, int $httpCode, bool $sucesso, int $tempoMs, string $trace, array $requestBody=[], ?string $responseBody=null, ?string $erro=null)` — Função auxiliar do domínio de Tiny/Olist; usada para apoiar regras internas da classe TinyV3Service.
- Linha 80: `private normalizeList(array $ret)` — Padroniza formato de dados usado por Tiny/Olist.
- Linha 89: `public consultarProduto(string $sku)` — Função auxiliar do domínio de Tiny/Olist; usada para apoiar regras internas da classe TinyV3Service.
- Linha 104: `public criarProduto(array $produto)` — Cria registro, payload ou recurso do módulo de Tiny/Olist.
- Linha 106: `public atualizarProduto(array $produto)` — Atualiza cadastro, status ou estrutura no módulo de Tiny/Olist.
- Linha 114: `public atualizarStatusProduto(string $sku, string $situacao)` — Atualiza cadastro, status ou estrutura no módulo de Tiny/Olist.
- Linha 121: `public atualizarEstoque(string $sku, float $qtd)` — Atualiza cadastro, status ou estrutura no módulo de Tiny/Olist.
- Linha 127: `public consultarPedido(string $id)` — Função auxiliar do domínio de Tiny/Olist; usada para apoiar regras internas da classe TinyV3Service.
- Linha 128: `public criarPedido(array $pedido)` — Cria registro, payload ou recurso do módulo de Tiny/Olist.
- Linha 129: `public consultarNotaFiscal(string $id)` — Função auxiliar do domínio de Tiny/Olist; usada para apoiar regras internas da classe TinyV3Service.
- Linha 130: `public lancarEstoquePedido(string $idPedido)` — Função auxiliar do domínio de Tiny/Olist; usada para apoiar regras internas da classe TinyV3Service.
- Linha 132: `public testarModulo(string $modulo, array $params=[])` — Executa teste/diagnóstico controlado do módulo de Tiny/Olist.
- Linha 144: `public consultarEstoquePorSku(string $sku)` — Função auxiliar do domínio de Tiny/Olist; usada para apoiar regras internas da classe TinyV3Service.
- Linha 152: `public enviarNfeXmlPedido(string $pedidoId, array $dados)` — Envia dados para integração/destino externo no módulo de Tiny/Olist.

### `app/Services/TinyV3TokenService.php` — `TinyV3TokenService`
- Linha 3: `public static getTokenRow(?string $ambiente=null)` — Gere/valida/consulta token seguro no módulo de Tiny/Olist.
- Linha 20: `public static accessToken()` — Gere/valida/consulta token seguro no módulo de Tiny/Olist.
- Linha 81: `public static isExpired(?array $row=null)` — Função auxiliar do domínio de Tiny/Olist; usada para apoiar regras internas da classe TinyV3TokenService.
- Linha 87: `public static saveManual(string $accessToken, ?string $refreshToken=null, int $expiresIn=3600, ?string $scope=null, ?string $ambiente=null)` — Função auxiliar do domínio de Tiny/Olist; usada para apoiar regras internas da classe TinyV3TokenService.
- Linha 91: `public static saveOAuthToken(string $accessToken, ?string $refreshToken=null, int $expiresIn=3600, ?string $scope=null, ?string $ambiente=null)` — Gere/valida/consulta token seguro no módulo de Tiny/Olist.
- Linha 95: `private static saveToken(string $accessToken, ?string $refreshToken, int $expiresIn, ?string $scope, string $origem, ?string $ambiente=null)` — Gere/valida/consulta token seguro no módulo de Tiny/Olist.
- Linha 127: `public static refresh()` — Função auxiliar do domínio de Tiny/Olist; usada para apoiar regras internas da classe TinyV3TokenService.
- Linha 153: `public static status()` — Função auxiliar do domínio de Tiny/Olist; usada para apoiar regras internas da classe TinyV3TokenService.
- Linha 173: `public static statusResumo()` — Função auxiliar do domínio de Tiny/Olist; usada para apoiar regras internas da classe TinyV3TokenService.
- Linha 191: `public static homologationReady()` — Função auxiliar do domínio de Tiny/Olist; usada para apoiar regras internas da classe TinyV3TokenService.

### `app/Services/TinyValidationService.php` — `TinyValidationService`
- Linha 7: `public static ensureSchema()` — Garante que tabelas/colunas necessárias existam antes da operação.
- Linha 26: `public static ultimo()` — Função auxiliar do domínio de Tiny/Olist; usada para apoiar regras internas da classe TinyValidationService.
- Linha 33: `public static executar(string $versao, array $params=[])` — Executa processo operacional solicitado pelo painel ou worker.
- Linha 94: `public static podeMarcarV3Operacional()` — Função auxiliar do domínio de Tiny/Olist; usada para apoiar regras internas da classe TinyValidationService.

### `app/Services/TinyWebhookSecurityService.php` — `TinyWebhookSecurityService`
- Linha 3: `public static validar(array $config, string $raw, array $payload)` — Valida payload, banco, regra, arquivo ou configuração.
- Linha 53: `private static auditBlock(string $codigo, string $mensagem, array $headers, string $hash, array $extra=[])` — Bloqueia operação insegura ou não autorizada em Tiny/Olist.

### `app/Services/TinyWebhookService.php` — `TinyWebhookService`
- Linha 3: `public static rawPayload()` — Função auxiliar do domínio de Tiny/Olist; usada para apoiar regras internas da classe TinyWebhookService.
- Linha 10: `public static normalizarHeaders()` — Padroniza formato de dados usado por Tiny/Olist.
- Linha 22: `public static validarBase(array $payload, string $tipoEsperado)` — Valida dados/regras antes de concluir operação de Tiny/Olist.
- Linha 41: `public static tipo(array $payload)` — Função auxiliar do domínio de Tiny/Olist; usada para apoiar regras internas da classe TinyWebhookService.
- Linha 45: `public static referencia(array $payload, string $tipo)` — Função auxiliar do domínio de Tiny/Olist; usada para apoiar regras internas da classe TinyWebhookService.
- Linha 67: `public static dados(array $payload)` — Função auxiliar do domínio de Tiny/Olist; usada para apoiar regras internas da classe TinyWebhookService.
- Linha 72: `public static registrar(string $tipo, string $raw, array $payload, string $status='recebido', ?string $mensagem=null)` — Registra evento, histórico, auditoria ou snapshot no banco.
- Linha 108: `public static atualizarStatus(int $id, string $status, array $retorno=[])` — Atualiza cadastro, status ou estrutura no módulo de Tiny/Olist.
- Linha 114: `public static baixaDeEstoqueTiny(array $payload)` — Função auxiliar do domínio de Tiny/Olist; usada para apoiar regras internas da classe TinyWebhookService.
- Linha 145: `public static retornoMapeamentoProduto(array $payload, bool $ok=true, ?string $erro=null)` — Função auxiliar do domínio de Tiny/Olist; usada para apoiar regras internas da classe TinyWebhookService.

### `app/Services/TokenVaultService.php` — `TokenVaultService`
- Linha 3: `public static ensureSchema()` — Garante que tabelas/colunas necessárias existam antes da operação.
- Linha 23: `public static store(string $provider,string $ambiente,string $type,string $plain,?string $expiresAt=null)` — Função auxiliar do domínio de regra de negócio/serviço; usada para apoiar regras internas da classe TokenVaultService.
- Linha 33: `public static getActive(string $provider,string $ambiente,string $type)` — Função auxiliar do domínio de regra de negócio/serviço; usada para apoiar regras internas da classe TokenVaultService.
- Linha 40: `public static getActiveRow(string $provider,string $ambiente,string $type)` — Função auxiliar do domínio de regra de negócio/serviço; usada para apoiar regras internas da classe TokenVaultService.
- Linha 50: `public static rotateIfExpiring(string $provider,string $ambiente,string $type,int $seconds=3600)` — Função auxiliar do domínio de regra de negócio/serviço; usada para apoiar regras internas da classe TokenVaultService.
- Linha 56: `public static health()` — Executa verificação de saúde/disponibilidade em regra de negócio/serviço.
- Linha 63: `private static hashKey()` — Gera ou valida hash para integridade/idempotência em regra de negócio/serviço.

### `app/Services/TrustedProxyService.php` — `TrustedProxyService`
- Linha 3: `public static configuredProxies()` — Função auxiliar do domínio de regra de negócio/serviço; usada para apoiar regras internas da classe TrustedProxyService.
- Linha 9: `public static remoteAddr()` — Função auxiliar do domínio de regra de negócio/serviço; usada para apoiar regras internas da classe TrustedProxyService.
- Linha 13: `public static isTrustedProxy(?string $ip=null)` — Função auxiliar do domínio de regra de negócio/serviço; usada para apoiar regras internas da classe TrustedProxyService.
- Linha 18: `public static clientIp()` — Função auxiliar do domínio de regra de negócio/serviço; usada para apoiar regras internas da classe TrustedProxyService.
- Linha 30: `public static isHttps()` — Função auxiliar do domínio de regra de negócio/serviço; usada para apoiar regras internas da classe TrustedProxyService.

### `app/Services/TwoFactorService.php` — `TwoFactorService`
- Linha 3: `public static generateSecret(int $length=20)` — Função auxiliar do domínio de regra de negócio/serviço; usada para apoiar regras internas da classe TwoFactorService.
- Linha 9: `public static encryptSecret(?string $secret)` — Função auxiliar do domínio de regra de negócio/serviço; usada para apoiar regras internas da classe TwoFactorService.
- Linha 14: `public static decryptSecret(?string $value)` — Função auxiliar do domínio de regra de negócio/serviço; usada para apoiar regras internas da classe TwoFactorService.
- Linha 19: `public static provisioningUri(string $email, string $secret, string $issuer='Hub de Integração')` — Função auxiliar do domínio de regra de negócio/serviço; usada para apoiar regras internas da classe TwoFactorService.
- Linha 24: `public static totp(string $secret, ?int $timeSlice=null)` — Função auxiliar do domínio de regra de negócio/serviço; usada para apoiar regras internas da classe TwoFactorService.
- Linha 34: `public static verify(string $secret, string $code)` — Função auxiliar do domínio de regra de negócio/serviço; usada para apoiar regras internas da classe TwoFactorService.
- Linha 43: `private static base32Decode(string $b32)` — Função auxiliar do domínio de regra de negócio/serviço; usada para apoiar regras internas da classe TwoFactorService.

### `app/Services/UniversalUpgradeService.php` — `UniversalUpgradeService`
- Linha 3: `public __construct(private string $root)` — Inicializa dependências internas de UniversalUpgradeService.
- Linha 5: `public run()` — Função auxiliar do domínio de regra de negócio/serviço; usada para apoiar regras internas da classe UniversalUpgradeService.

### `app/Services/VisualProfileService.php` — `VisualProfileService`
- Linha 3: `public static current()` — Função auxiliar do domínio de regra de negócio/serviço; usada para apoiar regras internas da classe VisualProfileService.
- Linha 18: `public static allowedPages(string $mode)` — Autoriza operação conforme configuração/regra de regra de negócio/serviço.
- Linha 25: `public static canSee(string $page, ?string $mode=null)` — Função auxiliar do domínio de regra de negócio/serviço; usada para apoiar regras internas da classe VisualProfileService.
- Linha 30: `public static label(string $mode)` — Converte chave técnica em rótulo legível para a interface.

### `app/Services/VsmEndpointSecurityService.php` — `VsmEndpointSecurityService`
- Linha 3: `public static sanitizePath(string $path)` — Função auxiliar do domínio de VSM; usada para apoiar regras internas da classe VsmEndpointSecurityService.

### `app/Services/VsmEndpointService.php` — `VsmEndpointService`
- Linha 3: `private static safeExec(PDO $pdo, string $sql)` — Função auxiliar do domínio de VSM; usada para apoiar regras internas da classe VsmEndpointService.
- Linha 5: `public static ensureSchema()` — Garante que tabelas/colunas necessárias existam antes da operação.
- Linha 87: `public static seedDefaults()` — Função auxiliar do domínio de VSM; usada para apoiar regras internas da classe VsmEndpointService.
- Linha 124: `public static baseConfig()` — Função auxiliar do domínio de VSM; usada para apoiar regras internas da classe VsmEndpointService.
- Linha 126: `public static listEndpoints(?string $categoria=null)` — Função auxiliar do domínio de VSM; usada para apoiar regras internas da classe VsmEndpointService.
- Linha 137: `public static listCampos(?string $categoria=null)` — Função auxiliar do domínio de VSM; usada para apoiar regras internas da classe VsmEndpointService.
- Linha 148: `public static validateEndpointPath(string $endpoint)` — Função auxiliar do domínio de VSM; usada para apoiar regras internas da classe VsmEndpointService.
- Linha 158: `public static validateMethod(string $method)` — Função auxiliar do domínio de VSM; usada para apoiar regras internas da classe VsmEndpointService.
- Linha 165: `public static saveEndpoint(array $p)` — Função auxiliar do domínio de VSM; usada para apoiar regras internas da classe VsmEndpointService.
- Linha 191: `public static saveCampo(array $p)` — Função auxiliar do domínio de VSM; usada para apoiar regras internas da classe VsmEndpointService.
- Linha 208: `public static buildUrl(string $endpoint, array $params=[])` — Função auxiliar do domínio de VSM; usada para apoiar regras internas da classe VsmEndpointService.
- Linha 215: `public static testEndpoint(int $id, array $params=[], bool $modoSeguro=true)` — Executa teste/diagnóstico controlado do módulo de VSM.
- Linha 223: `private static executeTest(array $ep, array $params=[], bool $modoSeguro=true)` — Função auxiliar do domínio de VSM; usada para apoiar regras internas da classe VsmEndpointService.
- Linha 251: `public static health()` — Executa verificação de saúde/disponibilidade em VSM.
- Linha 257: `public static logs(int $limit=100)` — Função auxiliar do domínio de VSM; usada para apoiar regras internas da classe VsmEndpointService.

### `app/Services/VsmFichaTecnicaService.php` — `VsmFichaTecnicaService`
- Linha 3: `public static endpoints()` — Função auxiliar do domínio de VSM; usada para apoiar regras internas da classe VsmFichaTecnicaService.
- Linha 8: `public static metricas()` — Função auxiliar do domínio de VSM; usada para apoiar regras internas da classe VsmFichaTecnicaService.
- Linha 13: `public static payloads()` — Função auxiliar do domínio de VSM; usada para apoiar regras internas da classe VsmFichaTecnicaService.
- Linha 18: `public static registrarEndpoint(string $nome,string $metodo,string $endpoint,string $tipo='estoque',string $ambiente='homologacao')` — Função auxiliar do domínio de VSM; usada para apoiar regras internas da classe VsmFichaTecnicaService.
- Linha 24: `private static tableExists(string $table)` — Função auxiliar do domínio de VSM; usada para apoiar regras internas da classe VsmFichaTecnicaService.

### `app/Services/VsmService.php` — `VsmService`
- Linha 4: `public __construct(?array $cfg=null)` — Inicializa dependências internas de VsmService.
- Linha 11: `private request(string $method, string $endpoint, array $payload=[], int $timeout=30)` — Função auxiliar do domínio de VSM; usada para apoiar regras internas da classe VsmService.
- Linha 60: `private post(string $endpoint, array $payload)` — Função auxiliar do domínio de VSM; usada para apoiar regras internas da classe VsmService.
- Linha 61: `public enviarBaixaEstoque(array $baixa)` — Envia dados para integração/destino externo no módulo de VSM.
- Linha 62: `public enviarPedido(array $pedido)` — Envia dados para integração/destino externo no módulo de VSM.
- Linha 64: `public consultarEstoque(string $sku)` — Função auxiliar do domínio de VSM; usada para apoiar regras internas da classe VsmService.

### `app/Services/VsmSimulatorService.php` — `VsmSimulatorService`
- Linha 3: `public static exemplos()` — Função auxiliar do domínio de VSM; usada para apoiar regras internas da classe VsmSimulatorService.
- Linha 13: `public static simular(string $tipo)` — Função auxiliar do domínio de VSM; usada para apoiar regras internas da classe VsmSimulatorService.

### `app/Services/WafService.php` — `WafService`
- Linha 13: `public static inspect()` — Função auxiliar do domínio de WAF; usada para apoiar regras internas da classe WafService.
- Linha 31: `public static shouldInspect(string $page)` — Função auxiliar do domínio de WAF; usada para apoiar regras internas da classe WafService.
- Linha 53: `private static limited(array $arr)` — Função auxiliar do domínio de WAF; usada para apoiar regras internas da classe WafService.
- Linha 58: `private static rateGuard()` — Função auxiliar do domínio de WAF; usada para apoiar regras internas da classe WafService.
- Linha 68: `private static autoBlockIfNeeded()` — Bloqueia operação insegura ou não autorizada em WAF.

### `app/Services/WebhookSecurityService.php` — `WebhookSecurityService`
- Linha 3: `private static headers()` — Função auxiliar do domínio de segurança; usada para apoiar regras internas da classe WebhookSecurityService.
- Linha 18: `public static validar(array $config, string $raw)` — Valida payload, banco, regra, arquivo ou configuração.

### `app/Services/XmlNfeHomologationService.php` — `XmlNfeHomologationService`
- Linha 7: `public static responsabilidades()` — Função auxiliar do domínio de XML/NF-e; usada para apoiar regras internas da classe XmlNfeHomologationService.
- Linha 15: `public static resumoOperacional()` — Função auxiliar do domínio de XML/NF-e; usada para apoiar regras internas da classe XmlNfeHomologationService.
- Linha 22: `public static checksBase()` — Função auxiliar do domínio de XML/NF-e; usada para apoiar regras internas da classe XmlNfeHomologationService.
- Linha 30: `public static validarChaveNfe(?string $chave)` — Valida dados/regras antes de concluir operação de XML/NF-e.
- Linha 35: `public static localizarPedidoRelacionado(array $nota)` — Função auxiliar do domínio de XML/NF-e; usada para apoiar regras internas da classe XmlNfeHomologationService.
- Linha 41: `public static compararPedidoNota(array $pedido, array $nota)` — Converte/mapeia dados entre formatos do módulo de XML/NF-e.
- Linha 47: `public static registrarEvidencia(string $etapa, array $contexto=[])` — Função auxiliar do domínio de XML/NF-e; usada para apoiar regras internas da classe XmlNfeHomologationService.
- Linha 50: `private static tableExists(string $table)` — Função auxiliar do domínio de XML/NF-e; usada para apoiar regras internas da classe XmlNfeHomologationService.
- Linha 53: `private static dvNfeValido(string $chave)` — Função auxiliar do domínio de XML/NF-e; usada para apoiar regras internas da classe XmlNfeHomologationService.

### `public/install.php` — `global`
- Linha 12: `public/global h($v)` — Função auxiliar do domínio de utilitário/core; usada para apoiar regras internas da classe global.
- Linha 13: `public/global random_secret()` — Função auxiliar do domínio de utilitário/core; usada para apoiar regras internas da classe global.
- Linha 14: `public/global export_config(array $data)` — Função auxiliar do domínio de utilitário/core; usada para apoiar regras internas da classe global.
- Linha 15: `public/global normalize_db(string $name)` — Padroniza formato de dados usado por utilitário/core.
- Linha 16: `public/global hosting_db_help(string $db, string $user)` — Função auxiliar do domínio de utilitário/core; usada para apoiar regras internas da classe global.
- Linha 19: `public/global test_database_access(string $host, string $db, string $user, string $pass)` — Função auxiliar do domínio de utilitário/core; usada para apoiar regras internas da classe global.
- Linha 27: `public/global run_sql(PDO $pdo, string $sql)` — Função auxiliar do domínio de utilitário/core; usada para apoiar regras internas da classe global.
- Linha 41: `public/global install_log_error(Throwable $e)` — Função auxiliar do domínio de utilitário/core; usada para apoiar regras internas da classe global.
- Linha 49: `public/global checks()` — Função auxiliar do domínio de utilitário/core; usada para apoiar regras internas da classe global.
- Linha 200: `public/global val($key,$default)` — Função auxiliar do domínio de utilitário/core; usada para apoiar regras internas da classe global.

### `views/auditoria_detalhe.php` — `global`
- Linha 1: `public/global pretty($v)` — Função auxiliar do domínio de auditoria; usada para apoiar regras internas da classe global.

### `views/backups.php` — `global`
- Linha 10: `public/global backup_size_fmt($bytes)` — Função auxiliar do domínio de backups; usada para apoiar regras internas da classe global.

### `views/central_homologacao.php` — `global`
- Linha 3: `public/global homBadge(array $resumo)` — Função auxiliar do domínio de homologação; usada para apoiar regras internas da classe global.
- Linha 9: `public/global homCheck(array $checks, string $key)` — Função auxiliar do domínio de homologação; usada para apoiar regras internas da classe global.

### `views/tiny_v2_homologacao.php` — `global`
- Linha 3: `public/global badgeStatusHomologacao(string $status)` — Função auxiliar do domínio de Tiny/Olist; usada para apoiar regras internas da classe global.

### `views/tiny_v3_ficha.php` — `global`
- Linha 20: `public/global badge_bool($ok)` — Função auxiliar do domínio de Tiny/Olist; usada para apoiar regras internas da classe global.

### `views/tiny_v3_homologacao.php` — `global`
- Linha 3: `public/global badgeStatusHomologacao(string $status)` — Função auxiliar do domínio de Tiny/Olist; usada para apoiar regras internas da classe global.

### `views/usuarios.php` — `global`
- Linha 91: `public/global editarUsuario(u)` — Função auxiliar do domínio de utilitário/core; usada para apoiar regras internas da classe global.
- Linha 104: `public/global alterarSenhaUsuario(u)` — Função auxiliar do domínio de utilitário/core; usada para apoiar regras internas da classe global.
- Linha 111: `public/global limparUsuarioForm()` — Função auxiliar do domínio de utilitário/core; usada para apoiar regras internas da classe global.
- Linha 118: `public/global confirmarExclusaoUsuario(form)` — Função auxiliar do domínio de utilitário/core; usada para apoiar regras internas da classe global.

## 8. Recomendações após a V104.3
- Separar `DashboardController` em controllers menores, porque ele ainda concentra muitas funções.
- Criar testes automatizados simples para cada fluxo do catálogo: ligado/desligado, macro ligada/desligada e travamento obrigatório.
- Criar tela de “simulador de pedido Tiny → VSM” com payload de exemplo, validação e resultado antes de enviar para produção.
- Adicionar status visual “teste executado em” para cada fluxo, mostrando último Trace ID e resultado.
- Manter WAF fora dos payloads Tiny/VSM; proteger integrações por HMAC/secret, idempotência, anti-replay, auditoria e circuit breaker.
