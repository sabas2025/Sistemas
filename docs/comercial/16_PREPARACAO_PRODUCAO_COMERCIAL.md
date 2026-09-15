# Preparação para Produção Comercial — V104.16

Esta versão organiza o HUB para venda, implantação profissional e operação controlada.

## Antes de vender

1. Rodar `Segurança > Teste Segurança Assistido`.
2. Rodar `Central Técnica > Validar Banco`.
3. Rodar `Central Técnica > Mapa do Banco`.
4. Gerar backup e testar restore em banco separado.
5. Cadastrar licença do cliente.
6. Definir se `commercial.license_mode` ficará em `monitor` ou `enforce`.
7. Definir se `commercial.tenant_scope_required` será obrigatório.
8. Homologar Tiny/VSM com dados controlados.
9. Documentar credenciais sem expor segredos.
10. Assinar contrato, SLA, política de backup e termos de responsabilidade.

## Modo de licença

- `off`: não valida licença.
- `monitor`: registra alerta quando licença está ausente/vencida, mas não bloqueia.
- `enforce`: bloqueia módulos operacionais se a licença estiver inválida.

## Multiempresa/multifilial

Para SaaS com mais de um cliente, habilite escopo obrigatório por empresa/filial e valide todas as consultas operacionais com `empresa_id` e `filial_id`.

## Conectores plugáveis

A V104.16 adiciona registro operacional para conectores. Tiny e VSM estão ativos; Bling, Omie e marketplaces ficam como planejados até homologação própria.
