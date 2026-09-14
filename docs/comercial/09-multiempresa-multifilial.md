# Multiempresa e multifilial

## Objetivo
Separar operação por empresa, filial, CNPJ, credenciais, estoque e regras de integração.

## Recomendações
- Cada filial deve possuir identificação própria.
- Estoque deve respeitar origem oficial definida: VSM, Tiny ou outro ERP.
- Tokens e credenciais devem ser segregados por ambiente e empresa quando possível.
- Relatórios devem permitir filtrar por empresa/filial.

## Risco evitado
Mistura de pedidos, estoque ou NF-e entre empresas diferentes.
