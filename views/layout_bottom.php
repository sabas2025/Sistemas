    <footer class="sabas-footer mt-4">
      <div class="sabas-glow"></div>
      <div>
        <strong>Desenvolvido por Sabas</strong>
        <span>Hub de Integração Enterprise</span>
      </div>
    </footer>
    </section>
  </main>
</div>
<script src="assets/vendor/bootstrap/bootstrap.bundle.min.js"></script>
<script src="assets/notifications.min.js?v=104.49.3"></script>
<script src="assets/futuristic-ui.min.js?v=104.49.3" defer></script>
<script src="assets/minimalist-ui.min.js?v=104.49.3" defer></script>

<script nonce="<?=App::cspNonce()?>">
(function(){
  const copyText = async (text) => {
    try { if(navigator.clipboard) await navigator.clipboard.writeText(text || ''); }
    catch(e) { console.warn('Clipboard indisponível', e); }
  };
  document.addEventListener('click', function(ev){
    const btn = ev.target.closest('[data-confirm]');
    if(btn && !btn.closest('form')){
      const msg = btn.getAttribute('data-confirm') || 'Confirmar ação?';
      if(!confirm(msg)){ ev.preventDefault(); ev.stopPropagation(); }
    }
    const printBtn = ev.target.closest('.js-print');
    if(printBtn){ ev.preventDefault(); window.print(); }
    const copySource = ev.target.closest('[data-copy-target]');
    if(copySource){
      ev.preventDefault();
      const el = document.querySelector(copySource.getAttribute('data-copy-target'));
      copyText(el ? (el.innerText || el.value || '') : '');
    }
    const copyDirect = ev.target.closest('[data-copy-text]');
    if(copyDirect){ ev.preventDefault(); copyText(copyDirect.getAttribute('data-copy-text') || ''); }
  });
  document.addEventListener('submit', function(ev){
    const form = ev.target.closest('form');
    if(!form) return;
    const submitter = ev.submitter && ev.submitter.closest('[data-confirm]');
    const source = submitter || (form.matches('[data-confirm]') ? form : null);
    if(!source) return;
    const msg = source.getAttribute('data-confirm') || 'Confirmar ação?';
    if(!confirm(msg)){ ev.preventDefault(); ev.stopPropagation(); }
  });
  document.addEventListener('focusin', function(ev){
    if(ev.target.matches('.js-select-on-focus')) ev.target.select();
  });
  document.addEventListener('click', function(ev){
    const btn = ev.target.closest('[data-topbar-more]');
    const menu = document.querySelector('[data-topbar-menu]');
    if(btn && menu){ ev.preventDefault(); const open = menu.classList.toggle('show'); btn.setAttribute('aria-expanded', open ? 'true' : 'false'); return; }
    if(menu && !ev.target.closest('.topbar-more')) menu.classList.remove('show');
  });
  document.querySelectorAll('.table').forEach(function(table){
    const labels = Array.from(table.querySelectorAll('thead th')).map(function(th){ return (th.innerText || '').trim(); });
    table.querySelectorAll('tbody tr').forEach(function(tr){
      Array.from(tr.children).forEach(function(td, i){ if(labels[i] && !td.hasAttribute('data-label')) td.setAttribute('data-label', labels[i]); });
    });
  });
})();
</script>
</body>
</html>
