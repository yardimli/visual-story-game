import {notify} from './toasts';
export function storyFlags(url){return {
 flags:[],revision:1,dirty:false,saving:false,active:null,timer:null,loading:true,
 async init(){window.vsgFlags=this;try{const d=await this.request();this.flags=d.flags;this.revision=d.revision;this.publish();}catch(e){notify(e.message,'error');}finally{this.loading=false;}},
 destroy(){clearTimeout(this.timer);delete window.vsgFlags;},
 publish(){window.dispatchEvent(new CustomEvent('vsg-flags',{detail:this.flags}));},
 async request(body){const r=await fetch(url,{method:body?'PUT':'GET',headers:{Accept:'application/json','Content-Type':'application/json','X-CSRF-TOKEN':document.querySelector('meta[name="csrf-token"]').content},...(body?{body:JSON.stringify(body)}:{})});const d=await r.json();if(!r.ok)throw Error(Object.values(d.errors||{}).flat().join(' ')||d.message);return d;},
 add(){let n=1;while(this.flags.some(f=>f.key==='flag_'+n))n++;this.flags.push({key:'flag_'+n,label:'New flag '+n,initial:false});this.changed();},
 changed(){this.dirty=true;clearTimeout(this.timer);this.timer=setTimeout(()=>this.flush(),400);},
 async flush(){clearTimeout(this.timer);if(this.active)return this.active;if(!this.dirty)return true;this.active=this.save();const result=await this.active;this.active=null;return result;},
 async save(){this.saving=true;try{while(this.dirty){this.dirty=false;const d=await this.request({revision:this.revision,flags:JSON.parse(JSON.stringify(this.flags))});this.revision=d.revision;}this.publish();notify('Story flags saved.');return true;}catch(e){this.dirty=true;notify(e.message,'error');return false;}finally{this.saving=false;}}
};}
