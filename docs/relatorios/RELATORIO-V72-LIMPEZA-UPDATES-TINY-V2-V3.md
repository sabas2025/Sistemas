# RELATÓRIO V72 — Limpeza de updates e separação Tiny V2/V3

## Alterações aplicadas

1. Removidos da pasta `database/` os arquivos históricos:
   - `update_vXX.sql`
   - `install_multidb_vXX.sql`

2. Criado instalador consolidado:
   - `database/install_final_v72.sql`

3. Criado manifesto da limpeza:
   - `database/REMOVIDOS_UPDATES_ANTIGOS.md`

4. Atualizado `UniversalUpgradeService`:
   - não executa mais updates legados automaticamente;
   - orienta usar `install_final_v72.sql` para instalação nova;
   - orienta usar Validação de Banco/Health de Módulos para bases antigas.

5. Criada tela nova:
   - `index.php?page=tiny-ambientes`
   - arquivo: `views/tiny_ambientes.php`
   - serviço: `app/Services/TinyEnvironmentReadinessService.php`

6. Central Técnica atualizada:
   - novo atalho “Tiny V2 / V3”.

7. Configurações atualizadas:
   - botão para abrir separação Tiny V2/V3;
   - remoção visual dos botões antigos de update V16;
   - aviso para usar o schema final V72.

8. Homologação atualizada:
   - separação visual entre Homologação Tiny V2 e Homologação Tiny V3;
   - removido botão de atualização antiga V16 da tela.

## Nova regra operacional recomendada

- 🟢 Tiny V2 = Produção ou fallback controlado.
- 🟡 Tiny V3 = Homologação OAuth separada.
- Tiny V3 só deve ser marcado operacional após:
  - Client ID configurado;
  - Client Secret configurado;
  - Redirect URI validada;
  - token por ambiente;
  - testes de produto;
  - testes de estoque;
  - testes de pedido;
  - testes fiscais/NF-e;
  - evidência por Trace ID.

## Validação técnica

- Todos os arquivos PHP foram verificados com `php -l`.
- Nenhum erro de sintaxe encontrado.

## Observação importante

Algumas rotas antigas de atualização ainda existem para compatibilidade, mas não devem mais ser usadas como fluxo principal. O fluxo principal agora é:

1. Instalação nova: `database/install_final_v72.sql`.
2. Base existente: tela `Validar Banco`, `Health de Módulos` e serviços `ensureSchema`.
3. Homologação: tela `Tiny V2/V3` + `Checklist de Homologação`.
