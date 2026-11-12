import {notify} from './toasts.js';
export class GameAudio {
 constructor(){this.preview=null;this.scene=null;this.effects=new Set();}
 play(sound,volume=1,loop=false){if(!sound?.url || typeof Audio==='undefined')return null;const audio=new Audio(sound.url);audio.volume=Math.max(0,Math.min(1,Number(volume)));audio.loop=loop;audio.play().catch(()=>{});return audio;}
 audition(area,sounds){this.stopAudition();this.preview=this.play(sounds.find(s=>s.id===Number(area?.sound_id)),area?.sound_volume??1);}
 stopAudition(){this.preview?.pause();this.preview=null;}
 enter(scene,sounds){this.stopAudition();this.scene?.pause();this.scene=null;this.scene=this.play(sounds.find(s=>s.id===Number(scene?.sound_id)),scene?.sound_volume??1,!!scene?.sound_loop);}
 click(area,sounds){const audio=this.play(sounds.find(s=>s.id===Number(area.sound_id)),area.sound_volume??1);if(audio){this.effects.add(audio);audio.onended=()=>this.effects.delete(audio);}}
 stop(){this.stopAudition();this.scene?.pause();this.scene=null;for(const a of this.effects)a.pause();this.effects.clear();}
}
export function storyAudio(url){return {
 audioMode:'search', sounds:[],jobs:[],searches:[],results:[],query:'',maxDuration:3,page:1,hasMore:false,count:0,busy:false,searching:false,generating:false,name:'',prompt:'',seconds:10,requestKey:null,timer:null,error:'',
 async init(){await this.load();},
 destroy(){clearTimeout(this.timer);this.$el?.querySelectorAll('audio').forEach(a=>a.pause());},
 async request(path='',method='GET',body){const r=await fetch(url+path,{method,headers:{Accept:'application/json','Content-Type':'application/json','X-CSRF-TOKEN':document.querySelector('meta[name="csrf-token"]').content},...(body?{body:JSON.stringify(body)}:{})});const data=await r.json();if(!r.ok)throw new Error(Object.values(data.errors||{}).flat()[0]||data.message||'Audio request failed.');return data;},
 async load(){clearTimeout(this.timer);try{const data=await this.request();this.sounds=data.sounds;this.jobs=data.jobs;this.searches=data.searches;window.dispatchEvent(new CustomEvent('vsg-audio',{detail:this.sounds}));if(this.jobs.some(j=>['queued','submitting','running'].includes(j.status)))this.timer=setTimeout(()=>this.load(),5000);}catch(e){this.error=e.message;}},
 async search(page=1){if(this.searching||!this.query.trim())return;this.searching=true;this.error='';try{const d=await this.request('/search?query='+encodeURIComponent(this.query.trim())+'&page='+page+'&max_duration='+encodeURIComponent(this.maxDuration));this.results=d.results;this.page=d.page;this.hasMore=d.has_more;this.count=d.count;if(!this.searches.includes(this.query.trim()))this.searches.unshift(this.query.trim());}catch(e){this.error=e.message;notify(e.message,'error');}finally{this.searching=false;}},
 added(id){return this.sounds.some(s=>s.provider==='freesound' && String(s.external_id)===String(id));},
 async add(id){if(this.busy)return;this.busy=true;try{await this.request('/import','POST',{id});await this.load();notify('Sound added to project.');}catch(e){notify(e.message,'error');}finally{this.busy=false;}},
 async generate(){if(this.generating)return;this.generating=true;this.error='';this.requestKey??=crypto.randomUUID();try{await this.request('/generate','POST',{request_key:this.requestKey,name:this.name,prompt:this.prompt,seconds:Number(this.seconds)});this.requestKey=null;await this.load();notify('Audio generation queued.');}catch(e){this.error=e.message;notify(e.message,'error');}finally{this.generating=false;}}
};}
