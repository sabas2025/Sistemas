#!/usr/bin/env bash
set -euo pipefail

ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/../.." && pwd)"
count=0
failed=0
failed_files=()
while IFS= read -r -d '' test_file; do
  # Reauditoria 2026-09-14: chamar "php $test_file" direto, sem if/while ao redor, fazia o
  # "set -e" acima abortar o script inteiro no primeiro teste que retornasse falha (exit!=0)
  # - todos os testes seguintes em ordem alfabética eram pulados em silêncio, e a falha real
  # (v104_48_1_schema_vsm_contract_test.php) mascarava dezenas de outros testes sem executar.
  # Um comando dentro de "if" não aciona o errexit, então isto deixa CADA teste rodar até o
  # fim e só falha a suíte no final, com a lista completa de quem falhou.
  if php "$test_file"; then
    :
  else
    failed=$((failed + 1))
    failed_files+=("$(basename "$test_file")")
  fi
  count=$((count + 1))
done < <(find "$ROOT/tests/enterprise" -maxdepth 1 -type f -name '*_test.php' -print0 | sort -z)

if [[ "$count" -eq 0 ]]; then
  echo "Nenhum teste Enterprise encontrado." >&2
  exit 1
fi

if [[ "$failed" -gt 0 ]]; then
  echo "Enterprise regression FALHOU: $failed de $count teste(s) com falha: ${failed_files[*]}" >&2
  exit 1
fi

echo "Enterprise regression OK: $count testes"
