# Playwright E2E — HUB de Integração

Testes seguros para homologação final sem ataque agressivo.

## Instalar

```bash
cd tests/e2e
npm install
npx playwright install chromium
```

## Rodar teste público

```bash
HUB_BASE_URL="https://hub.ctba.top/public" npm test
```

## Rodar smoke autenticado

Use usuário temporário de auditoria, nunca senha definitiva de produção.

```bash
HUB_BASE_URL="https://hub.ctba.top/public" HUB_USER="auditor@hub.local" HUB_PASS="SenhaForteTemporaria" npm test
```

## Carga leve

```bash
HUB_BASE_URL="https://hub.ctba.top/public" REQUESTS=30 CONCURRENCY=5 ../load/light-smoke.sh
```
