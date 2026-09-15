<?php
declare(strict_types=1);
require __DIR__.'/_helpers.php';
$checks=[];

/**
 * Reauditoria 2026-09-14 (achado A-11): a assinatura de backup provava apenas que ESTE Hub
 * ingeriu o arquivo - não que a origem fosse confiável. Um SQL/ZIP trazido de fora era assinado
 * no import e, dali em diante, ficava indistinguível de um backup gerado localmente; bastava a
 * confirmação padrão "RESTAURAR" para sobrescrever a base com conteúdo de origem externa.
 *
 * A correção grava a proveniência DENTRO do payload assinado (portanto coberta pelo HMAC) e o
 * restore passa a exigir confirmação reforçada para importados. Este teste existia como lacuna:
 * a correção foi entregue sem regressão automatizada. Ele cobre a propriedade que realmente
 * importa - a proveniência não pode ser rebaixada de "importado" para "local" sem a chave HMAC -
 * além dos caminhos de compatibilidade (assinatura v1) e de falha fechada.
 */

// App mínimo: key() só precisa de security.backup_signature_key e de saber que não é produção.
if (!class_exists('App')) {
    class App {
        /** @return array<string,mixed> */
        public static function config(): array {
            return ['security' => ['backup_signature_key' => 'teste-de-regressao-a11-0123456789abcdef']];
        }
        public static function isProduction(): bool { return false; }
    }
}
if (!class_exists('RequestContext')) {
    class RequestContext { public static function id(): string { return 'teste-a11'; } }
}
require_once hub_root().'/app/Services/BackupSignatureService.php';

$tmp = sys_get_temp_dir().'/hub-a11-'.bin2hex(random_bytes(6));
@mkdir($tmp, 0700, true);
$limpar = static function (string $dir): void {
    foreach (glob($dir.'/*') ?: [] as $f) @unlink($f);
    @rmdir($dir);
};

try {
    // --- 1. Proveniência local viaja no payload assinado ---
    $local = $tmp.'/backup_local.zip';
    file_put_contents($local, 'conteudo-de-backup-local');
    BackupSignatureService::sign($local, BackupSignatureService::PROV_LOCAL);
    $payloadLocal = BackupSignatureService::verify($local);
    hub_check($checks,'sign() grava a proveniência dentro do payload assinado',
        ($payloadLocal['provenance'] ?? null) === BackupSignatureService::PROV_LOCAL);
    hub_check($checks,'Assinatura declara versão 2 (payload com proveniência)',
        (int)($payloadLocal['version'] ?? 0) === 2);
    hub_check($checks,'provenance() confirma backup gerado localmente',
        BackupSignatureService::provenance($local) === BackupSignatureService::PROV_LOCAL);

    // --- 2. Proveniência importada é preservada ---
    $importado = $tmp.'/importado_externo.zip';
    file_put_contents($importado, 'conteudo-de-backup-externo');
    BackupSignatureService::sign($importado, BackupSignatureService::PROV_IMPORTED);
    hub_check($checks,'Backup importado é assinado como origem externa',
        BackupSignatureService::provenance($importado) === BackupSignatureService::PROV_IMPORTED);

    // --- 3. A propriedade central do A-11: rebaixar a proveniência quebra o HMAC ---
    $sigPath = BackupSignatureService::signaturePath($importado);
    $sig = json_decode((string)file_get_contents($sigPath), true);
    $sig['provenance'] = BackupSignatureService::PROV_LOCAL; // adulteração: "vira" backup local
    file_put_contents($sigPath, json_encode($sig, JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE));
    $recusou = false;
    try { BackupSignatureService::verify($importado); } catch (Throwable $e) { $recusou = true; }
    hub_check($checks,'Trocar a proveniência na assinatura invalida o HMAC (não é falsificável sem a chave)', $recusou);
    hub_check($checks,'Assinatura adulterada é tratada como origem externa (falha fechada)',
        BackupSignatureService::provenance($importado) === BackupSignatureService::PROV_IMPORTED);

    // --- 4. Compatibilidade: assinatura v1 não declara proveniência ---
    // Reproduz o formato antigo (sem a chave 'provenance') e confirma que o nome decide.
    $assinarV1 = static function (string $arquivo): void {
        $ref = new ReflectionMethod('BackupSignatureService', 'key');
        $ref->setAccessible(true);
        $payload = [
            'version'=>1,'file'=>basename($arquivo),'sha256'=>hash_file('sha256',$arquivo),
            'size'=>filesize($arquivo) ?: 0,'created_at'=>date('c'),'trace_id'=>null,
        ];
        $payload['hmac'] = hash_hmac('sha256', json_encode($payload, JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE), $ref->invoke(null));
        file_put_contents(BackupSignatureService::signaturePath($arquivo), json_encode($payload, JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE));
    };
    $v1Local = $tmp.'/backup_2026_09_14.zip';
    file_put_contents($v1Local, 'backup-antigo-local'); $assinarV1($v1Local);
    hub_check($checks,'Assinatura v1 sem proveniência: nome comum resolve como local',
        BackupSignatureService::provenance($v1Local) === BackupSignatureService::PROV_LOCAL);
    $v1Import = $tmp.'/importado_2026_09_14.zip';
    file_put_contents($v1Import, 'backup-antigo-importado'); $assinarV1($v1Import);
    hub_check($checks,'Assinatura v1 sem proveniência: prefixo importado_ resolve como externo',
        BackupSignatureService::provenance($v1Import) === BackupSignatureService::PROV_IMPORTED);

    // --- 5. Falhas fechadas ---
    $semAssinatura = $tmp.'/sem_assinatura.zip';
    file_put_contents($semAssinatura, 'arquivo-sem-assinatura');
    hub_check($checks,'Arquivo sem assinatura é tratado como origem externa, nunca como local',
        BackupSignatureService::provenance($semAssinatura) === BackupSignatureService::PROV_IMPORTED);
    $recusouProv = false;
    try { BackupSignatureService::sign($local, 'origem_inventada'); } catch (Throwable $e) { $recusouProv = true; }
    hub_check($checks,'sign() recusa proveniência desconhecida', $recusouProv);

    // --- 6. O restore precisa exigir a frase reforçada para importados ---
    $backupService = hub_read('app/Services/BackupService.php');
    hub_check($checks,'Import assina o arquivo como origem externa',
        str_contains($backupService, 'BackupSignatureService::sign($dest, BackupSignatureService::PROV_IMPORTED)'));
    hub_check($checks,'Restore exige RESTAURAR IMPORTADO quando a proveniência é externa',
        str_contains($backupService, "PROV_IMPORTED && trim(\$confirmacao)!=='RESTAURAR IMPORTADO'"));
    hub_check($checks,'Restore ainda aceita a confirmação padrão para backups locais',
        str_contains($backupService, "in_array(trim(\$confirmacao),['RESTAURAR','RESTAURAR IMPORTADO'],true)"));
} finally {
    $limpar($tmp);
}

hub_finish($checks);
