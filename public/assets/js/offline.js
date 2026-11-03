export const CONTENT_CACHE='inborgo-content-v1';
export async function registerOffline(){if(!('serviceWorker'in navigator)||!window.isSecureContext)return null;try{return await navigator.serviceWorker.register('/sw.js');}catch{return null;}}
export async function api(path){const response=await fetch(path,{headers:{Accept:'application/json'}});if(!response.ok)throw new Error('Content request failed');return response.json();}
export function routeURL(slug,lang){return `/api/routes/${encodeURIComponent(slug)}?lang=${encodeURIComponent(lang)}`;}
const markerURL=(slug,lang)=>`/offline-ready/${encodeURIComponent(slug)}/${encodeURIComponent(lang)}`;
export async function prepared(slug,lang){if(!('caches'in window))return false;try{const cache=await caches.open(CONTENT_CACHE),r=await cache.match(markerURL(slug,lang));if(!r)return false;const {urls}=await r.json();for(const url of urls)if(!await cache.match(url))return false;const latest=await cache.match(routeURL(slug,lang));if(!latest)return false;const route=await latest.json();const media=[route.featured_image];for(const stop of route.stops||[]){media.push(stop.location.featured_image);for(const item of stop.location.media||[])media.push(item.file_path);}for(const path of media.filter(Boolean))if(!await cache.match(path))return false;return true;}catch{return false;}}
export async function prepareTour(route,lang,progress){
 if(!('caches'in window)||!('serviceWorker'in navigator)||!window.isSecureContext)throw new Error('unsupported');
 const registration=await registerOffline();if(!registration)throw new Error('unsupported');
 await Promise.race([navigator.serviceWorker.ready,new Promise((_,reject)=>setTimeout(()=>reject(new Error('Service worker timeout')),15000))]);
 const cache=await caches.open(CONTENT_CACHE);await cache.delete(markerURL(route.slug,lang));
 const urls=new Set(['/',`/route/${route.slug}`,'/api/languages',`/api/routes?lang=${encodeURIComponent(lang)}`,routeURL(route.slug,lang)]);
 if(route.featured_image)urls.add(route.featured_image);
 for(const stop of route.stops){const l=stop.location;if(l.featured_image)urls.add(l.featured_image);for(const m of l.media||[])urls.add(m.file_path);}
 let done=0;
 for(const url of urls){const target=new URL(url,location.origin);if(target.origin!==location.origin)throw new Error('External media is not supported');const response=await fetch(target,{cache:'reload'});if(!response.ok)throw new Error('Download failed');await cache.put(target,response);progress(++done,urls.size);}
 await cache.put(markerURL(route.slug,lang),new Response(JSON.stringify({urls:[...urls],preparedAt:new Date().toISOString()}),{headers:{'Content-Type':'application/json'}}));
 if(!await prepared(route.slug,lang)){await cache.delete(markerURL(route.slug,lang));throw new Error('Content changed during preparation; retry');}
 if(navigator.storage?.persist)await navigator.storage.persist().catch(()=>false);
}
