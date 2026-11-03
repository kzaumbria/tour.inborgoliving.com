/* OSM tiles are deliberately never intercepted or cached here. */
const SHELL='inborgo-shell-design-20261101',CONTENT='inborgo-content-v1';
const FILES=['/','/assets/css/style.css','/assets/js/app.js','/assets/js/i18n.js','/assets/js/gps.js','/assets/js/proximity.js','/assets/js/map.js','/assets/js/audio.js','/assets/js/tour.js','/assets/js/storage.js','/assets/js/offline.js','/assets/js/debug.js','/assets/vendor/leaflet.js','/assets/vendor/leaflet.css','/assets/images/village.svg','/assets/images/inborgo-logo.png','/assets/images/acquasparta-hero.webp','/assets/images/icon.svg','/assets/images/icon-192.png','/assets/images/icon-512.png','/manifest.json'];
self.addEventListener('install',event=>event.waitUntil(caches.open(SHELL).then(cache=>cache.addAll(FILES))));
self.addEventListener('activate',event=>event.waitUntil((async()=>{for(const key of await caches.keys())if(key.startsWith('inborgo-shell-')&&key!==SHELL)await caches.delete(key);await self.clients.claim();})()));
async function partial(request,response){const range=request.headers.get('Range');if(!range)return response;const buffer=await response.arrayBuffer();const match=/^bytes=(\d*)-(\d*)$/.exec(range);if(!match)return new Response(null,{status:416,headers:{'Content-Range':`bytes */${buffer.byteLength}`}});let start=match[1]?Number(match[1]):Math.max(0,buffer.byteLength-Number(match[2])),end=match[1]?(match[2]?Math.min(Number(match[2]),buffer.byteLength-1):buffer.byteLength-1):buffer.byteLength-1;if(start>end||start>=buffer.byteLength)return new Response(null,{status:416,headers:{'Content-Range':`bytes */${buffer.byteLength}`}});return new Response(buffer.slice(start,end+1),{status:206,headers:{'Content-Type':response.headers.get('Content-Type')||'audio/mpeg','Content-Range':`bytes ${start}-${end}/${buffer.byteLength}`,'Content-Length':String(end-start+1),'Accept-Ranges':'bytes'}});}
self.addEventListener('fetch',event=>{
 const req=event.request,url=new URL(req.url);
 if(req.method!=='GET'||url.origin!==self.location.origin||url.pathname.startsWith('/admin')||(!url.pathname.startsWith('/api/')&&!url.pathname.startsWith('/assets/')&&!url.pathname.startsWith('/uploads/')&&!url.pathname.startsWith('/route/')&&url.pathname!=='/'&&url.pathname!=='/manifest.json'))return;
 event.respondWith((async()=>{
  const content=await caches.open(CONTENT),shell=await caches.open(SHELL);
  if(url.pathname.startsWith('/assets/')||url.pathname==='/manifest.json'){const cached=await shell.match(req);return cached||fetch(req);}
  if(url.pathname.startsWith('/uploads/')){const cached=await content.match(url.href);return cached?partial(req,cached):fetch(req);}
  try{
   const response=await fetch(req);if(response.ok&&url.pathname.startsWith('/api/')){await content.put(req,response.clone()).catch(()=>{});}return response;
  }catch(error){const cached=await content.match(req,{ignoreSearch:req.mode==='navigate'});if(cached)return cached;if(req.mode==='navigate')return await shell.match('/');return new Response(JSON.stringify({error:'Offline content unavailable'}),{status:503,headers:{'Content-Type':'application/json'}});}
 })());
});
