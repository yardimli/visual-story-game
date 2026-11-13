import {notify} from './toasts.js';
export const audioActions={examine:'Examine',inspect:'Inspect object',pickup:'Pick up object',pickup_v2:'Pick up local layer',state_v2:'Change local state',transition:'Change scene',inventory:'Use inventory',choices:'Choose an action',page:'Move to page',complete:'Finish story'};
export const speechText=text=>String(text||'').replace(/<[^>]*>/g,' ').replace(/\s+/g,' ').trim();
export class GameAudio {
 constructor(){this.channels={music:null,voice:null,effects:null};this.preview=null;this.muted=false;this.defaults={};}
 play(sound,volume=1,loop=false){if(!sound?.url || typeof Audio==='undefined')return null;const audio=new Audio(sound.url);audio.volume=1;audio.muted=this.muted;audio.loop=loop;audio.play().catch(()=>{});return audio;}
 channel(name,sound,loop=false){this.channels[name]?.pause();this.channels[name]=this.play(sound,1,loop);return this.channels[name];}
 toggleMute(){this.muted=!this.muted;for(const a of [...Object.values(this.channels),this.preview])if(a)a.muted=this.muted;return this.muted;}
 audition(area,sounds){this.stopAudition();this.preview=this.play(sounds.find(s=>s.id===Number(area?.sound_id)));}
 stopAudition(){this.preview?.pause();this.preview=null;}
 start(settings,sounds){this.stop();this.defaults=settings?.defaults||{};this.channel('music',sounds.find(s=>s.id===Number(settings?.music_id)),!!settings?.music_loop);}
 enter(scene,sounds){this.stopAudition();this.channel('voice',null);if(scene?.music_mode==='stop')this.channel('music',null);else if(scene?.music_mode==='replace')this.channel('music',sounds.find(s=>s.id===Number(scene.music_id)),!!scene.music_loop);if(scene?.sound_id)this.channel('effects',sounds.find(s=>s.id===Number(scene.sound_id)));}
 resolve(value,fallback){return value==='silent'?null:(value===undefined||value===null||value===''?fallback:Number(value));}
 click(area,sounds){const id=this.resolve(area.sound_id,this.defaults[area.action||'inspect']?.click);if(id)this.channel('effects',sounds.find(s=>s.id===Number(id)));}
 action(area,item,sounds){const fallback=area.action==='state_v2'?null:this.resolve(item?.action_sound_id,this.defaults[area.action||'inventory']?.action);const id=this.resolve(area.action_sound_id,fallback);if(id)this.channel('effects',sounds.find(s=>s.id===Number(id)));}
 speak(text,sounds){const normalized=speechText(text);this.channel('voice',normalized?sounds.find(s=>s.source_text && speechText(s.source_text)===normalized):null);}
 stop(){this.stopAudition();for(const key of Object.keys(this.channels)){this.channels[key]?.pause();this.channels[key]=null;}}
}
export function storyAudio(url){return {
 editingJob:null,voiceModels:[],batchBusy:false,batchProgress:'',jobBusy:false,
 get pendingJobs(){return this.jobs.filter(j=>j.status==='draft');},
 editableJob(j){return ['draft','queued'].includes(j.status)&&!j.submitted_at&&!j.provider_request_id;},
 async startJob(j){await this.request('/jobs/'+j.id+'/start','POST');},
 async startPending(){if(this.batchBusy)return;this.batchBusy=true;try{const jobs=[...this.pendingJobs];for(let i=0;i<jobs.length;i++){this.batchProgress='Queuing '+(i+1)+' of '+jobs.length;await this.startJob(jobs[i]);}notify('Pending audio queued.');}catch(e){notify(e.message,'error');}finally{this.batchBusy=false;this.batchProgress='';await this.load();}},
 async generateDraft(j){if(this.jobBusy)return;this.jobBusy=true;try{await this.startJob(j);notify('Audio queued.');}catch(e){notify(e.message,'error');}finally{this.jobBusy=false;await this.load();}},
 async editJob(j){this.editingJob=JSON.parse(JSON.stringify(j));this.editingJob.settings??={};this.$refs.audioJobDialog.showModal();try{if(!this.voiceModels.length)this.voiceModels=(await this.request('/voices')).models;}catch(e){notify(e.message,'error');}},
 get draftVoices(){return this.voiceModels.find(m=>m.id===this.editingJob?.model)?.supported_voices||[];},
 async saveJob(){if(this.jobBusy)return;this.jobBusy=true;try{await this.request('/jobs/'+this.editingJob.id,'PUT',this.editingJob);this.$refs.audioJobDialog.close();await this.load();notify('Saved as pending audio. Generate it from Audio when ready.');}catch(e){notify(e.message,'error');}finally{this.jobBusy=false;}},
 async deleteJob(j){if(!confirm('Delete this pending audio request?'))return;try{await this.request('/jobs/'+j.id,'DELETE');await this.load();}catch(e){notify(e.message,'error');}},
 async deleteSound(s){try{const d=await this.request('/sounds/'+s.id+'/usage');const warning=d.uses.length?'Used in:\n'+d.uses.join('\n')+'\n\nThese places will be set to no audio.\n\n':'';if(!confirm(warning+'Delete '+s.name+'?'))return;await this.request('/sounds/'+s.id,'DELETE',{confirm:true});await this.load();notify('Audio deleted.');}catch(e){notify(e.message,'error');}},
 categories:{},categoryFilter:'',newCategory:'',categoriesOpen:false,
 async saveCategories(){try{await this.request('/categories','PUT',{categories:this.categories});window.vsgAudioCategories=this.categories;window.dispatchEvent(new CustomEvent('vsg-audio-categories',{detail:this.categories}));}catch(e){notify(e.message,'error');}},
 async addCategory(){if(!this.newCategory.trim())return;this.categories['custom_'+crypto.randomUUID()]=this.newCategory.trim();this.newCategory='';await this.saveCategories();},
 async categorize(sound,category){try{await this.request('/sounds/'+sound.id,'PUT',{category});sound.category=category;window.dispatchEvent(new CustomEvent('vsg-audio',{detail:this.sounds}));}catch(e){notify(e.message,'error');}},
 get filteredSounds(){return this.sounds.filter(s=>!this.categoryFilter||(s.category||'effects')===this.categoryFilter);},
 model:'fal-ai/stable-audio',audioMode:'search', sounds:[],jobs:[],searches:[],results:[],query:'',maxDuration:3,page:1,hasMore:false,count:0,busy:false,searching:false,generating:false,name:'',prompt:'',seconds:10,requestKey:null,timer:null,error:'',
 async init(){await this.load();},
 destroy(){clearTimeout(this.timer);this.$el?.querySelectorAll('audio').forEach(a=>a.pause());},
 async request(path='',method='GET',body){const r=await fetch(url+path,{method,headers:{Accept:'application/json','Content-Type':'application/json','X-CSRF-TOKEN':document.querySelector('meta[name="csrf-token"]').content},...(body?{body:JSON.stringify(body)}:{})});const data=await r.json();if(!r.ok)throw new Error(Object.values(data.errors||{}).flat()[0]||data.message||'Audio request failed.');return data;},
 async load(){clearTimeout(this.timer);try{const data=await this.request();this.sounds=data.sounds;this.categories=data.categories;window.vsgAudioCategories=data.categories;window.dispatchEvent(new CustomEvent('vsg-audio-categories',{detail:data.categories}));window.dispatchEvent(new CustomEvent('vsg-credit',{detail:data.remaining}));this.jobs=data.jobs;this.searches=data.searches;window.dispatchEvent(new CustomEvent('vsg-audio',{detail:this.sounds}));window.dispatchEvent(new CustomEvent('vsg-audio-settings',{detail:data.settings}));if(this.jobs.some(j=>['queued','submitting','running'].includes(j.status)))this.timer=setTimeout(()=>this.load(),5000);}catch(e){this.error=e.message;}},
 async search(page=1){if(this.searching||!this.query.trim())return;this.searching=true;this.error='';try{const d=await this.request('/search?query='+encodeURIComponent(this.query.trim())+'&page='+page+'&max_duration='+encodeURIComponent(this.maxDuration));this.results=d.results;this.page=d.page;this.hasMore=d.has_more;this.count=d.count;if(!this.searches.includes(this.query.trim()))this.searches.unshift(this.query.trim());}catch(e){this.error=e.message;notify(e.message,'error');}finally{this.searching=false;}},
 added(id){return this.sounds.some(s=>s.provider==='freesound' && String(s.external_id)===String(id));},
 async add(id){if(this.busy)return;this.busy=true;try{await this.request('/import','POST',{id});await this.load();notify('Sound added to project.');}catch(e){notify(e.message,'error');}finally{this.busy=false;}},
 async generate(){if(this.generating)return;this.generating=true;this.error='';this.requestKey??=crypto.randomUUID();try{await this.request('/generate','POST',{request_key:this.requestKey,name:this.name,model:this.model,prompt:this.prompt,seconds:Number(this.seconds)});this.requestKey=null;await this.load();notify('Audio generation queued.');}catch(e){this.error=e.message;notify(e.message,'error');}finally{this.generating=false;}}
};}
