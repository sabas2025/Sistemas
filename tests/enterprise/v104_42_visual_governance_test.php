<?php
declare(strict_types=1);
require __DIR__.'/_helpers.php';
$checks=[];$css=hub_read('public/assets/minimalist-enterprise.css');$js=hub_read('public/assets/minimalist-ui.js');$layout=hub_read('views/layout_top.php');
hub_check($checks,'Versão visual 104.42+',hub_sw_version()!==''&&version_compare(hub_sw_version(),'104.42.0','>='),hub_sw_version());
hub_check($checks,'Breadcrumb acessível',str_contains($layout,'hub-breadcrumb')&&str_contains($layout,'aria-label="Navegação estrutural"'));
hub_check($checks,'Preferência de densidade',str_contains($js,'initDensity')&&str_contains($css,'data-hub-density'));
hub_check($checks,'Favoritos locais',str_contains($js,'initFavorites')&&str_contains($css,'hub-favorites'));
hub_check($checks,'Erros com Trace ID',str_contains($js,'enhanceTraceErrors'));
hub_check($checks,'Teste visual Playwright',is_file(hub_root().'/tests/e2e/specs/visual-responsive.spec.js'));
hub_finish($checks);
