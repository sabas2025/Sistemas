<?php
declare(strict_types=1);
require __DIR__.'/_helpers.php';
$checks=[];
$root=hub_root();
$installer=hub_read('public/install.php');
$service=hub_read('app/Services/FileIntegrityService.php');
$manifestPath=$root.'/storage/file_integrity_manifest.json';
$manifest=is_file($manifestPath)?json_decode((string)file_get_contents($manifestPath),true):null;
$config=require hub_config_file();
$key=(string)($config['security']['fim_manifest_hmac_key']??'');
if($key==='')$key=(string)($config['security']['encryption_key']??'');
if($key==='')$key='hub-fim-dev-key-change-me';
$files=[];
foreach(['app/Core','app/Services','app/Controllers','public'] as $dir){
  foreach(glob($root.'/'.$dir.'/*.php')?:[] as $file){$files[str_replace($root.'/','',$file)]=hash_file('sha256',$file);}
}
ksort($files);
$expectedHmac=hash_hmac('sha256',json_encode($files,JSON_UNESCAPED_SLASHES),hash('sha256',$key,true));

$safeUpdate=hub_read('ATUALIZACAO-SEGURA.txt');
$manifestIntentionallyAbsent=!is_file($manifestPath) && (str_contains($safeUpdate,'manifesto FIM') || str_contains($safeUpdate,'file_integrity_manifest') || str_contains($safeUpdate,'regenere a baseline FIM'));
hub_check($checks,'Manifesto FIM existe na instalação ou foi excluído intencionalmente da atualização segura',
    (is_array($manifest)&&is_array($manifest['files']??null)&&is_string($manifest['hmac']??null)) || $manifestIntentionallyAbsent);
hub_check($checks,'Manifesto FIM cobre exatamente os arquivos críticos quando presente',
    $manifestIntentionallyAbsent || (is_array($manifest)&&($manifest['files']??null)===$files),
    'manifesto='.(is_array($manifest['files']??null)?count($manifest['files']):0).', atual='.count($files));
hub_check($checks,'Assinatura HMAC do manifesto FIM é válida quando presente',
    $manifestIntentionallyAbsent || (is_array($manifest)&&hash_equals((string)($manifest['hmac']??''),$expectedHmac)));
hub_check($checks,'Instalador gera manifesto com a chave secreta da própria instalação',str_contains($installer,'function publish_install_artifacts')&&str_contains($installer,"build_fim_manifest(\$root,(string)(\$config['security']['fim_manifest_hmac_key']??''))"));
$fimPos=strpos($installer,'$fimPath=>$fim.PHP_EOL');
$configPos=strpos($installer,'$configPath=>export_config($config)');
$lockPos=strpos($installer,'$lockPath=>\'installed_at=\'');
hub_check($checks,'Publicação agrupa manifesto, configuração e install.lock, deixando o lock por último',$fimPos!==false&&$configPos!==false&&$lockPos!==false&&$fimPos<$configPos&&$configPos<$lockPos);
hub_check($checks,'Falha de publicação restaura o estado anterior dos três artefatos',str_contains($installer,'$snapshots[$path]')&&str_contains($installer,'array_reverse(array_keys($contents))')&&str_contains($installer,'throw $publishError'));
hub_check($checks,'Serviço FIM usa escrita temporária, rename atômico e permissão privada',str_contains($service, "\$path.'.tmp.'")&&str_contains($service, 'rename($tmp, $path)')&&str_contains($service, 'chmod($path, 0600)'));

hub_finish($checks);
