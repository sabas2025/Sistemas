# V94 — Segurança Extrema e Proteção contra Indexação/Ataques

## Aplicado
- Bloqueio de motores de busca com `robots.txt`, meta robots e header `X-Robots-Tag`.
- `.htaccess` na raiz bloqueando acesso a `app`, `config`, `database`, `storage`, logs, backups e arquivos sensíveis.
- `.htaccess` no `public` com `Options -Indexes`, headers de segurança e bloqueio de arquivos sensíveis.
- Headers HTTP reforçados: CSP, HSTS, X-Frame-Options, nosniff, Referrer-Policy, Permissions-Policy, COOP e CORP.
- Sessões endurecidas: cookie HttpOnly, SameSite=Lax, Secure em HTTPS, strict mode, timeout e expiração absoluta.
- Tokens e segredos com criptografia AES-256-GCM já mantida e mascaramento adicional em logs/auditoria.
- Banco com PDO `ATTR_EMULATE_PREPARES=false`.
- Instalador bloqueado por `install.lock`.
- Nova tela `Segurança Extrema` com score e checklist.
- Criado `install_final_v94.sql`.

## Observação
Nenhum sistema é invulnerável. A V94 reduz superfície de ataque e melhora defesa, mas produção ainda exige HTTPS, senhas fortes, 2FA, usuário MySQL dedicado, backups testados e monitoramento.
