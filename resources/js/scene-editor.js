import {imageCatalog} from './image-catalog.js';
import {notify, watchNotices} from './toasts.js';
import {selectableModels, rememberedModel, rememberModel, imagePriceLabel, imageModelLabel} from './model-preferences.js';
export function sceneEditor(options) {
 return {
  imagePickerPage:null, otherPageJobs:{}, loadedImagePages:[], loadingImagePages:[], previewPages:[], editorPageId:null, previewPageId:null, generationMode:!!options.generationMode, generationName:'', namingImage:false, retryingJob:null, editingVisual:false, showArchived:false, showFailed:false, cardStates:{}, expandedPrompts:{}, assetNames:{}, assetSaving:{}, copiedPrompt:null, document: {visuals:[]}, revision:1, assets:[], jobs:[], selected:'', hotspotId:'', models:[], model:'', endpoints:[],  settings:{}, references:[],
  loading:true, dirty:false, saving:false, halted:false, status:'Loading scene artwork…', error:'', timer:null, active:null, polling:null, generating:false, uploading:false, uploadStatus:'', requestKey:crypto.randomUUID(),
  typeChange:null, moveJob:null, moveTarget:'', movingImage:false, visualAction:'scene', visualName:'', visualSourceId:'', deleteVisualId:'', areaDrag:null, renamingId:'', labelDraft:'', pickerKind:'object', deleteAreaId:'', suppressImageClick:false, inventoryOpen:false, outgoingScene:null, sceneTransition:false, transitionTimer:null, modalTimer:null, pendingModal:null, preview:false, previewId:'', inventory:[], collected:[], modal:null, drawing:false, drag:null, drawRect:null, imagePriceLabel, imageModelLabel,
  async init() {
   watchNotices(this,{status:'success',error:'error',uploadStatus:'success'});
   window.vsgSceneEditor=this;
   this.loadCardStates();
   this.model=rememberedModel('vsg-image-model',options.model || '');
   this.unload=e=>{if(this.dirty || this.saving){e.preventDefault();e.returnValue='';}};
   window.addEventListener('beforeunload',this.unload);
   try {
    const data=await this.request(options.url);this.document=data.document;this.previewPages=data.preview_pages||[];this.editorPageId=data.page_id;this.revision=data.revision;this.assets=data.assets;this.jobs=data.jobs;this.credit(data);this.restoreVisual();
    this.status='';
   } catch(e){this.error=e.message;this.halted=true;}
   this.loading=false;
   try { this.models=selectableModels((await imageCatalog()).models,'image'); if(!this.models.some(m=>m.id===this.model)) this.model=''; if(this.model) await this.loadEndpoints(); } catch(e) { this.error='Image catalog unavailable. You can still edit existing visuals; reload to try discovery again.'; }
   this.polling=setInterval(()=>this.refresh(),5000);
  },
  destroy(){clearTimeout(this.timer);clearInterval(this.polling);window.removeEventListener('beforeunload',this.unload);delete window.vsgSceneEditor;},
  async request(url,method='GET',body=null) {
   const response=await fetch(url,{method,headers:{Accept:'application/json','Content-Type':'application/json','X-CSRF-TOKEN':document.querySelector('meta[name="csrf-token"]').content},...(body ? {body:JSON.stringify(body)} : {})});
   const data=await response.json();
   if(response.status===429){const seconds=Number(response.headers.get('Retry-After'));throw new Error(seconds>0?`Too many requests. Try again in ${Math.ceil(seconds)} seconds.`:'Too many requests. Please wait a minute and try again.');}
   if(!response.ok){const e=new Error(Object.values(data.errors??{}).flat().join(' ')||data.message||'Request failed.');e.conflict=!!data.errors?.revision;e.validation=response.status===422;throw e;}
   return data;
  },
  get visual(){return this.document.visuals.find(v=>v.id===this.selected);},
  get visualDetailsVisible(){return !!this.visual && (!this.visual.asset_id || this.editingVisual);},
  selectVisualOption(value){if(value==='new:scene'||value==='new:object'){this.requestVisualName(value.slice(4));}else this.choose(value);},
  get hotspot(){return this.visual?.hotspots.find(h=>h.id===this.hotspotId);},
  get generationVisual(){
   if(!this.preview && ['inspect','pickup'].includes(this.hotspot?.action))return this.objects.find(v=>v.id===this.hotspot.object_id);
   return this.visual;
  },
  async openGeneration(base,forInteraction=true){
   try {
    if(forInteraction && !this.preview && ['inspect','pickup'].includes(this.hotspot?.action) && !this.generationVisual){
     if(this.document.visuals.length>=100)throw new Error('This page has reached its 100-visual limit.');
     const object={id:crypto.randomUUID(),kind:'object',name:this.hotspot.label,asset_id:null,prompt:'',hotspots:[]};
     this.document.visuals.push(object);this.hotspot.object_id=object.id;this.changed();
    }
    const target=forInteraction?this.generationVisual:this.visual;
    if(!target)throw new Error('Select a visual before opening image generation.');
    const id=target.id;
    if(window.vsgAutosave && !(await window.vsgAutosave.flush()))return;
    if(!(await this.flush()))return;
    window.location.assign(base+'?visual='+encodeURIComponent(id));
   }catch(e){this.error=e.message;}
  },
  get playVisuals(){return this.preview && this.previewPageId!==this.editorPageId ? (this.previewPages.find(p=>p.id===this.previewPageId)?.visuals||this.document.visuals) : this.document.visuals;},
  get scenes(){return this.playVisuals.filter(v=>v.kind==='scene');},
  get objects(){return (this.preview?[...this.document.visuals,...this.previewPages.filter(p=>p.id!==this.editorPageId).flatMap(p=>p.visuals)]:this.document.visuals).filter(v=>v.kind==='object');},
  get endpoint(){
   if(!this.endpoints.length)return null;
   const supported_parameters={};
   for(const key of ['quality','resolution','aspect_ratio','background']) {
    const caps=this.endpoints.map(e=>e.supported_parameters?.[key]);
    if(caps.every(c=>c?.type==='enum')) {const values=caps[0].values.filter(v=>caps.every(c=>c.values.includes(v)));if(values.length)supported_parameters[key]={type:'enum',values};}
   }
   return {supported_parameters,pricing:[...new Map(this.endpoints.flatMap(e=>e.pricing||[]).map(l=>[JSON.stringify(l),l])).values()]};
  },
  get displayed(){return this.preview ? this.playVisuals.find(v=>v.id===this.previewId) : this.visual;},
  get currentHotspots(){return (this.displayed?.hotspots||[]).filter(h=>!this.preview || !this.collected.includes(h.id));},
  asset(id){return this.assets.find(a=>a.id===Number(id));},
  async uploadReferences(input){
   const files=[...input.files];if(!files.length||this.uploading)return;
   if(files.length>8||files.some(f=>f.size>10*1024*1024)){this.error='Choose up to 8 images, no larger than 10 MB each.';input.value='';return;}
   this.uploading=true;this.uploadStatus='Uploading references…';
   try {
    const body=new FormData();body.append('role','reference');files.forEach(f=>body.append('images[]',f));
    const response=await fetch(options.upload,{method:'POST',headers:{Accept:'application/json','X-CSRF-TOKEN':document.querySelector('meta[name="csrf-token"]').content},body});
    const data=await response.json();if(!response.ok)throw new Error(Object.values(data.errors??{}).flat().join(' ')||data.message||'Upload failed.');
    this.assets=[...data.assets,...this.assets];this.references=[...new Set([...this.references,...data.assets.map(a=>String(a.id))])];
    window.dispatchEvent(new CustomEvent('vsg-assets-uploaded',{detail:data.assets}));
    this.uploadStatus=`${data.assets.length} reference image(s) uploaded and selected. Saved in References.`;
   } catch(e){this.uploadStatus='';this.error=e.message||'Upload failed. Please try again.';}
   finally{this.uploading=false;input.value='';}
  },
  visualAsset(id){return this.asset(this.document.visuals.find(v=>v.id===id)?.asset_id);},
  style(h){return `left:${h.x*100}%;top:${h.y*100}%;width:${h.width*100}%;height:${h.height*100}%`;},
  restoreVisual(){
   const requested=new URL(window.location.href).searchParams.get('visual');
   this.selected=this.document.visuals.some(v=>v.id===requested)?requested:(this.generationMode && requested?'':this.document.visuals[0]?.id||'');
   if(!this.generationMode)this.syncVisualUrl();
  },
  syncVisualUrl(){
   if(typeof window==='undefined'||!window.history?.replaceState||!window.location?.href)return;
   const url=new URL(window.location.href);
   if(this.selected)url.searchParams.set('visual',this.selected);else url.searchParams.delete('visual');
   window.history.replaceState(window.history.state,'',url);
  },
  choose(id){this.cancelDraw();this.editingVisual=false;this.selected=id;this.hotspotId='';this.preview=false;this.syncVisualUrl();},
  add(kind,name=null){if(this.document.visuals.length>=100)return;const v={id:crypto.randomUUID(),kind,name:name||(kind==='scene'?'Scene state ':'Object image ')+(this.document.visuals.filter(v=>v.kind===kind).length+1),asset_id:null,prompt:kind==='scene'?options.prompt||'':'',hotspots:[]};this.document.visuals.push(v);this.choose(v.id);this.changed();},
  selectGenerationVisual(){if(this.selected.startsWith('new:'))this.newGeneration(this.selected.slice(4));else{this.choose(this.selected);this.references=[];}},
  newGeneration(kind){this.add(kind);this.references=[];this.$nextTick(()=>this.$refs.generationForm?.scrollIntoView({behavior:'smooth',block:'nearest'}));},
  loadCardStates(){try{const saved=JSON.parse(localStorage.getItem('vsg-image-cards:'+options.url)||'{}');this.cardStates=saved&&typeof saved==='object'&&!Array.isArray(saved)?saved:{};}catch(_){this.cardStates={};}},
  cardOpen(job){return this.cardStates[job.id]??(job.localFailure || job.status!=='failed');},
  rememberCard(job,open){this.cardStates={...this.cardStates,[job.id]:open};try{localStorage.setItem('vsg-image-cards:'+options.url,JSON.stringify(this.cardStates));}catch(_){}},
  get otherImagePages(){return this.previewPages.filter(p=>p.id!==this.editorPageId);},
  get historyJobs(){return [...this.jobs,...this.loadedImagePages.flatMap(id=>this.otherPageJobs[id]||[])];},
  pageImageLabel(page){const label='Page '+page.position,title=(page.title||'').trim();return !title || title.toLowerCase()===label.toLowerCase()?label:label+' \u00b7 '+title;},
  async togglePageImages(page){
   if(this.loadingImagePages.includes(page.id))return;
   if(this.loadedImagePages.includes(page.id)){this.loadedImagePages=this.loadedImagePages.filter(id=>id!==page.id);return;}
   this.loadingImagePages.push(page.id);
   try{
    const data=await this.request(page.images_url);
    this.otherPageJobs[page.id]=data.jobs.filter(job=>job.status==='completed').map(job=>({...job,sourcePage:this.pageImageLabel(page)}));
    for(const asset of data.assets)if(!this.assets.some(a=>a.id===asset.id))this.assets.push(asset);
    this.loadedImagePages.push(page.id);
    if(!this.otherPageJobs[page.id].length)notify('This page has no completed images yet.','info');
   }catch(e){this.error=e.message;}finally{this.loadingImagePages=this.loadingImagePages.filter(id=>id!==page.id);}
  },
  get visibleJobs(){return this.historyJobs.filter(job=>(this.showFailed || job.status!=='failed') && (!job.asset_ids?.length || this.jobAssets(job).length));},
  get failedCount(){return this.jobs.filter(job=>job.status==='failed').length;},
  jobAssets(job){return (job.asset_ids||[]).filter(id=>this.showArchived || !this.asset(id)?.archived);},
  get archivedCount(){return this.assets.filter(a=>a.archived && this.historyJobs.some(j=>j.asset_ids?.includes(a.id))).length;},
  jobTitle(job){return (job.asset_ids||[]).map(id=>this.asset(id)?.name).filter(Boolean).join(', ') || job.name;},
  promptShort(job){const words=(job.prompt||'').trim().split(/\s+/);return words.slice(0,20).join(' ')+(words.length>20?'…':'');},
  promptLong(job){return (job.prompt||'').trim().split(/\s+/).length>20;},
  async updateAsset(id,changes){
   const asset=this.asset(id);if(!asset||this.assetSaving[id])return;
   this.assetSaving={...this.assetSaving,[id]:true};
   try{const data=await this.request(asset.update_url||options.assetBase+'/'+id,'PATCH',changes);Object.assign(asset,data);delete this.assetNames[id];notify('archived' in changes?(changes.archived?'Image archived.':'Image restored.'):'Image name saved.');}
   catch(e){this.error=e.message;}finally{this.assetSaving={...this.assetSaving,[id]:false};}
  },
  renameAsset(id){const name=(this.assetNames[id]??this.asset(id)?.name??'').trim();if(!name){this.error='Enter an image name.';return;}if(name!==this.asset(id)?.name)return this.updateAsset(id,{name});},
  async copyPrompt(job){
   try{await navigator.clipboard.writeText(job.prompt||'');this.copiedPrompt=job.id;notify('Prompt copied.');}
   catch(_){this.error='Could not copy the prompt. Select the prompt text and copy it manually.';}
  },
  duplicate(name=null){if(!this.visual||this.document.visuals.length>=100)return;const v=JSON.parse(JSON.stringify(this.visual));v.id=crypto.randomUUID();v.name=name||v.name+' copy';v.hotspots.forEach(h=>h.id=crypto.randomUUID());(v.actions||[]).forEach(a=>a.id=crypto.randomUUID());this.document.visuals.push(v);this.choose(v.id);this.changed();},
  applyInteractionProposal(proposal,pageId){
   const target=proposal._interaction,change=proposal.interaction;
   if(target.page_id!==pageId||target.revision!==this.revision||target.visual_id!==this.selected||target.hotspot_id!==this.hotspotId||this.dirty||this.saving||!this.hotspot)throw new Error('Select the original area and request a fresh suggestion; this area has changed.');
   if(!change|| (change.description!==undefined&&(typeof change.description!=='string'||change.description.length>3000)))throw new Error('Invalid area description.');
   if(change.action && !['examine','inspect','pickup'].includes(change.action))throw new Error('Invalid interaction action.');
   let object=this.objects.find(v=>v.id===this.hotspot.object_id);
   if(change.object){
    if(typeof change.object.name!=='string'||!change.object.name.trim()||change.object.name.length>160||typeof change.object.prompt!=='string'||!change.object.prompt.trim()||change.object.prompt.length>10000)throw new Error('Invalid object image prompt.');
    if(!object && this.document.visuals.length>=100)throw new Error('This page has reached its 100-visual limit.');
    if(!object){object={id:crypto.randomUUID(),kind:'object',name:'',prompt:'',asset_id:null,hotspots:[]};this.document.visuals.push(object);}
    object.name=change.object.name;object.prompt=change.object.prompt;this.hotspot.object_id=object.id;
   }
   if(change.description!==undefined)this.hotspot.description=change.description;
   if(change.action)this.hotspot.action=change.action;
   this.changed();return change.object?object.id:null;
  },
  requestVisualName(action){
   if(this.document.visuals.length>=100){this.error='This page has reached its 100-visual limit.';return;}
   this.visualAction=action;this.visualSourceId=this.selected;this.visualName=action==='duplicate'?(this.visual?.name||'Visual')+' copy':'';
   this.$refs.visualNameDialog.showModal();this.$nextTick(()=>{this.$refs.visualNameInput.focus();this.$refs.visualNameInput.select();});
  },
  createNamedVisual(){
   const name=this.visualName.trim();if(!name||name.length>160){notify('Enter a name between 1 and 160 characters.','error');return;}
   if(this.visualAction==='duplicate'){this.choose(this.visualSourceId);this.duplicate(name);}else this.add(this.visualAction,name);
   this.$refs.visualNameDialog.close();
  },
  requestVisualType(kind){
   const visual=this.visual;if(!visual||!['scene','object'].includes(kind)||kind===visual.kind)return;
   const areas=kind==='object'?visual.hotspots.length:0;
   const actions=kind==='scene'?(visual.actions||[]).length:0;
   const actionLinks=kind==='scene'?this.document.visuals.flatMap(v=>v.actions||[]).filter(a=>a.target_id===visual.id).length:0;
   const ruleLinks=this.document.visuals.flatMap(v=>v.hotspots||[]).flatMap(h=>[...(h.inventory_rules||[]),...(h.choices||[])]).filter(r=>kind==='object'?r.target_id===visual.id:r.object_id===visual.id).length;
   const links=ruleLinks+this.document.visuals.flatMap(v=>v.hotspots||[]).filter(h=>kind==='object'?h.target_id===visual.id:h.object_id===visual.id).length;
   this.typeChange={id:visual.id,kind,message:`This will remove ${areas} clickable area(s) from this visual and remove ${actions} object action(s) and clear ${links+actionLinks} incompatible interaction link(s). Affected actions will become Examine.`};
   if(areas||links||actions||actionLinks)this.$refs.visualTypeDialog.showModal();else this.confirmVisualType();
  },
  confirmVisualType(){
   const change=this.typeChange;if(!change)return;const visual=this.document.visuals.find(v=>v.id===change.id);if(!visual)return;
   if(change.kind==='object')visual.hotspots=[];
   else {visual.actions=[];for(const v of this.document.visuals)for(const a of v.actions||[])if(a.target_id===visual.id)a.target_id=null;}
   for(const v of this.document.visuals)for(const h of v.hotspots||[]){
    if(change.kind==='object'){for(const rule of [...(h.inventory_rules||[]),...(h.choices||[])])if(rule.target_id===visual.id)rule.target_id=null;}else if(h.inventory_rules){h.inventory_rules=h.inventory_rules.filter(rule=>rule.object_id!==visual.id);}
    if(change.kind==='object' && h.target_id===visual.id){h.target_id=null;if(h.action==='transition')h.action='examine';}
    if(change.kind==='scene' && h.object_id===visual.id){h.object_id=null;h.overlay=false;if(['inspect','pickup'].includes(h.action))h.action='examine';}
   }
   visual.kind=change.kind;this.hotspotId='';this.renamingId='';this.areaDrag=null;this.preview=false;this.cancelDraw();
   this.typeChange=null;this.changed();this.$refs.visualTypeDialog?.close();
  },
  requestVisualDelete(){if(!this.visual)return;this.deleteVisualId=this.visual.id;this.$refs.deleteVisualDialog.showModal();},
  get deletingVisual(){return this.document.visuals.find(v=>v.id===this.deleteVisualId);},
  get dependentAreas(){return this.document.visuals.filter(v=>v.id!==this.deleteVisualId).flatMap(v=>[...(v.hotspots||[]),...(v.actions||[]),...(v.hotspots||[]).flatMap(h=>[...(h.inventory_rules||[]),...(h.choices||[])])]).filter(h=>h.target_id===this.deleteVisualId||h.object_id===this.deleteVisualId).length;},
  confirmVisualDelete(){
   const id=this.deleteVisualId;if(!this.deletingVisual)return;
   this.document.visuals=this.document.visuals.filter(v=>v.id!==id);
   for(const v of this.document.visuals)for(const a of v.actions||[])if(a.target_id===id)a.target_id=null;
   for(const v of this.document.visuals)for(const h of v.hotspots){
    if(h.inventory_rules)h.inventory_rules=h.inventory_rules.filter(rule=>rule.object_id!==id);
    for(const rule of [...(h.inventory_rules||[]),...(h.choices||[])])if(rule.target_id===id)rule.target_id=null;
    if(h.target_id===id){h.target_id=null;if(h.action==='transition')h.action='examine';}
    if(h.object_id===id){h.object_id=null;h.overlay=false;if(['inspect','pickup'].includes(h.action))h.action='examine';}
   }
   this.choose(this.document.visuals[0]?.id||'');this.drawing=false;this.drag=null;this.areaDrag=null;this.renamingId='';this.modal=null;this.references=[];
   this.deleteVisualId='';this.changed();this.$refs.deleteVisualDialog.close();
  },
  changed(){this.dirty=true;this.status='Unsaved visual changes';clearTimeout(this.timer);if(!this.halted)this.timer=setTimeout(()=>this.flush(),450);},
  async flush(){
   clearTimeout(this.timer);if(this.loading)return false;if(this.active){await this.active;return !this.dirty&&!this.error;}
   if(this.halted)return false;
   this.active=this.saveLoop();try{return await this.active;}finally{this.active=null;}
  },
  async saveLoop(){
   while(this.dirty){
    this.dirty=false;this.saving=true;this.status='Saving visuals…';
    try {const data=await this.request(options.url,'PUT',{revision:this.revision,document:JSON.parse(JSON.stringify(this.document))});this.revision=data.revision;this.error='';this.status='All visual changes saved';}
    catch(e){this.dirty=true;this.error=e.message;this.status='Visual changes not saved';this.halted=e.conflict||!e.validation;return false;}
    finally{this.saving=false;}
   }return true;
  },
  mergeJobs(jobs){this.jobs=[...this.jobs.filter(job=>job.localFailure),...jobs];},
  async refresh(){if(this.generating || !this.jobs.some(j=>['queued','running'].includes(j.status)))return;try{const d=await this.request(options.url);for(const job of d.jobs){const previous=this.jobs.find(j=>j.id===job.id);if(previous && previous.status!==job.status && ['completed','failed'].includes(job.status))notify(job.status==='completed'?job.name+' image ready.':job.error||job.name+' generation failed.',job.status==='failed'?'error':'success');}this.assets=d.assets;this.mergeJobs(d.jobs);this.credit(d);}catch(_){/* A later poll can recover; no paid action is repeated. */}},
  credit(data){if(typeof data.remaining==='number')window.dispatchEvent(new CustomEvent('vsg-credit',{detail:data.remaining}));},
  async loadEndpoints(){
   rememberModel('vsg-image-model',this.model);this.endpoints=[];this.settings={};
   if(!this.model)return;const selected=this.model;
   try{const endpoints=this.models.find(m=>m.id===selected)?.imageEndpoints||[];if(selected===this.model)this.endpoints=endpoints;}catch(e){this.error=e.message;}
  },
  async generate(){
   if(this.generating)return;
   this.generating=true;this.error='';let pendingId;
   try{
    if(window.vsgAutosave && !(await window.vsgAutosave.flush()))return;
    if(!(await this.flush())||!this.visual)return;
    if(!this.generationName.trim() && options.nameUrl){
     const writingModel=rememberedModel('vsg-writing-model',options.writingModel||'');
     if(!writingModel)throw new Error('Select a writing model in Creative companion, or enter an image name.');
     this.namingImage=true;
     try{const named=await this.request(options.nameUrl,'POST',{prompt:this.visual.prompt,model:writingModel,request_key:crypto.randomUUID()});this.generationName=named.name;this.credit(named);}finally{this.namingImage=false;}
    }
    pendingId='pending:'+this.requestKey;
    const pending={id:pendingId,visual_id:this.visual.id,name:this.generationName.trim()||this.visual.name,prompt:this.visual.prompt,model:this.model,status:'submitting',asset_ids:[]};
    this.jobs=[pending,...this.jobs];
    this.$nextTick(()=>{if(this.$refs.imageResults)this.$refs.imageResults.scrollTop=0;});
    const result=await this.request(options.generate,'POST',{name:this.generationName.trim()||undefined,revision:this.revision,visual_id:this.visual.id,request_key:this.requestKey,model:this.model,references:this.references.map(Number),settings:Object.fromEntries(Object.entries(this.settings).filter(([,v])=>v))});
    this.jobs=this.jobs.map(job=>job.id===pendingId?{...pending,id:result.id,status:result.status}:job);
    this.generationName='';
    this.requestKey=crypto.randomUUID();this.status='Image queued. You can keep editing.';
    // Keep the accepted job visible even if the follow-up status read fails.
    try{const d=await this.request(options.url);this.mergeJobs(d.jobs);this.assets=d.assets;this.credit(d);}catch(_){}
   }catch(e){this.jobs=this.jobs.map(job=>job.id===pendingId?{...job,id:'rejected:'+crypto.randomUUID(),status:'failed',localFailure:true,error:e.message}:job);this.error=e.message;}
   finally{this.generating=false;}
  },
  async retryImage(job){
   if(this.generating || !job.retry_url || job.status!=='failed')return;
   this.generating=true;this.retryingJob=job.id;this.error='';
   job.retryKey??=crypto.randomUUID();const pendingId='retry:'+job.retryKey;
   const pending={id:pendingId,name:job.name,prompt:job.prompt,model:job.model,status:'submitting',asset_ids:[]};this.jobs=[pending,...this.jobs];
   this.$nextTick(()=>{if(this.$refs.imageResults)this.$refs.imageResults.scrollTop=0;});
   try{
    const result=await this.request(job.retry_url,'POST',{request_key:job.retryKey});
    this.jobs=this.jobs.map(item=>item.id===pendingId?{...pending,id:result.id,status:result.status}:item);delete job.retryKey;
    this.status='Image queued again with a four-minute limit.';
    try{const data=await this.request(options.url);this.mergeJobs(data.jobs);this.assets=data.assets;this.credit(data);}catch(_){}
   }catch(e){this.jobs=this.jobs.filter(item=>item.id!==pendingId);this.error=e.message;}
   finally{this.generating=false;this.retryingJob=null;}
  },
  useJobImage(job,id){const target=this.document.visuals.find(v=>v.id===job.visual_id);if(target){target.asset_id=Number(id);this.changed();}},
  openMoveImage(job){this.moveJob=job;this.moveTarget=job.visual_id;this.$refs.moveImageDialog.showModal();},
  async moveImage(){
   if(this.movingImage||!this.moveJob)return;this.movingImage=true;
   try{if(!(await this.flush()))return;
    await this.request(options.moveBase+'/'+this.moveJob.id,'PATCH',{visual_id:this.moveTarget,revision:this.revision});
    const data=await this.request(options.url);this.mergeJobs(data.jobs);this.assets=data.assets;this.$refs.moveImageDialog.close();notify('Generated image moved.');
   }catch(e){this.error=e.message;}finally{this.movingImage=false;}
  },
  useImage(id){this.visual.asset_id=Number(id);this.editingVisual=false;this.changed();},
  newHotspot(rect={x:0.1,y:0.1,width:0.2,height:0.2}){if(!this.visual||this.visual.kind!=='scene'||this.visual.hotspots.length>=100)return;const h={id:crypto.randomUUID(),label:'Clickable area',description:'',...rect,action:'examine',target_id:null,object_id:null,overlay:false};this.visual.hotspots.push(h);this.hotspotId=h.id;this.drawing=false;this.beginRename(h);this.changed();},
  requestAreaDelete(id){if(this.preview)return;this.hotspotId=id;this.deleteAreaId=id;this.$refs.deleteAreaDialog.showModal();},
  get areaObjectToDelete(){
   const area=this.visual?.hotspots.find(h=>h.id===this.deleteAreaId);
   const object=this.objects.find(v=>v.id===area?.object_id);if(!object)return null;
   const shared=this.document.visuals.some(v=>(v.hotspots||[]).some(h=>h!==area && (h.object_id===object.id||h.target_id===object.id)) || (v.actions||[]).some(a=>a.target_id===object.id) || (v.hotspots||[]).some(h=>(h.inventory_rules||[]).some(r=>r.object_id===object.id)));
   return shared?null:object;
  },
  confirmAreaDelete(){
   const visual=this.visual,area=visual?.hotspots.find(h=>h.id===this.deleteAreaId);if(!area)return;
   const object=this.areaObjectToDelete;
   visual.hotspots=visual.hotspots.filter(h=>h.id!==area.id);
   if(object)this.document.visuals=this.document.visuals.filter(v=>v.id!==object.id);
   this.hotspotId='';this.deleteAreaId='';this.renamingId='';this.areaDrag=null;this.changed();this.$refs.deleteAreaDialog.close();
  },
  beginRename(h){if(this.preview)return;this.hotspotId=h.id;this.renamingId=h.id;this.labelDraft=h.label;},
  finishRename(h){
   if(this.renamingId!==h.id)return;const label=this.labelDraft.trim();this.renamingId='';if(!label)return;
   const oldLabel=h.label;h.label=label;
   let object=this.objects.find(v=>v.id===h.object_id);
   if(!object){
    if(this.document.visuals.length>=100){this.error='Area named, but no object visual could be added: this page has reached its 100-visual limit.';this.changed();return;}
    object={id:crypto.randomUUID(),kind:'object',name:label,asset_id:null,prompt:'',hotspots:[]};
    this.document.visuals.push(object);h.object_id=object.id;
   }else if(object.name===oldLabel && !this.document.visuals.some(v=>(v.hotspots||[]).some(other=>other.id!==h.id && other.object_id===object.id))){object.name=label;}
   this.changed();
  },
  startArea(e,h,handle='move'){
   if(this.preview||this.drawing||this.renamingId===h.id||e.button!==0)return;
   e.preventDefault();this.hotspotId=h.id;
   const rect=e.currentTarget.closest('[data-scene-canvas]').getBoundingClientRect();
   this.areaDrag={id:h.id,handle,startX:e.clientX,startY:e.clientY,rect,original:{x:h.x,y:h.y,width:h.width,height:h.height}};
   e.currentTarget.setPointerCapture(e.pointerId);
  },
  moveArea(e){
   const d=this.areaDrag;if(!d)return;const h=this.visual.hotspots.find(h=>h.id===d.id);if(!h)return;
   const dx=(e.clientX-d.startX)/d.rect.width,dy=(e.clientY-d.startY)/d.rect.height,o=d.original;
   const clamp=(n,min,max)=>Math.max(min,Math.min(max,n));
   if(d.handle==='move'){h.x=clamp(o.x+dx,0,1-o.width);h.y=clamp(o.y+dy,0,1-o.height);}
   else {
    let left=o.x,right=o.x+o.width,top=o.y,bottom=o.y+o.height;
    if(d.handle.includes('w'))left=clamp(o.x+dx,0,right-.005);
    if(d.handle.includes('e'))right=clamp(right+dx,left+.005,1);
    if(d.handle.includes('n'))top=clamp(o.y+dy,0,bottom-.005);
    if(d.handle.includes('s'))bottom=clamp(bottom+dy,top+.005,1);
    Object.assign(h,{x:left,y:top,width:right-left,height:bottom-top});
   }
  },
  endArea(cancel=false){const d=this.areaDrag;if(!d)return;const h=this.visual.hotspots.find(h=>h.id===d.id);if(h){if(cancel)Object.assign(h,d.original);else if(Object.keys(d.original).some(k=>h[k]!==d.original[k]))this.changed();}this.areaDrag=null;},
  areaKey(e,h){if(this.preview||e.target!==e.currentTarget)return;if(e.key==='Delete'||e.key==='Backspace'){e.preventDefault();this.requestAreaDelete(h.id);return;}if(e.key==='F2'){e.preventDefault();this.beginRename(h);return;}const delta={ArrowLeft:[-.005,0],ArrowRight:[.005,0],ArrowUp:[0,-.005],ArrowDown:[0,.005]}[e.key];if(!delta)return;e.preventDefault();this.hotspotId=h.id;if(e.shiftKey){h.width+=delta[0];h.height+=delta[1];}else{h.x=Math.max(0,Math.min(1-h.width,h.x+delta[0]));h.y=Math.max(0,Math.min(1-h.height,h.y+delta[1]));}this.clampArea();},
  sceneLabel(v){return 'Scene '+(this.scenes.findIndex(s=>s.id===v.id)+1)+' · '+v.name+' · '+(this.asset(v.asset_id)?.name||'No image');},
  get pickerAssets(){return this.assets.filter(a=>this.imagePickerPage==='uploads'?!a.page_ids?.length:(a.page_ids||[]).some(id=>String(id)===String(this.imagePickerPage)));},
  openVisualImagePicker(){this.imagePickerPage=String(this.editorPageId);this.$refs.visualImagePicker.showModal();},
  openInteractionPicker(kind){this.imagePickerPage=String(this.editorPageId);this.pickerKind=kind;this.$refs.interactionImagePicker.showModal();},
  selectObjectImage(id){
   if(!this.hotspot)return;let object=this.objects.find(v=>v.id===this.hotspot.object_id && !v.asset_id);
   if(object)object.asset_id=Number(id);
   else object=this.objects.find(v=>v.asset_id===Number(id));
   if(!object){if(this.document.visuals.length>=100){this.error='This page has reached its 100-visual limit.';return;}object={id:crypto.randomUUID(),kind:'object',name:this.asset(id)?.name||'Object image',asset_id:Number(id),prompt:'',hotspots:[]};this.document.visuals.push(object);}
   this.hotspot.object_id=object.id;this.changed();this.$refs.interactionImagePicker.close();
  },
  clampArea(){const h=this.hotspot;if(!h)return;h.x=Math.max(0,Math.min(.995,Number(h.x)||0));h.y=Math.max(0,Math.min(.995,Number(h.y)||0));h.width=Math.max(.005,Math.min(1-h.x,Number(h.width)||.005));h.height=Math.max(.005,Math.min(1-h.y,Number(h.height)||.005));this.changed();},
  point(e){const r=e.currentTarget.getBoundingClientRect();return {x:Math.max(0,Math.min(1,(e.clientX-r.left)/r.width)),y:Math.max(0,Math.min(1,(e.clientY-r.top)/r.height))};},
  enlarge(asset){if(this.suppressImageClick){this.suppressImageClick=false;return;}if(!this.drawing&&!this.preview&&asset)window.dispatchEvent(new CustomEvent('vsg-image',{detail:asset}));},
  cancelDraw(){this.drag=null;this.drawRect=null;this.drawing=false;},
  toggleDrawing(){if(this.drawing)this.cancelDraw();else{this.drawing=true;this.renamingId='';}},
  startDraw(e){
   if(!this.drawing||this.preview||e.button!==0)return;
   this.suppressImageClick=true;e.preventDefault();e.currentTarget.setPointerCapture(e.pointerId);
   this.drag={...this.point(e),pointerId:e.pointerId};this.moveDraw(e);
  },
  moveDraw(e){
   if(!this.drag||e.pointerId!==this.drag.pointerId)return;
   const p=this.point(e),a=this.drag;
   this.drawRect={x:Math.min(a.x,p.x),y:Math.min(a.y,p.y),width:Math.abs(a.x-p.x),height:Math.abs(a.y-p.y)};
  },
  endDraw(e){
   if(!this.drag||e.pointerId!==this.drag.pointerId)return;
   this.moveDraw(e);const rect=this.drawRect;this.drag=null;this.drawRect=null;
   if(e.currentTarget.hasPointerCapture?.(e.pointerId))e.currentTarget.releasePointerCapture(e.pointerId);
   if(rect.width>=.005&&rect.height>=.005)this.newHotspot(rect);
   setTimeout(()=>{this.suppressImageClick=false;},0);
  },
  addInventoryRule(objectId=null){if(!this.hotspot)return;this.hotspot.inventory_rules??=[];if(this.hotspot.inventory_rules.length>=100)return;this.hotspot.inventory_rules.push({object_id:objectId,response:'',target_id:null,consume:false});this.changed();},
  addAllInventoryRules(){if(!this.hotspot)return;for(const object of this.objects)if(!(this.hotspot.inventory_rules||[]).some(r=>r.object_id===object.id))this.addInventoryRule(object.id);},
  addAreaChoice(){if(!this.hotspot)return;this.hotspot.choices??=[];if(this.hotspot.choices.length>=20)return;this.hotspot.choices.push({label:'New action',response:'',target_id:null});this.changed();},
  get modalArea(){return this.playVisuals.find(v=>v.id===this.modal?.sceneId)?.hotspots?.find(h=>h.id===this.modal?.areaId);},
  openAreaInteraction(h){this.inventoryOpen=false;this.showObjectModal({areaId:h.id,sceneId:this.previewId,label:h.label,text:h.interaction_text||'',showItems:false});},
  useInventoryOnArea(inventoryId){
   const area=this.modalArea;if(!this.preview||area?.action!=='inventory'||this.modal.sceneId!==this.previewId)return;
   const index=this.inventory.findIndex(item=>(item.inventoryId||item.id)===inventoryId);if(index<0)return;
   const rule=(area.inventory_rules||[]).find(r=>r.object_id===this.inventory[index].id);
   if(rule?.consume)this.inventory.splice(index,1);
   this.areaOutcome(area,rule?.response?.trim() || area.fallback_text?.trim() || 'Nothing happens.',rule?.target_id);
  },
  chooseAreaAction(index){const area=this.modalArea;if(!this.preview||area?.action!=='choices'||this.modal.sceneId!==this.previewId)return;const choice=area.choices?.[index];if(choice)this.areaOutcome(area,choice.response||'',choice.target_id);},
  areaOutcome(area,text,targetId){
   if(targetId && targetId!==this.previewId && this.scenes.some(v=>v.id===targetId)){
    this.transitionScene(targetId);if(text)this.showObjectModal({label:area.label,text,url:null});
   }else {this.modal={...this.modal,text,showItems:false};}
  },
  addObjectAction(){if(this.visual?.kind!=='object')return;this.visual.actions??=[];if(this.visual.actions.length>=50)return;this.visual.actions.push({id:crypto.randomUUID(),label:'New action',target_id:null});this.changed();},
  removeObjectAction(id){this.visual.actions=(this.visual.actions||[]).filter(a=>a.id!==id);this.changed();},
  showObjectModal(value){if(this.sceneTransition || this.modalTimer!==null){this.pendingModal=value;}else{this.modal=value;}},
  clearSceneTransition(){clearTimeout(this.transitionTimer);clearTimeout(this.modalTimer);this.transitionTimer=null;this.modalTimer=null;this.sceneTransition=false;this.outgoingScene=null;this.pendingModal=null;},
  transitionScene(id,nextPageId=this.previewPageId){
   const target=(nextPageId===this.previewPageId?this.scenes:(this.previewPages.find(p=>p.id===nextPageId)?.visuals||[]).filter(v=>v.kind==='scene')).find(v=>v.id===id);if(!target||id===this.previewId)return;
   this.clearSceneTransition();this.outgoingScene=this.displayed;this.modal=null;this.sceneTransition=true;this.previewPageId=nextPageId;this.previewId=id;
   this.transitionTimer=setTimeout(()=>{this.sceneTransition=false;this.outgoingScene=null;this.transitionTimer=null;this.modalTimer=setTimeout(()=>{this.modalTimer=null;if(this.preview)this.modal=this.pendingModal;this.pendingModal=null;},250);},200);
  },
  openInventory(item){this.inventoryOpen=false;this.showObjectModal({label:item.name,text:item.description||'',url:this.asset(item.asset_id)?.url,inventoryId:item.inventoryId||item.id});},
  get inventoryActions(){const item=this.inventory.find(v=>(v.inventoryId||v.id)===this.modal?.inventoryId);return item?(this.objects.find(v=>v.id===item.id)?.actions||[]):[];},
  performObjectAction(id){
   if(!this.preview||!this.modal?.inventoryId)return;
   const index=this.inventory.findIndex(v=>(v.inventoryId||v.id)===this.modal.inventoryId);if(index<0)return;
   const item=this.inventory[index],action=(this.objects.find(v=>v.id===item.id)?.actions||[]).find(a=>a.id===id);
   const target=this.objects.find(v=>v.id===action?.target_id);if(!target)return;
   const next={...target,inventoryId:item.inventoryId||item.id};this.inventory.splice(index,1,next);this.openInventory(next);
  },
  startPreview(){this.clearSceneTransition();this.previewPageId=this.editorPageId;if(!this.scenes.length){notify('Add a scene before testing interactions.','error');return;}this.previewId=this.visual?.kind==='scene'?this.visual.id:this.scenes[0]?.id;this.preview=!!this.previewId;this.inventory=[];this.inventoryOpen=false;this.collected=[];this.modal=null;this.cancelDraw();if(this.preview && !this.$refs?.previewDialog?.open)this.$refs?.previewDialog?.showModal();},
  closePreview(){this.clearSceneTransition();this.inventoryOpen=false;this.preview=false;this.modal=null;this.inventory=[];this.collected=[];},
  nextPreviewPage(){
   if(this.sceneTransition)return;
   const index=this.previewPages.findIndex(p=>p.id===this.previewPageId),next=this.previewPages[index+1];
   if(index<0){this.showObjectModal({label:'Next page',text:'Reload the editor to load storyboard pages.',url:null});return;}
   if(!next){this.showObjectModal({label:'Story complete',text:'You have reached the end of the story.',url:null});return;}
   const scene=next.visuals.find(v=>v.kind==='scene');
   if(!scene){this.showObjectModal({label:next.title||'Next page',text:'Add a scene to the next page before testing this transition.',url:null});return;}
   this.inventoryOpen=false;this.transitionScene(scene.id,next.id);
  },
  activate(h){
   if(!this.preview){if(!this.drawing)this.hotspotId=h.id;return;}
   if(h.action==='next_page'){this.nextPreviewPage();return;}
   if(['inventory','choices'].includes(h.action)){this.openAreaInteraction(h);return;}
   if(h.target_id)this.transitionScene(h.target_id);
   const object=this.playVisuals.find(v=>v.id===h.object_id);
   if(h.action==='pickup' && object){if(!this.inventory.some(v=>v.id===object.id))this.inventory.push({...object,inventoryId:h.id});this.collected.push(h.id);}
   if(h.action==='examine')this.showObjectModal({label:h.label,text:h.description||'',url:null});
   if(h.action==='inspect' && object)this.showObjectModal({label:object.name,text:object.description||'',url:this.asset(object.asset_id)?.url});
   if(h.action==='pickup' && object){const item=this.inventory.find(v=>v.id===object.id);if(item)this.openInventory(item);}
  }
 };
}
