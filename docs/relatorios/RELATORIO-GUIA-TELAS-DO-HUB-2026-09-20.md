# RELATÓRIO — Guia de telas do Hub (página por página)

**Data:** 2026-09-20 · **Release base:** V104.49.3-R7 · **Commit base:** `48ffc4e`
**Escopo:** explicar, tela por tela, o que o Hub faz. Fonte autoritativa: a definição de menu em
`views/layout_top.php` (`$items` = ícone/rótulo/permissão por rota; `$groups` = agrupamento) e os
controllers donos de cada rota. Documento informativo — não altera comportamento.

## Como o Hub funciona no geral

O entrypoint `public/index.php` recebe tudo via `index.php?page=<rota>`, inicializa
config/sessão/autoload e entrega ao dispatcher (`FastRouteDispatcherService`), que chama o
controller dono da rota. Cada tela exige **login** e uma **permissão** (`PermissionService`, por
perfil). O menu lateral é montado dinamicamente e só mostra o que o perfil pode ver. Há 4 perfis
visuais (Operador/Supervisor/Admin/Dev) que alteram quais grupos aparecem.

## Telas de acesso (fora do menu)

- **Login** (`login`) — e-mail + senha, CSRF, rate-limit, opcional 2FA; cria a sessão.
- **Trocar senha** (`trocar-senha`) — obrigatória no 1º acesso (`deve_trocar_senha`); redireciona
  toda tela autenticada até a troca.
- **Sair** (`logout`) — encerra a sessão.

## Grupo OPERAÇÃO (o dia a dia)

| Tela | Rota | Função |
|---|---|---|
| Dashboard | `dashboard` | visão geral: KPIs de pedidos/estoque/fiscal, saúde das integrações, últimos eventos |
| Centro de Operações | `centro-operacoes` | painel operacional consolidado (filas, alertas, status ao vivo) |
| Dashboard Executivo | `dashboard-executivo` | métricas de alto nível para gestão |
| Alertas | `alertas-operacionais` | avisos operacionais (falhas, pendências, limites) |
| Pedidos | `pedidos` / `pedido-detalhe` | lista de pedidos Tiny→VSM; o detalhe traz payload, Trace ID, status e histórico |
| Produtos | `produtos` / `produtos-pendencias` | catálogo VSM→Tiny por SKU; pendências = produtos bloqueados pela governança aguardando aprovação |
| Estoque | `estoque-dashboard` | saldos e movimentações; sincronização VSM↔Tiny com anti-loop |
| Divergências | `monitor-divergencias` / `divergencia-estoque` / `divergencia-acao` | onde os dados divergem entre sistemas e a ação para resolver |
| XML / NF-e | `fiscal` (+ `fiscal-dashboard`, `fiscal-xml`, `fiscal-timeline`, `fiscal-reconciliacao`, `fiscal-health`, `fiscal-reenviar`) | fluxo fiscal VSM→Tiny: recebe NF-e/XML, valida, vincula ao pedido, linha do tempo, reprocessamento, saúde |
| Notificações | `notificacoes` | central de avisos do sistema |

Motor assíncrono relacionado: a **Fila** (`fila`, `fila-reprocessar`, `fila-morta-reprocessar`,
`fila-criar-teste`) — processa pedidos/estoque/fiscal com retry, backoff, DLQ (fila morta) e
reprocessamento sem duplicar.

## Grupo GESTÃO (configurar e homologar)

| Tela | Rota | Função |
|---|---|---|
| Central de Homologação | `central-homologacao` | orquestra a homologação das integrações antes de produção |
| Integrações | `integracoes` | configura conexões Tiny/VSM (endpoints, credenciais, mapeamentos) |
| Diagnóstico Config Real | `diagnostico-config-real` | confere se a configuração viva bate com o esperado (host, ambiente, chaves) |
| Homologação Tiny V2 | `tiny-v2-homologacao` (+ `-executar`) | testa a API V2 do Tiny (token, endpoints, pedidos, estoque, fiscal) |
| Homologação Tiny V3 | `tiny-v3-homologacao` (+ `-executar`) | testa a API V3 (OAuth 2.0: client id/secret, tokens, escopos, renovação) |
| Evidências | `evidencias-homologacao` / `evidencia-trace` | coleta provas de homologação por Trace ID |
| Reconciliação | `reconciliacao` | fecha diferenças entre sistemas de forma controlada |
| Relatórios | `logs` | logs de integração e operação, pesquisáveis |
| Configurações | `configuracoes` | parâmetros gerais do Hub |
| Backups | `backups` (+ `backup`, `-download`, `-excluir`, `-importar`, `-restaurar`) | gerar/baixar/restaurar backup, com assinatura e proveniência |
| Entrada em Produção | `entrada-producao` | checklist e travas para liberar o go-live |
| Produção Segura | `producao-ready` | validação de prontidão de produção |
| Central Técnica | `central-tecnica` | porta para telas técnicas (Enterprise Core, Observabilidade, **Governança LLM**, testes de regressão, design system) |

> A **Governança LLM** (`llm-governance`) vive sob a Central Técnica — detalhada nos relatórios
> `RELATORIO-USO-E-ATIVACAO-LLM` e `RELATORIO-RUNBOOK-LLM-EXECUCAO-REAL-HOMOLOGACAO`.

## Grupo SEGURANÇA (políticas separadas por superfície)

| Tela | Rota | Função |
|---|---|---|
| Segurança Extrema | `seguranca-extrema` | painel-mestre de segurança |
| Central de Segurança | `security-center` | consolidação dos controles |
| SOC | `security-soc` | monitoração tipo centro de operações de segurança |
| Eventos de Segurança | `security-events` | trilha de eventos de segurança |
| IPs Bloqueados | `security-ips` | IPs barrados (brute force, abuso) |
| Circuit Breakers | `security-circuit-breakers` | estado dos disjuntores de resiliência |
| Teste Segurança Assistido | `security-assisted-test` | roda checagens de segurança guiadas |
| Auditoria de Código | `security-code-audit` | varredura estática do próprio código |
| Inventário Legado/Banco | `security-inventory` | mapa de tabelas/classes e o que é legado |
| Backup Trust | `security-backup-trust` | confiança/assinatura dos backups |
| Health Real Time | `security-health` | saúde dos controles em tempo real |
| Assinaturas Auditoria | `security-audit-signatures` | cadeia de assinatura da trilha (anti-adulteração) |
| Integridade de Arquivos (FIM) | `security-fim` | confere os arquivos contra `CHECKSUMS-SHA256.txt` |
| Security Score | `security-score` | nota consolidada de segurança |
| Hardening Produção | `security-hardening` | endurecimento para produção |
| Certificados SSL | `security-ssl` | estado dos certificados |
| Auditoria de Usuários | `security-user-audit` | ações e permissões dos usuários |
| Pentest Checklist | `security-pentest` | roteiro de teste de invasão |

## Grupo COMERCIAL (camada SaaS — em boa parte dormente)

Camada comercial/multi-cliente (licenciamento, cobrança, SLA, demo). Numa instalação de **empresa
única**, a maioria fica **dormente** (tabelas `comercial_*` sem CRUD em runtime — ver
`RELATORIO`/auditoria). As telas existem e renderizam, mas não movimentam dados nesse uso.

`planos-comerciais`, `licencas-clientes`, `conectores-plugaveis`, `painel-cobranca`,
`ambiente-demo`, `cliente-portal`, `suporte-sla`, `documentos-comerciais`, `producao-comercial`,
`producao-comercial-final`, `analise-comercial-tecnica` — respectivamente: planos, licenças por
cliente, catálogo de conectores, faturas, ambiente de demonstração, portal do cliente, SLA/suporte,
documentos comerciais e checklists de go-live comercial.

## Grupo SISTEMA

| Tela | Rota | Função |
|---|---|---|
| Sobre | `sobre` | versão, ambiente, informações do build |
| PWA do Hub | `pwa-status` | estado do app instalável (service worker, cache, instalação) |
| Tutorial | `tutorial-sistema` | guia de uso |

## Onde os 4 fluxos de negócio moram (visão transversal)

- **Pedidos:** Tiny → webhook → **Fila** → VSM → tela **Pedidos**.
- **Estoque:** VSM ↔ **Fila/worker** ↔ Tiny → telas **Estoque/Divergências**.
- **Fiscal:** VSM → **Fila fiscal** → Tiny → tela **XML/NF-e**.
- **Produtos:** VSM → governança (aprovação) → Tiny → telas **Produtos/Pendências**.

## Ressalvas honestas

- Os rótulos e agrupamentos vêm do menu real (`views/layout_top.php`); a **função** de cada tela é
  descrita a partir do rótulo, do módulo/permissão e da auditoria de rotas desta linha — não de
  suposição. Para o detalhe interno botão a botão de uma tela específica (ex.: Fiscal, Fila),
  consulte o controller dono e as evidências da auditoria (RELATORIOs de rotas e de mutação).
- A camada **Comercial** é majoritariamente dormente para empresa única, por decisão de produto.
