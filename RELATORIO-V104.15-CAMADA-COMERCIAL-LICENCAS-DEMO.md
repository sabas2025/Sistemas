# RELATÓRIO V104.15 — Camada Comercial, Licenças, Conectores e Demo

## Objetivo
Adicionar ao HUB uma camada comercial para transformar o sistema em produto vendável, com frases comerciais, página institucional, planos, licenças por cliente, conectores plugáveis, painel de cobrança, documentos comerciais/técnicos e ambiente demo sem dados reais.

## Itens aplicados

### 1. Frases comerciais
Criado catálogo de frases comerciais em `CommercialProductService::phrases()` e documento `docs/comercial/01-frases-comerciais.md`.

Frase principal:
> Automatize pedidos, estoque e NF-e entre Tiny, VSM e outros ERPs com segurança, auditoria e rastreabilidade total.

### 2. Tela comercial de planos
Nova rota autenticada:
- `index.php?page=planos-comerciais`

Nova view:
- `views/commercial_plans.php`

Planos criados:
- Starter
- Professional
- Enterprise
- White-label / Código-fonte

### 3. Manual de instalação
Documento criado:
- `docs/comercial/02-manual-instalacao.md`

### 4. Manual do cliente
Documento criado:
- `docs/comercial/03-manual-cliente.md`

### 5. Manual técnico da VSM
Documento criado:
- `docs/comercial/04-manual-tecnico-vsm.md`

Reforço técnico:
- usar `pedidos-integradora` como padrão do HUB;
- deixar `pedidos-loja` opcional;
- pedir token/API Key/OAuth/empresa/filial/CNPJ/webhook secret/HMAC conforme contrato VSM.

### 6. Contrato de suporte
Documento criado:
- `docs/comercial/05-contrato-suporte.md`

Observação: modelo inicial, precisa revisão jurídica antes de venda formal.

### 7. Termos de responsabilidade
Documento criado:
- `docs/comercial/06-termos-responsabilidade.md`

### 8. Política de backup
Documento criado:
- `docs/comercial/07-politica-backup.md`

### 9. SLA
Documento criado:
- `docs/comercial/08-sla.md`

### 10. Tela de licença por cliente
Nova rota:
- `index.php?page=licencas-clientes`

Ação demo:
- `index.php?page=licencas-clientes-demo`

Nova tabela:
- `comercial_clientes_licencas`

A tela permite gerar licença demo sem expor a chave no banco. A chave é exibida uma vez e o banco guarda HMAC/hash.

### 11. Multiempresa/multifilial
Documento criado:
- `docs/comercial/09-multiempresa-multifilial.md`

O documento orienta separação por empresa, filial, CNPJ, credenciais, estoque e regras.

### 12. Conectores plugáveis
Nova rota:
- `index.php?page=conectores-plugaveis`

Nova tabela:
- `comercial_conectores_catalogo`

Conectores cadastrados/planejados:
- Tiny V2
- Tiny V3
- VSM pedidos-integradora
- VSM pedidos-loja opcional
- Bling
- Omie
- Mercado Livre
- Shopee
- Amazon
- TikTok Shop

Documento criado:
- `docs/comercial/10-conectores-plugaveis.md`

### 13. Painel de cobrança
Nova rota:
- `index.php?page=painel-cobranca`

Ação demo:
- `index.php?page=painel-cobranca-demo`

Nova tabela:
- `comercial_cobranca_faturas`

Observação: painel de controle comercial, ainda não integrado a gateway real. Mercado Pago/Pix/boleto/cartão deve ser integrado em etapa própria.

### 14. Página institucional do produto
Nova rota pública:
- `index.php?page=produto-institucional`

Nova view:
- `views/commercial_landing_public.php`

Por segurança, o HUB mantém headers globais `noindex` no app. Para SEO público real, recomenda-se publicar essa página em domínio/site institucional separado.

### 15. Demonstração online
Nova rota pública:
- `index.php?page=demo-online`

Nova view:
- `views/demo_online_public.php`

A demo exibe dados fictícios e aviso claro:
- sem dados reais;
- sem tokens;
- sem pedidos reais;
- sem XML/NF-e real;
- sem chamadas reais Tiny/VSM.

### 16. Ambiente demo sem dados reais
Nova rota autenticada:
- `index.php?page=ambiente-demo`

Nova tabela:
- `comercial_demo_ambientes`

Documento criado:
- `docs/comercial/11-ambiente-demo.md`

### 17. Central de documentos comerciais
Nova rota:
- `index.php?page=documentos-comerciais`

Leitura individual:
- `index.php?page=documento-comercial&file=NOME.md`

Views:
- `views/commercial_docs.php`
- `views/commercial_doc_view.php`

## Arquivos principais criados
- `app/Controllers/CommercialController.php`
- `app/Services/CommercialProductService.php`
- `views/commercial_plans.php`
- `views/commercial_licenses.php`
- `views/commercial_connectors.php`
- `views/commercial_billing.php`
- `views/commercial_demo_environment.php`
- `views/commercial_docs.php`
- `views/commercial_doc_view.php`
- `views/commercial_landing_public.php`
- `views/demo_online_public.php`
- `database/update_v104_15_comercial_produto.sql`
- `database/install_final_v104_15.sql`
- `docs/comercial/*.md`

## Arquivos alterados
- `app/Services/FastRouteDispatcherService.php`
- `views/layout_top.php`
- `app/Core/Database.php`
- `database/install.sql`
- `database/modules/core.sql`
- `storage/cache/classmap.php`

## Banco de dados
Novas tabelas:
- `comercial_clientes_licencas`
- `comercial_conectores_catalogo`
- `comercial_cobranca_faturas`
- `comercial_demo_ambientes`

SQL incremental:
- `database/update_v104_15_comercial_produto.sql`

## Validação
- PHP lint executado em todos os arquivos `.php`.
- Nenhum erro de sintaxe encontrado.
- Classmap regenerado.
- ZIP testado com `unzip -t`.

## Próximas melhorias recomendadas
1. Integrar painel de cobrança com Mercado Pago/Pix/boleto.
2. Criar tela de edição completa de licenças.
3. Criar bloqueio runtime por licença expirada, se o modelo comercial exigir.
4. Criar domínio institucional separado para SEO público.
5. Criar ambiente demo hospedado separado da produção.
6. Criar contrato final revisado juridicamente.
7. Criar assinatura digital dos contratos e aceite eletrônico.
