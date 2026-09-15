# Relatório final — auditoria e correções V104.49.3-R5

**Data:** 22/08/2026

**Origem auditada:** `hub-tiny-v104-49-2-r4-enterprise-map-recovery-completo(1).zip`

**SHA-256 da origem:** `79946a45a7cdca0dfaab943b6a69a091096633781fa7eae9be84ca319b9a0295`

**Incidente inicial:** `index.php?page=vsm-endpoints` recusava o catálogo porque seis colunas de `vsm_endpoints` estavam ausentes.

## Conclusão executiva

A causa raiz foi corrigida no código completo. O instalador ativo usava oito SQLs modulares que estavam atrasados em relação ao contrato exigido pelo runtime. A release R5 passa a manter 134 tabelas e o contrato integral equivalente entre módulos, consolidado e reparo, incluindo as 24 colunas críticas que faltavam em cinco tabelas.

A requisição operacional VSM continua correta e conservadora: ela apenas detecta schema incompleto e nunca executa DDL. Para instalação existente, o caminho suportado é **Central Técnica > Banco > Enterprise Core** ou, em banco único e após backup, as migrations `20260712_001`, `002`, `003`, `004` e `20260713_007`.

## Situação dos achados originais

| ID | Severidade original | Resultado na R5 |
|---|---|---|
| F-01 | Crítico | Corrigido: oito módulos, consolidado, alias, repair e baseline contêm as 24 colunas; paridade de 134 tabelas. |
| F-02 | Crítico | Corrigido: autorização CLI aleatória de 256 bits, hash-only, 15 minutos, uso único, vínculo de navegador, POST, CSRF e HTTPS. |
| F-03 | Alto | Corrigido: Quality Gate valida tabelas, contratos de coluna, índices e conexão; metadado indisponível reprova. |
| F-04 | Alto | Corrigido: CI inclui paridade, instalação consolidada e oito bancos modulares em MySQL 8 e MariaDB 11.4. |
| F-05 | Alto | Corrigido: Enterprise Core é o fluxo único; updater morto foi removido/bloqueado e `repair_current.sql` atual foi documentado. |
| F-06 | Alto | Corrigido no fechamento: manifesto SHA-256 é regenerado somente depois do último arquivo e exclui estado runtime mutável. |
| F-07 | Médio | Corrigido: confirmações usam `data-confirm` e listener compatível com CSP e `event.submitter`. |
| F-08 | Médio | Corrigido: confirmação não é herdada; fonte auditável é obrigatória; alteração sem reconfirmação desativa e desverifica. |
| F-09 | Médio | Corrigido: o seed textual de administrador é removido e a conta é inserida com PDO preparado. |
| F-10 | Baixo | Corrigido: rotas configuráveis foram centralizadas no `VsmController`; tutorial atualizado. |
| F-11 | Baixo | Corrigido: versão `V104.49.3-R5`, data, PWA, package/lockfile e documentação sincronizados. |

## Endurecimentos adicionais da auditoria independente

- O instalador agora é exclusivo para banco novo. Um preflight consulta o catálogo canônico e bloqueia qualquer tabela do Hub preexistente antes do DDL dos módulos. Não há modo implícito de reinstalação ou sobrescrita de administrador.
- O teste posterior de privilégios usa nome criptograficamente aleatório, `CREATE` sem `IF NOT EXISTS`, marca a criação própria antes do `finally` e só então permite o `DROP`; também testa `ALTER` e `INDEX`. A antiga tabela fixa de permissão nunca é tocada.
- O gate final deixou de validar apenas nomes/24 colunas: compara todas as 134 tabelas e 1.511 colunas declaradas, tipos, nulabilidade, defaults, `AUTO_INCREMENT`, `ON UPDATE`, índices com ordem/unicidade, engine, charset e collation explicitamente declarada.
- `X-Forwarded-Proto` só é confiado quando `REMOTE_ADDR` consta em `HUB_INSTALL_TRUSTED_PROXIES`; endereço privado isoladamente não concede confiança.
- Host, banco, usuário, senha, nome/e-mail do administrador e Base URL têm limites, controles e formatos validados. Base URL absoluta exige HTTPS e rejeita userinfo, query e fragmento.
- Publicação de config, manifesto FIM e lock é atômica e reversível, com lock por último. A documentação deixa explícito que DDL MySQL não possui rollback integral e pode ficar parcial se o servidor falhar durante a criação.
- O schema consolidado é gerado deterministicamente dos oito módulos; a CI bloqueia qualquer diferença entre `install.sql` e `install_final_current.sql`.
- Reaplicar o consolidado não atualiza usuários, credenciais nem configurações operacionais; seeds conflitantes usam inserção idempotente.
- O AutoRepair operacional executa apenas `CREATE TABLE IF NOT EXISTS` e `ALTER TABLE ... ADD`, ignora DML e termina com `SchemaMigrationService::status()` estrito. Divergência existente é erro, nunca “já atendido”.
- O corpo de resposta VSM não é persistido. São armazenados somente status HTTP, duração, tamanho, content-type, SHA-256 e código diagnóstico. Corpos/erros legados são ocultados na leitura.
- Fonte HTTPS de contrato rejeita credenciais, query, fragmento, controles e traversal; o banco e a auditoria conservam apenas host e hash da referência. OpenAPI continua restrito a `contracts/vsm/*.json`, sem busca externa.
- Sondas comuns são sempre seguras: métodos mutáveis são substituídos por GET e não comprovam a saúde do método original.

## Contrato crítico recuperado

| Tabela | Colunas recuperadas |
|---|---|
| `configuracoes_integracao` | `vsm_ambiente`, `vsm_producao_liberada`, `vsm_ultimo_teste_ok`, `vsm_ultimo_teste_em`, `vsm_host_producao_liberado`, `vsm_producao_liberada_em`, `vsm_producao_liberada_por` |
| `schema_migrations` | `version`, `description` |
| `vsm_endpoints` | `modo_teste_seguro`, `origem`, `contract_verified`, `contract_source`, `contract_checked_at`, `is_template` |
| `vsm_campos_mapeamento` | `tipo_dado`, `valor_padrao`, `regra_validacao`, `exemplo_payload`, `origem`, `contract_verified`, `contract_source`, `is_template` |
| `vsm_endpoint_logs` | `modo_teste_seguro` |

## Evidências estáticas executadas

| Verificação | Resultado |
|---|---|
| ZIP original | SHA-256 confirmado conforme acima |
| PHP | 430 arquivos inventariados; balanceamento lexical/estrutural sem anomalia |
| JavaScript/MJS | 20 arquivos aprovados por `node --check` |
| JSON | 22 arquivos parseados |
| YAML | 2 workflows parseados |
| Shell | 3 scripts aprovados por `bash -n` |
| SQL ativo | 42 arquivos com aspas/comentários/parênteses balanceados |
| Alias consolidado | `install.sql` e `install_final_current.sql` byte a byte idênticos |
| Paridade | 134 tabelas; módulos, consolidado e `repair_current.sql` sem divergência estrutural |
| Parser do gate | Cobertura estática de 134 tabelas, 1.511 colunas e 517 índices declarados |
| Inventário | Mesmo conjunto de 134 tabelas, sem duplicatas |
| DML sensível no consolidado | Nenhum overwrite de usuário/configuração, `DELETE` ou `TRUNCATE` |
| Rotas VSM | Sem duplicação do grupo configurável; mutações em POST/CSRF |
| Regressões de segurança | Ordem preflight/sonda/módulos, preservação da tabela fixa legada e matriz URL com userinfo/query/fragment cobertas |
| Whitespace | `git diff --no-index --check` sem erro |

## Limitações e critério de promoção

Este ambiente de montagem não possui PHP CLI, PDO MySQL, servidor MySQL, MariaDB ou Docker. Portanto, não foi possível executar aqui `php -l`, a suíte Enterprise, uma instalação web real ou as migrations contra um servidor SQL. O acesso de rede necessário para `npm ci` também não foi autorizado; o `package-lock.json` foi validado estaticamente contra o `package.json`.

Essas execuções estão configuradas como gates no workflow: PHP 8.2, MySQL 8.0, MariaDB 11.4, instalação consolidada, instalação modular em oito bancos, reaplicação, migrations e testes Enterprise. A promoção para produção deve ocorrer somente após o workflow real ficar verde e após backup validado da instalação alvo.

## Procedimento da instalação afetada

1. Faça backup verificável de arquivos e de todos os bancos/módulos.
2. Preserve `config/config.php`, `storage/install.lock` e o manifesto FIM atual.
3. Atualize os arquivos da aplicação com a R5, sem copiar cache, logs ou autorização de outro servidor.
4. Abra **Central Técnica > Banco > Enterprise Core** e execute **Simular**.
5. Confirme banco selecionado/configurado e aplique **Enterprise Core**.
6. Exija Quality Gate 100% válido; depois reabra Endpoints, Campos, Logs e Saúde VSM.
7. Só ative endpoints depois de validar o contrato oficial e executar sonda segura em homologação.

`database/repair_current.sql` é fallback para banco único, após backup. Ele contém DDL e seeds idempotentes de catálogo/permissões/migrations; o AutoRepair do painel ignora esses DMLs. Em banco modular, prefira sempre Enterprise Core.
