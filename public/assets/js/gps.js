import {distance} from './proximity.js';
export class GPS {
 constructor(onPosition,onStatus){this.onPosition=onPosition;this.onStatus=onStatus;this.watch=null;this.last=null;this.lastFix=0;this.timer=null;this.simulated=false;}
 start(){this.stop();this.simulated=false;if(!navigator.geolocation){this.onStatus('unavailable');return;}
  this.watch=navigator.geolocation.watchPosition(p=>this.accept({latitude:p.coords.latitude,longitude:p.coords.longitude,accuracy:p.coords.accuracy,timestamp:p.timestamp}),e=>{this.last=null;this.onStatus(e.code===1?'denied':e.code===3?'timeout':'unavailable');},{enableHighAccuracy:true,maximumAge:3000,timeout:15000});
  this.timer=setInterval(()=>{if(Date.now()-this.lastFix>25000)this.onStatus('lost');},5000);
 }
 accept(p){
  this.latestReading=p;
  if(!Number.isFinite(p.latitude)||!Number.isFinite(p.longitude)||Math.abs(p.latitude)>90||Math.abs(p.longitude)>180||!Number.isFinite(p.accuracy)||p.accuracy<0)return false;
  if(!this.simulated&&(Date.now()-p.timestamp>15000||p.timestamp>Date.now()+5000)){this.onStatus('stale');return false;}
  if(p.accuracy>75){this.onStatus('poor');return false;}
  if(this.last&&!this.simulated){const dt=(p.timestamp-this.last.timestamp)/1000;if(dt<=0)return false;if(dt<30&&distance(this.last,p)>Math.max(50,dt*8+this.last.accuracy+p.accuracy)){this.onStatus('jump');return false;}}
  this.last=p;this.lastFix=Date.now();this.onStatus(this.simulated?'simulated':'ready');this.onPosition(p);return true;
 }
 simulate(latitude,longitude,accuracy=5){this.stop();this.simulated=true;this.accept({latitude:+latitude,longitude:+longitude,accuracy:+accuracy,timestamp:Date.now()});}
 stop(){if(this.watch!==null)navigator.geolocation.clearWatch(this.watch);clearInterval(this.timer);this.watch=null;this.timer=null;this.last=null;}
}
