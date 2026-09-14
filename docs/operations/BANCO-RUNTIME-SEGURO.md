# Usuários MySQL separados

Use uma credencial temporária de instalação/migration e uma credencial permanente de runtime.

```sql
CREATE USER 'hub_runtime'@'localhost' IDENTIFIED BY 'SUBSTITUA_POR_SENHA_FORTE';
GRANT SELECT, INSERT, UPDATE, DELETE ON seu_banco.* TO 'hub_runtime'@'localhost';
FLUSH PRIVILEGES;
```

A credencial runtime não deve possuir `CREATE`, `ALTER`, `DROP`, `INDEX`, `FILE` ou `GRANT OPTION`. Execute migrations com uma credencial administrativa controlada e depois remova-a da configuração do aplicativo.
