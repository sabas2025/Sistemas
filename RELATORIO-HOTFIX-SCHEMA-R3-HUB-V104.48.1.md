# Hotfix R3 — detecção de schema em hospedagem compartilhada

## Sintoma confirmado

O Enterprise Core executava `CREATE TABLE`, mas em seguida informava “Tabela não foi localizada após criação” para praticamente todas as tabelas. As páginas VSM continuavam bloqueadas.

## Causa técnica

A detecção dependia de `INFORMATION_SCHEMA` e de `SHOW TABLES LIKE ?`. Alguns provedores cPanel limitam `INFORMATION_SCHEMA` ou não aceitam marcador preparado nesse comando. O resultado era falso negativo: a tabela podia existir, mas o HUB a tratava como ausente.

Também não havia diagnóstico visível do banco realmente selecionado pela conexão PDO, do banco configurado e do usuário MySQL usado no reparo.

## Correções

- Consulta direta `SELECT 1 FROM tabela LIMIT 0` como verificação principal.
- `INFORMATION_SCHEMA` usa explicitamente o nome retornado por `SELECT DATABASE()`.
- `SHOW TABLES` e `SHOW COLUMNS` deixam de depender de placeholder preparado.
- Criação é confirmada na mesma conexão PDO usada pelo `CREATE TABLE`.
- Enterprise Core mostra banco selecionado, banco configurado, usuário MySQL e modo de armazenamento.
- O reparo aborta quando o banco selecionado diverge do configurado.
- O reparo para no primeiro erro estrutural, evitando dezenas de mensagens repetidas.
- Cache completo de schema é invalidado após criação.
- Adicionado SQL somente leitura para diagnóstico no phpMyAdmin.

## Interpretação após instalar

- Banco selecionado diferente do configurado: corrigir `config/config.php`.
- Bancos iguais e erro `CREATE command denied`/`1142`: conceder CREATE, ALTER, INDEX e privilégios operacionais ao usuário no cPanel.
- Bancos iguais e tabelas existentes: a nova detecção deve reconhecê-las e liberar VSM/Enterprise Core.

## Compatibilidade

Nenhuma regra Tiny/VSM foi alterada. Nenhum endpoint foi removido. Nenhuma tabela foi apagada. O pacote não inclui `config/config.php` nem manifesto FIM da instalação.
