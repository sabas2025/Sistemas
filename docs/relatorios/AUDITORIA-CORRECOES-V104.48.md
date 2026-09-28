# Auditoria de correções — HUB Tiny V104.48

Data: 2026-07-12

## Escopo aplicado

1. Concorrência do OAuth Tiny V3
   - Adicionado lock MySQL por ambiente com `GET_LOCK` e liberação em `finally` com `RELEASE_LOCK`.
   - Evita dois workers renovarem o mesmo refresh token simultaneamente.
   - Mantido o fluxo OAuth, Token Vault, criptografia e compatibilidade com tabelas antigas.

2. Lease e heartbeat da fila
   - Novo parâmetro `security.queue_lease_minutes`, padrão 5, limitado entre 1 e 120 minutos.
   - Reserva inicial e heartbeat usam o mesmo valor configurável.
   - Falhas de heartbeat continuam não interrompendo o trabalho, mas agora são registradas no logger.

3. Reprocessamento administrativo
   - Um reprocessamento manual inicia novo ciclo com `tentativas=0`.
   - Limpa o código de erro operacional anterior.
   - Preserva na auditoria o estado, número de tentativas e código de erro anteriores.

4. Eventos auxiliares da fila
   - Falhas ao registrar eventos de claim/finalização deixaram de ser silenciosas.
   - Registro best-effort no `JsonLogger`, sem interromper o processamento principal.

5. PWA e erros HTTP 500
   - O service worker não substitui mais uma resposta HTTP 500 real pela tela offline.
   - A tela offline é usada apenas quando há falha de rede/fetch.
   - Preserva o erro do servidor e o Trace ID apresentado pela aplicação.

6. Regressão de versão do PWA
   - O teste enterprise deixou de aceitar apenas 104.37/104.38.
   - Agora extrai `HUB_VERSION` e valida semanticamente versão >= 104.37.0.

7. Teste de correções
   - Criado `tests/enterprise/v104_48_corrections_test.php`.
   - Valida lease, heartbeat, reprocessamento, OAuth lock, comportamento offline e bloqueio web dos workers.

## Arquivos alterados

- `app/Services/EnterpriseRegressionTestService.php`
- `app/Services/QueueService.php`
- `app/Services/TinyV3TokenService.php`
- `config/config.php`
- `public/sw.js`
- `tests/enterprise/v104_48_corrections_test.php` (novo)

## Validações executadas

- Lint PHP em todos os arquivos PHP: aprovado.
- Sintaxe JavaScript do `public/sw.js` com Node: aprovada.
- Teste específico V104.48: todos os checks aprovados.
- Verificação de workers públicos: todos bloqueiam execução web e delegam para CLI.
- Teste enterprise de versão PWA: aprovado com versão 104.48.0.
- Comparação contra o ZIP original: somente os seis arquivos listados foram alterados/adicionados.

## Limitação da validação

A suíte de integração com banco não pôde executar porque o ambiente de auditoria não possui o driver `pdo_mysql`. Os erros `could not find driver` não são falhas comprovadas do projeto. Antes da produção, executar a regressão em PHP 8.x com PDO MySQL e MySQL 8 usando banco de homologação.

## Compatibilidade

- Nenhuma rota foi removida.
- Nenhum endpoint Tiny ou VSM foi alterado.
- Nenhuma tabela ou coluna foi removida.
- O padrão do lease permanece 5 minutos.
- Workers públicos continuam com os mesmos nomes, mas permanecem bloqueados para navegador.
- O refresh OAuth mantém o retorno existente e adiciona somente códigos de lock concorrente.
