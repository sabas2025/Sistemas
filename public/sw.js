const HUB_CACHE = 'hub-integracao-v104-49-3';
const HUB_CACHE_PREFIX = 'hub-integracao-';
const HUB_VERSION = '104.49.3';
const CORE_REQUIRED = [
  './offline.html','./assets/offline.min.css?v=104.49.3','./assets/offline.min.js?v=104.49.3',
  './assets/app.min.css?v=104.49.3','./assets/responsive-enterprise.min.css?v=104.49.3',
  './assets/scroll-enterprise.min.css?v=104.49.3','./assets/minimalist-enterprise.min.css?v=104.49.3',
  './assets/integration-center.min.css?v=104.49.3',
  './assets/pwa.min.js?v=104.49.3','./assets/img/hub-integracao-logo.svg','./assets/img/favicon-32.png'
];
const CORE_OPTIONAL = [
  './manifest.webmanifest','./assets/vendor/bootstrap/bootstrap.min.css?v=5','./assets/vendor/bootstrap/bootstrap.bundle.min.js?v=5',
  './assets/vendor/bootstrap-icons/bootstrap-icons.css?v=1','./assets/login.min.css?v=104.49.3','./assets/bootstrap-login-lite.min.css?v=104.49.3',
  './assets/notifications.min.js?v=104.49.3','./assets/futuristic-ui.min.js?v=104.49.3','./assets/minimalist-ui.min.js?v=104.49.3',
  './assets/img/icon-180.png','./assets/img/icon-192.png','./assets/img/icon-512.png',
  './assets/img/icon-maskable-192.png','./assets/img/icon-maskable-512.png',
  './assets/img/shortcut-operacoes-192.png','./assets/img/shortcut-homologacao-192.png','./assets/img/shortcut-divergencias-192.png'
];
const EXPECTED_ASSETS = [...CORE_REQUIRED, ...CORE_OPTIONAL];
const isHubCache = key => key.startsWith(HUB_CACHE_PREFIX);
const isCacheableAsset = url => url.pathname.includes('/assets/') || url.pathname.endsWith('/manifest.webmanifest') || url.pathname.endsWith('/offline.html');
function expectedContent(url) {
  const p = new URL(url, self.location.href).pathname.toLowerCase();
  if (p.endsWith('.css')) return ['text/css'];
  if (p.endsWith('.js')) return ['javascript','ecmascript'];
  if (p.endsWith('.json') || p.endsWith('.webmanifest')) return ['json','manifest'];
  if (p.endsWith('.svg')) return ['image/svg+xml'];
  if (/\.(png|jpg|jpeg|webp|gif|ico)$/.test(p)) return ['image/'];
  if (p.endsWith('.html')) return ['text/html'];
  return [];
}
function validAssetResponse(requestUrl,response) {
  if (!response || !response.ok || response.type !== 'basic') return false;
  const expected=expectedContent(requestUrl); if(!expected.length) return true;
  const actual=(response.headers.get('content-type')||'').toLowerCase();
  return expected.some(type=>actual.includes(type));
}
async function addAsset(cache,asset,required=false) {
  const response=await fetch(asset,{cache:'reload',credentials:'same-origin'});
  if(!validAssetResponse(asset,response)) throw new Error('Resposta inválida para '+asset);
  await cache.put(asset,response.clone()); return true;
}
async function populateCache() {
  const target=await caches.open(HUB_CACHE);
  for(const asset of CORE_REQUIRED) await addAsset(target,asset,true);
  const optional=await Promise.allSettled(CORE_OPTIONAL.map(asset=>addAsset(target,asset,false)));
  optional.forEach((result,index)=>{if(result.status==='rejected') console.warn('[PWA] Asset opcional não armazenado:',CORE_OPTIONAL[index],result.reason);});
  return target;
}
async function cleanupOldCaches() {
  const keys=await caches.keys();
  await Promise.all(keys.filter(key=>isHubCache(key)&&key!==HUB_CACHE).map(key=>caches.delete(key)));
}

function safeMessage(error){return String(error&&error.message?error.message:error||'erro').slice(0,240);}
async function notifyClients(type,payload={}){const clients=await self.clients.matchAll({type:'window',includeUncontrolled:true});clients.forEach(client=>client.postMessage({type,...payload,version:HUB_VERSION}));}

async function cacheDiagnostics() {
  const cache=await caches.open(HUB_CACHE); const keys=await cache.keys();
  const urls=keys.map(r=>r.url); const missing=EXPECTED_ASSETS.filter(asset=>!urls.some(url=>url.endsWith(asset.replace(/^\.\//,''))||url.includes(asset.replace(/^\.\//,''))));
  return {type:'HUB_PWA_DIAGNOSTICS',version:HUB_VERSION,cache:HUB_CACHE,expected:EXPECTED_ASSETS.length,found:keys.length,missing};
}
self.addEventListener('install',event=>{event.waitUntil(populateCache().catch(async error=>{await notifyClients('HUB_PWA_TELEMETRY',{event:'sw_install_failed',message:safeMessage(error)});throw error;}));});
self.addEventListener('activate',event=>{event.waitUntil(populateCache().then(cleanupOldCaches).then(()=>self.clients.claim()).catch(async error=>{await notifyClients('HUB_PWA_TELEMETRY',{event:'sw_activate_failed',message:safeMessage(error)});throw error;}));});
self.addEventListener('message',event=>{
  const data=event.data||{};
  if(data.type==='HUB_PWA_VERSION'&&event.source) event.source.postMessage({type:'HUB_PWA_VERSION',version:HUB_VERSION,cache:HUB_CACHE});
  if(data.type==='HUB_PWA_DIAGNOSTICS') event.waitUntil(cacheDiagnostics().then(info=>event.source&&event.source.postMessage(info)));
  if(data.type==='HUB_PWA_SKIP_WAITING') self.skipWaiting();
  if(data.type==='HUB_PWA_REFRESH') {
    event.waitUntil(populateCache().then(cleanupOldCaches).then(cacheDiagnostics).then(info=>notifyClients('HUB_PWA_REFRESHED',info)).catch(error=>notifyClients('HUB_PWA_TELEMETRY',{event:'sw_refresh_failed',message:safeMessage(error)})));
  }
});
self.addEventListener('fetch',event=>{
  const request=event.request; if(request.method!=='GET') return;
  const url=new URL(request.url); if(url.origin!==self.location.origin) return;
  if(request.mode==='navigate'||url.pathname.endsWith('.php')||url.searchParams.has('page')||url.pathname.includes('/api/')) {
    event.respondWith(fetch(request,{cache:'no-store'}).catch(async()=>await caches.match('./offline.html')||new Response('Offline',{status:503,headers:{'Content-Type':'text/plain; charset=utf-8'}}))); return;
  }
  if(isCacheableAsset(url)) {
    event.respondWith((async()=>{
      const cached=await caches.match(request);
      const network=fetch(request,{cache:'no-cache'}).then(async response=>{
        if(validAssetResponse(request.url,response)) {const c=await caches.open(HUB_CACHE); await c.put(request,response.clone());}
        return response;
      }).catch(()=>null);
      return cached||await network||new Response('',{status:504,statusText:'Offline'});
    })());
  }
});
