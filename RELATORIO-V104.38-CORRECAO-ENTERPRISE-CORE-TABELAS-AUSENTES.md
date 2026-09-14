# V104.38 — Correção Enterprise Core

- O aplicador agora cria/repara `usuarios`, `configuracoes_integracao`, `fila_integracao` e `fila_morta` antes das tabelas enterprise.
- `schema_migrations` foi tornado compatível com formatos antigos e novos.
- O enum da fila recebe `ignorado` de forma idempotente e passa a registrar erro real se falhar.
- Os testes mostram o banco efetivamente consultado, evitando falso diagnóstico de tabela ausente.
- A tela de regressão ganhou botão de reparação segura.
- O teste do PWA foi atualizado para V104.37 ou superior.

Nenhuma tabela é apagada e os fluxos Tiny/VSM não são alterados.
