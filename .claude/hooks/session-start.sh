#!/bin/bash
# SessionStart — Hub de Integração Tiny ERP ↔ VSM
#
# Este repositório NÃO tem manifesto de dependência (sem package.json, composer.json,
# requirements.txt). O Hub é PHP 8.x vanilla, deliberadamente SEM Composer: o autoload é um
# classmap próprio, gerado por scripts/build-classmap.php. Não há, portanto, nada para instalar —
# um hook que rodasse "npm install" ou "composer install" aqui seria encenação.
#
# O que este hook faz é o que de fato falta quando uma sessão começa: conferir que o container tem
# a ferramenta necessária para rodar os 12 portões de CI do Hub e as extensões PHP que o próprio
# instalador do Hub exige (public/install.php, seção de requisitos). Sem isso, os portões falham
# mais tarde com mensagens que não apontam a causa.
#
# Não bloqueia a sessão: relata e sai 0. Uma sessão pode estar trabalhando em outra coisa.
set -uo pipefail

if [ "${CLAUDE_CODE_REMOTE:-}" != "true" ]; then
  exit 0
fi

falta_critico=0
falta_opcional=0

echo "── Hub: verificação de ambiente ───────────────────────────────"

# ---------------------------------------------------------------- ferramentas dos portões
if command -v php >/dev/null 2>&1; then
  echo "  php ........... $(php -r 'echo PHP_VERSION;')"
  if ! php -r 'exit(PHP_VERSION_ID >= 80000 ? 0 : 1);'; then
    echo "  ‼️  o Hub exige PHP 8.x; esta versão é anterior"
    falta_critico=$((falta_critico + 1))
  fi
else
  echo "  ‼️  php AUSENTE — 11 dos 12 portões de CI não rodam"
  falta_critico=$((falta_critico + 1))
fi

if command -v node >/dev/null 2>&1; then
  echo "  node .......... $(node -v)"
else
  echo "  ‼️  node AUSENTE — o portão build-consolidated-schema.mjs não roda"
  falta_critico=$((falta_critico + 1))
fi

# ------------------------------------- extensões PHP exigidas pelo instalador do próprio Hub
if command -v php >/dev/null 2>&1; then
  ausentes=""
  for ext in pdo pdo_mysql curl json mbstring openssl zip simplexml; do
    php -r "exit(extension_loaded('$ext') ? 0 : 1);" || ausentes="$ausentes $ext"
  done
  if [ -n "$ausentes" ]; then
    echo "  ‼️  extensões PHP ausentes:$ausentes"
    echo "      (a lista vem de public/install.php, seção de requisitos)"
    falta_opcional=$((falta_opcional + 1))
  else
    echo "  extensões ..... pdo, pdo_mysql, curl, json, mbstring, openssl, zip, simplexml"
  fi
fi

# ---------------------------------------------------------------- estado do repositório
if [ ! -f "${CLAUDE_PROJECT_DIR:-.}/CLAUDE.md" ]; then
  echo "  ‼️  CLAUDE.md ausente — as regras de trabalho do Hub não serão carregadas"
  falta_opcional=$((falta_opcional + 1))
fi

# O pacote do Hub chega como zip e NÃO vive neste repositório. Dizer isso é útil: evita que a
# sessão procure app/, workers/ ou scripts/ci/ aqui dentro e conclua que sumiram.
if [ ! -d "${CLAUDE_PROJECT_DIR:-.}/scripts/ci" ]; then
  echo "  nota .......... o código do Hub não está neste repositório; chega como zip por sessão"
fi

if [ "$falta_critico" -gt 0 ]; then
  echo "  RESULTADO: $falta_critico item(ns) crítico(s) ausente(s) — os portões vão falhar"
elif [ "$falta_opcional" -gt 0 ]; then
  echo "  RESULTADO: ambiente utilizável, com ressalvas acima"
else
  echo "  RESULTADO: ambiente pronto para rodar os 12 portões do Hub"
fi
echo "───────────────────────────────────────────────────────────────"

exit 0
