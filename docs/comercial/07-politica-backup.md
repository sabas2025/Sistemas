# Política de backup

## Frequência recomendada
- Backup diário automático do banco.
- Backup antes de atualização de versão.
- Backup antes de restore, importação ou migração.

## Segurança
- Backup assinado com HMAC.
- SHA-256 para integridade.
- Restore apenas de backup validado.
- Backups não devem ficar acessíveis publicamente.

## Retenção sugerida
- Diários: 7 a 15 dias.
- Semanais: 4 a 8 semanas.
- Mensais: 6 a 12 meses, conforme contrato.

## Teste de restore
Todo cliente deve ter restore testado em banco de homologação antes de produção crítica.
