# Servidor licenciador remoto

Para SaaS/white-label, recomenda-se ativar um servidor licenciador externo.

Campos sugeridos em `config/config.php`:

```php
'commercial' => [
  'license_mode' => 'enforce',
  'license_server_enabled' => true,
  'license_server_url' => 'https://licencas.seudominio.com/api/check',
  'license_hmac_key' => 'CHAVE_FORTE_FORA_DO_REPOSITORIO',
]
```

O HUB continua aceitando licença local HMAC, mas o servidor remoto permite bloquear contrato vencido, limitar módulos e auditar instalações.
