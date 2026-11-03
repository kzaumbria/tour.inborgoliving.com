export function distance(a,b){const r=Math.PI/180,dlat=(b.latitude-a.latitude)*r,dlng=(b.longitude-a.longitude)*r;const h=Math.sin(dlat/2)**2+Math.cos(a.latitude*r)*Math.cos(b.latitude*r)*Math.sin(dlng/2)**2;return 6371000*2*Math.atan2(Math.sqrt(h),Math.sqrt(Math.max(0,1-h)));}
/** One arrival per update. Expected stop wins, then nearest, then route order.
 * Two good fixes >=1.5s apart, no more than 15s apart. A 15m/35% exit buffer
 * keeps nearby state stable; triggering still requires being inside the real radius.
 * Triggered IDs persist until explicitly reset. No server GPS calls.
 */
export class Proximity {
 constructor(stops,triggered=[]){this.stops=stops;this.triggered=new Set(triggered.map(String));this.nearby=new Map();}
 expected(){return this.stops.find(s=>!this.triggered.has(String(s.id)));}
 reset(){this.triggered.clear();this.nearby.clear();}
 mark(id){this.triggered.add(String(id));this.nearby.delete(String(id));}
 update(position,now=Date.now(),blocked=false){
  const measured=this.stops.map(s=>({stop:s,distance:distance(position,s.location)}));
  const candidates=[];
  for(const item of measured){const s=item.stop,key=String(s.id),radius=+s.radius;
   if(this.triggered.has(key))continue;
   if(position.accuracy>Math.min(50,Math.max(15,radius))){this.nearby.delete(key);continue;}
   if(item.distance>radius+Math.max(15,radius*.35)){this.nearby.delete(key);continue;}
   if(item.distance<=radius){let n=this.nearby.get(key);if(!n||now-n.last>15000)n={since:now,last:now,count:0};n.count++;n.last=now;this.nearby.set(key,n);if(n.count>=2&&now-n.since>=1500)candidates.push(item);}
  }
  const expected=this.expected();candidates.sort((a,b)=>(a.stop.id===expected?.id?-1:0)-(b.stop.id===expected?.id?-1:0)||a.distance-b.distance||a.stop.stop_order-b.stop.stop_order);
  const chosen=blocked?null:candidates[0]?.stop;
  if(chosen)this.mark(chosen.id);
  return {arrival:chosen||null,measured,nearest:[...measured].sort((a,b)=>a.distance-b.distance||a.stop.stop_order-b.stop.stop_order)[0]||null};
 }
}
