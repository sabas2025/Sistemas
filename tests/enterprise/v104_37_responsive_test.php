<?php
declare(strict_types=1);
require __DIR__.'/_helpers.php';
$checks=[];$layout=hub_read('views/layout_top.php');$login=hub_read('views/login.php');$css=hub_read('public/assets/responsive-enterprise.css');$ui=hub_read('public/assets/futuristic-ui.js');$sw=hub_read('public/sw.js');
hub_check($checks,'CSS responsivo final',hub_asset_exists('responsive-enterprise.css'));
hub_check($checks,'Layout carrega CSS responsivo',hub_layout_references($layout,'responsive-enterprise.css'));
hub_check($checks,'Login carrega camada responsiva',hub_layout_references($login,'responsive-enterprise.css')||str_contains($login,'viewport-fit=cover'));
hub_check($checks,'Viewport com safe area',str_contains($layout,'viewport-fit=cover'));
hub_check($checks,'Service worker 104.37+',hub_sw_version()!==''&&version_compare(hub_sw_version(),'104.37.0','>='),hub_sw_version());
hub_check($checks,'CSS incluído no cache PWA',str_contains($sw,'responsive-enterprise.min.css')||str_contains($sw,'responsive-enterprise.css'));
hub_check($checks,'JS prepara tabelas móveis',str_contains($ui,'responsive-cards'));
hub_check($checks,'Safe area iOS no CSS',str_contains($css,'safe-area-inset-top'));
hub_check($checks,'Sidebar off-canvas',str_contains($css,'translate3d(-105%'));
hub_finish($checks);
