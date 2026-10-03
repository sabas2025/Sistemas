<?php
/**
 * Componente de abas padrão do Hub (Variação C — abas por domínio).
 *
 * Uso na view:
 *   <?php $hubTabs=[['id'=>'op','label'=>'Operação','icon'=>'bi-diagram-3'], ...];
 *         $hubTabsLabel='Domínios do dashboard'; ?>
 *   <div class="hub-tabgroup" data-hub-tabgroup="dashboard">
 *     <?php require __DIR__.'/partials/hub_tabs.php'; ?>
 *     <section class="hub-tabpane" data-hub-pane="op" role="tabpanel"> ... </section>
 *     <section class="hub-tabpane" data-hub-pane="fila" role="tabpanel" hidden> ... </section>
 *   </div>
 *
 * A primeira aba (índice 0) e a primeira seção sem [hidden] começam ativas.
 * A troca é feita pelo handler global initHubTabs() em public/assets/minimalist-ui.js
 * (delegação por [data-hub-tabgroup], sem depender de data-bs-toggle="tab").
 */
$hubTabs = $hubTabs ?? [];
?>
<div class="nav nav-pills integration-tabs" data-hub-tabs role="tablist"<?= !empty($hubTabsLabel) ? ' aria-label="'.e($hubTabsLabel).'"' : '' ?>>
  <?php foreach($hubTabs as $i=>$t): ?>
    <button class="nav-link<?= $i===0 ? ' active' : '' ?>" type="button" role="tab" data-hub-tab="<?=e($t['id'])?>" aria-selected="<?= $i===0 ? 'true' : 'false' ?>"><?php if(!empty($t['icon'])): ?><i class="bi <?=e($t['icon'])?>"></i> <?php endif; ?><?=e($t['label'])?></button>
  <?php endforeach; ?>
</div>
