import {storage} from './storage.js';
export class Tour {
 constructor(route,simulation=false){this.route=route;this.key=(simulation?'simulation:':'tour:')+route.slug;const saved=storage.get(this.key,{});this.state={started:!!saved.started,triggered:Array.isArray(saved.triggered)?saved.triggered:[],visited:Array.isArray(saved.visited)?saved.visited:[],current:saved.current??null,autoplay:!!saved.autoplay};const ids=new Set(route.stops.map(s=>String(s.id)));for(const k of ['triggered','visited'])this.state[k]=this.state[k].map(String).filter(id=>ids.has(id));if(!ids.has(String(this.state.current)))this.state.current=null;}
 save(){return storage.set(this.key,this.state);}
 arrive(stop){const id=String(stop.id);if(!this.state.triggered.includes(id))this.state.triggered.push(id);this.state.current=id;return this.save();}
 visit(stop){this.arrive(stop);if(!this.state.visited.includes(String(stop.id)))this.state.visited.push(String(stop.id));return this.save();}
 next(){return this.route.stops.find(s=>!this.state.visited.includes(String(s.id)));}
 reset(){this.state={started:false,triggered:[],visited:[],current:null,autoplay:false};this.save();}
}
