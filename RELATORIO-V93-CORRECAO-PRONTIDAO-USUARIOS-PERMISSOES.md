# V93 - Correção Prontidão + Administração de Usuários e Permissões

## Correções aplicadas

### 1. Erro fatal em produção
Corrigido o erro:

```text
Call to undefined method ProductionReadinessV24Service::checks()
```

A classe `ProductionReadinessV24Service` agora possui o método `checks()` com compatibilidade para a tela antiga `relatorio_prontidao_producao.php`.

### 2. Usuários e permissões
A tela `Central Técnica > Administração > Usuários e Permissões` foi revisada e reforçada.

Implementado:
- botão **Editar** por usuário;
- botão **Alterar senha** por usuário;
- botão **Excluir** com confirmação `EXCLUIR`;
- exclusão lógica por inativação para preservar auditoria;
- proteção contra excluir o próprio usuário logado;
- proteção contra remover/inativar o último administrador ativo;
- validação de e-mail válido;
- bloqueio de e-mail duplicado;
- senha mínima de 8 caracteres;
- auditoria em criação, edição, alteração de senha e exclusão lógica;
- cartões de resumo: total, ativos, admins ativos e 2FA ativo.

## Análise técnica

A área de usuários já permitia cadastrar e editar via formulário, porém não tinha botões claros de ação por linha. Também não havia rota de exclusão segura. A V93 deixa a operação mais profissional e reduz risco operacional.

## Recomendação futura

- Criar reset seguro de 2FA por administrador;
- criar histórico visual de alterações por usuário;
- adicionar filtro por perfil/status;
- criar política de senha configurável;
- separar `UsuarioController` do `DashboardController` em versão futura.
