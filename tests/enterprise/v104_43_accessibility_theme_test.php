<?php
declare(strict_types=1);
require __DIR__.'/_helpers.php';
$checks=[];$layout=hub_read('views/layout_top.php');$bottom=hub_read('views/layout_bottom.php');$css=hub_read('public/assets/minimalist-enterprise.css');$js=hub_read('public/assets/minimalist-ui.js');
hub_check($checks,'Versão UI 104.43+',hub_sw_version()!==''&&version_compare(hub_sw_version(),'104.43.0','>='),hub_sw_version());
hub_check($checks,'CSS e JS versionados',preg_match('/minimalist-enterprise\.min\.css\?v=[0-9.]+/',$layout)===1&&preg_match('/minimalist-ui\.min\.js\?v=[0-9.]+/',$bottom)===1);
hub_check($checks,'Skip link acessível',str_contains($layout,'hub-skip-link')&&str_contains($layout,'hubMainContent'));
hub_check($checks,'Command palette',str_contains($layout,'data-hub-command-palette')&&str_contains($js,'initCommandPalette'));
hub_check($checks,'Tema escuro opcional',str_contains($layout,'data-hub-theme')&&str_contains($css,'data-hub-theme="dark"'));
hub_check($checks,'Indicador de conexão',str_contains($layout,'data-hub-connection')&&str_contains($js,'initConnectionStatus'));
hub_check($checks,'Tabelas longas',str_contains($js,'enhanceLongTables')&&str_contains($css,'hub-sticky-table'));
hub_finish($checks);
