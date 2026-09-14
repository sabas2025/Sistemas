(function(){
  let ultimoId = parseInt(localStorage.getItem('hub_notif_ultimo_id') || '0', 10);
  const badge = document.getElementById('notifBadge');
  const list = document.getElementById('notifList');
  const toggle = document.getElementById('notifToggle');
  const dropdown = document.getElementById('notifDropdown');
  let firstLoad = true;

  function icon(sev){ return sev === 'erro' ? '⛔' : sev === 'alerta' ? '⚠️' : sev === 'sucesso' ? '✅' : 'ℹ️'; }
  function esc(s){ return String(s||'').replace(/[&<>'"]/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;',"'":'&#39;','"':'&quot;'}[c])); }
  function beep(){
    try{
      const ctx = new (window.AudioContext||window.webkitAudioContext)();
      const o = ctx.createOscillator(); const g = ctx.createGain();
      o.type='sine'; o.frequency.value=880; g.gain.value=0.06; o.connect(g); g.connect(ctx.destination); o.start();
      setTimeout(()=>{o.stop(); ctx.close();},160);
    }catch(e){}
  }
  function browserNotify(n){
    if(!('Notification' in window)) return;
    if(Notification.permission === 'granted') new Notification(n.titulo, { body:n.mensagem, tag:'hub-notif-'+n.id });
    else if(Notification.permission !== 'denied') Notification.requestPermission();
  }
  function render(items){
    if(!list) return;
    if(!items || !items.length){ list.innerHTML='<div class="notif-empty">Nenhuma notificação nova.</div>'; return; }
    list.innerHTML = items.map(n => `<a class="notif-item sev-${esc(n.severidade)}" href="${esc(n.link || 'index.php?page=notificacoes')}"><span>${icon(n.severidade)}</span><div><b>${esc(n.titulo)}</b><small>${esc(n.mensagem)}</small><em>${esc(n.criada_em || '')}</em></div></a>`).join('');
  }
  async function poll(){
    if(!window.HUB_NOTIF_ENABLED) return;
    try{
      const r = await fetch('index.php?page=api/notificacoes/recentes&ultimo_id='+ultimoId, {cache:'no-store'});
      if(!r.ok) return;
      const data = await r.json();
      if(badge){
        const total = parseInt(data.nao_lidas || 0, 10);
        badge.textContent = total > 99 ? '99+' : total;
        badge.classList.toggle('d-none', total <= 0);
        if(total > 0) badge.classList.add('pulse');
      }
      const items = data.notificacoes || [];
      if(items.length){
        render(items);
        const maxId = Math.max(...items.map(n => parseInt(n.id,10)));
        if(maxId > ultimoId){
          if(!firstLoad){ beep(); browserNotify(items[0]); }
          ultimoId = maxId; localStorage.setItem('hub_notif_ultimo_id', String(ultimoId));
        }
      } else if(firstLoad){ render([]); }
      firstLoad = false;
    }catch(e){}
  }
  if(toggle && dropdown){
    toggle.addEventListener('click', ()=> dropdown.classList.toggle('d-none'));
    document.addEventListener('click', e => { if(!dropdown.contains(e.target) && !toggle.contains(e.target)) dropdown.classList.add('d-none'); });
  }
  poll(); setInterval(poll, 15000);
})();



/* Responsivo V27: controle do menu lateral em tablet/celular */
(function(){
  const sidebar = document.getElementById('sidebar');
  const toggle = document.getElementById('sidebarToggle');
  const closeBtn = document.getElementById('sidebarClose');
  const backdrop = document.getElementById('sidebarBackdrop');
  if(!sidebar || !toggle || !backdrop) return;
  function openMenu(){
    sidebar.classList.add('show');
    backdrop.classList.add('show');
    document.body.classList.add('sidebar-open');
    toggle.setAttribute('aria-expanded','true');
  }
  function closeMenu(){
    sidebar.classList.remove('show');
    backdrop.classList.remove('show');
    document.body.classList.remove('sidebar-open');
    toggle.setAttribute('aria-expanded','false');
  }
  toggle.addEventListener('click', function(){ sidebar.classList.contains('show') ? closeMenu() : openMenu(); });
  if(closeBtn) closeBtn.addEventListener('click', closeMenu);
  backdrop.addEventListener('click', closeMenu);
  sidebar.querySelectorAll('a').forEach(function(a){ a.addEventListener('click', function(){ if(window.innerWidth < 992) closeMenu(); }); });
  document.addEventListener('keydown', function(e){ if(e.key === 'Escape') closeMenu(); });
  window.addEventListener('resize', function(){ if(window.innerWidth >= 992) closeMenu(); });
})();


/* V29: busca no menu lateral + garantia de rolagem até último item */
(function(){
  const input = document.getElementById('menuSearch');
  const menu = document.getElementById('mainMenu') || document.querySelector('.menu');
  if(!input || !menu) return;
  function normalize(v){ return (v || '').toString().toLowerCase().normalize('NFD').replace(/[\u0300-\u036f]/g,''); }
  input.addEventListener('input', function(){
    const q = normalize(input.value.trim());
    menu.querySelectorAll('a').forEach(function(a){
      const text = normalize(a.textContent);
      a.classList.toggle('menu-hidden-by-search', q !== '' && !text.includes(q));
    });
    // forms de ações rápidas continuam visíveis só quando não está pesquisando
    menu.querySelectorAll('form').forEach(function(f){ f.classList.toggle('menu-hidden-by-search', q !== ''); });
  });
})();
