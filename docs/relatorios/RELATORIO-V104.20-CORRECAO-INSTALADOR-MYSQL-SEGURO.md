# RELATÓRIO V104.20 — Correção Instalador MySQL Seguro

## Problema real informado

O servidor registrou:

```text
Produção bloqueada: não use root sem senha no MySQL.
public/install.php:117
```

Esse comportamento é correto do ponto de vista de segurança: em produção não deve ser usado `root` sem senha.

## Melhorias aplicadas

- O instalador agora trata erro de validação do usuário como mensagem amigável, sem esconder tudo atrás de Trace técnico.
- Mensagem de bloqueio foi ampliada com orientação para cPanel/DirectAdmin.
- Adicionada confirmação explícita para permitir `root` sem senha somente em ambiente local/XAMPP.
- Em produção, `root` sem senha continua bloqueado.
- Em servidor público, mesmo com confirmação local marcada, o bloqueio permanece se `app_env=production`.
- Tela do instalador agora exibe aviso claro de Produção Segura.
- Rodapé da tela orienta usar banco único, banco já criado e usuário MySQL exclusivo.
- Versão centralizada atualizada para V104.20.

## Como instalar corretamente em hospedagem compartilhada

1. Criar banco no cPanel/DirectAdmin.
2. Criar usuário MySQL com senha forte.
3. Vincular o usuário ao banco com todas as permissões.
4. No instalador, escolher:
   - Modo do banco: Hospedagem compartilhada / usar banco já criado
   - Banco modular: desativado
   - App env: production
   - Ambiente: homologação ou produção conforme a etapa
5. Informar nome completo do banco e usuário, com prefixo da hospedagem.

Exemplo:

```text
Banco: ctbatop1_hub
Usuário: ctbatop1_hubuser
Senha: senha forte do usuário MySQL
Host: localhost
```

## Validação

- `public/install.php`: sem erro de sintaxe.
- `SystemVersionService.php`: sem erro de sintaxe.
- SQL current atualizado com migração V104.20.
