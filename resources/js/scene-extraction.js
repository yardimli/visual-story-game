import {notify} from './toasts.js';
const cropSources=new Map();
function cropSource(url){
 if(!cropSources.has(url))cropSources.set(url,new Promise((resolve,reject)=>{const image=new Image();image.onload=()=>resolve(image);image.onerror=()=>{cropSources.delete(url);reject(new Error('Could not load the scene image for crop previews.'));};image.src=url;}));
 return cropSources.get(url);
}

export function sceneExtraction(options){return {
 extractionPreview:'composition', extractionReferenceMode:'original_and_annotated', extractionMode:'collectibles', extractionPlanChoice:null, extractionRun:false, extractionSource:null, extractionBatches:[], extractionId:null, extractionAreas:[], extractionBusy:false, extractionPreparing:false, extractionRefreshing:false, extractionProgress:'', extractionError:'', extractionModel:'', extractionBounds:{}, extractionPrompts:{}, extractionQuotes:{}, extractionHidden:[], extractionInventoryRevision:0, extractionTimer:null,
 get extractionBatch(){return this.extractionBatches.find(b=>b.id===Number(this.extractionId));},
 get sceneExtractions(){return this.extractionBatches.filter(b=>b.visual_id===this.selected && (b.mode||'collectibles')===this.extractionMode);},
 get extractionBackground(){return this.extractionBatch?.outputs.find(o=>o.type==='background')?.accepted_asset_id;},
 get extractionLayers(){return this.extractionBatch?.outputs.filter(o=>['layer','switch_on'].includes(o.type) && o.accepted_asset_id)||[];},
 async drawExtractionCrop(canvas,bounds,sourceId){
  const url=this.asset(sourceId)?.url;if(!url)return;
  const key=JSON.stringify([url,bounds]);if(canvas.dataset.cropKey===key)return;canvas.dataset.cropKey=key;
  try{const source=await cropSource(url);if(canvas.dataset.cropKey!==key)return;
   const x=Math.floor(bounds.x*source.naturalWidth),y=Math.floor(bounds.y*source.naturalHeight);
   const width=Math.max(1,Math.min(source.naturalWidth-x,Math.round(bounds.width*source.naturalWidth))),height=Math.max(1,Math.min(source.naturalHeight-y,Math.round(bounds.height*source.naturalHeight)));
   canvas.width=width;canvas.height=height;canvas.getContext('2d').drawImage(source,x,y,width,height,0,0,width,height);
  }catch(e){delete canvas.dataset.cropKey;this.extractionError=e.message;}
 },
 async openExtraction(mode='collectibles'){
  this.extractionMode=mode;
  if(!(await this.flush()))return;this.extractionError='';this.extractionProgress='';this.extractionModel=this.model;this.extractionSource=this.visual.asset_id;
  this.extractionAreas=(this.visual?.hotspots||[]).filter(h=>h.action===(mode==='switches'?'state_v2':'pickup_v2')).map(h=>({id:h.id,label:h.label,eligible:this.inventoryItems.some(i=>i.id===h.object_id),selected:true,bounds:{x:h.x,y:h.y,width:h.width,height:h.height}}));
  await this.refreshExtraction(true);if(this.extractionError){notify(this.extractionError,'error');return;}
  if(this.sceneExtractions.length){this.extractionPlanChoice=this.sceneExtractions[0].id;this.$refs.extractionPlanDialog.showModal();}
  else this.chooseExtractionPlan(false);
 },
 chooseExtractionPlan(existing){
  const batch=existing?this.sceneExtractions.find(b=>b.id===Number(this.extractionPlanChoice)):null;
  if(existing && !batch)return;
  this.extractionId=batch?.id??null;this.extractionSource=batch?.source_asset_id??this.visual.asset_id;
  this.extractionQuotes={};this.extractionBounds={};this.extractionHidden=[];this.extractionPreview='composition';
  this.$refs.extractionPlanDialog?.close();this.$refs.extractionDialog.showModal();
  this.scheduleExtractionRefresh();
 },
 closeExtraction(){clearInterval(this.extractionTimer);this.$refs.extractionDialog.close();},
 scheduleExtractionRefresh(){
  clearTimeout(this.extractionTimer);this.extractionTimer=null;
  if(this.$refs.extractionDialog?.open && this.extractionBatch?.outputs.some(o=>this.extractionPending(o)))this.extractionTimer=setTimeout(()=>this.refreshExtraction(),5000);
 },
 async refreshExtraction(force=false){
  if((!force && !this.$refs.extractionDialog?.open) || this.extractionBusy || this.extractionRefreshing)return;
  this.extractionRefreshing=true;
  try{const data=await this.request(options.extractionUrl);this.extractionBatches=data.batches.map(b=>({...b,outputs:b.outputs.map(o=>({...o,bounds:this.extractionBounds[o.id]??o.bounds}))}));this.extractionInventoryRevision=data.inventory_revision;
   const images=await this.request(options.url);this.assets=images.assets;this.jobs=images.jobs;
  }catch(e){this.extractionError=e.message;}finally{this.extractionRefreshing=false;if(!this.extractionError)this.scheduleExtractionRefresh();}
 },
 async prepareExtraction(){
  if(this.extractionBusy || this.extractionPreparing)return;
  this.extractionPreparing=true;this.extractionBusy=true;this.extractionError='';this.extractionProgress='Saving scene changes…';
  try{
   await this.$nextTick?.();
   if(!(await this.flush())){this.extractionError=this.error||'Scene changes could not be saved. Please resolve the save error and try again.';return;}
   this.extractionProgress='Preparing cropped references and the extraction plan…';
   const batch=await this.request(options.extractionUrl,'POST',{background_references:this.extractionReferenceMode,mode:this.extractionMode,visual_id:this.selected,revision:this.revision,areas:this.extractionAreas.filter(a=>a.selected).map(a=>({id:a.id,bounds:a.bounds}))});
   this.document=batch.document;this.revision=batch.revision;this.inventoryItems=batch.inventory_items;this.extractionInventoryRevision=batch.inventory_revision;
   window.dispatchEvent(new CustomEvent('vsg-inventory',{detail:this.inventoryItems}));
   this.extractionAreas.forEach(a=>a.eligible=this.inventoryItems.some(i=>i.id===this.visual.hotspots.find(h=>h.id===a.id)?.object_id));
   this.extractionBatches.unshift(batch);this.extractionId=batch.id;this.extractionQuotes={};
   this.extractionProgress='Loading the plan and image previews…';
   const images=await this.request(options.url);this.assets=images.assets;this.jobs=images.jobs;
   this.extractionProgress='Plan ready. Review the outputs below before generating images.';
  }catch(e){this.extractionError=e.message;}
  finally{this.extractionPreparing=false;this.extractionBusy=false;if(this.extractionError)this.extractionProgress='';}
 },
 async extractionAction(output,action,assetId=null){
  if(this.extractionBusy)return;this.extractionBusy=true;this.extractionError='';
  try{
   const data=await this.request(options.extractionUrl+'/'+this.extractionId+'/outputs/'+output.id,'POST',{action,model:this.extractionModel||undefined,prompt:this.extractionPrompts[output.id]??output.prompt,...(assetId?{asset_id:assetId}:{}),...(action==='align'?{bounds:this.extractionBounds[output.id]??output.bounds}:{})});
   if(action==='quote')this.extractionQuotes[output.id]={estimate:data.estimate,model:this.extractionModel,prompt:this.extractionPrompts[output.id]??output.prompt};
   else{this.extractionBatches=this.extractionBatches.map(b=>b.id===data.id?data:b);delete this.extractionQuotes[output.id];if(action==='align')delete this.extractionBounds[output.id];}
  }catch(e){this.extractionError=e.message;}finally{this.extractionBusy=false;await this.refreshExtraction();}
 },
 extractionQuoted(output){const q=this.extractionQuotes[output.id];return q && q.model===this.extractionModel && q.prompt===(this.extractionPrompts[output.id]??output.prompt);},
 extractionPending(output){return output.jobs?.some(j=>['queued','running'].includes(j.status));},
 async quoteMissingExtraction(){
  if(this.extractionRun || this.extractionBusy)return;this.extractionRun=true;
  try{for(const o of this.extractionBatch.outputs.filter(o=>!o.accepted_asset_id && !this.extractionPending(o))){await this.extractionAction(o,'quote');if(this.extractionError)break;}}finally{this.extractionRun=false;}
 },
 get extractionMissing(){return this.extractionBatch?.outputs.filter(o=>!o.accepted_asset_id && !this.extractionPending(o))||[];},
 get extractionTotal(){return this.extractionMissing.reduce((sum,o)=>sum+(this.extractionQuotes[o.id]?.estimate||0),0);},
 async generateMissingExtraction(){
  if(this.extractionRun || this.extractionBusy || !this.extractionMissing.length || !this.extractionMissing.every(o=>this.extractionQuoted(o)))return;
  const outputs=[...this.extractionMissing];this.extractionRun=true;
  try{for(const o of outputs){await this.extractionAction(o,'generate');if(this.extractionError)break;}}finally{this.extractionRun=false;}
 },
 async activateExtraction(){
  if(this.extractionBusy)return;this.extractionBusy=true;this.extractionError='';
  try{if(!(await this.flush()))return;const applied=await this.request(options.extractionUrl+'/'+this.extractionId+'/activate','POST',{revision:this.revision,inventory_revision:this.extractionInventoryRevision});
   if(applied.chat_message)window.dispatchEvent(new CustomEvent('vsg-chat-message',{detail:applied.chat_message}));
   const data=await this.request(options.url);this.document=data.document;this.revision=data.revision;this.assets=data.assets;this.inventoryItems=data.inventory_items;this.flagDefinitions=data.game_flags;this.previewPages=data.preview_pages;
   window.dispatchEvent(new CustomEvent('vsg-inventory',{detail:this.inventoryItems}));window.dispatchEvent(new CustomEvent('vsg-flags',{detail:this.flagDefinitions}));notify('Accepted extraction activated.');this.closeExtraction();
  }catch(e){this.extractionError=e.message;}finally{this.extractionBusy=false;}
 }
};}
