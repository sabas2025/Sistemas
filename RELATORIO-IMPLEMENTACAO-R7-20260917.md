# Relatório de implementação — HUB Tiny/VSM R7 build 20260917.1

Data: 18/09/2026  
Base: `RELATORIO-PLANO-MELHORIAS-HUB-V104.49.3-R7(1).md`

## Resultado

As correções prioritárias do plano foram incorporadas ao código e documentadas em `UPGRADE-R7-20260917.md`.

### Implementado

- Identidade única da release: `V104.49.3-R7+20260917.1`, com data `2026-09-17`, usada pelo instalador, configuração efetiva, schema consolidado e `VERSAO.txt`.
- Mensagens de schema atualizadas para apontar o procedimento R7, sem apagar identificadores históricos.
- Executor `scripts/upgrade-r7.php` com simulação, allowlist das migrations 008–017, agrupamento single/modular, lock `GET_LOCK`, checksum do plano, registro JSONL e pós-condições.
- Parser de SQL que preserva literais, `PREPARE/EXECUTE/DEALLOCATE` e recusa `DELIMITER`/SQL incompleto.
- BIGINT 012 separado em fase de manutenção; sem promessa de rollback transacional de DDL.
- Isolamento empresarial fechado: contexto ausente nega leitura e bloqueia gravação; `OR` é agrupado antes do predicado de empresa; NULL permanece fora do processamento normal.
- Revalidação de empresa/perfil do usuário, filtros de fila/estoque/fiscal e preservação do vínculo na DLQ.
- Teste de warnings inesperados no executor Enterprise e teste mutacional que injeta uma gravação sem escopo em cópia descartável.
- MyOuro GraphQL separado do REST: URL fixa `https://msx.vsm.api.br/graphql`, token cifrado AES-256-GCM, empresa/loja vinculadas, query somente leitura de estoque, validação de `errors` GraphQL/HTTP 200, escopo produto/loja e ausência de mutation fictícia.
- Migration 017 e tela `index.php?page=myouro-configuracoes`, com confirmação explícita da empresa e token nunca devolvido ao HTML.
- Inventário, schema consolidado e classmap regenerados.

## Validações executadas

- PHP lint: aprovado.
- Suíte Enterprise: 47 testes aprovados.
- Rotas/controllers: 108 mapeamentos e 244 classes, aprovado.
- Isolamento estático: 172 consultas cobertas e 70 gravações cobertas.
- Paridade modular/consolidada: 136 tabelas, aprovada.
- Inventário SQL: 136 tabelas, aprovado.
- Higiene de segredos e schema estático: aprovados.
- MariaDB local descartável: instalação consolidada e modular, migrations históricas repetidas, migrations R7 008–017 repetidas, BIGINT, lock concorrente, falha parcial, preservação de token, criptografia MyOuro, escopo com `OR` e bloqueio multicliente: aprovados.
- HTTP local: login, tela MyOuro, salvamento sem exposição do token, CSRF (403) e método incorreto (405): aprovados.

## Não marcado como concluído

- Não foram usadas credenciais reais nem chamadas autenticadas à Tiny/VSM/MyOuro.
- Não foi comprovado o contrato REST de criação de pedidos, `clientToken/clientSecret`, endpoint de produção ou payload oficial da VSM.
- Não foi liberado multicliente: a release exige uma empresa por instalação até homologação de credenciais isoladas.
- A matriz MySQL 8 e MariaDB 11.4 do GitHub Actions permanece necessária no ambiente CI; o runtime local validado foi MariaDB 10.11.
- O pacote não é uma certificação de produção nem substitui backup/restauração e janela de manutenção.

## Rollback

Preservar o ZIP anterior, `config/config.php`, banco e logs do upgrade. Em falha, parar workers, identificar a migration/statement no JSONL, restaurar uma cópia testada e reconciliar eventos posteriores. Não apagar registros `schema_migrations` para forçar a aplicação.
