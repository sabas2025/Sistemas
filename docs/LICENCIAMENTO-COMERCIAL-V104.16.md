# Licenciamento Comercial — V104.16

A licença comercial é controlada pela tabela `comercial_clientes_licencas`.

Campos principais:

- cliente
- plano
- status
- ambiente
- limites de empresas, filiais e conectores
- data de expiração
- hash da chave de licença
- assinatura HMAC

O bloqueio por licença é configurado em `config/config.php`:

```php
'commercial' => [
  'license_mode' => 'monitor', // off|monitor|enforce
  'allow_unlicensed_internal_use' => true,
  'tenant_scope_required' => false,
]
```

Para cliente pagante, use `license_mode = enforce` após validar que há licença ativa.
