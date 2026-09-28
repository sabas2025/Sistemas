# V82 - Evolução técnica e limpeza

## Aplicado
- Criados controllers separados: `CentralHomologacaoController`, `TinyHomologacaoController`, `BackupController`, `DatabaseMaintenanceController` e `XmlNfeController`.
- `public/index.php` agora roteia essas áreas antes do `DashboardController`, reduzindo concentração operacional.
- Rotas legadas `atualizar-v*` não essenciais foram bloqueadas pela V82 e orientam uso de validação/schema consolidado.
- Removidos instaladores antigos `install_v54_*` e `install_final_v72.sql`.
- Criado `database/install_final_v82.sql` sem dependência de `SOURCE` legado.
- Fortalecido `XmlNfeHomologationService` com resumo operacional, validação de chave NF-e, localização flexível de pedido, comparação pedido x nota e evidência por Trace ID.

## Mantido por compatibilidade
- URLs antigas `fiscal*` continuam funcionando, mas agora são tratadas semanticamente como XML/NF-e via `XmlNfeController`.
- Métodos antigos no `DashboardController` foram deixados como legado não roteado diretamente para reduzir risco de regressão.

## Próximas melhorias sugeridas
1. Remover fisicamente métodos legados do `DashboardController` após uma rodada de testes em produção.
2. Renomear arquivos `fiscal_*` para `xml_nfe_*` com redirects compatíveis.
3. Criar relatório PDF de evidência por Trace ID.
4. Criar monitor de divergência de estoque Tiny x VSM.
5. Criar homologação automática diária com alerta por e-mail/WhatsApp.
