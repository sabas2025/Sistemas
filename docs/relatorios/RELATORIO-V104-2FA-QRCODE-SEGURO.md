# RELATÓRIO V104 — 2FA com QR Code local seguro

## Objetivo

Implementar o cadastro do 2FA/TOTP com QR Code na tela de login, permitindo que o administrador ou usuário escaneie pelo Google Authenticator, Microsoft Authenticator, Authy ou aplicativo compatível.

## Alterações aplicadas

### 1. QR Code local, sem serviço externo

Criado o serviço:

- `app/Services/QrCodeService.php`

O QR Code é gerado localmente em SVG, sem chamar Google Chart, QRServer, CDN ou API externa. Isso evita vazamento da chave TOTP/segredo 2FA para terceiros.

### 2. Tela de login com QR Code

Arquivo alterado:

- `views/login.php`

Quando o 2FA ainda precisa ser cadastrado, a tela passa a exibir:

- alerta explicando que o 2FA é obrigatório;
- QR Code para escanear no Google Authenticator;
- chave manual TOTP como alternativa;
- URI técnica OTP recolhida em bloco expansível;
- campo para digitar o código 2FA de 6 dígitos.

### 3. Auth com QR Code no fluxo pendente

Arquivo alterado:

- `app/Core/Auth.php`

`Auth::pendingTwoFactorInfo()` agora retorna também:

- `qr_data_uri`

Esse campo contém o SVG em `data:image/svg+xml;base64`, compatível com a CSP existente porque o sistema já permite `img-src 'self' data:`.

### 4. Correção para usuário criado com 2FA ativo

Antes, se o administrador criasse um usuário já com 2FA ativo, o sistema poderia pedir código 2FA sem o usuário ter visto a chave/QR Code.

Agora o sistema considera que o setup é obrigatório quando:

- `two_factor_enabled` está vazio; ou
- não existe segredo; ou
- `two_factor_last_verified_at` está vazio.

Assim, no primeiro login, o usuário vê o QR Code, cadastra no aplicativo e só depois o 2FA fica validado.

### 5. Melhor UX para código 2FA inválido

Arquivo alterado:

- `app/Controllers/LoginController.php`

Se o usuário errar o código 2FA, o sistema mantém a etapa 2FA na tela em vez de voltar para e-mail/senha.

Mensagem exibida:

- `Código 2FA inválido. Confira o horário do celular e tente novamente.`

### 6. CSS para QR Code

Arquivo alterado:

- `public/assets/login.css`

Foram adicionadas classes:

- `.totp-qr-box`
- `.totp-qr-img`
- `.totp-uri-code`

### 7. Aviso na tela de usuários

Arquivo alterado:

- `views/usuarios.php`

Foi adicionado aviso informando que, quando o 2FA for ativado para um usuário, o QR Code aparece no próximo login e o segredo não é enviado para API externa.

## Fluxo final

1. Administrador cria usuário ou habilita 2FA.
2. Usuário faz login com e-mail e senha.
3. Sistema detecta 2FA sem verificação anterior.
4. Tela mostra QR Code local.
5. Usuário escaneia com Google Authenticator.
6. Usuário informa código de 6 dígitos.
7. Sistema valida TOTP.
8. Sistema grava `two_factor_last_verified_at=NOW()`.
9. Login é concluído.

## Validações executadas

- 288 arquivos PHP validados com `php -l`.
- Nenhum erro de sintaxe encontrado.
- QR Code SVG gerado localmente e testado com decodificador QR.
- O QR decodificado retornou corretamente a URI `otpauth://totp/...`.
- Nenhuma chamada externa de QR Code foi adicionada.

## Observação de segurança

O segredo TOTP aparece somente durante o fluxo de cadastro/primeiro vínculo. Após validado, o usuário passa a informar apenas o código de 6 dígitos gerado pelo aplicativo autenticador.
