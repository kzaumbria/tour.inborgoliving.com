import {translator,translatePage} from './i18n.js';
import {storage} from './storage.js';
import {api,routeURL,registerOffline,prepareTour,prepared} from './offline.js';
import {Tour} from './tour.js';
import {GPS} from './gps.js';
import {Proximity,distance} from './proximity.js';
import {TourMap} from './map.js';
import {Narration} from './audio.js';
const $=selector=>document.querySelector(selector);
const slug=location.pathname.match(/^\/route\/([a-z0-9-]+)$/)?.[1];
const debugEnabled=new URLSearchParams(location.search).get('debug')==='1';
// New preference key avoids inheriting the previous automatically saved Italian default.
const savedLanguage=storage.get('languagePreference','en');
let lang=typeof savedLanguage==='string'?savedLanguage:'en',t=translator(lang),route,tour,engine,map,gps,debug,position,currentStop,latestResult,ready=false,languageRequest=0,active=false,lastDeparture='';
const audio=new Narration($('#narration'),key=>{$('#audio-status').textContent=t(key);});
function message(text){if($('#message').textContent!==text)$('#message').textContent=text;$('#message').hidden=!text;}
function save(){if(!tour.save())message(t('storagefail'));}
function node(tag,text,className){const el=document.createElement(tag);if(text!==undefined)el.textContent=text;if(className)el.className=className;return el;}
async function network(){ready=route?await prepared(route.slug,lang):false;$('#network').textContent=navigator.onLine?t('online'):ready?t('offlineready'):t('offline');}
window.addEventListener('online',network);window.addEventListener('offline',network);
registerOffline();
function routeCards(routes){const root=$('#route-cards');root.replaceChildren();if(!routes.length)root.append(node('p',t('emptyroutes')));
 for(const r of routes){const card=node('article',undefined,'card route-card');const image=node('img');image.src=r.featured_image||'/assets/images/village.svg';image.alt='';image.loading='lazy';card.append(image);const body=node('div',undefined,'card-body');body.append(node('h3',r.title),node('p',r.short_description||''));const meta=node('div',undefined,'route-meta');meta.append(node('span',`${r.estimated_duration_minutes} ${t('minutes')}`),node('span',`${(+r.estimated_distance_m/1000).toLocaleString(lang)} km`),node('span',`${r.stop_count} ${t('stopcount')}`));if(r.difficulty)meta.append(node('span',r.difficulty));body.append(meta);const start=node('a',t('starttour')+' →','button');start.href='/route/'+r.slug;body.append(start);card.append(body);root.append(card);}
}
function stopState(s){return tour.state.visited.includes(String(s.id))?'visited':tour.state.triggered.includes(String(s.id))?'triggered':engine.nearby.has(String(s.id))?'nearby':'unvisited';}
function renderTour(){
 $('#route-title').textContent=route.title;document.title=route.title+' · InBorgo';$('#route-description').textContent=route.short_description||'';$('#starting-instructions').textContent=route.starting_instructions||'';$('#about-content').textContent=route.full_description||'';
 $('#demo-notice').hidden=!route.stops.some(s=>+s.location.is_demo);$('#autoplay').checked=tour.state.autoplay;
 $('#onboarding').hidden=active;$('#start').textContent=t(tour.state.started?'resumetour':'start');
 const list=$('#stop-list');list.replaceChildren();for(const s of route.stops){const li=node('li'),button=node('button',`${s.location.title} · ${t(stopState(s))}${+s.is_optional?' · '+t('optional'):''}`,stopState(s));button.onclick=()=>openStop(s,false);li.append(button);list.append(li);}
 if(!route.stops.length)list.append(node('li',t('emptystops')));
 const next=tour.next();$('#next-title').textContent=next?.location.title||t('finished');$('#next-distance').textContent=next?(position?`${Math.round(distance(position,next.location))} ${t('away')}`:t('continue')):'';$('#open-next').hidden=!next;
 $('#walking-instruction').textContent=lastDeparture||(next?.arrival_instruction||'');map?.render(route.stops,tour.state.visited,next);debug?.update(position,engine,latestResult);
}
function availability(l){const days=(l.closed_days||'').split(',');const dayName=new Intl.DateTimeFormat('en-US',{timeZone:'Europe/Rome',weekday:'short'}).format(new Date());const day=['Mon','Tue','Wed','Thu','Fri','Sat','Sun'].indexOf(dayName)+1;const notices=[];if(days.includes(String(day)))notices.push(t('closed'));if(+l.guided_only)notices.push(t('guided'));if(l.opening_hours)notices.push(l.opening_hours);if(l.opening_information)notices.push(l.opening_information);return notices.join(' · ');}
function openStop(stop,arrival=false){
 audio.stop();currentStop=stop;const l=stop.location;$('#arrival-label').textContent=t(arrival?'arrived':'browse');$('#arrival-title').textContent=l.title;$('#arrival-short').textContent=l.short_description||'';
 const images=$('#arrival-images');images.replaceChildren();const photos=(l.media||[]).filter(m=>m.type==='image');if(l.featured_image&&!photos.some(m=>m.file_path===l.featured_image))photos.unshift({file_path:l.featured_image,caption:l.title});for(const photo of photos){const img=node('img',undefined,'arrival-photo');img.src=photo.file_path;img.alt=photo.caption||l.title;img.loading='lazy';images.append(img);}
 const notice=availability(l);$('#availability').textContent=notice;$('#availability').hidden=!notice;$('#arrival-instruction').textContent=[stop.arrival_instruction,stop.route_specific_notes].filter(Boolean).join('\n');$('#departure-instruction').textContent=stop.departure_instruction||'';
 $('#arrival-story').textContent=l.narration_text||'';$('#arrival-full').textContent=l.full_description===l.narration_text?'':l.full_description||'';$('#arrival-access').textContent=l.accessibility_information||'';$('#content-language').textContent=l.content_language&&l.content_language!==lang?`${t('fallback')} ${l.content_language}`:'';
 if(!$('#arrival').open)$('#arrival').showModal();
 if(arrival&&tour.state.autoplay)audio.play(l,lang);
}
function arrive(stop){if($('#arrival').open)return;if(tour.state.triggered.includes(String(stop.id)))return;engine.mark(stop.id);if(!tour.arrive(stop))message(t('storagefail'));renderTour();openStop(stop,true);}
function positionUpdate(p){if(gps.simulated&&!active){active=true;tour.state.started=true;save();}position=p;map.position(p);latestResult=engine.update(p,Date.now(),$('#arrival').open||!active);if(latestResult.arrival){const stop=latestResult.arrival;if(!tour.arrive(stop))message(t('storagefail'));renderTour();openStop(stop,true);}renderTour();}
function setupTour(){
 tour=new Tour(route,debugEnabled);engine=new Proximity(route.stops,[...tour.state.triggered,...tour.state.visited]);map=new TourMap($('#map'),s=>openStop(s,false));gps=new GPS(positionUpdate,status=>{message(t('gps'+status));if(!['ready','simulated'].includes(status))engine.nearby.clear();debug?.update(gps?.latestReading||position,engine,latestResult);});
 $('#tour').hidden=false;renderTour();map.resize();if(tour.state.started)message(t('locationneeded'));
 $('#start').onclick=()=>{audio.unlock();active=true;tour.state.started=true;tour.state.autoplay=$('#autoplay').checked;save();debug?.stop();gps.start();renderTour();};
 $('#manual').onclick=()=>{active=true;tour.state.started=true;tour.state.autoplay=$('#autoplay').checked;save();renderTour();showTab('stops-view');};
 $('#autoplay').onchange=()=>{tour.state.autoplay=$('#autoplay').checked;save();};
 $('#open-next').onclick=()=>{const next=tour.next();if(next)openStop(next,false);};
 $('#reset').onclick=()=>{if(!confirm(t('resetconfirm')))return;audio.stop();$('#arrival').close();debug?.stop();gps.stop();engine.reset();tour.reset();position=null;map.clearPosition();lastDeparture='';active=false;message('');renderTour();};
 $('#play').onclick=()=>{if(currentStop)audio.play(currentStop.location,lang);};$('#pause').onclick=()=>audio.pause();$('#resume').onclick=()=>audio.resume();$('#stop-audio').onclick=()=>audio.stop();
 $('#close-arrival').onclick=()=>$('#arrival').close();$('#arrival').addEventListener('close',()=>audio.stop());
 $('#complete').onclick=()=>{if(currentStop){tour.visit(currentStop);engine.mark(currentStop.id);lastDeparture=currentStop.departure_instruction||'';save();}$('#arrival').close();renderTour();};
 $('#prepare').onclick=async()=>{const button=$('#prepare');button.disabled=true;$('#language').disabled=true;try{await prepareTour(route,lang,(n,total)=>{$('#offline-progress').textContent=`${t('preparing')}… ${n} / ${total} ${t('downloaded')}`;});$('#offline-progress').textContent=t('offlineprepared');await network();}catch(error){$('#offline-progress').textContent=t(error.message==='unsupported'?'unsupportedoffline':'offlinefail');}finally{button.disabled=false;$('#language').disabled=false;}};
 if(debugEnabled)import('./debug.js').then(({DebugPanel})=>{debug=new DebugPanel($('#debug'),route,gps,stop=>{active=true;tour.state.started=true;arrive(stop);},()=>tour.state);debug.update(position,engine,latestResult);});
}
function showTab(id){for(const name of ['map-view','stops-view','about-view'])$('#'+name).hidden=name!==id;document.querySelectorAll('[data-tab]').forEach(b=>{const selected=b.dataset.tab===id;b.classList.toggle('selected',selected);b.setAttribute('aria-pressed',String(selected));});if(id==='map-view')map?.resize();}
document.querySelectorAll('[data-tab]').forEach(b=>b.onclick=()=>showTab(b.dataset.tab));
async function changeLanguage(code){const request=++languageRequest;$('#language').disabled=true;audio.stop();try{
 const data=await api(slug?routeURL(slug,code):`/api/routes?lang=${encodeURIComponent(code)}`);if(request!==languageRequest)return;lang=code;storage.set('languagePreference',lang);t=translator(lang);translatePage(t,lang);
 if(slug){const openId=currentStop?.id,wasOpen=$('#arrival').open;route=data;tour.route=route;engine.stops=route.stops;lastDeparture='';renderTour();if(wasOpen){const next=route.stops.find(s=>s.id===openId);if(next)openStop(next,false);else $('#arrival').close();}if(debug){debug.route=route;debug.update(position,engine,latestResult);}}
 else routeCards(data);message('');await network();if(slug)$('#offline-progress').textContent=ready?t('offlineprepared'):'';
 }catch{message(t('loadfail'));$('#language').value=lang;}finally{$('#language').disabled=false;}}
async function boot(){
 try{const languages=await api('/api/languages');if(!languages.some(l=>l.code===lang))lang=languages.find(l=>+l.is_default)?.code||languages[0]?.code||'en';t=translator(lang);storage.set('languagePreference',lang);for(const l of languages){const option=node('option',l.native_name);option.value=l.code;option.selected=l.code===lang;$('#language').append(option);}translatePage(t,lang);$('#language').onchange=()=>changeLanguage($('#language').value);
 if(slug){route=await api(routeURL(slug,lang));storage.set('selectedRoute',slug);setupTour();}else{$('#home').hidden=false;routeCards(await api(`/api/routes?lang=${encodeURIComponent(lang)}`));}await network();
 }catch{message(t('loadfail'));}
}
window.addEventListener('pagehide',()=>{audio.stop();gps?.stop();debug?.stop();});
window.addEventListener('pageshow',event=>{if(event.persisted&&tour){active=false;renderTour();message(t('locationneeded'));}});
boot();
