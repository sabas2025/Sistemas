#!/usr/bin/env bash
#
# deploy.sh — Atualização SEGURA de uma instalação do Hub de Integração.
#
# Copia o CÓDIGO de um pacote novo por cima da instalação existente SEM tocar
# em config/config.php nem em storage/ (logs, cache de sessão, backups,
# install.lock, install-authorization.json). Faz backup antes, atualiza o
# classmap (que vem no pacote), e confere a integridade (FIM) ao final.
#
# USO:
#   ./deploy.sh <pacote.zip | pasta-do-pacote> <destino-da-instalacao> [usuario:grupo]
#
# EXEMPLOS:
#   ./deploy.sh hub-V104.49.3-R7-0efcd92.zip /var/www/hub01
#   ./deploy.sh hub-V104.49.3-R7-0efcd92.zip /var/www/hub01 www-data:www-data
#   ./deploy.sh ./hub /var/www/hub01                 # a partir do pacote já extraído
#
# OPÇÕES (variáveis de ambiente):
#   DRY_RUN=1   ./deploy.sh ...   # só mostra o que mudaria, não copia nada
#   NO_BACKUP=1 ./deploy.sh ...   # pula o backup (não recomendado)
#
# O QUE É PRESERVADO (nunca sobrescrito):
#   - config/config.php            (segredos e conexão do ambiente)
#   - storage/                     (logs, cache, sessões, backups, install.lock,
#                                    install-authorization.json)
#   Obs.: storage/cache/classmap.php É atualizado de propósito — o autoloader
#   precisa bater com o código novo. Os placeholders versionados de storage/
#   (.htaccess / index.html de proteção de diretório) também são atualizados.
#
# O QUE NÃO FAZ (de propósito):
#   - Não roda migrations (elas são manuais; veja o GUIA/RUNBOOK).
#   - Não apaga arquivos do destino (sem --delete): nada de runtime é perdido.
#
set -euo pipefail

# ---------- argumentos ----------
if [[ $# -lt 2 ]]; then
  echo "Uso: $0 <pacote.zip|pasta> <destino> [usuario:grupo]" >&2
  exit 2
fi
SRC_ARG="$1"
DEST="$2"
OWNER="${3:-}"
DRY_RUN="${DRY_RUN:-0}"
NO_BACKUP="${NO_BACKUP:-0}"

command -v rsync >/dev/null 2>&1 || { echo "ERRO: rsync não está instalado (apt-get install rsync)." >&2; exit 1; }

# ---------- resolve a origem (zip ou pasta) para uma pasta com a árvore do Hub ----------
CLEANUP_TMP=""
resolve_src() {
  local a="$1"
  if [[ -d "$a" ]]; then
    # aceita tanto a pasta que CONTÉM public/ quanto a pasta pai "hub/"
    if [[ -f "$a/public/install.php" ]]; then echo "$a"; return; fi
    if [[ -f "$a/hub/public/install.php" ]]; then echo "$a/hub"; return; fi
    echo "ERRO: '$a' não parece o pacote do Hub (sem public/install.php)." >&2; exit 1
  elif [[ -f "$a" && "$a" == *.zip ]]; then
    command -v unzip >/dev/null 2>&1 || { echo "ERRO: unzip não está instalado." >&2; exit 1; }
    local tmp; tmp="$(mktemp -d)"; CLEANUP_TMP="$tmp"
    unzip -q "$a" -d "$tmp"
    if [[ -f "$tmp/hub/public/install.php" ]]; then echo "$tmp/hub"; return; fi
    if [[ -f "$tmp/public/install.php" ]]; then echo "$tmp"; return; fi
    echo "ERRO: o zip não contém public/install.php na raiz esperada." >&2; exit 1
  else
    echo "ERRO: origem '$a' não é uma pasta nem um .zip." >&2; exit 1
  fi
}
trap '[[ -n "$CLEANUP_TMP" ]] && rm -rf "$CLEANUP_TMP"' EXIT
SRC="$(resolve_src "$SRC_ARG")"

# ---------- validações do destino ----------
[[ -d "$DEST" ]] || { echo "ERRO: destino '$DEST' não existe." >&2; exit 1; }
[[ -f "$SRC/CHECKSUMS-SHA256.txt" ]] || { echo "ERRO: pacote sem CHECKSUMS-SHA256.txt." >&2; exit 1; }

echo "==> Origem : $SRC"
echo "==> Destino: $DEST"
if [[ -f "$DEST/config/config.php" ]]; then
  echo "==> Instalação detectada (config/config.php presente) — será PRESERVADO."
else
  echo "==> ATENÇÃO: não há config/config.php no destino."
  echo "    Isto parece uma instalação NOVA (ainda não instalada). O deploy só copia o"
  echo "    código; depois rode public/install.php para criar config e banco."
fi

# ---------- backup ----------
if [[ "$NO_BACKUP" != "1" && "$DRY_RUN" != "1" ]]; then
  TS="$(date +%Y%m%d-%H%M%S)"
  BK="$(dirname "$DEST")/$(basename "$DEST")-backup-$TS.tar.gz"
  echo "==> Backup do destino em: $BK"
  echo "    (exclui storage/backups, storage/logs, node_modules e .git para não inflar)"
  tar -czf "$BK" \
      --exclude="./storage/backups" \
      --exclude="./storage/logs" \
      --exclude="./node_modules" \
      --exclude="./.git" \
      -C "$DEST" . 2>/dev/null || { echo "ERRO: falha ao criar backup — abortando." >&2; exit 1; }
  echo "    Backup criado."
else
  echo "==> Backup PULADO."
fi

# ---------- rsync ----------
# Sem --delete: nada do destino é apagado (runtime preservado).
# Excluímos config/config.php e o conteúdo de runtime de storage/ para nunca
# sobrescrevê-los. classmap.php e placeholders de storage/ NÃO são excluídos.
RSYNC_ARGS=(-a --human-readable
  --exclude="config/config.php"
  --exclude="storage/logs/**"
  --exclude="storage/sessions/**"
  --exclude="storage/backups/**"
  --exclude="storage/cache/security/**"
  --exclude="storage/install.lock"
  --exclude="storage/install-authorization.json"
  --exclude=".git/**"
  --exclude="node_modules/**"
)
if [[ "$DRY_RUN" == "1" ]]; then
  echo "==> SIMULAÇÃO (DRY_RUN=1): arquivos que seriam atualizados:"
  rsync "${RSYNC_ARGS[@]}" --dry-run --itemize-changes "$SRC"/ "$DEST"/ | sed 's/^/    /'
  echo "==> Nada foi alterado (simulação)."
  exit 0
fi

echo "==> Copiando código (rsync, sem --delete)..."
rsync "${RSYNC_ARGS[@]}" "$SRC"/ "$DEST"/
echo "    Código atualizado."

# ---------- permissões (opcional) ----------
if [[ -n "$OWNER" ]]; then
  echo "==> Ajustando dono para $OWNER ..."
  chown -R "$OWNER" "$DEST"
fi
# storage/ precisa ser gravável pelo servidor web:
if [[ -d "$DEST/storage" ]]; then
  chmod -R u+rwX "$DEST/storage" 2>/dev/null || true
fi

# ---------- verificação de integridade (FIM) ----------
echo "==> Conferindo integridade (FIM) dos arquivos versionados..."
if ( cd "$DEST" && sha256sum -c CHECKSUMS-SHA256.txt ) >/tmp/hub-fim.$$ 2>&1; then
  echo "    FIM OK — todos os arquivos versionados batem com o manifesto."
else
  OKN=$(grep -c ': OK$' /tmp/hub-fim.$$ || true)
  BAD=$(grep -c 'FAILED' /tmp/hub-fim.$$ || true)
  echo "    ATENÇÃO: FIM acusou divergência (OK=$OKN, FAILED/erros=$BAD):"
  grep -iE 'FAILED|No such file' /tmp/hub-fim.$$ | grep -viE 'config/config.php' | sed 's/^/      /' | head -20
  echo "    Se as linhas acima forem só arquivos que VOCÊ editou de propósito, ignore."
  echo "    Caso contrário, restaure o backup: $BK"
fi
rm -f /tmp/hub-fim.$$

echo ""
echo "==> Deploy concluído."
echo "    Próximos passos:"
echo "      1) Se houver migrations novas, aplique-as no banco (execução manual)."
echo "      2) No navegador, Ctrl+F5 (o Service Worker do PWA cacheia assets)."
echo "      3) Confira uma tela autenticada e o rodapé com a versão."
echo "    Rollback: extraia o backup por cima do destino:"
echo "      tar -xzf <backup>.tar.gz -C \"$DEST\""
