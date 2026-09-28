# RELATÓRIO V76 — Correção do instalador para hospedagem MySQL

## Problema recebido
Erro na instalação:

> Usuário MySQL não tem permissão/acesso ao banco informado: ctbatop1_loja02.

## Causa provável
Em hospedagem compartilhada/cPanel/DirectAdmin, normalmente o PHP não pode executar `CREATE DATABASE`.
O banco precisa ser criado no painel da hospedagem e o usuário MySQL precisa ser vinculado ao banco com permissões completas.

## Melhorias aplicadas

### 1. Novo modo do banco no instalador
Adicionado campo:

- Hospedagem compartilhada: usar banco já criado
- Local/VPS: tentar criar banco automaticamente

### 2. Validação real de permissão
O instalador agora testa:

- conexão com o banco informado;
- permissão para criar tabela;
- permissão para remover tabela de teste.

### 3. Mensagem de erro mais clara
Agora o erro orienta:

- criar banco no painel;
- vincular usuário ao banco;
- marcar todas as permissões;
- conferir prefixo do banco;
- conferir host MySQL.

### 4. Banco único recomendado para hospedagem
Para hospedagem compartilhada, o padrão ficou:

- banco único;
- modular desativado;
- sem tentativa obrigatória de criar banco.

## Arquivo alterado

- `public/install.php`

## Validação

- PHP sem erro de sintaxe.
- ZIP testado.
