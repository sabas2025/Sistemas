# Arquitetura de Conectores — V104.16

Pasta base: `app/Connectors`.

Interface padrão: `ConnectorInterface`.

Métodos:

- `code()`
- `name()`
- `category()`
- `status()`
- `capabilities()`
- `requirements()`
- `health()`

Registro: `ConnectorRegistryService`.

Conectores ativos:

- Tiny V2/V3
- VSM pedidos-integradora

Conectores planejados:

- Bling
- Omie
- Mercado Livre
- Shopee
- Amazon
- TikTok Shop

Cada conector futuro deve implementar autenticação, homologação, rate limit, auditoria, retry e circuit breaker próprios.
