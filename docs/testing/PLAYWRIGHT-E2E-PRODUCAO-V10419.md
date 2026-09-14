# Playwright E2E — Produção controlada V104.19

## Testes obrigatórios

1. Login com 2FA em usuário de auditoria.
2. Acesso ao Dashboard executivo.
3. Diagnóstico Config Real.
4. Validar Banco.
5. Teste Segurança Assistido.
6. Checklist Final Comercial.
7. Análise Comercial/Técnica.
8. Backup gerar.
9. Fluxos ativos: salvar e testar regra.

## Boas práticas

- Usar ambiente de homologação.
- Nunca rodar teste de carga em produção real sem janela combinada.
- Nunca usar token Tiny/VSM real em arquivo de teste versionado.
