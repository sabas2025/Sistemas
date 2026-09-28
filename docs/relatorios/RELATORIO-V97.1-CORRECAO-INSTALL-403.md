# RELATÓRIO V97.1 — Correção de acesso ao public/install.php

Data: 2026-06-23

## Problema
Ao acessar `public/install.php`, o servidor retornava:

> You don't have permission to access this resource.

## Causa técnica provável
A regra adicionada no `.htaccess` da pasta `public/` usava `<If>` expression do Apache para bloquear o instalador quando houvesse lock. Em algumas hospedagens compartilhadas, essa sintaxe pode não ser suportada corretamente ou pode ser interpretada de forma restritiva, gerando 403 antes mesmo do PHP executar.

## Correção aplicada
A regra Apache foi alterada para permitir acesso ao arquivo `install.php` antes da instalação:

- Apache 2.4: `Require all granted`
- Apache 2.2: `Order allow,deny` + `Allow from all`

O bloqueio de segurança pós-instalação continua sendo feito dentro do próprio `public/install.php`, que verifica:

- `public/install.lock`
- `storage/install.lock`

## Segurança mantida
Após instalar, o sistema continua bloqueando o instalador por PHP quando `storage/install.lock` existir.

## Arquivo alterado
- `public/.htaccess`

## Recomendação de produção
Depois de instalar com sucesso:

1. Confirme que `storage/install.lock` foi criado.
2. Acesse novamente `public/install.php` e confirme que aparece bloqueado.
3. Se possível, remova fisicamente `public/install.php` do servidor em produção.
