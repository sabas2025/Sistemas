# RELATÓRIO V75 - Tela Profissional de Backups

## Objetivo
Melhorar a área de backups para operação profissional no Hub de Integração, com geração, importação, restauração controlada, histórico, segurança e rastreabilidade.

## Arquivos alterados

- `views/backups.php`
- `views/layout_top.php`
- `app/Services/BackupService.php`
- `app/Controllers/DashboardController.php`

## Melhorias aplicadas

### 1. Dashboard de backups
A tela agora exibe cards com:

- total de backups registrados;
- último backup;
- volume armazenado;
- percentual de integridade operacional.

### 2. Gerar backup
Foi mantida a geração de backup ZIP, agora com bloco visual mais claro e orientação de uso.

### 3. Importar backup
Criada opção para importar arquivos:

- `.zip`
- `.sql`

A importação salva o arquivo em `storage/backups` e registra o histórico em `backups_banco`.

### 4. Restaurar backup
Criada restauração controlada com segurança:

- exige permissão `backup/gerar`;
- exige CSRF;
- exige confirmação manual digitando `RESTAURAR`;
- gera um backup automático antes de restaurar;
- lê `.sql` diretamente;
- lê `.zip` e extrai o primeiro `.sql` encontrado;
- executa SQL com transação;
- registra auditoria com Trace ID.

### 5. Histórico mais profissional
A tabela agora mostra:

- ID;
- arquivo;
- tipo ZIP/SQL;
- tamanho formatado;
- status com badge;
- mensagem;
- Trace ID;
- data formatada;
- ações de baixar, excluir e restaurar.

### 6. Avisos de risco
A tela agora deixa claro que:

- importar não restaura automaticamente;
- restauração altera o banco;
- deve-se validar os módulos depois da restauração;
- restauração deve ser preferencialmente testada em homologação.

### 7. Menu lateral
Adicionado item `Backups` no menu de Gestão.

## Rotas novas

- `index.php?page=backup-importar`
- `index.php?page=backup-restaurar`

## Métodos novos

### `BackupService::importarUpload()`
Importa backup `.zip` ou `.sql` para `storage/backups`.

### `BackupService::restaurarPorId()`
Restaura backup selecionado com confirmação manual.

### `BackupService::extrairSqlDoBackup()`
Lê SQL direto ou dentro de ZIP.

### `BackupService::executarSqlSeguro()`
Divide e executa instruções SQL com transação.

## Observação importante
A restauração por painel é um recurso sensível. Para produção real, recomenda-se adicionar também:

- bloqueio de restauração em produção sem senha de administrador;
- modo dry-run para validar SQL sem executar;
- checksum SHA-256 do arquivo;
- validação de versão do schema antes da restauração;
- log detalhado de tabelas restauradas;
- backup externo em nuvem.

## Validação
Todos os arquivos PHP foram validados com `php -l` sem erro de sintaxe.
