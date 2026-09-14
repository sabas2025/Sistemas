# CI/CD de produção — V104.18

Pipeline mínimo recomendado antes de publicar versão comercial:

1. `bash scripts/ci/php-lint.sh`
2. `php scripts/ci/sql-inventory-check.php`
3. `cd tests/e2e && npm install && npx playwright test`
4. `bash tests/load/light-smoke.sh https://seu-dominio/public/index.php`
5. Gerar relatório em **Segurança > Teste Segurança Assistido**.
6. Gerar relatório em **Comercial > Checklist Final Comercial**.

Nunca rode carga agressiva em produção. Use homologação ou janela autorizada.
