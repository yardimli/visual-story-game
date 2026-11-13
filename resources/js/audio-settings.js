import {notify} from './toasts';

import {audioActions,GameAudio,speechText} from './story-audio';

async function request(url,method='GET',body){const r=await fetch(url,{method,headers:{Accept:'application/json','Content-Type':'application/json','X-CSRF-TOKEN':document.querySelector('meta[name="csrf-token"]').content},...(body?{body:JSON.stringify(body)}:{})});const d=await r.json();if(!r.ok)throw Error(Object.values(d.errors||{}).flat().join(' ')||d.message);return d;}

const voiceGroups={scene:'Scene descriptions',area:'Click-area descriptions',inventory:'Inventory descriptions'};

const emptyVoice=()=>({model:'',voice:'',instructions:'',temperature:1,language:''});

export function speechCategory(textarea){const field=textarea.getAttribute('x-model')||textarea.name||'';return textarea.closest('[data-speech-category=inventory]')?'inventory':/^(visual\.description|page\.narrative)|narrative/i.test(field)?'scene':'area';}

export function audioSettings(url){return {sounds:[],settings:{music_id:null,music_loop:false,defaults:{}},actions:audioActions,voiceGroups,models:[],testText:'The house is quiet, but somewhere beyond the door, something stirs.',saving:false,dirty:false,

 async init(){try{const d=await request(url);this.sounds=d.sounds;window.vsgAudioCategories=d.categories;window.dispatchEvent(new CustomEvent('vsg-audio-categories',{detail:d.categories}));this.settings={...this.settings,...d.settings};this.settings.voices??={};for(const key of Object.keys(voiceGroups))this.settings.voices[key]={...emptyVoice(),...Object.fromEntries(Object.entries(this.settings.voices[key]||{}).filter(([,v])=>v!==null))};for(const action of Object.keys(audioActions))this.settings.defaults[action]??={click:null,action:null};this.models=(await request(url+'/voices')).models;}catch(e){notify(e.message,'error');}},

 voicesFor(key){return this.models.find(m=>m.id===this.settings.voices[key].model)?.supported_voices||[];},

 changeVoiceModel(key){this.settings.voices[key].voice=this.voicesFor(key)[0]||'';this.save();},

 testVoice(key){window.vsgSpeech?.open(this.testText,key,this.settings.voices[key]);},

 async save(){this.dirty=true;if(this.saving)return;this.saving=true;try{while(this.dirty){this.dirty=false;await request(url+'/settings','PUT',this.settings);}window.dispatchEvent(new CustomEvent('vsg-audio-settings',{detail:this.settings}));notify('Audio defaults saved.');}catch(e){notify(e.message,'error');}finally{this.saving=false;}}

};}

export function storySpeech(url){return {models:[],sounds:[],jobs:[],presets:{},category:'scene',text:'',source:'',model:'',voice:'',instructions:'',temperature:1,language:'',busy:false,error:'',poll:null,player:null,observer:null,playingText:'',

 async init(){window.vsgSpeech=this;this.player=new GameAudio();this.listener=e=>{this.sounds=e.detail;};window.addEventListener('vsg-audio',this.listener);this.settingsListener=e=>{this.presets=e.detail.voices||{};};window.addEventListener('vsg-audio-settings',this.settingsListener);await this.load();this.decorate();this.observer=new MutationObserver(()=>this.decorate());this.observer.observe(document.querySelector('.story-editor-workspace'),{childList:true,subtree:true});},

 destroy(){clearTimeout(this.poll);this.observer?.disconnect();this.player?.stop();window.removeEventListener('vsg-audio',this.listener);window.removeEventListener('vsg-audio-settings',this.settingsListener);delete window.vsgSpeech;},

 decorate(){for(const textarea of document.querySelectorAll('.story-editor-workspace textarea')){const field=textarea.getAttribute('x-model')||textarea.name||'';if(textarea.dataset.speechControls||!/(description|response|interaction_text|fallback_text|unavailable_text|narrative)/i.test(field))continue;textarea.dataset.speechControls='1';const wrapper=document.createElement('div');wrapper.className='speech-field';textarea.parentNode.insertBefore(wrapper,textarea);wrapper.appendChild(textarea);if(textarea.hidden)wrapper.classList.add('speech-quill-controls');const controls=document.createElement('div');controls.className='speech-field-buttons';for(const [label,fn] of [['Generate speech',()=>this.open(textarea.value,speechCategory(textarea))],['Listen',()=>this.listen(textarea.value)]]){const b=document.createElement('button');b.type='button';b.textContent=label==='Listen'?'\u25B6':'\u266B';b.title=label;b.setAttribute('aria-label',label);b.className='btn-secondary !p-2 !text-xs';if(label==='Listen'){b.disabled=!this.speechFor(textarea.value);b.setAttribute('x-effect',"window.vsgSpeech?.updateListenButton($el,$el.closest('.speech-field').querySelector('textarea')._x_model?.get() ?? $el.closest('.speech-field').querySelector('textarea').value)");textarea.addEventListener('input',()=>{this.updateListenButton(b,textarea.value);});}b.addEventListener('click',fn);controls.appendChild(b);}wrapper.appendChild(controls);}},

 async load(){clearTimeout(this.poll);try{const d=await request(url);this.sounds=d.sounds;window.vsgAudioCategories=d.categories;window.dispatchEvent(new CustomEvent('vsg-audio-categories',{detail:d.categories}));window.dispatchEvent(new CustomEvent('vsg-credit',{detail:d.remaining}));this.jobs=d.jobs;this.presets=d.settings?.voices||{};window.dispatchEvent(new CustomEvent('vsg-audio',{detail:this.sounds}));if(this.jobs.some(j=>['queued','running','submitting'].includes(j.status)))this.poll=setTimeout(()=>this.load(),4000);}catch(e){this.error=e.message;}},

 async open(text,category='scene',override=null){if(!speechText(text)){notify('Write a description first.','info');return;}this.category=category;this.source=speechText(text);this.text=this.source;this.error='';this.$refs.speechDialog.showModal();try{if(!this.models.length)this.models=(await request(url+'/voices')).models;const preset={...emptyVoice(),...(override||this.presets[category]||{})};this.model=preset.model||this.models[0]?.id||'';this.selectModel();if(this.selected?.supported_voices?.includes(preset.voice))this.voice=preset.voice;this.instructions=preset.instructions||'';this.temperature=preset.temperature??1;this.language=preset.language||'';}catch(e){this.error=e.message;}},

 jobLabel(job){return (this.models.find(m=>m.id===job.model)?.name||job.model)+' · '+(job.settings?.voice||'Voice not recorded')+' · '+job.status;},

 get selected(){return this.models.find(m=>m.id===this.model);},

 selectModel(){this.voice=this.selected?.supported_voices?.[0]||'';},

 speechFor(text){const normalized=speechText(text);return normalized?this.sounds.find(s=>s.url && s.source_text && speechText(s.source_text)===normalized):null;},
 isListening(text){return !!this.playingText && this.playingText===speechText(text);},
 updateListenButton(button,text){const playing=this.isListening(text);button.disabled=!this.speechFor(text);button.textContent=playing?'\u25A0':'\u25B6';button.title=playing?'Stop':'Listen';button.setAttribute('aria-label',playing?'Stop speech preview':'Listen');button.setAttribute('aria-pressed',String(playing));},
 stopListening(){this.playingText='';this.player?.channel('voice',null);},
 listen(text){if(this.isListening(text)){this.stopListening();return;}const sound=this.speechFor(text);if(!sound)return;this.stopListening();const audio=new Audio(sound.url);this.player.channels.voice=audio;this.playingText=speechText(text);const finished=()=>{if(this.player.channels.voice===audio)this.playingText='';};audio.addEventListener('ended',finished);audio.addEventListener('pause',finished);audio.addEventListener('error',finished);audio.play().catch(finished);},

 async generate(){if(this.busy)return;this.busy=true;this.error='';try{await request(url+'/speech','POST',{request_key:crypto.randomUUID(),name:this.source.slice(0,100),source_text:this.source,category:({scene:'scenes',area:'click_areas',inventory:'inventory'})[this.category],prompt:this.text,model:this.model,settings:{voice:this.voice,...(this.model.startsWith('google/')?{instructions:this.instructions,temperature:Number(this.temperature)}:{}),...(this.model.startsWith('heygen/')?{language:this.language}:{})}});await this.load();notify('Speech queued.');}catch(e){this.error=e.message;}finally{this.busy=false;}}

};}


export function audioPreview(initialCategory='effects'){let audio=null;return {
 sound:null,playing:false,category:initialCategory,categories:window.vsgAudioCategories||{scenes:'Scenes',inventory:'Inventory',click_areas:'Click areas',music:'Music',effects:'Effects'},
 setSound(sound){if(this.sound?.url!==sound?.url)this.stop();this.sound=sound||null;},
 stop(){audio?.pause();audio=null;this.playing=false;},
 async toggle(){if(this.playing){this.stop();return;}if(!this.sound?.url)return;window.dispatchEvent(new CustomEvent('vsg-audio-preview-stop'));audio=new Audio(this.sound.url);const current=audio;current.volume=1;current.onended=()=>{if(audio===current)this.stop();};current.onerror=()=>{if(audio===current)this.stop();};this.playing=true;try{await current.play();}catch{if(audio===current)this.stop();}},
 destroy(){this.stop();}
};}
