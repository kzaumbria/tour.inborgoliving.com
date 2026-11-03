'use strict';
document.querySelectorAll('.danger-form').forEach(form=>form.addEventListener('submit',event=>{if(!confirm('Permanently delete this record?'))event.preventDefault();}));
const mapOptions={attribution:'© <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors',maxZoom:19};
function baseMap(id){const m=L.map(id).setView([42.693,12.543],16);L.tileLayer('https://tile.openstreetmap.org/{z}/{x}/{y}.png',mapOptions).addTo(m);return m;}
if(window.L&&document.querySelector('#coordinate-map')){
 const map=baseMap('coordinate-map'),lat=document.querySelector('[name="latitude"]'),lng=document.querySelector('[name="longitude"]');
 const valid=()=>lat.value!==''&&lng.value!==''&&Number.isFinite(+lat.value)&&Number.isFinite(+lng.value)&&Math.abs(+lat.value)<=90&&Math.abs(+lng.value)<=180;
 const marker=L.marker(valid()?[+lat.value,+lng.value]:map.getCenter(),{draggable:true}).addTo(map);
 function set(p){lat.value=p.lat.toFixed(7);lng.value=p.lng.toFixed(7);marker.setLatLng(p);}
 map.on('click',e=>set(e.latlng));marker.on('dragend',()=>set(marker.getLatLng()));
 for(const input of [lat,lng])input.addEventListener('change',()=>{if(valid()){marker.setLatLng([+lat.value,+lng.value]);map.panTo(marker.getLatLng());}});
 if(valid())map.panTo(marker.getLatLng());
 document.querySelector('#use-location').onclick=()=>{const status=document.querySelector('#coordinate-status');if(!navigator.geolocation){status.textContent='Location is unavailable. Pick on the map.';return;}navigator.geolocation.getCurrentPosition(p=>{set({lat:p.coords.latitude,lng:p.coords.longitude});map.panTo(marker.getLatLng());status.textContent=`Accuracy: ±${Math.round(p.coords.accuracy)} m. Verify before saving.`;},()=>{status.textContent='Location is unavailable. Pick on the map.';},{enableHighAccuracy:true,timeout:15000});};
}
const editors=document.querySelector('#stop-editors');
if(editors){
 const map=window.L?baseMap('route-map'):null,layer=map?L.layerGroup().addTo(map):null;
 function update(){
  const points=[];layer?.clearLayers();const empty=document.querySelector('#stops-empty');if(empty)empty.hidden=editors.children.length>0;
  [...editors.children].forEach((row,i)=>{
   row.querySelector('.stop-number').textContent=`Stop ${i+1}`;
   row.querySelectorAll('[name]').forEach(input=>{input.name=input.name.replace(/^stops\[\d+\]/,`stops[${i}]`);if(input.id){input.id='field-'+input.name.replace(/[^a-zA-Z0-9_-]/g,'-');const label=input.closest('label');if(label)label.htmlFor=input.id;}});
   const option=row.querySelector('select').selectedOptions[0];
   if(option&&!row.querySelector('[name$="[remove]"]').checked){const p=[+option.dataset.lat,+option.dataset.lng];points.push(p);if(layer)L.marker(p,{icon:L.divIcon({className:'number-marker',html:String(i+1),iconSize:[30,30]})}).addTo(layer);}
  });
  if(map&&points.length){L.polyline(points,{color:'#95502e',dashArray:'6 8'}).addTo(layer);map.fitBounds(L.latLngBounds(points),{padding:[30,30],maxZoom:17});}
 }
 document.querySelector('#add-stop').onclick=()=>{editors.append(document.querySelector('#stop-template').content.cloneNode(true));update();};
 editors.addEventListener('change',update);editors.addEventListener('click',e=>{const button=e.target.closest('[data-move]');if(!button)return;const row=button.closest('.stop-editor');if(button.dataset.move==='-1'&&row.previousElementSibling)row.previousElementSibling.before(row);else if(button.dataset.move==='1'&&row.nextElementSibling)row.nextElementSibling.after(row);update();});
 let dragging;editors.addEventListener('dragstart',e=>{if(e.target.closest('input,textarea,select')){e.preventDefault();return;}dragging=e.target.closest('.stop-editor');});editors.addEventListener('dragover',e=>e.preventDefault());editors.addEventListener('drop',e=>{e.preventDefault();const target=e.target.closest('.stop-editor');if(target&&dragging&&target!==dragging)target.before(dragging);update();});update();
}

// Removing a media association does not delete the uploaded file.
document.querySelectorAll('button[name="action"][value="remove_media"]').forEach(button=>button.closest('form').addEventListener('submit',event=>{if(!confirm('Remove this media from this location? The uploaded file itself will not be deleted.'))event.preventDefault();}));
