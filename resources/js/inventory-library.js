import {notify} from './toasts';
export function inventoryLibrary(url){return {
 sounds:[],items:[],assets:[],uses:{},revision:1,loading:true,dirty:false,saving:false,active:null,timer:null,pickerId:null,deleteId:null,
 async init(){window.vsgInventory=this;this.unload=e=>{if(this.dirty||this.saving){e.preventDefault();e.returnValue='';}};window.addEventListener('beforeunload',this.unload);try{await this.load();}catch(e){notify(e.message,'error');}finally{this.loading=false;}},
 destroy(){clearTimeout(this.timer);window.removeEventListener('beforeunload',this.unload);delete window.vsgInventory;},
 async request(method='GET',body){const r=await fetch(url,{method,headers:{Accept:'application/json','Content-Type':'application/json','X-CSRF-TOKEN':document.querySelector('meta[name="csrf-token"]').content},...(body?{body:JSON.stringify(body)}:{})});const d=await r.json();if(!r.ok)throw Error(Object.values(d.errors||{}).flat().join(' ')||d.message);return d;},
 async load(){if(this.dirty||this.saving)return;const d=await this.request();Object.assign(this,{items:d.items,assets:d.assets,uses:d.uses,revision:d.revision});this.publish();},
 publish(){window.dispatchEvent(new CustomEvent('vsg-inventory',{detail:this.items}));},
 asset(id){return this.assets.find(a=>a.id===Number(id));},
 changed(){this.dirty=true;clearTimeout(this.timer);this.timer=setTimeout(()=>this.flush(),400);},
 async flush(){clearTimeout(this.timer);if(this.active)return this.active;if(!this.dirty)return true;this.active=this.saveLoop();const ok=await this.active;this.active=null;return ok;},
 async saveLoop(){this.saving=true;try{while(this.dirty){this.dirty=false;const d=await this.request('PUT',{revision:this.revision,items:JSON.parse(JSON.stringify(this.items))});this.revision=d.revision;}this.publish();notify('Inventory saved.');return true;}catch(e){this.dirty=true;notify(e.message,'error');return false;}finally{this.saving=false;}},
 create(){this.items.push({id:crypto.randomUUID(),name:'New inventory item',kind:'object',asset_id:null,description:'',description_position:'top',prompt:'',hotspots:[],actions:[]});this.changed();},
 chooseImage(item){this.pickerId=item.id;this.$refs.inventoryImages.showModal();},
 selectImage(id){const item=this.items.find(i=>i.id===this.pickerId);if(item){item.asset_id=id;this.changed();}this.$refs.inventoryImages.close();},
 addAction(item){item.actions??=[];item.actions.push({id:crypto.randomUUID(),label:'New action',target_id:null});this.changed();},
 requestDelete(item){this.deleteId=item.id;this.$refs.inventoryDelete.showModal();},
 async remove(){if((this.uses[this.deleteId]||[]).length){notify('Remove this item’s scene links before deleting it.','error');return;}if(this.items.some(i=>(i.actions||[]).some(a=>a.target_id===this.deleteId))){notify('Remove replacement actions pointing to this item first.','error');return;}this.items=this.items.filter(i=>i.id!==this.deleteId);this.$refs.inventoryDelete.close();this.changed();await this.flush();}
};}
