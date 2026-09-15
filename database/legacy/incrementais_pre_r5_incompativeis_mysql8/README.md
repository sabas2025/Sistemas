# Incrementais históricos anteriores à R5

Arquivos preservados somente para auditoria. Eles usam formas como
`ADD COLUMN IF NOT EXISTS` e `CREATE INDEX IF NOT EXISTS`, que não pertencem ao
caminho oficial compatível com MySQL 8 desta release.

Não execute estes SQLs. Para uma base existente, use **Central Técnica > Banco >
Enterprise Core** ou as migrations oficiais `20260712_001` a `20260712_004` e
`20260713_007`. Para instalação nova, use `public/install.php`.
