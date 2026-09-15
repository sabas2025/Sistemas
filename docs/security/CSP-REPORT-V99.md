# Relatório CSP — V99

A V99 mantém CSP forte com nonce por requisição e adiciona `report-uri` para `index.php?page=api/csp-report`.

## Política recomendada

- `default-src 'self'`
- `script-src 'self' https://cdn.jsdelivr.net 'nonce-__NONCE__'`
- `style-src 'self' https://cdn.jsdelivr.net 'nonce-__NONCE__'`
- `object-src 'none'`
- `frame-ancestors 'self'`
- `base-uri 'self'`
- `form-action 'self'`
- `upgrade-insecure-requests`
- `report-uri index.php?page=api/csp-report`

## Observação operacional

Se aparecer violação CSP na Central de Eventos, verificar scripts inline antigos, CSS inline em views legadas ou CDN nova sem autorização explícita.
