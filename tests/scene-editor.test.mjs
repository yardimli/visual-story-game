import test from 'node:test';
import assert from 'node:assert/strict';
import {sceneEditor} from '../resources/js/scene-editor.js';

test('note inspection, stone transition and key pickup preview leave authored scene unchanged', () => {
 const editor=sceneEditor({});
 editor.document={visuals:[
  {id:'porch',kind:'scene',hotspots:[]},
  {id:'opened',kind:'scene',hotspots:[{id:'key-area'}]},
  {id:'note',kind:'object',asset_id:1},
  {id:'key',kind:'object',asset_id:2}
 ]};
 editor.assets=[{id:1,url:'/note.png'},{id:2,url:'/key.png'}];
 editor.selected='porch';const original=JSON.stringify(editor.document);
 editor.startPreview();
 editor.activate({id:'note-area',label:'Note',description:'Lift the lid.',action:'inspect',object_id:'note'});
 assert.equal(editor.modal.url,'/note.png');assert.equal(editor.previewId,'porch');
 editor.activate({id:'stone-area',action:'transition',target_id:'opened'});
 assert.equal(editor.previewId,'opened');
 const pickup={id:'key-area',action:'pickup',object_id:'key'};
 editor.activate(pickup);editor.activate(pickup);
 assert.equal(editor.inventory.length,1);assert.equal(editor.currentHotspots.length,0);
 assert.equal(JSON.stringify(editor.document),original);
 editor.startPreview();assert.equal(editor.inventory.length,0);assert.equal(editor.previewId,'porch');
});

test('rectangle coordinates stay in image bounds and edits during save are serialized', async () => {
 const editor=sceneEditor({url:'/visuals'});editor.loading=false;editor.changed=()=>{editor.dirty=true;};
 editor.document={visuals:[{id:'scene',kind:'scene',hotspots:[{id:'h',x:2,y:-1,width:2,height:-2}]}]};editor.selected='scene';editor.hotspotId='h';
 editor.clampArea();assert.equal(editor.hotspot.x,.995);assert.equal(editor.hotspot.y,0);
 assert.ok(editor.hotspot.x+editor.hotspot.width<=1);assert.equal(editor.hotspot.height,.005);
 const revisions=[];editor.request=async (_url,_method,body)=>{revisions.push(body.revision);if(revisions.length===1)editor.dirty=true;return {revision:body.revision+1};};
 assert.equal(await editor.flush(),true);assert.deepEqual(revisions,[1,2]);assert.equal(editor.revision,3);
});

test('copying a generated prompt does not change the draft or selected references', async () => {
 const editor=sceneEditor({generationMode:true});
 editor.document={visuals:[{id:'porch',kind:'scene',prompt:'Current draft',hotspots:[]}]};
 editor.selected='porch';editor.references=['42'];let copied;
 const descriptor=Object.getOwnPropertyDescriptor(globalThis,'navigator');
 Object.defineProperty(globalThis,'navigator',{configurable:true,value:{clipboard:{writeText:async text=>{copied=text;}}}});
 try {
  await editor.copyPrompt({id:7,prompt:'Original porch prompt'});
  assert.equal(copied,'Original porch prompt');assert.equal(editor.copiedPrompt,7);
  assert.equal(editor.visual.prompt,'Current draft');assert.deepEqual(editor.references,['42']);
 } finally { if(descriptor)Object.defineProperty(globalThis,'navigator',descriptor);else delete globalThis.navigator; }
});

test('generation immediately prepends a placeholder and retains accepted job if status read fails', async () => {
 const originalWindow=globalThis.window;globalThis.window={};
 try {
  const editor=sceneEditor({generate:'/generate',url:'/visuals'});
  editor.document={visuals:[{id:'scene',name:'Porch',prompt:'A porch'}]};editor.selected='scene';
  editor.jobs=[{id:1,status:'completed'}];editor.flush=async()=>true;
  editor.$refs={imageResults:{scrollTop:500}};editor.$nextTick=fn=>fn();
  let submitted,finish;const waiting=new Promise(resolve=>{submitted=resolve;});
  editor.request=async(url)=>{if(url==='/generate'){submitted();return new Promise(resolve=>{finish=resolve;});}throw new Error('Status unavailable');};
  const generation=editor.generate();await waiting;
  assert.equal(editor.jobs[0].status,'submitting');assert.equal(editor.jobs[0].prompt,'A porch');
  assert.equal(editor.jobs[1].id,1);assert.equal(editor.$refs.imageResults.scrollTop,0);
  await editor.generate();assert.equal(editor.jobs.length,2);
  finish({id:2,status:'queued'});await generation;
  assert.equal(editor.jobs[0].id,2);assert.equal(editor.jobs[0].status,'queued');assert.equal(editor.error,'');
  assert.equal(editor.generating,false);
 } finally {if(originalWindow===undefined)delete globalThis.window;else globalThis.window=originalWindow;}
});

test('area movement and resizing stay within bounds, cancel restores, and deletion requires confirmation',()=>{
 const editor=sceneEditor({});let saves=0,opened=0;
 const h={id:'area',label:'Note',x:.2,y:.3,width:.2,height:.2};
 editor.document={visuals:[{id:'scene',kind:'scene',hotspots:[h]}]};editor.selected='scene';editor.changed=()=>saves++;
 editor.$refs={deleteAreaDialog:{showModal:()=>opened++,close:()=>{}}};
 const target={closest:()=>({getBoundingClientRect:()=>({width:1000,height:500})}),setPointerCapture:()=>{}};
 const event={button:0,clientX:100,clientY:100,pointerId:1,currentTarget:target,preventDefault:()=>{}};
 editor.startArea(event,h);editor.moveArea({clientX:2000,clientY:-1000});assert.equal(h.x,.8);assert.equal(h.y,0);
 editor.endArea(true);assert.equal(h.x,.2);assert.equal(h.y,.3);assert.equal(saves,0);
 editor.startArea(event,h,'se');editor.moveArea({clientX:2000,clientY:2000});editor.endArea();
 assert.equal(h.width,.8);assert.equal(h.height,.7);assert.equal(saves,1);
 editor.requestAreaDelete(h.id);assert.equal(opened,1);assert.equal(editor.visual.hotspots.length,1);
 editor.confirmAreaDelete();assert.equal(editor.visual.hotspots.length,0);assert.equal(saves,2);
});
test('image selection creates or reuses an object visual without changing selected scene',()=>{
 const editor=sceneEditor({});editor.changed=()=>{};editor.$refs={interactionImagePicker:{close:()=>{}}};
 editor.document={visuals:[{id:'scene',kind:'scene',name:'Duplicate',hotspots:[{id:'h'}]},{id:'second',kind:'scene',name:'Duplicate',hotspots:[]}]};
 editor.selected='scene';editor.hotspotId='h';editor.assets=[{id:5,name:'Note',url:'/note.png'}];
 editor.selectObjectImage(5);const id=editor.hotspot.object_id;editor.selectObjectImage(5);
 assert.equal(editor.objects.length,1);assert.equal(editor.hotspot.object_id,id);assert.equal(editor.selected,'scene');
 assert.notEqual(editor.sceneLabel(editor.scenes[0]),editor.sceneLabel(editor.scenes[1]));
 editor.beginRename(editor.hotspot);editor.labelDraft='New label';editor.finishRename(editor.hotspot);assert.equal(editor.hotspot.label,'New label');
});

test('named visual creation waits for submission and duplication preserves independent areas',()=>{
 const editor=sceneEditor({});let saves=0;editor.changed=()=>saves++;editor.$nextTick=fn=>fn();
 editor.$refs={visualNameDialog:{showModal(){},close(){}},visualNameInput:{focus(){},select(){}}};
 editor.requestVisualName('scene');assert.equal(editor.document.visuals.length,0);assert.equal(saves,0);
 editor.visualName='  Garden  ';editor.createNamedVisual();assert.equal(editor.visual.name,'Garden');
 editor.visual.hotspots=[{id:'area',label:'Rose'}];const original=editor.visual;
 editor.requestVisualName('duplicate');assert.equal(editor.document.visuals.length,1);
 editor.visualName='Garden at night';editor.createNamedVisual();
 assert.equal(editor.visual.name,'Garden at night');assert.notEqual(editor.visual.id,original.id);
 assert.notEqual(editor.visual.hotspots[0].id,original.hotspots[0].id);
 editor.visual.hotspots[0].label='Stone';assert.equal(original.hotspots[0].label,'Rose');
});
test('visual deletion requires confirmation and clears dependent links without deleting assets',()=>{
 const editor=sceneEditor({});let saves=0;editor.changed=()=>saves++;
 editor.$refs={deleteVisualDialog:{showModal(){},close(){}}};
 editor.document={visuals:[{id:'scene',kind:'scene',hotspots:[{id:'h',object_id:'object',target_id:null,action:'pickup',overlay:true}]},{id:'object',kind:'object',asset_id:9,hotspots:[]}]};
 editor.assets=[{id:9}];editor.selected='object';editor.requestVisualDelete();
 assert.equal(editor.document.visuals.length,2);assert.equal(editor.dependentAreas,1);assert.equal(saves,0);
 editor.confirmVisualDelete();const h=editor.visual.hotspots[0];
 assert.equal(editor.selected,'scene');assert.equal(h.object_id,null);assert.equal(h.action,'examine');assert.equal(h.overlay,false);assert.equal(editor.assets.length,1);
 editor.requestVisualDelete();editor.confirmVisualDelete();assert.equal(editor.selected,'');assert.equal(editor.document.visuals.length,0);
});

test('interaction proposal updates selected description and prepares linked object, rejecting stale changes',()=>{
 const editor=sceneEditor({});editor.changed=()=>{};
 editor.document={visuals:[{id:'scene',kind:'scene',hotspots:[{id:'envelope',description:'Old',action:'inspect',object_id:null}]}]};editor.selected='scene';editor.hotspotId='envelope';
 const proposal={_interaction:{page_id:4,visual_id:'scene',hotspot_id:'envelope',revision:1},interaction:{description:'A sealed envelope.',object:{name:'Letter',prompt:'Readable letter with clue.'},action:'inspect'}};
 const id=editor.applyInteractionProposal(proposal,4);
 assert.equal(editor.hotspot.description,'A sealed envelope.');assert.equal(editor.hotspot.object_id,id);
 assert.equal(editor.objects[0].prompt,'Readable letter with clue.');assert.equal(editor.objects[0].asset_id,null);
 assert.equal(editor.selected,'scene');assert.equal(editor.objects.length,1);
 editor.applyInteractionProposal(proposal,4);assert.equal(editor.objects.length,1);
 editor.revision=2;assert.throws(()=>editor.applyInteractionProposal(proposal,4),/fresh suggestion/);
 editor.revision=1;editor.hotspotId='other';assert.throws(()=>editor.applyInteractionProposal(proposal,4),/fresh suggestion/);
});

test('using another visual generation preserves the fixed generation workspace selection',()=>{
 const editor=sceneEditor({generationMode:true});editor.changed=()=>{};
 editor.document={visuals:[{id:'current',asset_id:null},{id:'other',asset_id:null}]};editor.selected='current';
 editor.useJobImage({visual_id:'other'},12);
 assert.equal(editor.selected,'current');assert.equal(editor.visual.asset_id,null);assert.equal(editor.document.visuals[1].asset_id,12);
});

test('naming a drawn area creates one linked object and retains scene editing context',()=>{
 const editor=sceneEditor({});editor.changed=()=>{};
 editor.document={visuals:[{id:'scene',kind:'scene',hotspots:[]}]};editor.selected='scene';
 editor.newHotspot();const h=editor.hotspot;
 assert.equal(editor.renamingId,h.id);assert.equal(editor.objects.length,0);
 editor.labelDraft=' Envelope ';editor.finishRename(h);
 const object=editor.objects[0];assert.equal(object.name,'Envelope');assert.equal(h.object_id,object.id);
 assert.equal(editor.selected,'scene');assert.equal(h.action,'examine');assert.equal(object.asset_id,null);
 editor.finishRename(h);assert.equal(editor.objects.length,1);
 editor.beginRename(h);editor.labelDraft='Letter';editor.finishRename(h);
 assert.equal(object.name,'Letter');assert.equal(editor.objects.length,1);
 editor.$refs={interactionImagePicker:{close(){}}};editor.selectObjectImage(9);
 assert.equal(object.asset_id,9);assert.equal(h.object_id,object.id);assert.equal(editor.objects.length,1);
});

test('cancelled names and visual limits do not create stray objects',()=>{
 const editor=sceneEditor({});editor.changed=()=>{};
 editor.document={visuals:[{id:'scene',kind:'scene',hotspots:[]}]};editor.selected='scene';
 editor.newHotspot();editor.labelDraft='';editor.finishRename(editor.hotspot);assert.equal(editor.objects.length,0);
 editor.document.visuals.push(...Array.from({length:99},(_,i)=>({id:String(i),kind:'object',hotspots:[]})));
 editor.beginRename(editor.hotspot);editor.labelDraft='Stone';editor.finishRename(editor.hotspot);
 assert.equal(editor.document.visuals.length,100);assert.equal(editor.hotspot.label,'Stone');assert.equal(editor.hotspot.object_id,null);
 assert.match(editor.error,/100-visual limit/);
});

test('drawing previews a bounded rectangle before release without zooming or saving',()=>{
 const editor=sceneEditor({});let saves=0,zooms=0;
 editor.changed=()=>saves++;editor.document={visuals:[{id:'scene',kind:'scene',hotspots:[]}]};editor.selected='scene';
 const oldWindow=globalThis.window;globalThis.window={dispatchEvent(){zooms++;}};
 const target={getBoundingClientRect:()=>({left:0,top:0,width:100,height:100}),setPointerCapture(){},hasPointerCapture(){return true;},releasePointerCapture(){}};
 const event=(x,y)=>({button:0,pointerId:1,clientX:x,clientY:y,currentTarget:target,preventDefault(){}});
 try {
  editor.toggleDrawing();editor.enlarge({url:'/scene.png'});assert.equal(zooms,0);
  editor.startDraw(event(80,70));editor.moveDraw(event(20,10));
  assert.deepEqual(editor.drawRect,{x:.2,y:.1,width:.6000000000000001,height:.6});
  assert.equal(saves,0);assert.equal(editor.visual.hotspots.length,0);
  editor.moveDraw(event(-10,150));assert.equal(editor.drawRect.x,0);assert.equal(editor.drawRect.height,.30000000000000004);
  editor.endDraw(event(20,10));assert.equal(editor.drawRect,null);assert.equal(saves,1);assert.equal(editor.visual.hotspots.length,1);
  editor.enlarge({url:'/scene.png'});assert.equal(zooms,0);
  editor.toggleDrawing();editor.startDraw(event(20,20));editor.cancelDraw();editor.endDraw(event(70,70));
  assert.equal(editor.drawRect,null);assert.equal(editor.visual.hotspots.length,1);
 } finally {if(oldWindow===undefined)delete globalThis.window;else globalThis.window=oldWindow;}
});

test('object interaction generation saves before navigating and keeps scene image intact',async()=>{
 const editor=sceneEditor({});const events=[];
 editor.changed=()=>{};
 editor.document={visuals:[{id:'scene',kind:'scene',asset_id:12,hotspots:[{id:'area',label:'Envelope',action:'inspect',object_id:'envelope'}]},{id:'envelope',kind:'object',asset_id:null,hotspots:[]}]};
 editor.selected='scene';editor.hotspotId='area';
 const oldWindow=globalThis.window;globalThis.window={vsgAutosave:{flush:async()=>{events.push('form');return true;}},location:{assign:url=>events.push(url)}};
 editor.flush=async()=>{events.push('visuals');return true;};
 try {
  assert.equal(editor.generationVisual.id,'envelope');
  await editor.openGeneration('/image-studio');assert.deepEqual(events,['form','visuals','/image-studio?visual=envelope']);
  editor.useJobImage({visual_id:'envelope'},99);assert.equal(editor.objects[0].asset_id,99);assert.equal(editor.scenes[0].asset_id,12);
  editor.hotspot.action='pickup';assert.equal(editor.generationVisual.id,'envelope');
  editor.hotspot.action='examine';assert.equal(editor.generationVisual.id,'scene');
  editor.hotspot.action='inspect';events.length=0;editor.flush=async()=>false;
  await editor.openGeneration('/image-studio');assert.deepEqual(events,['form']);
  editor.hotspot.object_id=null;editor.flush=async()=>true;events.length=0;
  await editor.openGeneration('/image-studio');assert.equal(editor.generationVisual.name,'Envelope');
  assert.equal(events[1],'/image-studio?visual='+editor.hotspot.object_id);
  assert.equal(editor.selected,'scene');
 }finally{if(oldWindow===undefined)delete globalThis.window;else globalThis.window=oldWindow;}
});

test('visual selection persists in the URL and restores without losing page or tab',()=>{
 const oldWindow=globalThis.window;
 globalThis.window={location:{href:'http://localhost:8010/stories/2/edit?page=4&tab=visuals'},history:{state:{test:true},replaceState(state,unused,url){this.state=state;globalThis.window.location.href=String(url);}}};
 try {
  const editor=sceneEditor({});editor.document={visuals:[{id:'scene',kind:'scene'},{id:'object',kind:'object'}]};
  editor.choose('object');let url=new URL(window.location.href);
  assert.equal(url.searchParams.get('visual'),'object');assert.equal(url.searchParams.get('page'),'4');assert.equal(url.searchParams.get('tab'),'visuals');
  const restored=sceneEditor({});restored.document=editor.document;restored.restoreVisual();assert.equal(restored.selected,'object');
  restored.document={visuals:[{id:'scene',kind:'scene'}]};restored.restoreVisual();assert.equal(restored.selected,'scene');
  assert.equal(new URL(window.location.href).searchParams.get('visual'),'scene');
  restored.choose('');assert.equal(new URL(window.location.href).searchParams.has('visual'),false);
 }finally{if(oldWindow===undefined)delete globalThis.window;else globalThis.window=oldWindow;}
});

test('visual type conversion preserves artwork and confirms removal of incompatible areas and links',()=>{
 const editor=sceneEditor({});let saved=0,opened=0;editor.changed=()=>saved++;
 editor.$refs={visualTypeDialog:{showModal(){opened++;},close(){}}};
 const scene={id:'scene',kind:'scene',name:'Envelope',prompt:'Prompt',asset_id:9,hotspots:[{id:'own'}]};
 const other={id:'other',kind:'scene',hotspots:[{id:'link',target_id:'scene',action:'transition'}]};
 editor.document={visuals:[scene,other]};editor.selected='scene';
 editor.requestVisualType('object');assert.equal(opened,1);assert.equal(scene.kind,'scene');assert.equal(saved,0);
 editor.typeChange=null;editor.confirmVisualType();assert.equal(scene.hotspots.length,1);
 editor.requestVisualType('object');editor.confirmVisualType();assert.equal(scene.kind,'object');assert.equal(scene.hotspots.length,0);
 assert.equal(other.hotspots[0].target_id,null);assert.equal(other.hotspots[0].action,'examine');assert.equal(scene.asset_id,9);assert.equal(scene.prompt,'Prompt');
 other.hotspots=[{id:'inspect',object_id:'scene',action:'inspect',overlay:true}];
 editor.requestVisualType('scene');assert.equal(scene.kind,'object');editor.confirmVisualType();
 assert.equal(scene.kind,'scene');assert.equal(other.hotspots[0].object_id,null);assert.equal(other.hotspots[0].overlay,false);assert.equal(other.hotspots[0].action,'examine');
 editor.requestVisualType('object');assert.equal(scene.kind,'object');assert.equal(saved,3);
});

test('area deletion removes its linked unshared object but preserves shared visuals and image assets',()=>{
 const editor=sceneEditor({});let saves=0;editor.changed=()=>saves++;
 editor.$refs={deleteAreaDialog:{showModal(){},close(){}}};
 const area={id:'area',object_id:'object'};
 editor.document={visuals:[{id:'scene',kind:'scene',hotspots:[area]},{id:'object',kind:'object',name:'Envelope',asset_id:8,hotspots:[]},{id:'unrelated',kind:'object',name:'Envelope',hotspots:[]},{id:'second',kind:'scene',hotspots:[{id:'shared-area',object_id:'object'}]}]};
 editor.assets=[{id:8}];editor.selected='scene';editor.requestAreaDelete('area');
 assert.equal(editor.areaObjectToDelete,null);assert.equal(editor.objects.length,2);
 editor.confirmAreaDelete();assert.equal(editor.objects.length,2);assert.equal(saves,1);
 editor.choose('second');editor.requestAreaDelete('shared-area');assert.equal(editor.areaObjectToDelete.id,'object');
 assert.equal(editor.objects.length,2);editor.confirmAreaDelete();assert.deepEqual(editor.objects.map(v=>v.id),['unrelated']);
 assert.equal(editor.assets.length,1);assert.equal(editor.selected,'second');assert.equal(saves,2);
});

test('rejected image submission stays visible with its reason through status refreshes',async()=>{
 const previous=globalThis.window;globalThis.window={};
 try {
  const editor=sceneEditor({generate:'/generate',url:'/visuals'});editor.document={visuals:[{id:'object',name:'Envelope',prompt:'Inventory envelope'}]};editor.selected='object';editor.flush=async()=>true;editor.$refs={};editor.$nextTick=fn=>fn();
  editor.request=async()=>{throw new Error('Estimated reservation exceeds available trial credit.');};
  await editor.generate();assert.equal(editor.jobs.length,1);assert.equal(editor.jobs[0].status,'failed');assert.equal(editor.jobs[0].localFailure,true);assert.match(editor.jobs[0].error,/trial credit/);assert.equal(editor.jobs[0].prompt,'Inventory envelope');
  editor.mergeJobs([{id:1,status:'completed'}]);assert.equal(editor.jobs.length,2);assert.equal(editor.jobs[0].localFailure,true);
  assert.equal(editor.generating,false);
 }finally{if(previous===undefined)delete globalThis.window;else globalThis.window=previous;}
});

test('inventory actions replace the same item repeatedly without changing authored objects',()=>{
 const editor=sceneEditor({});editor.changed=()=>{};
 const closed={id:'closed',name:'Envelope',kind:'object',asset_id:1,hotspots:[],actions:[{id:'open',label:'Open',target_id:'opened'},{id:'crumple',label:'Crumple',target_id:'ball'}]};
 const opened={id:'opened',name:'Open envelope',kind:'object',asset_id:2,hotspots:[],actions:[{id:'close',label:'Close',target_id:'closed'}]};
 const ball={id:'ball',name:'Paper ball',kind:'object',asset_id:3,hotspots:[],actions:[{id:'uncrumple',label:'Uncrumple',target_id:'closed'}]};
 editor.document={visuals:[{id:'scene',kind:'scene',hotspots:[]},closed,opened,ball]};editor.assets=[{id:1,url:'/closed'},{id:2,url:'/open'},{id:3,url:'/ball'}];editor.selected='scene';
 const original=JSON.stringify(editor.document);editor.startPreview();editor.activate({id:'pickup',action:'pickup',object_id:'closed'});
 assert.equal(editor.inventoryActions.length,2);editor.performObjectAction('open');assert.equal(editor.inventory[0].id,'opened');assert.equal(editor.modal.url,'/open');
 editor.performObjectAction('close');assert.equal(editor.inventory[0].id,'closed');editor.performObjectAction('crumple');assert.equal(editor.inventory[0].id,'ball');
 editor.performObjectAction('uncrumple');editor.performObjectAction('open');assert.equal(editor.inventory.length,1);assert.equal(editor.inventory[0].inventoryId,'pickup');
 assert.equal(JSON.stringify(editor.document),original);editor.startPreview();assert.equal(editor.inventory.length,0);
});

test('linked object actions preserve area objects and clear targets when visuals are removed',()=>{
 const editor=sceneEditor({});editor.changed=()=>{};editor.$refs={deleteVisualDialog:{showModal(){},close(){}},deleteAreaDialog:{showModal(){},close(){}}};
 editor.document={visuals:[{id:'scene',kind:'scene',hotspots:[{id:'area',object_id:'closed'}]},{id:'closed',kind:'object',hotspots:[]},{id:'open',kind:'object',hotspots:[],actions:[{id:'close',label:'Close',target_id:'closed'}]}]};
 editor.selected='scene';editor.requestAreaDelete('area');assert.equal(editor.areaObjectToDelete,null);editor.confirmAreaDelete();assert.equal(editor.objects.length,2);
 editor.choose('closed');editor.requestVisualDelete();assert.equal(editor.dependentAreas,1);editor.confirmVisualDelete();assert.equal(editor.objects[0].actions[0].target_id,null);
});

test('inspection and inventory use visual descriptions while examine uses only area text',()=>{
 const editor=sceneEditor({});editor.document={visuals:[{id:'scene',kind:'scene',hotspots:[],description:'A quiet room.'},{id:'object',kind:'object',name:'Envelope',description:'A sealed letter.',asset_id:1,hotspots:[],actions:[]}]};editor.selected='scene';editor.assets=[{id:1,url:'/letter'}];editor.startPreview();
 editor.activate({id:'inspect',action:'inspect',object_id:'object',label:'Area',description:'Old area text'});assert.equal(editor.modal.label,'Envelope');assert.equal(editor.modal.text,'A sealed letter.');
 editor.activate({id:'pickup',action:'pickup',object_id:'object',description:'Old area text'});assert.equal(editor.modal.text,'A sealed letter.');
 editor.activate({id:'examine',action:'examine',object_id:'object',description:'Look under the rug.'});assert.equal(editor.modal.text,'Look under the rug.');assert.equal(editor.modal.url,null);
 editor.openInventory(editor.inventory[0]);assert.equal(editor.modal.text,'A sealed letter.');
});

test('scene crossfade waits another 250ms before inspection and closing cancels it', (t) => {
 t.mock.timers.enable({apis:['setTimeout']});
 const editor=sceneEditor({});
 editor.document={visuals:[{id:'before',kind:'scene'},{id:'after',kind:'scene'},{id:'letter',kind:'object',name:'Letter'}]};
 editor.selected='before';editor.startPreview();
 editor.activate({id:'pickup',action:'pickup',object_id:'letter',target_id:'after'});
 assert.equal(editor.previewId,'after');assert.equal(editor.outgoingScene.id,'before');
 assert.equal(editor.sceneTransition,true);assert.equal(editor.modal,null);
 t.mock.timers.tick(200);
 assert.equal(editor.sceneTransition,false);assert.equal(editor.outgoingScene,null);assert.equal(editor.modal,null);
 t.mock.timers.tick(249);assert.equal(editor.modal,null);
 t.mock.timers.tick(1);assert.equal(editor.modal.label,'Letter');
 editor.startPreview();editor.activate({id:'inspect',action:'inspect',object_id:'letter',target_id:'after'});
 t.mock.timers.tick(200);editor.closePreview();t.mock.timers.tick(250);
 assert.equal(editor.modal,null);assert.equal(editor.pendingModal,null);assert.equal(editor.sceneTransition,false);
});

test('image sidebar remembers collapse and filters archived assets without losing assignments',()=>{
 const previous=globalThis.localStorage;const stored=new Map();
 globalThis.localStorage={getItem:key=>stored.get(key),setItem:(key,value)=>stored.set(key,value)};
 try{
  const editor=sceneEditor({url:'/story/page/visuals'});
  editor.assets=[{id:1,name:'Envelope',archived:true},{id:2,name:'Porch',archived:false}];
  const job={id:10,name:'Old scene name',status:'completed',asset_ids:[1,2],prompt:Array.from({length:25},(_,i)=>'word'+i).join(' ')};
  editor.jobs=[job,{id:11,status:'completed',asset_ids:[1]}];
  assert.equal(editor.visibleJobs.length,1);assert.deepEqual(editor.jobAssets(job),[2]);assert.equal(editor.archivedCount,1);
  editor.showArchived=true;assert.equal(editor.visibleJobs.length,2);assert.deepEqual(editor.jobAssets(job),[1,2]);
  assert.equal(editor.jobTitle(job),'Envelope, Porch');assert.equal(editor.promptLong(job),true);assert.equal(editor.promptShort(job).split(' ').length,20);
  editor.rememberCard(job,false);const reopened=sceneEditor({url:'/story/page/visuals'});reopened.loadCardStates();assert.equal(reopened.cardOpen(job),false);
 }finally{globalThis.localStorage=previous;}
});

test('inventory areas match carried object states, show fallback, consume keys and transition without editing story', (t)=>{
 t.mock.timers.enable({apis:['setTimeout']});
 const editor=sceneEditor({});
 const lock={id:'lock',label:'Door lock',action:'inventory',interaction_text:'A brass keyhole.',fallback_text:'Nothing happens.',inventory_rules:[{object_id:'envelope',response:'You lack the lock-picking skill.',consume:false},{object_id:'key',response:'The door unlocks.',target_id:'open',consume:true}]};
 editor.document={visuals:[{id:'porch',kind:'scene',hotspots:[lock]},{id:'open',kind:'scene',hotspots:[]},{id:'key',kind:'object',name:'Key'},{id:'envelope',kind:'object',name:'Envelope'},{id:'stone',kind:'object',name:'Stone'}]};
 editor.selected='porch';editor.startPreview();editor.inventory=[{id:'envelope',inventoryId:'slot1'},{id:'key',inventoryId:'slot2'},{id:'stone',inventoryId:'slot3'}];const original=JSON.stringify(editor.document);
 editor.activate(lock);assert.equal(editor.modal.text,'A brass keyhole.');assert.equal(editor.previewId,'porch');
 editor.useInventoryOnArea('not-carried');assert.equal(editor.modal.text,'A brass keyhole.');
 editor.useInventoryOnArea('slot1');assert.equal(editor.modal.text,'You lack the lock-picking skill.');assert.equal(editor.inventory.length,3);
 editor.useInventoryOnArea('slot3');assert.equal(editor.modal.text,'Nothing happens.');
 editor.useInventoryOnArea('slot2');assert.equal(editor.previewId,'open');assert.equal(editor.inventory.length,2);assert.equal(editor.modal,null);
 t.mock.timers.tick(200);t.mock.timers.tick(250);assert.equal(editor.modal.text,'The door unlocks.');assert.equal(JSON.stringify(editor.document),original);
 editor.closePreview();
});

test('named area choices transition only after choosing, and dependencies are cleared on visual removal', (t)=>{
 t.mock.timers.enable({apis:['setTimeout']});
 const editor=sceneEditor({});editor.changed=()=>{};editor.$refs={deleteVisualDialog:{showModal(){},close(){}}};
 const door={id:'door',label:'Open door',action:'choices',choices:[{label:'Walk into the house',response:'You enter the hall.',target_id:'hall'}],inventory_rules:[{object_id:'key',target_id:'hall'}]};
 editor.document={visuals:[{id:'porch',kind:'scene',hotspots:[door]},{id:'hall',kind:'scene',hotspots:[]},{id:'key',kind:'object',hotspots:[]}]};editor.selected='porch';editor.startPreview();editor.activate(door);
 assert.equal(editor.previewId,'porch');editor.chooseAreaAction(99);assert.equal(editor.previewId,'porch');editor.chooseAreaAction(0);assert.equal(editor.previewId,'hall');t.mock.timers.tick(200);t.mock.timers.tick(250);assert.equal(editor.modal.text,'You enter the hall.');editor.closePreview();
 editor.choose('hall');editor.requestVisualDelete();assert.equal(editor.dependentAreas,2);editor.confirmVisualDelete();assert.equal(door.choices[0].target_id,null);assert.equal(door.inventory_rules[0].target_id,null);
 editor.choose('key');editor.requestVisualDelete();editor.confirmVisualDelete();assert.equal(door.inventory_rules.length,0);
});

test('generation names blank images with the writing model and skips naming for typed names',async()=>{
 const before=globalThis.window;globalThis.window={};
 try{
  const editor=sceneEditor({generate:'/generate',url:'/visuals',nameUrl:'/name',writingModel:'openai/writer'});editor.document={visuals:[{id:'scene',name:'Old name',prompt:'A rainy porch'}]};editor.selected='scene';editor.flush=async()=>true;editor.$refs={};editor.$nextTick=fn=>fn();
  const calls=[];editor.request=async(url,method,body)=>{calls.push({url,body});if(url==='/name')return {name:'Rainy Porch'};if(url==='/generate')return {id:3,status:'queued'};throw new Error('No refresh');};
  await editor.generate();assert.equal(calls[0].url,'/name');assert.equal(calls[0].body.model,'openai/writer');assert.equal(calls[1].body.name,'Rainy Porch');assert.equal(editor.generationName,'');
  calls.length=0;editor.generationName='My title';await editor.generate();assert.equal(calls[0].url,'/generate');assert.equal(calls[0].body.name,'My title');
  calls.length=0;editor.request=async(url)=>{calls.push({url});throw new Error('Name failed');};await editor.generate();assert.equal(calls.length,1);assert.equal(calls[0].url,'/name');assert.equal(editor.generating,false);assert.equal(editor.namingImage,false);assert.equal(editor.error,'Name failed');
 }finally{globalThis.window=before;}
});

test('loading visuals is read-only and does not announce a save',async()=>{
 const previousWindow=globalThis.window,previousFetch=globalThis.fetch;
 const notices=[];globalThis.window={addEventListener(){},removeEventListener(){},dispatchEvent:event=>notices.push(event),location:{href:'http://localhost:8010/stories/2/edit?tab=visuals'},history:{replaceState(){}}};
 globalThis.fetch=async()=>({ok:true,json:async()=>({models:[],endpoints:{}})});
 const editor=sceneEditor({url:'/visuals'});const requests=[];
 editor.$watch=(field,callback)=>{let value=editor[field];Object.defineProperty(editor,field,{configurable:true,get:()=>value,set:next=>{value=next;callback(next);}});};
 editor.request=async(url,method='GET')=>{requests.push({url,method});return {document:{visuals:[]},revision:7,assets:[],jobs:[]};};
 try{await editor.init();await editor.flush();assert.deepEqual(requests,[{url:'/visuals',method:'GET'}]);assert.equal(editor.dirty,false);assert.equal(editor.status,'');assert.equal(notices.length,0);}
 finally{editor.destroy();globalThis.window=previousWindow;globalThis.fetch=previousFetch;}
});

test('manual retry creates a new history entry without changing the current image form',async()=>{
 const editor=sceneEditor({url:'/visuals'});const failed={id:3,status:'failed',name:'Old name',prompt:'Original prompt',model:'old/model',retry_url:'/retry/3',asset_ids:[]};editor.jobs=[failed];editor.generationName='New draft';editor.model='new/model';editor.$refs={};editor.$nextTick=fn=>fn();
 const calls=[];editor.request=async(url,method,body)=>{calls.push({url,method,body});if(url==='/retry/3')return {id:4,status:'queued'};throw new Error('refresh unavailable');};
 await editor.retryImage(failed);assert.equal(calls[0].url,'/retry/3');assert.ok(calls[0].body.request_key);assert.equal(editor.jobs[0].id,4);assert.equal(editor.jobs[1].status,'failed');assert.equal(editor.jobs[0].prompt,'Original prompt');assert.equal(editor.generationName,'New draft');assert.equal(editor.model,'new/model');assert.equal(editor.generating,false);
});


test('next page preserves inventory and editor document, then completes on last page', () => {
 const e=sceneEditor({});e.editorPageId=1;e.document={visuals:[{id:'outside',kind:'scene',hotspots:[]},{id:'key',kind:'object',actions:[]}]};e.selected='outside';
 e.previewPages=[{id:1,visuals:e.document.visuals},{id:2,title:'Inside',visuals:[{id:'inside',kind:'scene',hotspots:[]}]}];
 e.startPreview();e.inventory=[{id:'key',inventoryId:'slot'}];const original=JSON.stringify(e.document);
 e.activate({action:'next_page'});assert.equal(e.previewPageId,2);assert.equal(e.displayed.id,'inside');assert.equal(e.outgoingScene.id,'outside');assert.equal(e.inventory.length,1);assert.equal(JSON.stringify(e.document),original);
 e.clearSceneTransition();e.activate({action:'next_page'});assert.equal(e.modal.label,'Story complete');
 e.startPreview();assert.equal(e.previewId,'outside');assert.equal(e.previewPageId,1);e.closePreview();
});

test('next page with no scene does not skip ahead', () => {
 const e=sceneEditor({});e.editorPageId=1;e.document={visuals:[{id:'outside',kind:'scene',hotspots:[]}]};e.selected='outside';e.previewPages=[{id:1,visuals:e.document.visuals},{id:2,visuals:[]}];e.startPreview();e.activate({action:'next_page'});assert.equal(e.previewPageId,1);assert.match(e.modal.text,/Add a scene/);e.closePreview();
});


test('other page image links lazily load completed cards without changing generation context', async () => {
 const e=sceneEditor({});e.editorPageId=2;e.previewPages=[{id:1,position:1,title:'Porch',images_url:'/page1'},{id:2,position:2}];e.selected='current';e.references=['10'];e.jobs=[{id:2,status:'completed',asset_ids:[]}];e.document={visuals:[]};
 let calls=0;e.request=async url=>{assert.equal(url,'/page1');calls++;return {jobs:[{id:1,status:'completed',asset_ids:[10]},{id:3,status:'failed',asset_ids:[]}],assets:[{id:10,name:'Porch'}]};};
 assert.equal(e.otherImagePages.length,1);await e.togglePageImages(e.otherImagePages[0]);assert.equal(calls,1);assert.deepEqual(e.visibleJobs.map(j=>j.id),[2,1]);assert.equal(e.selected,'current');assert.deepEqual(e.references,['10']);assert.equal(e.asset(10).name,'Porch');
 e.mergeJobs([{id:2,status:'completed',asset_ids:[]}]);assert.equal(e.visibleJobs.length,2);await e.togglePageImages(e.otherImagePages[0]);assert.equal(e.visibleJobs.length,1);assert.equal(calls,1);
});


test('image pickers reset to current page and filter shared images and unassigned uploads', () => {
 const e=sceneEditor({});e.editorPageId=2;e.assets=[{id:1,page_ids:[1]},{id:2,page_ids:[2]},{id:3,page_ids:[1,2]},{id:4,page_ids:[]}];e.$refs={visualImagePicker:{showModal(){}},interactionImagePicker:{showModal(){}}};
 e.openVisualImagePicker();assert.deepEqual(e.pickerAssets.map(a=>a.id),[2,3]);
 e.imagePickerPage='1';assert.deepEqual(e.pickerAssets.map(a=>a.id),[1,3]);
 e.imagePickerPage='uploads';assert.deepEqual(e.pickerAssets.map(a=>a.id),[4]);
 e.openInteractionPicker('object');assert.equal(e.imagePickerPage,'2');
});
