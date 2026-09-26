# RELATÓRIO — Fase 3 (Arquitetura), auditoria

**Data:** 2026-09-26 · **Natureza:** auditoria (leitura/análise). **NADA ALTERADO.**
**Escopo:** acoplamento, controller-deus, código morto, responsabilidade misturada (regra/SQL em
view, acesso a banco fora de camada), dependência circular, método ausente. Segue a fase 3 do
`CLAUDE.md` — que **proíbe refatorar por estética**: cada achado traz problema real, risco,
benefício, compatibilidade, estratégia incremental e rollback.

---

## A3-01 · `DashboardController` é um controller-deus — **CONFIRMADO / Gravidade Média-Alta**

- **Evidência:** `app/Controllers/DashboardController.php` tem **140 funções, 2.596 linhas,
  163.608 bytes**. O teste `tests/enterprise/v104_48_1_architecture_test.php:16` trava o arquivo
  **abaixo de 160 KB (163.840 B)** — a folga atual é de **232 bytes**.
- **Causa raiz:** o `dispatch()` (linha 67) é um `switch` gigante; parte das rotas já delega a
  controllers dedicados (`PedidoController`, `FilaController`, `EstoqueController`,
  `FiscalController`, `AuditoriaController`, `OrquestracaoController`), mas **muitos handlers de
  domínio continuam inline** no próprio DashboardController.
- **Impacto (real, não estético):** o arquivo está a 232 bytes do teto. **Qualquer lógica de
  domínio nova nele reprova a CI** — foi o que já aconteceu ao adicionar o campo Empresa (I-?) e
  na etapa 1 do F6-07 (resolvido movendo para serviço). O teto é uma guarda contra o crescimento,
  não uma solução; o arquivo **precisa ser decomposto** (registrado no `CLAUDE.md`).
- **Clusters inline cujo dono o `RouteModuleRegistry::architectureControllers()` JÁ declara:**
  | Cluster | Rotas (dispatch) | Dono declarado | Métodos no Dashboard |
  |---|---|---|---|
  | **Tiny** | `tiny-v3-ficha`, `tiny-v2-ficha-tecnica`, `teste-real-tiny(-executar)`, `tiny-v3-token-salvar/renovar/revogar`, `tiny-v3-testar(-modulo)`, `tiny-v3-endpoints-salvar`, `tiny-webhooks` | `TinyController` | `tinyV3Ficha`, `tinyV2FichaTecnica`, `testeRealTiny(Executar)`, `tinyV3Token*`, `tinyV3Testar(Modulo)`, `tinyV3EndpointsSalvar`, `tinyWebhooks`, `testarTiny`, `tinyAmbientes` |
  | **VSM** | `vsm-ficha-tecnica`, `testar-vsm`, `simular-baixa-tiny`, `simular-produto/estoque/status-vsm` | `VsmController` | `vsmFichaTecnica`, `testarVsm`, `simular*` |
  | **Homologação** | `homologacao(-acao/-relatorio)`, `homologacao-automatica(-executar)`, `oauth-v3-checklist`, `selftest(-executar)` | `HomologacaoController` | idem |
  | **Config/Usuários/Backup** | `configuracoes`, `usuarios`, `backups`, `bancos-modulos` | `ConfiguracaoController` | `configuracoes`, `salvarConfiguracoes`, `usuarios`, `usuarioSalvar/Excluir`, `permissoesSalvar`, `backup*`, `bancosModulos*` |
- **Cluster SEM dono declarado (candidato a controller novo):** `security-center`/`-soc`/`-code-audit`/
  `-inventory`/`-backup-trust`/`-health`/`-audit-signatures`/`-events`/`-ips`/`-circuit-breakers`/
  `-hardening`/`-ssl`/`-user-audit`/`-pentest` + `security-audit-sign`, `security-fim(-gerar)`,
  `security-score`, `seguranca-auditoria`, `seguranca-extrema` (~20 rotas) → hoje em `securityCenter`
  e vizinhos. É o maior bloco coeso e o de maior ganho de bytes.
- **Correção recomendada (INCREMENTAL, um cluster por PR — padrão dos achados I-12/I-17):** mover
  cada cluster para o controller que o `RouteModuleRegistry` já aponta (ou criar `SecurityController`
  para o bloco de segurança), trocando o `case` do `dispatch()` por
  `(new XController())->dispatch($page)` — exatamente o mecanismo já usado por Pedido/Fila/Estoque.
- **Risco:** médio — é roteamento. Mitigado pela varredura de 178 rotas por HTTP (status + texto),
  o `controller-route-check`, e o teste E2E. Cada cluster é um PR isolado e reversível.
- **Compatibilidade:** nenhuma rota muda de URL; só muda quem a atende. Handlers preservam CSRF e
  permissões.
- **Como testar:** por cluster — a rota responde o mesmo HTTP/tela antes e depois; `controller-route-check`,
  E2E, e o teste de arquitetura (o arquivo encolhe). Reprovar sobre o código antigo.
- **Como reverter:** devolver o `case` ao método no Dashboard.
- **Status:** confirmado (decomposição proposta, incremental; NÃO aplicada nesta auditoria).

## A3-02 · Camada de view está limpa — **INFORMATIVO (sem achado)**
- **Evidência:** varredura de `views/` por `Database::`/`->prepare(`/`->query(` retorna **zero**
  acesso real a banco (o único casamento, `views/bancos_modulos.php:18`, é texto de documentação
  dentro de `<code>`, não consulta). Nenhuma regra de negócio/SQL vive em view.
- **Status:** confirmado OK — não há "regra em view" a corrigir.

## A3-03 · Acesso a banco nos controllers é o idioma do projeto — **INFORMATIVO**
- **Evidência:** os controllers usam `TenantScopeService::run(...)` + `Database::forTable(...)`
  diretamente (não há camada de repositório). `DashboardController` concentra ~68 pontos.
- **Avaliação:** é a arquitetura estabelecida do Hub (MVC vanilla, sem ORM), coerente com
  hospedagem compartilhada. Introduzir repositório seria reescrita ampla **sem problema medido** —
  a fase 3 proíbe. O isolamento por empresa já é garantido pelo `tenant-scope-check`.
- **Status:** registrado, sem ação.

## A3-04 · Código morto / órfão — **INFORMATIVO**
- **Evidência:** o `build-classmap.php --check` mantém a contagem (hoje **253 classes**) e o
  `RELATORIO-REMOCAO-CLASSES-ORFAS` já removeu 5 órfãs. Não foi encontrada nova classe órfã óbvia
  nesta passada.
- **Ressalva honesta:** não rodei uma varredura exaustiva de referência classe-a-classe (custosa e
  com risco de falso positivo por chamada dinâmica). Fica registrado como não reexecutado, não como
  "não há".
- **Status:** não identificado nesta passada (sem evidência de novo órfão).

## A3-05 · Método ausente / chamada a inexistente — **coberto por portões**
- `controller-route-check.php` confere método presente para as rotas de `$directActions`; e
  `v104_49_3_service_call_test` cobre chamadas a serviço. O achado I-11 (método de serviço
  inexistente) já está travado. Sem novo achado estático.

---

## Conclusão

O único achado acionável da Fase 3 é o **A3-01 (controller-deus)** — problema real (232 bytes de
folga; bloqueia feature nova) com caminho de correção **incremental, reversível e já provado**
(I-12/I-17). View limpa, DB-nos-controllers idiomático, sem órfão novo evidente.

**Não apliquei refatoração** — a fase 3 exige apresentar antes, e a decomposição do god-controller
é mudança arquitetural. Proposta: executar por **etapas, um cluster por PR**, começando pelo bloco
de **Segurança** (maior ganho de bytes e coeso, precisa de `SecurityController` novo) ou pelo
**Tiny** (dono já declarado). Aguardando decisão.
