# Manual de instalação

## Requisitos
- PHP 8.1 ou superior recomendado.
- MySQL/MariaDB com InnoDB e utf8mb4.
- HTTPS ativo.
- Extensões PHP: PDO MySQL, cURL, JSON, OpenSSL, ZIP.

## Instalação em hospedagem compartilhada
1. Criar banco MySQL único no painel da hospedagem.
2. Enviar arquivos do HUB para o servidor.
3. Garantir que `config` e `storage` estejam protegidos por `.htaccess`.
4. Acessar `public/install.php`.
5. Selecionar modo banco único.
6. Criar administrador com senha forte: maiúscula, minúscula, número e mínimo recomendado de 10 caracteres.
7. Após instalar, remover/bloquear `install.php`.
8. Entrar no painel e rodar Validar Banco, Mapa do Banco e Health de Módulos.

## Pós-instalação obrigatório
- Ativar 2FA do administrador.
- Configurar backup.
- Configurar Tiny/VSM em homologação.
- Rodar Teste de Segurança Assistido.
- Configurar cron dos workers via CLI.
