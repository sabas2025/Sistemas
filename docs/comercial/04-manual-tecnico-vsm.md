# Manual técnico da VSM

## API recomendada
Para HUB Tiny ↔ VSM, usar como padrão o perfil `pedidos-integradora`.

O perfil `pedidos-loja` deve ficar opcional e só deve ser usado quando a VSM solicitar endpoint específico de loja.

## Credenciais necessárias
- Base URL de homologação e produção.
- Tipo de autenticação: Bearer, API Key, Basic Auth ou OAuth.
- Token/API Key/usuário/senha/Client ID/Client Secret conforme o contrato.
- Código de empresa, loja ou filial.
- CNPJ autorizado.
- Secret de webhook se a VSM enviar eventos ao HUB.
- Chave HMAC se houver assinatura de payload.
- IPs autorizados se a VSM exigir allowlist.

## Segurança do fluxo
- Não aplicar WAF agressivo no payload VSM.
- Usar auditoria, Trace ID, retry, timeout, circuit breaker e anti-replay.
- Validar HMAC/secret antes de gravar replay guard.
- Evitar duplicidade e loop entre pedido, estoque e NF-e.
