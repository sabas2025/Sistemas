(function(){
  'use strict';
  function initMenuGroups(){
    const menu=document.getElementById('mainMenu'); if(!menu) return;
    menu.querySelectorAll('.menu-section').forEach(function(section){
      const label=section.getAttribute('data-menu-label')||section.textContent.trim();
      section.setAttribute('role','button'); section.setAttribute('tabindex','0');
      const links=Array.from(menu.querySelectorAll('[data-menu-group="'+CSS.escape(label)+'"]'));
      const active=links.some(function(a){return a.classList.contains('active');});
      const key='hubMenuGroup:'+label; let open=active;
      try{if(!active) open=localStorage.getItem(key)==='1';}catch(e){}
      function apply(){section.setAttribute('aria-expanded',open?'true':'false');links.forEach(function(a){a.hidden=!open;});}
      function toggle(){open=!open;try{localStorage.setItem(key,open?'1':'0');}catch(e){}apply();}
      section.addEventListener('click',toggle); section.addEventListener('keydown',function(e){if(e.key==='Enter'||e.key===' '){e.preventDefault();toggle();}}); apply();
    });
  }
  function classifyTables(){
    document.querySelectorAll('.table').forEach(function(table){
      if(table.classList.contains('keep-scroll')||table.classList.contains('responsive-cards')) return;
      const cols=table.querySelectorAll('thead th').length;
      const rows=table.querySelectorAll('tbody tr').length;
      const hasComplex=!!table.querySelector('textarea, canvas, iframe, table, [colspan="3"], [colspan="4"], [colspan="5"]');
      if(cols>0&&cols<=7&&!hasComplex){table.classList.add('responsive-cards');const wrap=table.closest('.table-responsive');if(wrap)wrap.classList.add('responsive-cards-wrap');}
      table.dataset.rowCount=String(rows);
    });
  }
  function improveIconButtons(){
    document.querySelectorAll('button,a').forEach(function(el){
      const text=(el.textContent||'').trim(); if(text) return;
      if(!el.getAttribute('aria-label')){
        const title=el.getAttribute('title');
        const icon=el.querySelector('i');
        const inferred=icon&&icon.className.includes('trash')?'Excluir':icon&&icon.className.includes('refresh')?'Atualizar':icon&&icon.className.includes('download')?'Baixar':icon&&icon.className.includes('eye')?'Visualizar':'Ação';
        el.setAttribute('aria-label',title||inferred);
      }
    });
  }
  function addBackToTop(){
    const btn=document.createElement('button');btn.type='button';btn.className='back-to-top';btn.setAttribute('aria-label','Voltar ao topo');btn.innerHTML='<i class="bi bi-arrow-up"></i>';document.body.appendChild(btn);
    function sync(){btn.classList.toggle('show',window.scrollY>600);}window.addEventListener('scroll',sync,{passive:true});btn.addEventListener('click',function(){window.scrollTo({top:0,behavior:'smooth'});});sync();
  }
  function markPageActions(){
    document.querySelectorAll('.d-flex.justify-content-between,.d-flex.gap-2,.toolbar,.actions').forEach(function(el){if(el.querySelector('.btn')&&el.children.length<=8)el.classList.add('page-actions');});
  }

  function initDensity(){
    const key='hubUiDensity'; let density='comfortable';
    try{density=localStorage.getItem(key)||density;}catch(e){}
    function apply(){document.body.dataset.hubDensity=density;document.querySelectorAll('[data-hub-density-toggle]').forEach(function(btn){btn.innerHTML='<i class="bi bi-layout-text-window"></i> Densidade '+(density==='compact'?'compacta':'confortável');btn.setAttribute('aria-pressed',density==='compact'?'true':'false');});}
    document.addEventListener('click',function(e){const btn=e.target.closest('[data-hub-density-toggle]');if(!btn)return;density=density==='compact'?'comfortable':'compact';try{localStorage.setItem(key,density);}catch(err){}apply();});apply();
  }
  function initFavorites(){
    const key='hubFavoritePages'; const page=document.body.dataset.hubPage||''; const title=(document.querySelector('.topbar-title h1')||{}).textContent||page;
    let list=[];try{list=JSON.parse(localStorage.getItem(key)||'[]');if(!Array.isArray(list))list=[];}catch(e){list=[];}
    function save(){try{localStorage.setItem(key,JSON.stringify(list.slice(0,8)));}catch(e){}}
    function render(){
      const menu=document.getElementById('mainMenu');if(!menu)return;let box=menu.querySelector('.hub-favorites');if(box)box.remove();
      if(list.length){box=document.createElement('div');box.className='hub-favorites';box.innerHTML='<div class="hub-favorites-title"><i class="bi bi-star-fill"></i> Favoritos</div>'+list.map(function(x){return '<a href="index.php?page='+encodeURIComponent(x.page)+'"><i class="bi bi-pin-angle"></i><span>'+escapeHtml(x.title)+'</span></a>';}).join('');menu.prepend(box);}
      document.querySelectorAll('[data-hub-favorite]').forEach(function(btn){const active=list.some(function(x){return x.page===page;});btn.innerHTML='<i class="bi bi-star'+(active?'-fill':'')+'"></i> '+(active?'Remover dos favoritos':'Fixar página');btn.setAttribute('aria-pressed',active?'true':'false');});
    }
    function escapeHtml(v){const d=document.createElement('div');d.textContent=String(v);return d.innerHTML;}
    document.addEventListener('click',function(e){const btn=e.target.closest('[data-hub-favorite]');if(!btn||!page)return;const i=list.findIndex(function(x){return x.page===page;});if(i>=0)list.splice(i,1);else list.unshift({page:page,title:title.trim()});save();render();});render();
  }
  function normalizeStatusLabels(){
    const map={falha_definitiva:'Falha definitiva',processando:'Em processamento',pendente:'Pendente',ignorado:'Ignorado',sucesso:'Concluído',erro:'Erro',recebido:'Recebido'};
    document.querySelectorAll('.badge-status,.status-badge,.badge').forEach(function(el){const raw=(el.textContent||'').trim();const key=raw.toLowerCase().replace(/^[●\s]+/,'');if(map[key])el.textContent=map[key];});
  }
  function enhanceTraceErrors(){
    document.querySelectorAll('.alert-danger,.error-box').forEach(function(el){if(el.dataset.enhancedError)return;const text=el.textContent||'';const m=text.match(/TRC-[A-Z0-9-]+/i);if(!m)return;el.dataset.enhancedError='1';el.classList.add('hub-inline-error');el.insertAdjacentHTML('afterbegin','<i class="bi bi-exclamation-octagon" aria-hidden="true"></i>');const copy=document.createElement('button');copy.type='button';copy.className='btn btn-sm btn-outline-danger ms-2';copy.textContent='Copiar Trace ID';copy.addEventListener('click',function(){navigator.clipboard&&navigator.clipboard.writeText(m[0]);});el.appendChild(copy);});
  }
  function protectHorizontalOverflow(){
    document.querySelectorAll('pre,.codebox,.table-responsive').forEach(function(el){el.setAttribute('tabindex','0');});
    const root=document.documentElement;if(root.scrollWidth>root.clientWidth+2){document.body.classList.add('has-horizontal-overflow');console.warn('HUB UI: overflow horizontal detectado',root.scrollWidth,root.clientWidth);}
  }

  function initTheme(){
    const key='hubUiTheme';
    let theme='light';
    try{theme=localStorage.getItem(key)==='dark'?'dark':'light';}catch(e){theme='light';}
    if(theme!=='dark')theme='light';
    function apply(){
      document.body.dataset.hubTheme=theme;
      document.documentElement.style.colorScheme=theme;
      const meta=document.getElementById('hubThemeColor'); if(meta)meta.setAttribute('content',theme==='dark'?'#0f172a':'#2563eb');
      document.querySelectorAll('[data-hub-theme-toggle]').forEach(function(btn){btn.innerHTML='<i class="bi bi-'+(theme==='dark'?'sun':'moon-stars')+'"></i> Tema '+(theme==='dark'?'claro':'escuro');btn.setAttribute('aria-pressed',theme==='dark'?'true':'false');});
    }
    document.addEventListener('click',function(e){const btn=e.target.closest('[data-hub-theme-toggle]');if(!btn)return;theme=theme==='dark'?'light':'dark';try{localStorage.setItem(key,theme);}catch(err){}apply();});
    apply();
  }
  function initConnectionStatus(){
    const el=document.querySelector('[data-hub-connection]'); if(!el)return;
    let timer=null;
    function sync(){
      const online=navigator.onLine;
      el.hidden=false;el.classList.toggle('is-offline',!online);el.classList.toggle('is-online',online);
      el.innerHTML='<i class="bi bi-'+(online?'wifi':'wifi-off')+'" aria-hidden="true"></i> '+(online?'Conexão restaurada':'Sem conexão. Dados operacionais podem estar desatualizados.');
      clearTimeout(timer); if(online)timer=setTimeout(function(){el.hidden=true;},2600);
    }
    window.addEventListener('online',sync);window.addEventListener('offline',sync);if(!navigator.onLine)sync();
  }
  function initCommandPalette(){
    const palette=document.querySelector('[data-hub-command-palette]');const backdrop=document.querySelector('[data-hub-command-backdrop]');const input=document.querySelector('[data-hub-command-input]');const results=document.querySelector('[data-hub-command-results]');if(!palette||!input||!results)return;
    const links=Array.from(document.querySelectorAll('#mainMenu a[href*="page="]')).map(function(a){return {title:(a.textContent||'').trim(),url:a.getAttribute('href'),icon:(a.querySelector('i')||{}).className||'bi bi-arrow-right',group:a.getAttribute('data-menu-group')||'Atalho'};}).filter(function(x,i,arr){return x.title&&arr.findIndex(function(y){return y.url===x.url;})===i;});
    let filtered=links.slice(0,12),selected=0,lastFocus=null;
    function normalize(v){return String(v||'').normalize('NFD').replace(/[\u0300-\u036f]/g,'').toLowerCase();}
    function render(){results.innerHTML=filtered.length?filtered.map(function(x,i){return '<a role="option" aria-selected="'+(i===selected?'true':'false')+'" class="hub-command-item '+(i===selected?'is-selected':'')+'" href="'+x.url+'"><i class="'+x.icon+'" aria-hidden="true"></i><span><b>'+escapeHtml(x.title)+'</b><small>'+escapeHtml(x.group)+'</small></span><i class="bi bi-arrow-return-left" aria-hidden="true"></i></a>';}).join(''):'<div class="hub-command-empty"><i class="bi bi-search"></i><strong>Nenhuma página encontrada</strong><small>Tente outro nome ou use o menu lateral.</small></div>';}
    function escapeHtml(v){const d=document.createElement('div');d.textContent=String(v);return d.innerHTML;}
    function filter(){const q=normalize(input.value);filtered=links.filter(function(x){return normalize(x.title+' '+x.group).includes(q);}).slice(0,18);selected=0;render();}
    function open(){lastFocus=document.activeElement;palette.hidden=false;if(backdrop)backdrop.hidden=false;document.body.classList.add('hub-command-open');input.value='';filter();setTimeout(function(){input.focus();},0);}
    function close(){palette.hidden=true;if(backdrop)backdrop.hidden=true;document.body.classList.remove('hub-command-open');if(lastFocus&&lastFocus.focus)lastFocus.focus();}
    document.addEventListener('click',function(e){if(e.target.closest('[data-hub-command-open]')){e.preventDefault();open();}if(e.target===backdrop)close();});
    document.addEventListener('keydown',function(e){if((e.ctrlKey||e.metaKey)&&e.key.toLowerCase()==='k'){e.preventDefault();palette.hidden?open():close();return;}if(palette.hidden)return;if(e.key==='Escape'){e.preventDefault();close();}else if(e.key==='ArrowDown'){e.preventDefault();selected=Math.min(selected+1,Math.max(0,filtered.length-1));render();results.querySelector('.is-selected')?.scrollIntoView({block:'nearest'});}else if(e.key==='ArrowUp'){e.preventDefault();selected=Math.max(0,selected-1);render();results.querySelector('.is-selected')?.scrollIntoView({block:'nearest'});}else if(e.key==='Enter'&&document.activeElement===input&&filtered[selected]){e.preventDefault();location.href=filtered[selected].url;}});
    input.addEventListener('input',filter);render();
  }
  function enhanceLongTables(){
    document.querySelectorAll('.table-responsive').forEach(function(wrap){const table=wrap.querySelector(':scope > table');if(!table)return;const rows=table.querySelectorAll('tbody tr').length;if(rows>=8){wrap.classList.add('hub-long-table');table.classList.add('hub-sticky-table');}if(rows>=25){const info=document.createElement('div');info.className='hub-table-summary';info.innerHTML='<span><i class="bi bi-list-ul"></i> '+rows+' registros nesta página</span><button type="button" class="btn btn-sm btn-outline-secondary" data-hub-table-top>Ir ao início da tabela</button>';wrap.insertAdjacentElement('beforebegin',info);info.querySelector('button').addEventListener('click',function(){wrap.scrollIntoView({behavior:'smooth',block:'start'});});}});
  }
  function initKeyboardShortcuts(){
    document.addEventListener('keydown',function(e){if(e.target.matches('input,textarea,select,[contenteditable="true"]'))return;if(e.key==='/'&&!e.ctrlKey&&!e.metaKey){const search=document.getElementById('menuSearch');if(search){e.preventDefault();search.focus();}}if(e.altKey&&e.key.toLowerCase()==='h'){e.preventDefault();location.href='index.php?page=dashboard';}});
  }

  function init(){initMenuGroups();classifyTables();improveIconButtons();markPageActions();addBackToTop();initDensity();initFavorites();initTheme();initConnectionStatus();initCommandPalette();initKeyboardShortcuts();enhanceLongTables();normalizeStatusLabels();enhanceTraceErrors();protectHorizontalOverflow();}
  if(document.readyState==='loading')document.addEventListener('DOMContentLoaded',init);else init();
})();
