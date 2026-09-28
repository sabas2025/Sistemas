# Guia — Multi-cliente por subdomínio (instalações isoladas numa VPS)

**Data:** 2026-09-28 · **Base:** `main` @ `b2aa2e1` (V104.49.3-R7) · **Natureza:** guia operacional.
**Não altera código do Hub.**

> Modelo: **uma instalação isolada por cliente**, cada uma num subdomínio (ex.: `hub01.ctba.top`,
> `hub02.ctba.top`), todas na **mesma VPS**. Não é multiempresa numa instalação só (isso é o H-01,
> bloqueado) — é N instalações independentes. Como cada install é **empresa única**, que o Hub já
> suporta hoje, este modelo **não depende do H-01** e não precisa de segundo VPS nem de EH-2/EH-3.

---

## 1. Por que este modelo encaixa

- O Hub é desenhado para **empresa única**. Um install por cliente = cada install com uma empresa =
  caso totalmente suportado.
- O sinal "de qual cliente é este webhook?" é resolvido pelo **subdomínio/install que o recebeu** —
  `hub01.ctba.top` e `hub02.ctba.top` são instalações diferentes, com bancos e segredos diferentes.
  Isso dispensa a resolução de empresa na entrada (a metade aberta do H-01).
- Isolamento por **banco separado** é mais forte que o escopo em aplicação.

### A regra de ouro (não quebrar)
> **Cada subdomínio = seu próprio banco.** Se dois subdomínios apontarem para o **mesmo** banco,
> você recai no H-01 (dados de clientes misturados). Bancos separados = isolamento garantido.

---

## 2. Estrutura de diretórios (uma pasta por cliente)

O bootstrap lê `config/config.php` por caminho **fixo** (`public/index.php` faz
`require __DIR__.'/../config/config.php'`). Por isso o caminho **sem alterar código** é um
diretório de instalação por cliente, cada um com seu `config/` e `storage/`:

```
/var/www/
  hub01/                      # cliente 1 → hub01.ctba.top
    public/                   # docroot do vhost
    app/  database/  workers/  scripts/  views/  ...
    config/config.php         # próprio (banco hub01, segredos próprios)
    storage/                  # próprio (logs, backups, sessão, locks, cache)
  hub02/                      # cliente 2 → hub02.ctba.top
    public/
    ...
    config/config.php         # próprio (banco hub02, segredos próprios)
    storage/
```

Cada pasta é uma cópia do pacote do Hub. `config/` e `storage/` **nunca** são compartilhados entre
clientes. (Compartilhar o **código** por symlink é possível, mas mantenha `config/` e `storage/`
físicos e separados por cliente — e lembre que atualizar o código afeta todos de uma vez.)

---

## 3. Banco por cliente

Crie um banco e um usuário MySQL **por cliente** (usuário próprio limita o alcance de um vazamento):

```sql
CREATE DATABASE hub01 CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER 'hub01'@'127.0.0.1' IDENTIFIED BY '<senha-forte-hub01>';
GRANT ALL PRIVILEGES ON hub01.* TO 'hub01'@'127.0.0.1';

CREATE DATABASE hub02 CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER 'hub02'@'127.0.0.1' IDENTIFIED BY '<senha-forte-hub02>';
GRANT ALL PRIVILEGES ON hub02.* TO 'hub02'@'127.0.0.1';
FLUSH PRIVILEGES;
```

No `config/config.php` de cada install (o instalador preenche a maior parte; confira estes):

```php
'db' => [ 'host' => '127.0.0.1', 'name' => 'hub01', 'user' => 'hub01', 'pass' => '...' ],
// db_modules.* também apontam para o banco do cliente (o Hub usa um banco por instalação):
'db_modules' => [
  'core' => ['name' => 'hub01'], 'pedidos' => ['name' => 'hub01'], 'produtos' => ['name' => 'hub01'],
  'estoque' => ['name' => 'hub01'], 'fiscal' => ['name' => 'hub01'], 'fila' => ['name' => 'hub01'],
  'observabilidade' => ['name' => 'hub01'], 'backups' => ['name' => 'hub01'],
],
'base_url' => 'https://hub01.ctba.top',
'app_env' => 'production',
'security' => [
  'force_https' => true,
  'canonical_host' => 'hub01.ctba.top',   // P0-08: fixa o host e a Redirect URI do OAuth
  'trusted_proxies' => '<IP do proxy/CDN, se houver>',
  // encryption_key e demais segredos: PRÓPRIOS deste install (o install.php gera; nunca copie de outro cliente)
],
```

> **Segredos por cliente:** cada install tem `encryption_key`, `backup_signature_key`,
> `token_vault_hmac_key` etc. **próprios** — gerados pelo `install.php`/`rotate-secrets.php` naquele
> install. Nunca reaproveite as chaves de um cliente noutro (quebraria o isolamento de tokens e
> assinatura de backup). O `enforceProductionSafety` recusa segredo fraco/vazio em produção.

---

## 4. vhost por subdomínio

Um certificado curinga (`*.ctba.top`) cobre todos. Exemplo nginx (o repositório traz
`nginx.conf.example` como base) — um server block por cliente, apontando para o `public/` daquele
install e descartando `X-Forwarded-*` da internet:

```nginx
server {
  listen 443 ssl;
  server_name hub01.ctba.top;
  root /var/www/hub01/public;
  index index.php;

  ssl_certificate     /etc/letsencrypt/live/ctba.top/fullchain.pem;   # curinga *.ctba.top
  ssl_certificate_key /etc/letsencrypt/live/ctba.top/privkey.pem;

  location / { try_files $uri $uri/ /index.php?$query_string; }
  location ~ \.php$ {
    include fastcgi_params;
    fastcgi_pass unix:/run/php/php8.x-fpm.sock;
    fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name;
  }
  # nunca expor config/ e storage/
  location ~ ^/(config|storage|app|database|workers|scripts|tests)/ { deny all; }
}
# repita o bloco para hub02.ctba.top → /var/www/hub02/public, e assim por diante.
# redirecione :80 → :443 no virtual host (o Hub também força HTTPS via enforceProductionSafety).
```

O `canonical_host` de cada install tem de bater com o `server_name` do vhost dele.

---

## 5. Ordem de deploy e migrations (por install)

Para **cada** cliente, na pasta dele:

1. Copiar o pacote do Hub para `/var/www/hubNN/`.
2. Gerar o código de autorização e instalar:
   ```
   cd /var/www/hubNN
   php scripts/create-install-authorization.php        # gera o código no terminal
   # abrir https://hubNN.ctba.top/install.php, colar o código, ambiente=producao,
   # informar banco (hubNN), base URL e admin
   ```
3. Aplicar as migrations manuais no banco **daquele** cliente (elas não têm executor automático):
   ```
   for f in database/migrations/*.sql; do mysql -u hubNN -p hubNN < "$f"; done
   # a 20260914_012 (PK BIGINT) segue o RUNBOOK-MIGRATION-012 — em janela, se a base já tiver volume.
   ```
4. Agendar os workers **daquele** install no cron (caminhos apontando para `/var/www/hubNN/workers`):
   ```
   */10 * * * * php /var/www/hubNN/workers/worker_fila.php      >> /var/log/hubNN-fila.log 2>&1
   0,10,20,30,40,50 * * * * php /var/www/hubNN/workers/worker_retencao.php >> /var/log/hubNN-ret.log 2>&1
   0 */6 * * * php /var/www/hubNN/workers/worker_tiny_v3_refresh.php >> /var/log/hubNN-tinyv3.log 2>&1
   0 * * * * php /var/www/hubNN/workers/worker_vsm_token_refresh.php >> /var/log/hubNN-vsm.log 2>&1
   # + os demais workers conforme os fluxos ativos do cliente.
   ```
   > O lock do EH-1 (`SchedulerLockService`) é **escopado por instalação** (hash de
   > db+base_url+raiz), então hub01 e hub02 **não colidem** — cada um tem seu próprio lock.
5. Conectar Tiny (OAuth) e VSM **daquele** cliente e rodar a homologação (Fase A do
   `CHECKLIST-GO-LIVE-HOMOLOGACAO-PRODUCAO`).

**Atualização futura do Hub:** repita o deploy do código em cada pasta e rode as migrations novas em
cada banco. Vale um script `deploy.sh` que itere sobre `/var/www/hub*`.

---

## 6. Checagem de isolamento (prove antes de pôr o 2º cliente pra valer)

Com hub01 e hub02 instalados, confirme que **nada é compartilhado**:

- [ ] **Bancos distintos:** `config.php` de hub01 aponta para `hub01`, o de hub02 para `hub02`
      (`grep "'name'" /var/www/hub01/config/config.php` ≠ hub02).
- [ ] **Segredos distintos:** `encryption_key` (e as demais chaves) diferem entre os dois
      `config.php`. Nunca iguais.
- [ ] **Storage separado:** `/var/www/hub01/storage` e `/var/www/hub02/storage` são pastas físicas
      diferentes (logs, backups, sessão, locks, cache não se cruzam).
- [ ] **Sessão não vaza:** logar em `hub01.ctba.top` **não** autentica em `hub02.ctba.top` (cookies
      são por host; e os segredos de sessão diferem).
- [ ] **Dado não vaza:** criar um pedido de teste em hub01 e confirmar que ele **não** aparece em
      hub02 (bancos distintos garantem isso).
- [ ] **Webhook certo no cliente certo:** o webhook do Tiny/VSM de cada cliente aponta para o
      subdomínio dele; um evento de hub01 grava em `hub01`, nunca em `hub02`.
- [ ] **Host fixado:** cada install recusa acesso por Host diferente do seu `canonical_host`
      (P0-08).

Se qualquer item falhar, **pare** — provavelmente há banco/segredo/storage compartilhado.

---

## 7. Capacidade numa VPS só

A Fase 10 mediu o caminho quente em **9–13 ms** a 500 pedidos/min numa instância. Uma VPS aguenta
vários clientes de baixo/médio volume, mas **some as cargas**: N installs = N conjuntos de workers,
N bancos, N PHP-FPM pools. Dimensione CPU/RAM/IO pela soma e monitore. Quando a soma real (medida,
não estimada) saturar a VPS, aí sim entra a conversa de segundo VPS — e é outro assunto (escala
**vertical**: VPS maior; ou distribuir clientes entre VPS), não o EH-2/EH-3 de balanceador.

---

## 8. Quando NÃO usar este modelo

- Se você quiser **um login único** que enxerga vários clientes numa tela só → isso é multiempresa
  (H-01), que continua bloqueado nos pré-requisitos da `NOTA-DECISAO-H01-E-PERGUNTA-VSM`.
- Se os clientes precisarem **compartilhar** dados → não é este modelo.

Para "cada cliente é uma operação independente com seu Tiny e sua VSM", o modelo de subdomínio
isolado é o mais simples, o mais seguro e o que o Hub suporta **hoje**, sem código novo.

---

## 9. Resumo de uma linha

Uma pasta + um banco + segredos próprios + um vhost **por subdomínio**, todos numa VPS; o lock EH-1
já isola os crons por install; a regra inegociável é **banco separado por cliente**. Sem 2º VPS, sem
EH-2, sem H-01.
