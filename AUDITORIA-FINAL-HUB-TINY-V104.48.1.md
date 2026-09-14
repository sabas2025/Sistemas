# Auditoria final — HUB Tiny V104.48.1

**Data da auditoria:** 12 de julho de 2026  
**Base comparada:** `hub-tiny-v104-48-correcoes-auditadas.zip`  
**Resultado:** correções de código concluídas e aprovadas nos gates locais; liberação definitiva de produção condicionada aos gates externos de homologação descritos no final.

## 1. Veredito executivo

As correções anteriormente ausentes ou parciais foram aplicadas no código. A auditoria final confirmou:

- nenhuma funcionalidade, rota, tabela ou arquivo existente foi removido;
- 119 arquivos existentes foram modificados e 28 arquivos foram adicionados;
- nenhuma remoção foi identificada na comparação com o pacote corrigido anterior;
- Tiny V2/V3 e VSM mantêm as mesmas interfaces públicas e fluxos de negócio;
- fila, OAuth, banco, PWA, backup, autenticação, logs e segurança receberam correções verificáveis;
- todos os testes executáveis no ambiente atual passaram;
- os itens que exigem infraestrutura externa não foram mascarados como aprovados.

O pacote pode seguir para **homologação controlada**. A aprovação de produção depende de MySQL real, extensões `pdo_mysql`, `curl` e `zip`, credenciais de homologação Tiny/VSM e contratos OpenAPI oficiais da VSM.

## 2. Correções aplicadas

### 2.1 OAuth Tiny V3

**Problema:** a renovação manual podia retornar sucesso sem renovar; locks MySQL podiam colidir entre instalações e não havia fallback seguro.

**Impacto:** token expirado, refresh concorrente, sobrescrita de token e comportamento incorreto do botão “Renovar”.

**Solução aplicada:**

- `refresh(true)` sempre executa a chamada OAuth;
- `refresh(false)` reutiliza token renovado por outro worker somente no fluxo automático;
- lock nomeado por `installation_id` + ambiente, limitado a 64 caracteres;
- fallback local por arquivo com `flock` quando `GET_LOCK` estiver indisponível;
- liberação de lock em `finally`;
- mensagens e códigos de erro distintos para lock ocupado e lock indisponível.

**Benefícios:** renovação determinística e proteção contra múltiplos workers.

**Riscos residuais:** fallback por arquivo protege apenas processos no mesmo nó. Em cluster, `GET_LOCK` ou um lock distribuído compartilhado continua obrigatório.

**Compatibilidade:** assinatura pública de `refresh()` preservada; chamadas automáticas e manuais continuam funcionando.

### 2.2 Fila, lease, heartbeat e reprocessamento

**Problema:** lease fixo, reprocessamento de item ainda ativo, resultados de worker obsoleto e ações de idempotência sem prova de posse.

**Impacto:** processamento duplicado, corrida entre workers e sobrescrita de resultado.

**Solução aplicada:**

- lease padrão e lease por tipo configuráveis;
- `locked_by`, heartbeat e conclusão exigem posse;
- resultado de worker obsoleto é descartado e auditado;
- reprocessamento administrativo bloqueia item com lease ativo;
- item expirado pode ser reprocessado em transação;
- tentativas e erro anteriores são preservados em histórico;
- ações de idempotência e DLQ exigem proprietário atual;
- fallback de `SKIP LOCKED` é registrado uma vez por processo.

**Benefícios:** reduz duplicidade e mantém rastreabilidade administrativa.

**Riscos residuais:** throughput e duração de lease devem ser ajustados com dados reais de produção.

**Compatibilidade:** colunas novas são opcionais durante transição e possuem migration idempotente.

### 2.3 Banco, schema e migrations

**Problema:** tabelas duplicadas no schema oficial, DDL durante requisições operacionais e migrations incompletas para bancos existentes.

**Impacto:** erros de coluna duplicada, metadata locks, instalações divergentes e telas travadas.

**Solução aplicada:**

- `install_final_current.sql` consolidado em 134 tabelas sem duplicidade;
- migrations idempotentes:
  - `20260712_001_queue_oauth_concurrency.sql`;
  - `20260712_002_schema_runtime_vsm_contract.sql`;
- compatibilidade com versões antigas de `schema_migrations`;
- 12 tabelas antes criadas em runtime migradas para schema/migration;
- 42 alterações condicionais na migration 002, com `PREPARE/EXECUTE/DEALLOCATE` balanceados;
- DDL operacional substituído por validação de schema;
- reparo automático desativado por padrão;
- reparo permitido somente em instalação/manutenção explícita;
- cache de existência de tabelas/colunas com invalidação após migration;
- verificador CI reprova novo DDL literal ou indireto fora da lista autorizada.

**Benefícios:** instalação e atualização previsíveis, menor risco de lock e rollback mais claro.

**Riscos residuais:** a execução real da migration precisa ser homologada em MySQL 8/MariaDB compatível.

**Compatibilidade:** instalações novas usam schema consolidado; instalações existentes usam migrations sem apagar dados.

### 2.4 Arquitetura e controllers

**Problema:** duplicação da orquestração e grande concentração no `DashboardController`.

**Impacto:** divergência de regras, manutenção difícil e regressões.

**Solução aplicada:**

- atualizações legadas movidas para `LegacyDatabaseUpgradeController`;
- orquestração removida do controller central e delegada a `OrquestracaoController`;
- fallback das rotas antigas preservado;
- `DashboardController` reduzido para 2.525 linhas e 157.832 bytes;
- verificador de rotas valida classes, métodos e duplicidades.

**Benefícios:** uma única implementação por domínio e menor superfície de regressão.

**Riscos residuais:** o controller central ainda é grande e deve continuar sendo reduzido por domínio em versões futuras.

**Compatibilidade:** URLs, nomes de páginas e formulários existentes foram preservados.

### 2.5 Tiny V2/V3 — proteção de saída HTTP

**Problema:** URLs configuráveis de Tiny V2, Tiny V3 e OAuth não possuíam proteção SSRF equivalente à VSM.

**Impacto:** acesso indevido a hosts internos, DNS rebinding, redirecionamento e uso de endpoint OAuth malicioso.

**Solução aplicada:**

- novo `TinyEndpointSecurityService`;
- HTTPS obrigatório;
- allowlist padrão `api.tiny.com.br,accounts.tiny.com.br`;
- porta padrão permitida: 443;
- bloqueio de credenciais embutidas, query/fragmento em URL base, host local, IP privado/reservado e traversal codificado;
- resolução DNS validada e fixada com `CURLOPT_RESOLVE`;
- redirects e protocolos alternativos desativados;
- validação ao salvar configurações;
- Auth URL inválida bloqueia o botão OAuth e gera auditoria;
- corpo de erro Tiny V2 passa por redaction antes da auditoria.

**Benefícios:** reduz SSRF, phishing administrativo e vazamento de dados.

**Riscos residuais:** novos hosts oficiais precisam ser incluídos explicitamente na allowlist.

**Compatibilidade:** hosts oficiais atuais continuam permitidos; endpoints relativos Tiny permanecem iguais.

### 2.6 VSM, Swagger e OpenAPI

**Problema:** endpoints locais de exemplo podiam parecer oficiais e ativos; faltava governança de contrato.

**Impacto:** chamadas para caminhos não confirmados e falsa sensação de homologação.

**Solução aplicada:**

- modelos locais VSM nascem inativos, `is_template=1` e `contract_verified=0`;
- teste de endpoint é bloqueado enquanto o modelo não for confirmado;
- UI identifica “Modelo não verificado”;
- registros manuais ficam identificados como `manual_admin`;
- proteção SSRF, DNS pinning, sem redirects e protocolos restritos;
- `VsmOpenApiContractService` e CI local adicionados;
- diretório `contracts/vsm` contém instruções e não inclui contrato inventado.

**Benefícios:** diferencia configuração manual de contrato oficial e evita ativação acidental.

**Riscos residuais:** os JSON OpenAPI oficiais ainda precisam ser exportados do Swagger VSM.

**Compatibilidade:** registros já existentes não são apagados; apenas novos modelos locais seguem a política segura.

### 2.7 Autenticação, senha e rate limit

**Problema:** políticas de senha divergentes e rate limit de login podia falhar aberto quando o banco estivesse indisponível.

**Impacto:** senhas fracas, brute force durante falha parcial e possível erro 500 ao registrar tentativa.

**Solução aplicada:**

- `PasswordPolicyService` central;
- mínimo de 10 caracteres, maiúscula, minúscula e número;
- bloqueio de senhas comuns e do identificador do e-mail;
- senha temporária forte e exibida uma única vez;
- troca de senha incrementa versão de sessão;
- fallback de rate limit por arquivo, com hash de IP/e-mail;
- falha de persistência não derruba o login;
- logs não expõem e-mail em claro.

**Benefícios:** política uniforme e proteção degradada durante indisponibilidade do MySQL.

**Riscos residuais:** em cluster, o fallback local não é compartilhado entre nós.

**Compatibilidade:** senhas existentes não são invalidadas; a nova política é aplicada somente a novas senhas e alterações.

### 2.8 Logs, Trace ID e LGPD

**Problema:** exceções “best effort” desapareciam silenciosamente e payloads podiam conter dados sensíveis.

**Impacto:** incidentes sem diagnóstico e risco LGPD.

**Solução aplicada:**

- `BestEffortLogService` central;
- falhas auxiliares passam a registrar contexto e Trace ID sem interromper Tiny/VSM;
- `SensitiveDataService` ampliado;
- snapshots e logs de request/response passam por redaction;
- tokens, authorization, CPF/CNPJ, e-mail e telefone são mascarados;
- erros de UI usam mensagem segura;
- auditoria de catches silenciosos: zero ocorrência inesperada.

**Benefícios:** observabilidade sem reduzir resiliência.

**Riscos residuais:** política de retenção deve ser configurada conforme LGPD e obrigação fiscal.

**Compatibilidade:** tabelas e campos de log permanecem os mesmos.

### 2.9 Backup e restore

**Problema:** backup de ambiente modular podia copiar apenas o banco principal; restore precisava de controles adicionais.

**Impacto:** backup incompleto, restauração no banco errado e risco de ZIP malicioso.

**Solução aplicada:**

- pacote modular com um SQL por conexão/módulo;
- manifesto V2 assinado com hash, módulo, modo e instalação;
- bloqueio de backup legado em ambiente modular;
- compatibilidade com backup antigo em banco único;
- limites contra path traversal e ZIP bomb;
- nome aleatório, escrita com lock e permissão `0600`;
- restore valida comandos permitidos e cria ponto de retorno;
- DDL com commit implícito é identificado.

**Benefícios:** backup completo nos modos hospedagem compartilhada e VPS modular.

**Riscos residuais:** restauração real precisa ser testada em ambiente com `ZipArchive` e MySQL.

**Compatibilidade:** backups legados de banco único continuam aceitos no modo compatível.

### 2.10 PWA e qualidade visual

**Problema:** versão divergente em `offline.html`, teste histórico rígido e HTTP 500 mascarado como offline.

**Impacto:** assets antigos, diagnóstico incorreto e falso negativo de teste.

**Solução aplicada:**

- versão sincronizada em `104.48.1`;
- service worker, pacote, offline e testes usam a mesma versão;
- erro HTTP 500 não é substituído por offline;
- fallback ocorre somente em falha de rede;
- assets minificados regenerados;
- teste Playwright renderiza localmente quando política corporativa bloqueia loopback;
- dependências npm auditadas sem vulnerabilidades conhecidas.

**Benefícios:** atualização previsível, mensagem correta e cobertura E2E real.

**Compatibilidade:** manifesto, escopo, ícones e URLs existentes preservados.

### 2.11 Integridade de arquivos — FIM

**Problema:** o manifesto distribuído continha hashes antigos e o instalador criava uma nova chave FIM sem recriar a assinatura.

**Impacto:** uma instalação nova poderia apresentar alerta falso de adulteração, mesmo com arquivos legítimos.

**Solução aplicada:**

- manifesto atual regenerado para os 234 arquivos PHP críticos;
- assinatura HMAC validada com a chave configurada;
- instalador recria o manifesto depois de publicar o `config.php` e antes do `install.lock`;
- a chave usada é a `fim_manifest_hmac_key` recém-gerada na própria instalação;
- gravação administrativa passou a usar arquivo temporário, `LOCK_EX`, permissão `0600` e `rename` atômico;
- teste enterprise verifica arquivos, hashes, assinatura e ordem segura da instalação.

**Benefícios:** elimina falso positivo pós-instalação e impede publicação parcial do manifesto.

**Riscos residuais:** qualquer alteração legítima posterior em arquivo crítico exige regeneração administrativa auditada do manifesto.

**Compatibilidade:** o formato JSON e o método público `FileIntegrityService::saveManifest()` foram preservados.

## 3. Resultados da validação final

| Validação | Resultado |
|---|---:|
| Arquivos PHP verificados | 416 |
| Falhas de sintaxe PHP | 0 |
| Testes enterprise independentes | 19 aprovados / 0 falhas |
| Regressão agregada | 0 erros; 11 checks MySQL marcados como `skip` por falta de driver |
| Testes Playwright/PWA com Chromium | 5 aprovados / 0 falhas |
| `npm audit` | 0 vulnerabilidades |
| Build dos assets PWA | aprovado |
| Sintaxe do service worker | aprovada |
| Tabelas no schema consolidado | 134 |
| Duplicidade de tabelas | 0 |
| Rotas verificadas | 96 |
| Classes indexadas no verificador | 229 |
| DDL operacional não autorizado | 0 |
| Arquivos críticos no manifesto FIM | 234 |
| Assinatura HMAC FIM | válida |
| Catches silenciosos inesperados | 0 |
| Migrations com aspas/comentários desbalanceados | 0 |
| Migration 001 — PREPARE/EXECUTE/DEALLOCATE | 18/18/18 |
| Migration 002 — PREPARE/EXECUTE/DEALLOCATE | 42/42/42 |
| Arquivos removidos em relação ao pacote anterior | 0 |

## 4. Testes adicionados ou reforçados

- concorrência, posse e reprocessamento da fila;
- idempotência e DLQ por proprietário;
- refresh manual OAuth e isolamento de lock;
- rate limit degradado;
- redaction de dados sensíveis;
- política de senhas;
- backup modular e segurança de restore;
- arquitetura, rotas e DDL runtime;
- VSM SSRF e governança de template/contrato;
- Tiny V2/V3/OAuth SSRF;
- schema e migration 002;
- PWA, telemetria, offline e renderização visual.
- manifesto FIM, assinatura e publicação atômica no instalador.

## 5. Gates externos obrigatórios antes da produção

Estes itens não podem ser comprovados dentro do ambiente atual e permanecem explicitamente bloqueados:

### 5.1 MySQL real

O ambiente de auditoria possui PDO sem drivers. Execute em homologação com `pdo_mysql`:

```bash
export HUB_TEST_DB_HOST=127.0.0.1
export HUB_TEST_DB_PORT=3306
export HUB_TEST_DB_USER=usuario_homolog
export HUB_TEST_DB_PASS='senha'
export HUB_TEST_DB_NAME=hub_v104_48_1_homolog_test
php scripts/ci/mysql-runtime-regression.php
```

O script cria e remove somente banco cujo nome contenha `test`, `homolog` ou `ci`. Ele valida instalação, migrations repetidas, tabelas críticas e posse da fila.

### 5.2 OpenAPI oficial da VSM

Exporte os documentos oficiais para:

```text
contracts/vsm/pedidos-integradora.openapi.json
contracts/vsm/pedidos-loja.openapi.json
```

Depois execute:

```bash
php scripts/ci/vsm-openapi-check.php
```

### 5.3 Tiny e VSM reais

Executar em homologação, com SKU e pedido exclusivos de teste:

- OAuth e refresh Tiny V3;
- leitura Tiny V2/V3;
- pedido Tiny → HUB → VSM;
- estoque VSM → HUB → Tiny;
- NF-e/XML VSM → HUB → Tiny;
- 429, timeout, token expirado, Circuit Breaker e DLQ;
- idempotência com requisição duplicada;
- reconciliação sem sobrescrever a fonte de verdade.

### 5.4 Backup/restore real

O ambiente atual não possui `ZipArchive`. Validar em homologação com extensões `zip`, `curl` e `pdo_mysql`, restaurando em banco temporário antes de qualquer troca.

## 6. Aprovação

| Etapa | Situação |
|---|---|
| Correções de código solicitadas | **Concluídas** |
| Auditoria estática | **Aprovada** |
| Testes comportamentais locais | **Aprovados** |
| Testes PWA/Chromium | **Aprovados** |
| Integridade e compatibilidade de arquivos | **Aprovadas** |
| Homologação MySQL real | **Obrigatória / não executada neste ambiente** |
| Homologação Tiny/VSM real | **Obrigatória / sem credenciais neste ambiente** |
| Contrato OpenAPI oficial VSM | **Pendente de fornecimento/importação** |
| Produção imediata | **Condicionada aos gates externos** |

## 7. Conclusão

As correções ausentes e parciais identificadas na validação anterior foram efetivamente aplicadas. O pacote possui evidências reproduzíveis, migrations idempotentes, testes atualizados e verificadores CI. Não há evidência de remoção de funcionalidade ou quebra deliberada de compatibilidade.

A limitação restante não é código omitido: é a ausência, no ambiente de auditoria, de MySQL/PDO, cURL, ZipArchive, credenciais de homologação e documentos OpenAPI oficiais. Esses gates foram deixados visíveis e com executores incluídos, em vez de serem marcados falsamente como aprovados.
