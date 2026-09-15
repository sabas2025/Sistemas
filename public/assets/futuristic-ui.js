(function(){
  'use strict';
  document.addEventListener('keydown', function(ev){
    if((ev.ctrlKey || ev.metaKey) && ev.key.toLowerCase()==='k'){
      ev.preventDefault();
      const q = prompt('Buscar no Hub: pedido, estoque, Tiny, VSM, banco, logs...');
      if(!q) return;
      const query = encodeURIComponent(q.trim());
      window.location.href = 'index.php?page=central-tecnica&busca=' + query;
    }
  });
  document.querySelectorAll('.panel,.card-soft,.flow-card').forEach(function(card){ card.classList.add('enterprise-glass-ready'); });
})();

/* V104.37 — normalização responsiva progressiva */
(function(){
  'use strict';
  function prepareTables(){
    document.querySelectorAll('.table').forEach(function(table){
      if (table.classList.contains('keep-scroll') || table.closest('[data-table-scroll-only]')) return;
      table.classList.add('responsive-cards');
      const labels = Array.from(table.querySelectorAll('thead th')).map(function(th){
        return (th.textContent || '').trim();
      });
      table.querySelectorAll('tbody tr').forEach(function(row){
        Array.from(row.children).forEach(function(cell,index){
          if (!cell.hasAttribute('data-label') && labels[index]) cell.setAttribute('data-label',labels[index]);
        });
      });
    });
  }
  function normalizeActions(){
    document.querySelectorAll('.panel-header, .card-header').forEach(function(header){
      const children = Array.from(header.children);
      if (children.length > 1) header.classList.add('has-actions');
    });
  }
  function closeMobileLayers(){
    const sidebar=document.getElementById('sidebar');
    const backdrop=document.getElementById('sidebarBackdrop');
    const toggle=document.getElementById('sidebarToggle');
    if(sidebar) sidebar.classList.remove('show');
    if(backdrop) backdrop.classList.remove('show');
    document.body.classList.remove('sidebar-open');
    if(toggle) toggle.setAttribute('aria-expanded','false');
  }
  document.addEventListener('DOMContentLoaded',function(){
    prepareTables();
    normalizeActions();
    window.addEventListener('orientationchange',function(){ setTimeout(closeMobileLayers,120); });
  });
})();


/* V104.39 — recuperação de rolagem após navegação, histórico e rotação */
(function(){
  'use strict';
  function sidebarIsOpen(){
    var sidebar=document.getElementById('sidebar');
    return !!(sidebar && sidebar.classList.contains('show') && window.innerWidth < 992);
  }
  function restorePageScroll(force){
    if(force || !sidebarIsOpen()){
      document.body.classList.remove('sidebar-open');
      document.documentElement.style.removeProperty('overflow');
      document.body.style.removeProperty('overflow');
      document.body.style.removeProperty('position');
      document.body.style.removeProperty('top');
      document.body.style.removeProperty('width');
    }
  }
  document.addEventListener('DOMContentLoaded',function(){ restorePageScroll(true); });
  window.addEventListener('pageshow',function(){ restorePageScroll(true); });
  window.addEventListener('pagehide',function(){ restorePageScroll(true); });
  window.addEventListener('resize',function(){ if(window.innerWidth >= 992) restorePageScroll(true); });
  window.addEventListener('orientationchange',function(){ setTimeout(function(){ restorePageScroll(true); },180); });
  document.addEventListener('click',function(ev){
    if(ev.target.closest('#sidebarBackdrop,#sidebarClose,.sidebar a')) setTimeout(function(){ restorePageScroll(false); },40);
  });
})();
