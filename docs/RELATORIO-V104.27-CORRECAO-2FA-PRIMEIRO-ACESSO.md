# RELATÓRIO V104.27 - Correção 2FA primeiro acesso

## Problema relatado
Mensagem em tela: `Código 2FA inválido. Confira o horário do celular e tente novamente.`

## Diagnóstico provável
O fluxo de primeiro cadastro 2FA exibia o QR Code a partir do segredo salvo em sessão, mas a conclusão validava prioritariamente o segredo persistido no banco. Em cenários de troca de chave de criptografia, reaproveitamento de banco antigo, sessão de primeiro acesso ou segredo gravado divergente, o código TOTP podia ser rejeitado mesmo após escanear o QR Code correto.

## Correções aplicadas

1. `app/Core/Auth.php`
   - Adicionado controle de tempo da etapa pendente 2FA: 15 minutos.
   - Na conclusão do 2FA, se for etapa de cadastro, valida também pelo segredo pendente da sessão.
   - Quando validado pela sessão, regrava o segredo criptografado atual no banco e marca como verificado.
   - Limpa `pending_2fa_started_at` ao finalizar login.

2. `app/Services/TwoFactorService.php`
   - `decryptSecret()` agora não derruba login se a chave antiga/inválida impedir descriptografia; retorna vazio e audita.
   - `verify()` agora usa tolerância configurável de TOTP, padrão 2 janelas de 30 segundos antes/depois.

3. `config/config.php`
   - Adicionado `security.two_factor_time_window_steps => 2`.

4. `app/Controllers/LoginController.php`
   - Mensagem de erro 2FA ficou mais clara para usuário final.

5. `database/manual_reset_2fa_primeiro_acesso_v104_27.sql`
   - Script manual de recuperação para resetar 2FA do administrador caso o banco já tenha ficado preso.

## Compatibilidade
- Não remove 2FA.
- Não altera Tiny.
- Não altera VSM.
- Não altera regras de pedido, estoque, fiscal ou XML.
- Não altera schema automaticamente.
- Mantém compatibilidade com banco antigo.

## Rollback
Restaurar os arquivos da V104.26:
- `app/Core/Auth.php`
- `app/Services/TwoFactorService.php`
- `app/Controllers/LoginController.php`
- `config/config.php`

Remover, se desejar:
- `database/manual_reset_2fa_primeiro_acesso_v104_27.sql`
