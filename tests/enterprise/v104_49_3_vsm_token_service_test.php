<?php
declare(strict_types=1);
require __DIR__.'/_helpers.php';
$checks = [];

/**
 * F6-07 etapa 2 (2026-09-26) — VsmTokenService (troca clientToken+clientSecret por JWT).
 *
 * Contrato: POST /v1/auth/token {clientToken, clientSecret} -> {accessToken, expiresIn}.
 * A VSM não usa refresh_token; o Hub re-troca as credenciais quando o JWT expira.
 *
 * Parte COMPORTAMENTAL: isExpired() é pura (só time/strtotime) e é exercida de verdade.
 * Parte ESTRUTURAL: trava a fidelidade ao contrato (endpoint, corpo, SSRF, cache cifrado, e o
 * ponto do achado F6-07(e): 401/403 de credencial NÃO são retentados).
 * O happy-path HTTP real depende de rede+credenciais de Stage — fora de alcance daqui.
 *
 * Reprova sobre o código antigo: a classe não existe em main.
 */

require_once hub_root().'/app/Services/VsmTokenService.php';

// --- Comportamental: expiração com margem de 60s ---
hub_check($checks, 'isExpired: cache vazio conta como expirado', VsmTokenService::isExpired('') === true);
hub_check($checks, 'isExpired: instante passado é expirado', VsmTokenService::isExpired(date('Y-m-d H:i:s', time()-100)) === true);
hub_check($checks, 'isExpired: +600s é válido', VsmTokenService::isExpired(date('Y-m-d H:i:s', time()+600)) === false);
hub_check($checks, 'isExpired: +30s cai na margem de segurança (expirado)', VsmTokenService::isExpired(date('Y-m-d H:i:s', time()+30)) === true);
hub_check($checks, 'isExpired: data inválida é tratada como expirada', VsmTokenService::isExpired('nao-e-data') === true);

// --- Estrutural: fidelidade ao contrato ---
$svc = hub_read('app/Services/VsmTokenService.php');
hub_check($checks, 'serviço lido (N>0)', strlen($svc) > 500);
hub_check($checks, 'troca no endpoint /v1/auth/token', str_contains($svc, '/v1/auth/token'));
hub_check($checks, 'envia clientToken e clientSecret no corpo', str_contains($svc, "'clientToken'=>") && str_contains($svc, "'clientSecret'=>"));
hub_check($checks, 'lê accessToken do retorno', str_contains($svc, "\$json['accessToken']"));
hub_check($checks, 'lê expiresIn do retorno', str_contains($svc, "expiresIn"));
hub_check($checks, 'cacheia o JWT cifrado (CryptoService::encrypt em vsm_access_token)',
    str_contains($svc, 'CryptoService::encrypt') && str_contains($svc, 'vsm_access_token'));
hub_check($checks, 'valida a URL contra SSRF (VsmEndpointSecurityService)', str_contains($svc, 'VsmEndpointSecurityService::validateBaseUrl'));
hub_check($checks, 'grava só colunas existentes (compatível com banco antigo)', str_contains($svc, 'Database::columnExists'));
// F6-07(e): 401/403 são erro de credencial (permanente) — não podem ser retentados.
hub_check($checks, 'só retenta transitório (429/5xx/curl), nunca 401/403',
    str_contains($svc, '$http === 429 || $http >= 500') && !str_contains($svc, '$http === 401'));
hub_check($checks, 'exige credenciais antes de chamar (VSM_CREDENTIALS_MISSING)', str_contains($svc, 'VSM_CREDENTIALS_MISSING'));

hub_finish($checks);
