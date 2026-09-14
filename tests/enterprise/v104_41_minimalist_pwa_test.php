<?php
declare(strict_types=1);
require __DIR__.'/_helpers.php';
$checks=[];$layout=hub_read('views/layout_top.php');$bottom=hub_read('views/layout_bottom.php');$css=hub_read('public/assets/minimalist-enterprise.css');$sw=hub_read('public/sw.js');$manifest=hub_read('public/manifest.webmanifest');
hub_check($checks,'CSS minimalista existe',hub_asset_exists('minimalist-enterprise.css'));
hub_check($checks,'JS de usabilidade existe',hub_asset_exists('minimalist-ui.js'));
hub_check($checks,'Layout carrega CSS minimalista',hub_layout_references($layout,'minimalist-enterprise.css'));
hub_check($checks,'Layout carrega JS minimalista',hub_layout_references($bottom,'minimalist-ui.js'));
hub_check($checks,'PWA 104.41+',hub_sw_version()!==''&&version_compare(hub_sw_version(),'104.41.0','>='),hub_sw_version());
hub_check($checks,'Tabela móvel em cards',str_contains($css,'.table.responsive-cards'));
hub_check($checks,'Movimento reduzido',str_contains($css,'prefers-reduced-motion'));
hub_check($checks,'Foco visível',str_contains($css,':focus-visible'));
hub_check($checks,'Screenshots reais no manifesto',str_contains($manifest,'dashboard-mobile.png'));
hub_finish($checks);
