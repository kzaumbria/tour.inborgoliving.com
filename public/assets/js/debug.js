import {distance} from './proximity.js';
/** Only constructed for explicit ?debug=1. Uses a separate saved session. */
export class DebugPanel {
 constructor(element,route,gps,onForce,getState){this.element=element;this.route=route;this.gps=gps;this.getState=getState;this.index=0;this.timer=null;element.hidden=false;
  const title=document.createElement('h2');title.textContent='SIMULATION MODE';element.append(title);
  const note=document.createElement('p');note.textContent='Demo tools. Simulated movement stops real GPS. Progress is isolated from the normal tour.';element.append(note);
  this.select=document.createElement('select');this.select.setAttribute('aria-label','Simulated stop');route.stops.forEach((s,i)=>{const option=document.createElement('option');option.value=i;option.textContent=`${i+1}. ${s.location.title}`;this.select.append(option);});element.append(this.select);
  this.lat=this.input('Latitude',-90,90);this.lng=this.input('Longitude',-180,180);this.accuracy=this.input('Accuracy (metres)',0,10000);this.accuracy.value=5;
  this.select.onchange=()=>this.choose(+this.select.value);
  const toolbar=document.createElement('div');toolbar.className='toolbar';element.append(toolbar);
  const button=(label,fn)=>{const b=document.createElement('button');b.textContent=label;b.onclick=fn;toolbar.append(b);};
  button('Move here',()=>this.move());button('Previous stop',()=>{this.choose(Math.max(0,this.index-1));this.move();});button('Next stop',()=>{this.choose(Math.min(route.stops.length-1,this.index+1));this.move();});
  button('Trigger arrival',()=>{const stop=route.stops[this.index];if(stop){clearInterval(this.timer);gps.stop();gps.simulated=true;onForce(stop);}});
  button('Use real GPS',()=>{this.stop();gps.start();});
  this.output=document.createElement('pre');element.append(this.output);this.choose(0);
 }
 input(label,min,max){const wrapper=document.createElement('label');wrapper.textContent=label;const input=document.createElement('input');input.type='number';input.step='any';input.min=min;input.max=max;wrapper.append(input);this.element.append(wrapper);return input;}
 choose(index){this.index=index;this.select.value=index;const l=this.route.stops[index]?.location;if(l){this.lat.value=l.latitude;this.lng.value=l.longitude;}}
 move(){if(!this.lat.checkValidity()||!this.lng.checkValidity()||!this.accuracy.checkValidity()||this.lat.value===''||this.lng.value==='')return;clearInterval(this.timer);const move=()=>this.gps.simulate(this.lat.value,this.lng.value,this.accuracy.value);move();this.timer=setInterval(move,2000);}
 stop(){clearInterval(this.timer);this.timer=null;}
 update(position,engine,result){const state=this.getState();const next=engine.expected();this.output.textContent=JSON.stringify({mode:this.gps.simulated?'SIMULATED':'REAL / IDLE',latitude:position?.latitude,longitude:position?.longitude,accuracy:position?.accuracy,route:this.route.slug,nextExpected:next?.location.title,distanceToNext:position&&next?Math.round(distance(position,next.location)):null,triggerRadius:next?.radius,nearest:result?.nearest?.stop.location.title,triggered:state.triggered,visited:state.visited,nearby:[...engine.nearby.keys()]},null,2);}
}
