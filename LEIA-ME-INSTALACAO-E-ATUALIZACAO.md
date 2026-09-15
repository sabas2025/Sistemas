# HUB Tiny/VSM V104.49.3-R5 — pacote completo

Release final de 22/08/2026. Este pacote contém o código completo, instalador modular, banco de dados, migrations históricas preservadas, PWA e correções acumuladas até a V104.49.3-R5.

## Instalação nova

1. Envie todo o conteúdo ao servidor.
2. Garanta PHP 8.1+ com PDO MySQL, cURL, JSON, OpenSSL, mbstring e ZipArchive.
3. Garanta permissão de escrita em `config/` e `storage/` durante a instalação.
4. No terminal da raiz do projeto, execute `php scripts/create-install-authorization.php` e mantenha o código temporário em sigilo.
5. Acesse `public/install.php` por HTTPS, envie o código pelo formulário e conclua a autorização vinculada ao navegador.
6. Informe um banco novo e crie o primeiro administrador. Se houver qualquer tabela do Hub, o preflight bloqueia antes do DDL modular e orienta o fluxo de atualização. O teste posterior usa tabela aleatória e só a remove após comprovar que a própria conexão a criou. A autorização expira em 15 minutos e é consumida na primeira tentativa.
7. Após concluir, confirme a existência de `storage/install.lock` e restrinja as permissões de `config/config.php`.
8. Entre em **Central Técnica > Banco**, execute **Simular Enterprise Core** e confirme que o contrato do schema está 100% válido.

O instalador cria `config/config.php`; o pacote distribui somente `config/config.example.php` para não expor credenciais.
Os SQLs consolidados contêm placeholders e não devem ser executados diretamente sem renderização completa; o instalador oficial faz essa substituição e bloqueia o sucesso antes do gate integral de todas as tabelas, colunas, tipos, nulabilidade, defaults, índices, engine, charset e collation declarada.
O rollback automático restaura somente `config/config.php`, manifesto FIM e `storage/install.lock`. DDL MySQL não é transacional e pode ficar parcial se o servidor falhar durante a criação; revise o banco antes de repetir.
O caminho oficial da instalação executa, na ordem, `database/modules/core.sql`, `pedidos.sql`, `produtos.sql`, `estoque.sql`, `fiscal.sql`, `fila.sql`, `observabilidade.sql` e `backups.sql`. A CI exige paridade de tabelas, colunas, tipos, valores padrão e índices entre esses módulos e `database/install_final_current.sql`.

## Atualização de instalação existente

1. Faça backup dos arquivos e do banco.
2. Preserve `config/config.php`, `storage/install.lock` e o manifesto FIM da instalação.
3. Substitua os arquivos pelo conteúdo deste pacote.
4. Entre em **Central Técnica > Banco**, execute primeiro **Simular Enterprise Core** e revise as pendências.
5. Execute **Aplicar Enterprise Core**. O resultado só é marcado como sucesso depois da verificação final de tabelas, colunas, tipos, nulabilidade, valores padrão e índices críticos.
6. Se a Central Técnica não puder ser usada, aplique as migrations `20260712_001`, `20260712_002`, `20260712_003`, `20260712_004` e `20260713_007`, nessa ordem e no banco do módulo correspondente.
7. Valide `Mapa do Banco`, `Self-Test`, Quality Gate, Tiny V3 e VSM.
8. Regere a baseline FIM apenas depois de confirmar a atualização.

Não use `database/repair_current.sql` de versões anteriores como reparo da R5. O arquivo atual contém DDL e seeds idempotentes de catálogo/permissões/migrations, embora preserve dados operacionais; o AutoRepair do painel ignora todo DML. As rotas antigas `atualizar-vXX` são bloqueadas pelo `MigrationController`; o updater V48/V49 morto foi removido para impedir caminhos paralelos e falsos registros de sucesso. Use a ação explícita **Central Técnica > Banco > Enterprise Core**.

## Quality Gate de schema

O status verde exige o contrato completo. A simples existência da tabela não é suficiente. O gate bloqueia a liberação quando houver:

- tabela ou coluna obrigatória ausente;
- tipo, nulabilidade ou valor padrão incompatível;
- índice crítico ausente, com colunas em ordem incorreta ou unicidade incorreta;
- falha ao consultar metadados ou conexão apontando para banco diferente do configurado.

O diagnóstico é somente leitura. Nenhuma página operacional executa DDL automaticamente.

## Validação e CI

A CI oficial executa PHP lint, regressão Enterprise, política de ausência de DDL operacional, contratos VSM/OpenAPI, inventário SQL e paridade de tabelas/colunas/índices. A regressão destrutiva roda em MySQL 8 e MariaDB 11.4: valida o consolidado em banco isolado e reproduz o instalador modular em oito bancos separados. Para repetir os testes estáticos em um ambiente com PHP 8.2:

```bash
bash scripts/ci/php-lint.sh
bash scripts/ci/enterprise-tests.sh
php scripts/ci/mysql-schema-static-check.php
php scripts/ci/mysql-module-parity-check.php
```

## Correções incluídas

- recuperação controlada de HTTP 500;
- detecção de schema compatível com hospedagem compartilhada/cPanel;
- migrations de tabelas Core, Comercial, VSM, Segurança e Self-Test;
- correção da consulta do Self-Test sem coluna `score` inexistente;
- proteção de configuração real durante atualização;
- layout profissional e responsivo das páginas Tiny V3 e Configurações;
- identidade visual unificada entre web, PWA e aplicativo instalado no Windows;
- manifesto e cache PWA sincronizados na V104.49.3-R5;
- Quality Gate por contrato de schema, sem falso sinal verde;
- updater legado VSM removido e rotas históricas bloqueadas, sem falso sucesso;
- paridade entre instalador modular e schema consolidado;
- logs VSM sem corpo de resposta e referências HTTPS de contrato reduzidas a host + hash;
- HTTPS atrás de proxy somente com IP configurado em `HUB_INSTALL_TRUSTED_PROXIES`;
- regressão de instalação e migrations repetidas em MySQL 8 e MariaDB.

## Segurança

Nunca envie ao suporte o arquivo `config/config.php`, tokens Tiny/VSM, chaves OAuth ou backups contendo dados reais.

Todo o hardening HTTP do pacote (bloqueio de `app/`, `config/`, `database/`, `storage/`, extensões sensíveis, bloqueio de `worker_*.php` via navegador e headers de segurança) está implementado em `public/.htaccess` e `.htaccess` da raiz, o que só tem efeito em **Apache**. Se a hospedagem usar **Nginx**, use `nginx.conf.example` (raiz do pacote) como ponto de partida - sem um equivalente configurado, essas proteções HTTP simplesmente não existem no servidor. Prefira apontar o document root para `public/`, conforme explicado no próprio arquivo de exemplo.

Se `security.admin_ip_allowlist` estiver configurado em `config/config.php`, o painel administrativo (incluindo a tela de login) passa a responder 403 para qualquer IP fora da lista; rotas públicas do site comercial e os webhooks Tiny/VSM (que têm sua própria validação por HMAC) não são afetados.
