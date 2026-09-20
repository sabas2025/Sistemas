<?php
/**
 * Fase A (2026-09-20) — Validação de saída estruturada (structured output) da IA.
 *
 * Puro, local, determinístico: não chama provedor, não toca banco, não depende de rede.
 * Serve para NUNCA confiar num JSON só porque é sintaticamente válido (Fase 14 da auditoria LLM):
 * valida tipos, obrigatórios, enums e faixas contra um schema NOMEADO, com um "repair" limitado
 * (remove cercas markdown ```json e extrai o primeiro objeto {...} balanceado).
 *
 * O registro de schemas é em código (sem tabela nova). Novos schemas entram aqui.
 * Enquanto a execução real de IA está desligada, este serviço é exercitado por teste e pela
 * governança; na Fase B ele passa a validar a resposta real do modelo antes de qualquer uso.
 */
class LlmStructuredOutputService {
  /** @return array<string,array> registro de schemas suportados */
  private static function schemas(): array {
    return [
      // Exemplo inicial: classificação de intenção de uma mensagem operacional.
      'classificacao_intencao' => [
        'type' => 'object',
        'required' => ['intencao', 'confianca'],
        'properties' => [
          'intencao' => ['type' => 'string', 'enum' => ['pedido','estoque','fiscal','produto','duvida','outro']],
          'confianca' => ['type' => 'number', 'min' => 0, 'max' => 1],
          'resumo' => ['type' => 'string', 'maxLength' => 500],
        ],
      ],
    ];
  }

  /** @return string[] chaves de schema disponíveis */
  public static function schemaKeys(): array { return array_keys(self::schemas()); }

  /**
   * @return array{valid:bool,data:mixed,errors:string[]}
   */
  public static function validate(string $json, string $schemaKey): array {
    $schemas = self::schemas();
    if (!isset($schemas[$schemaKey])) {
      return ['valid' => false, 'data' => null, 'errors' => ['Schema desconhecido: ' . $schemaKey]];
    }
    $parsed = self::parseWithRepair($json);
    if ($parsed === null) {
      return ['valid' => false, 'data' => null, 'errors' => ['JSON inválido ou ausente, mesmo após repair.']];
    }
    $errors = self::validateNode($parsed, $schemas[$schemaKey], '$');
    return ['valid' => empty($errors), 'data' => empty($errors) ? $parsed : null, 'errors' => $errors];
  }

  /** Decodifica com repair limitado; devolve null se não houver JSON aproveitável. */
  private static function parseWithRepair(string $raw): mixed {
    $t = trim($raw);
    if ($t === '') return null;
    $d = json_decode($t, true);
    if (json_last_error() === JSON_ERROR_NONE) return $d;
    if (preg_match('/```(?:json)?\s*(.+?)\s*```/is', $t, $m)) {
      $d = json_decode(trim($m[1]), true);
      if (json_last_error() === JSON_ERROR_NONE) return $d;
    }
    $obj = self::firstJsonObject($t);
    if ($obj !== null) {
      $d = json_decode($obj, true);
      if (json_last_error() === JSON_ERROR_NONE) return $d;
    }
    return null;
  }

  /** Extrai o primeiro objeto {...} balanceado, respeitando strings e escapes. */
  private static function firstJsonObject(string $s): ?string {
    $start = strpos($s, '{');
    if ($start === false) return null;
    $depth = 0; $inStr = false; $esc = false;
    for ($i = $start, $n = strlen($s); $i < $n; $i++) {
      $c = $s[$i];
      if ($inStr) {
        if ($esc) { $esc = false; }
        elseif ($c === '\\') { $esc = true; }
        elseif ($c === '"') { $inStr = false; }
        continue;
      }
      if ($c === '"') { $inStr = true; }
      elseif ($c === '{') { $depth++; }
      elseif ($c === '}') { $depth--; if ($depth === 0) return substr($s, $start, $i - $start + 1); }
    }
    return null;
  }

  /** @return string[] erros; vazio = válido */
  private static function validateNode(mixed $value, array $schema, string $path): array {
    $errors = [];
    $type = $schema['type'] ?? null;
    if ($type === 'object') {
      if (!is_array($value) || array_is_list($value)) return ["$path deveria ser objeto."];
      foreach (($schema['required'] ?? []) as $req) {
        if (!array_key_exists($req, $value)) $errors[] = "$path.$req é obrigatório.";
      }
      foreach (($schema['properties'] ?? []) as $k => $sub) {
        if (array_key_exists($k, $value)) $errors = array_merge($errors, self::validateNode($value[$k], $sub, "$path.$k"));
      }
    } elseif ($type === 'string') {
      if (!is_string($value)) return ["$path deveria ser string."];
      if (isset($schema['enum']) && !in_array($value, $schema['enum'], true)) $errors[] = "$path fora do enum.";
      if (isset($schema['maxLength']) && mb_strlen($value) > (int)$schema['maxLength']) $errors[] = "$path excede maxLength.";
    } elseif ($type === 'number') {
      if (!is_int($value) && !is_float($value)) return ["$path deveria ser número."];
      if (isset($schema['min']) && $value < $schema['min']) $errors[] = "$path abaixo do mínimo.";
      if (isset($schema['max']) && $value > $schema['max']) $errors[] = "$path acima do máximo.";
    } elseif ($type === 'integer') {
      if (!is_int($value)) return ["$path deveria ser inteiro."];
    } elseif ($type === 'boolean') {
      if (!is_bool($value)) return ["$path deveria ser booleano."];
    }
    return $errors;
  }
}
