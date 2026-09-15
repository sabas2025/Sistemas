<?php
declare(strict_types=1);
require __DIR__.'/_helpers.php';
$checks=[];$css=hub_read('public/assets/scroll-enterprise.css');$layout=hub_read('views/layout_top.php');$ui=hub_read('public/assets/futuristic-ui.js');$sw=hub_read('public/sw.js');
hub_check($checks,'CSS de rolagem existe',hub_asset_exists('scroll-enterprise.css'));
hub_check($checks,'Layout carrega CSS de rolagem',hub_layout_references($layout,'scroll-enterprise.css'));
hub_check($checks,'Body permite rolagem vertical',str_contains($css,'overflow-y:auto!important'));
hub_check($checks,'App shell sem altura máxima',str_contains($css,'.app-shell')&&str_contains($css,'max-height:none!important'));
hub_check($checks,'PWA standalone rolável',str_contains($css,'@media (display-mode:standalone)'));
hub_check($checks,'Recuperação pageshow',str_contains($ui,"addEventListener('pageshow'"));
hub_check($checks,'Cache PWA inclui correção',str_contains($sw,'scroll-enterprise.min.css')||str_contains($sw,'scroll-enterprise.css'));
hub_check($checks,'Cache PWA 104.39+',hub_sw_version()!==''&&version_compare(hub_sw_version(),'104.39.0','>='),hub_sw_version());
hub_finish($checks);
