#!/bin/bash
# SessionStart — Hub de Integração Tiny ERP ↔ VSM
#
# O Hub traz package.json (clean-css, terser, @playwright/test, @lhci/cli), usados apenas pelos
# scripts build:pwa, test:pwa e lighthouse:pwa.
#
# DECISÃO, com medição: este hook NÃO roda "npm install" quando node_modules está ausente.
# Medi neste container: a instalação passa de 7 minutos, por causa do Playwright e do Lighthouse
# CI. Como o hook é síncrono, isso atrasaria TODA sessão fria em 7+ minutos para instalar
# dependências que a maioria das sessões nunca usa — os 12 portões estáticos precisam só de php e
# node, nenhum pacote npm. Quando node_modules já existe (container reaproveitado), o hook apenas
# confirma; quando não existe, ele diz o comando e sai. Trocar para modo assíncrono resolveria
# isso de outro jeito, e é uma escolha de quem mantém o repositório.
#
# O Hub também não tem "composer install" a fazer: é PHP 8.x vanilla deliberadamente SEM Composer,
# com autoload por classmap gerado por scripts/build-classmap.php.
#
# Relata e sai 0 em vez de bloquear a sessão: ela pode estar trabalhando em outra coisa.
set -uo pipefail

if [ "${CLAUDE_CODE_REMOTE:-}" != "true" ]; then
  exit 0
fi

raiz="${CLAUDE_PROJECT_DIR:-$(cd "$(dirname "${BASH_SOURCE[0]}")/../.." && pwd)}"
cd "$raiz" || exit 0

criticos=0
ressalvas=0

echo "── Hub: preparação do ambiente ────────────────────────────────"

# ---------------------------------------------------------------- PHP (11 dos 12 portões)
if command -v php >/dev/null 2>&1; then
  echo "  php ........... $(php -r 'echo PHP_VERSION;')"
  php -r 'exit(PHP_VERSION_ID >= 80000 ? 0 : 1);' || {
    echo "  ‼️  o Hub exige PHP 8.x; esta versão é anterior"
    criticos=$((criticos + 1))
  }
  # Extensões exigidas pelo próprio instalador do Hub (public/install.php, requisitos).
  ausentes=""
  for ext in pdo pdo_mysql curl json mbstring openssl zip simplexml; do
    php -r "exit(extension_loaded('$ext') ? 0 : 1);" || ausentes="$ausentes $ext"
  done
  if [ -n "$ausentes" ]; then
    echo "  ‼️  extensões PHP ausentes:$ausentes"
    ressalvas=$((ressalvas + 1))
  else
    echo "  extensões ..... as 8 exigidas por public/install.php estão presentes"
  fi
else
  echo "  ‼️  php AUSENTE — 11 dos 12 portões de CI não rodam"
  criticos=$((criticos + 1))
fi

# ---------------------------------------------------------------- Node + dependências npm
if command -v node >/dev/null 2>&1; then
  echo "  node .......... $(node -v)"
  if [ -f package.json ]; then
    if [ -d node_modules ]; then
      echo "  npm ........... node_modules presente ($(ls node_modules | wc -l) pacotes no topo)"
    else
      echo "  npm ........... node_modules ausente — os 12 portões NÃO dependem disso"
      echo "                  para build:pwa/test:pwa/lighthouse:pwa: npm install (leva ~7 min)"
    fi
  fi
else
  echo "  ‼️  node AUSENTE — o portão build-consolidated-schema.mjs não roda"
  criticos=$((criticos + 1))
fi

# ---------------------------------------------------------------- estado do próprio Hub
[ -f CLAUDE.md ] || { echo "  ‼️  CLAUDE.md ausente — as regras de trabalho não serão carregadas"; ressalvas=$((ressalvas + 1)); }
[ -d scripts/ci ] || { echo "  ‼️  scripts/ci ausente — o código do Hub não está neste checkout"; criticos=$((criticos + 1)); }

# O config.php real é criado pelo instalador e NÃO é versionado. Ausência é o estado normal
# de um checkout limpo; dizer isso evita que a sessão trate como defeito.
if [ -d config ] && [ ! -f config/config.php ]; then
  echo "  config ........ sem config/config.php (normal em checkout limpo; os scripts CLI caem no config.example.php)"
fi

if [ "$criticos" -gt 0 ]; then
  echo "  RESULTADO: $criticos item(ns) crítico(s) — os portões vão falhar"
elif [ "$ressalvas" -gt 0 ]; then
  echo "  RESULTADO: utilizável, com as ressalvas acima"
else
  echo "  RESULTADO: pronto — os 12 portões estáticos podem rodar"
fi
echo "───────────────────────────────────────────────────────────────"

exit 0
