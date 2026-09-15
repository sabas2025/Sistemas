<?php
declare(strict_types=1);
require __DIR__.'/_helpers.php';
$checks=[];$manifest=hub_read('public/manifest.webmanifest');$js=hub_read('public/assets/minimalist-ui.js');$pwa=hub_read('public/assets/pwa.js');$sw=hub_read('public/sw.js');
hub_check($checks,'Manifest tema claro',str_contains($manifest,'"theme_color": "#2563eb"'));
hub_check($checks,'Manifest fundo claro',str_contains($manifest,'"background_color": "#f4f7fb"'));
hub_check($checks,'Sem tema escuro automático',!str_contains($js,'prefers-color-scheme: dark'));
hub_check($checks,'Cache PWA 104.45+',hub_sw_version()!==''&&version_compare(hub_sw_version(),'104.45.0','>='),hub_sw_version());
hub_check($checks,'PWA e SW sincronizados',str_contains($pwa,hub_sw_version())&&str_contains($sw,hub_sw_version()),hub_sw_version());
hub_finish($checks);
