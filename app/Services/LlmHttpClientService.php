<?php
/**
 * Fase B1 (2026-09-20) — Cliente HTTP para a Messages API da Anthropic.
 *
 * SEM GATILHO: este serviço NÃO é chamado por nenhuma rota. Ele é a biblioteca que o
 * executeReal() da Fase B (ainda não construído) usará quando a execução real for ligada,
 * atrás de aprovação humana, custo e homologação. Enquanto B2/B5 não existirem, nada aqui roda.
 *
 * Endurecimento SSRF idêntico ao MyOuroGraphqlService: URL FIXA (host não é controlável),
 * só HTTPS, sem redirect, SSL verify on, sem proxy, timeout e teto de corpo. A chave de API é
 * recebida por parâmetro (o executeReal a resolve do Token Vault) — este serviço não decifra cofre.
 *
 * buildAnthropicRequest() e normalizeAnthropic() são PUROS (sem rede, sem banco), para teste
 * determinístico. send()/anthropicMessages() aceitam um transporte injetável; o default é cURL.
 */
class LlmHttpClientService {
  public const ANTHROPIC_URL = 'https://api.anthropic.com/v1/messages';
  public const ANTHROPIC_VERSION = '2023-06-01';
  private const CONNECT_TIMEOUT = 10;
  private const TIMEOUT = 60;
  private const MAX_BODY = 5000000; // 5 MB

  /** Monta o corpo/headers do request Anthropic. Puro. @return array{url:string,headers:array,body:array} */
  public static function buildAnthropicRequest(array $params): array {
    $body = [
      'model' => (string)($params['model'] ?? ''),
      'max_tokens' => max(1, (int)($params['max_tokens'] ?? 1024)),
      'messages' => array_values($params['messages'] ?? []),
    ];
    if (isset($params['system']) && (string)$params['system'] !== '') $body['system'] = (string)$params['system'];
    if (isset($params['temperature'])) $body['temperature'] = (float)$params['temperature'];
    return [
      'url' => self::ANTHROPIC_URL,
      'headers' => ['content-type' => 'application/json', 'anthropic-version' => self::ANTHROPIC_VERSION],
      'body' => $body,
    ];
  }

  /**
   * Normaliza a resposta bruta do transporte num resultado uniforme, compatível com o executor
   * do LlmFallbackService: ['ok','tentar_proximo','status','text','stop_reason','usage','erro'].
   * Puro. 'tentar_proximo' distingue falha de provedor (5xx/429/timeout → tenta o próximo) de
   * falha global (recusa, 4xx de contrato → não adianta trocar de provedor).
   */
  public static function normalizeAnthropic(int $status, string $rawBody, string $transportError = ''): array {
    if ($transportError !== '') {
      return ['ok' => false, 'tentar_proximo' => true, 'status' => 0, 'text' => '', 'stop_reason' => null, 'usage' => null, 'erro' => 'Transporte: ' . $transportError];
    }
    $data = json_decode($rawBody, true);
    if (!is_array($data)) {
      return ['ok' => false, 'tentar_proximo' => ($status >= 500 || $status === 429), 'status' => $status, 'text' => '', 'stop_reason' => null, 'usage' => null, 'erro' => 'Resposta não-JSON (HTTP ' . $status . ').'];
    }
    if ($status < 200 || $status >= 300) {
      $msg = (string)($data['error']['message'] ?? ('HTTP ' . $status));
      $recuperavel = ($status >= 500 || $status === 429 || $status === 408 || $status === 529);
      return ['ok' => false, 'tentar_proximo' => $recuperavel, 'status' => $status, 'text' => '', 'stop_reason' => null, 'usage' => null, 'erro' => $msg];
    }
    $stop = (string)($data['stop_reason'] ?? '');
    if ($stop === 'refusal') {
      return ['ok' => false, 'tentar_proximo' => false, 'status' => $status, 'text' => '', 'stop_reason' => 'refusal', 'usage' => self::usage($data), 'erro' => 'Recusa do provedor (refusal).'];
    }
    $text = '';
    foreach (($data['content'] ?? []) as $b) {
      if (is_array($b) && ($b['type'] ?? '') === 'text') $text .= (string)($b['text'] ?? '');
    }
    return ['ok' => true, 'tentar_proximo' => false, 'status' => $status, 'text' => $text, 'stop_reason' => $stop !== '' ? $stop : null, 'usage' => self::usage($data), 'erro' => ''];
  }

  private static function usage(array $data): array {
    $u = is_array($data['usage'] ?? null) ? $data['usage'] : [];
    return ['input_tokens' => (int)($u['input_tokens'] ?? 0), 'output_tokens' => (int)($u['output_tokens'] ?? 0)];
  }

  /**
   * Executa a chamada. $apiKey é fornecida pelo chamador (o executeReal a tira do Token Vault).
   * $transport é injetável para teste: fn(string $url, array $headers, string $jsonBody): array
   * com ['status'=>int,'body'=>string,'error'=>string]. Default = cURL endurecido.
   */
  public static function anthropicMessages(array $params, string $apiKey, ?callable $transport = null): array {
    $req = self::buildAnthropicRequest($params);
    $headers = $req['headers'];
    if ($apiKey !== '') $headers['x-api-key'] = $apiKey;
    $json = json_encode($req['body'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    if ($json === false) return self::normalizeAnthropic(0, '', 'falha ao serializar o corpo JSON');
    $transport = $transport ?? self::curlTransport();
    $res = $transport($req['url'], $headers, $json);
    return self::normalizeAnthropic((int)($res['status'] ?? 0), (string)($res['body'] ?? ''), (string)($res['error'] ?? ''));
  }

  /** Transporte cURL endurecido (SSRF), mesmo desenho do MyOuroGraphqlService. */
  private static function curlTransport(): callable {
    return static function (string $url, array $headers, string $jsonBody): array {
      if (!function_exists('curl_init')) return ['status' => 0, 'body' => '', 'error' => 'Extensão PHP cURL ausente.'];
      $hdr = [];
      foreach ($headers as $k => $v) $hdr[] = $k . ': ' . $v;
      $body = '';
      $ch = curl_init($url);
      curl_setopt_array($ch, [
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => $jsonBody,
        CURLOPT_HTTPHEADER => $hdr,
        CURLOPT_SSL_VERIFYPEER => true,
        CURLOPT_SSL_VERIFYHOST => 2,
        CURLOPT_FOLLOWLOCATION => false,
        CURLOPT_MAXREDIRS => 0,
        CURLOPT_PROTOCOLS => CURLPROTO_HTTPS,
        CURLOPT_REDIR_PROTOCOLS => CURLPROTO_HTTPS,
        CURLOPT_PROXY => '',
        CURLOPT_CONNECTTIMEOUT => self::CONNECT_TIMEOUT,
        CURLOPT_TIMEOUT => self::TIMEOUT,
        CURLOPT_RETURNTRANSFER => false,
        CURLOPT_WRITEFUNCTION => static function ($curl, string $chunk) use (&$body): int {
          $body .= $chunk;
          if (strlen($body) > self::MAX_BODY) return 0; // aborta se estourar o teto
          return strlen($chunk);
        },
      ]);
      curl_exec($ch);
      $status = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
      $error = curl_errno($ch) ? curl_error($ch) : '';
      curl_close($ch);
      return ['status' => $status, 'body' => $body, 'error' => $error];
    };
  }
}
