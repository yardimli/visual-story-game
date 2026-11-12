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
 editor.selected='porch';editor.generationInput.prompt='Current draft';editor.references=['42'];let copied;
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
test('item selection reuses a shared object without creating page visuals',()=>{
 const editor=sceneEditor({});editor.changed=()=>{};editor.$refs={interactionImagePicker:{close:()=>{}}};
 editor.document={visuals:[{id:'scene',kind:'scene',name:'Duplicate',hotspots:[{id:'h'}]},{id:'second',kind:'scene',name:'Duplicate',hotspots:[]}]};
 editor.selected='scene';editor.hotspotId='h';editor.assets=[{id:5,name:'Note',url:'/note.png'}];
 editor.inventoryItems=[{id:'note',kind:'object',name:'Note',asset_id:5}];editor.selectObjectImage(5);const id=editor.hotspot.object_id;editor.selectObjectImage(5);
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
 assert.equal(editor.selected,'current');assert.equal(editor.document.visuals[0].asset_id,null);assert.equal(editor.document.visuals[1].asset_id,12);
});

test('drawing and renaming areas leave visuals independent until explicitly selecting an object image',()=>{
 const editor=sceneEditor({});editor.changed=()=>{};
 editor.document={visuals:[{id:'scene',kind:'scene',hotspots:[]}]};editor.selected='scene';
 editor.newHotspot();const h=editor.hotspot;editor.labelDraft='Envelope';editor.finishRename(h);
 assert.equal(h.label,'Envelope');assert.equal(h.object_id,null);assert.equal(editor.objects.length,0);
 editor.$refs={interactionImagePicker:{close(){}}};editor.selectObjectImage(9);assert.equal(editor.objects.length,0);editor.inventoryItems=[{id:'letter',name:'Letter',kind:'object',asset_id:9}];editor.selectInventoryItem('letter');assert.equal(editor.document.visuals.length,1);
 const object=editor.objects[0];assert.equal(object.asset_id,9);assert.equal(h.object_id,object.id);
 const name=object.name;editor.beginRename(h);editor.labelDraft='Read letter';editor.finishRename(h);
 assert.equal(object.name,name);assert.equal(editor.objects.length,1);assert.equal(editor.selected,'scene');
});

test('cancelled names and visual limits do not create stray objects',()=>{
 const editor=sceneEditor({});editor.changed=()=>{};
 editor.document={visuals:[{id:'scene',kind:'scene',hotspots:[]}]};editor.selected='scene';
 editor.newHotspot();editor.labelDraft='';editor.finishRename(editor.hotspot);assert.equal(editor.objects.length,0);
 editor.document.visuals.push(...Array.from({length:99},(_,i)=>({id:String(i),kind:'object',hotspots:[]})));
 editor.beginRename(editor.hotspot);editor.labelDraft='Stone';editor.finishRename(editor.hotspot);
 assert.equal(editor.document.visuals.length,100);assert.equal(editor.hotspot.label,'Stone');assert.equal(editor.hotspot.object_id,null);
 assert.equal(editor.error,'');
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
  await editor.openGeneration('/image-studio');assert.equal(editor.generationVisual,undefined);
  assert.equal(events.at(-1),'/image-studio');assert.equal(editor.objects.length,1);assert.equal(editor.hotspot.object_id,null);
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

test('area deletion preserves independently authored objects and image assets',()=>{
 const editor=sceneEditor({});let saves=0;editor.changed=()=>saves++;
 editor.$refs={deleteAreaDialog:{showModal(){},close(){}}};
 const area={id:'area',object_id:'object'};
 editor.document={visuals:[{id:'scene',kind:'scene',hotspots:[area]},{id:'object',kind:'object',name:'Envelope',asset_id:8,hotspots:[]},{id:'unrelated',kind:'object',name:'Envelope',hotspots:[]},{id:'second',kind:'scene',hotspots:[{id:'shared-area',object_id:'object'}]}]};
 editor.assets=[{id:8}];editor.selected='scene';editor.requestAreaDelete('area');
 assert.equal(editor.objects.length,2);
 editor.confirmAreaDelete();assert.equal(editor.objects.length,2);assert.equal(saves,1);
 editor.choose('second');editor.requestAreaDelete('shared-area');
 assert.equal(editor.objects.length,2);editor.confirmAreaDelete();assert.deepEqual(editor.objects.map(v=>v.id),['object','unrelated']);
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
 assert.equal(editor.modal,null);assert.equal(editor.inventoryPulse,true);editor.openInventory(editor.inventory[0]);assert.equal(editor.inventoryActions.length,2);editor.performObjectAction('open');assert.equal(editor.inventory[0].id,'opened');assert.equal(editor.modal.url,'/open');
 editor.performObjectAction('close');assert.equal(editor.inventory[0].id,'closed');editor.performObjectAction('crumple');assert.equal(editor.inventory[0].id,'ball');
 editor.performObjectAction('uncrumple');editor.performObjectAction('open');assert.equal(editor.inventory.length,1);assert.equal(editor.inventory[0].inventoryId,'pickup');
 assert.equal(JSON.stringify(editor.document),original);editor.startPreview();assert.equal(editor.inventory.length,0);
});

test('linked object actions preserve area objects and clear targets when visuals are removed',()=>{
 const editor=sceneEditor({});editor.changed=()=>{};editor.$refs={deleteVisualDialog:{showModal(){},close(){}},deleteAreaDialog:{showModal(){},close(){}}};
 editor.document={visuals:[{id:'scene',kind:'scene',hotspots:[{id:'area',object_id:'closed'}]},{id:'closed',kind:'object',hotspots:[]},{id:'open',kind:'object',hotspots:[],actions:[{id:'close',label:'Close',target_id:'closed'}]}]};
 editor.selected='scene';editor.requestAreaDelete('area');editor.confirmAreaDelete();assert.equal(editor.objects.length,2);
 editor.choose('closed');editor.requestVisualDelete();assert.equal(editor.dependentAreas,1);editor.confirmVisualDelete();assert.equal(editor.objects[0].actions[0].target_id,null);
});

test('inspection and inventory use visual descriptions while examine uses only area text',()=>{
 const editor=sceneEditor({});editor.document={visuals:[{id:'scene',kind:'scene',hotspots:[],description:'A quiet room.'},{id:'object',kind:'object',name:'Envelope',description:'A sealed letter.',asset_id:1,hotspots:[],actions:[]}]};editor.selected='scene';editor.assets=[{id:1,url:'/letter'}];editor.startPreview();
 editor.activate({id:'inspect',action:'inspect',object_id:'object',label:'Area',description:'Old area text'});assert.equal(editor.modal.label,'Envelope');assert.equal(editor.modal.text,'A sealed letter.');
 editor.modal=null;editor.activate({id:'pickup',action:'pickup',object_id:'object',description:'Old area text'});assert.equal(editor.modal,null);assert.equal(editor.narration,null);assert.equal(editor.pendingModal,null);assert.equal(editor.inventory.length,1);
 editor.activate({id:'examine',action:'examine',object_id:'object',description:'Look under the rug.'});assert.equal(editor.narration.text,'Look under the rug.');assert.equal(editor.modal,null);
 editor.openInventory(editor.inventory[0]);assert.equal(editor.modal.text,'A sealed letter.');
});

test('scene crossfade waits another 250ms before inspection and closing cancels it', (t) => {
 t.mock.timers.enable({apis:['setTimeout']});
 const editor=sceneEditor({});
 editor.document={visuals:[{id:'before',kind:'scene'},{id:'after',kind:'scene'},{id:'letter',kind:'object',name:'Letter'}]};
 editor.selected='before';editor.startPreview();
 editor.activate({id:'inspect',action:'inspect',object_id:'letter',target_id:'after'});
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
 editor.activate(lock);assert.equal(editor.narration.text,'A brass keyhole.');assert.equal(editor.modal,null);assert.equal(editor.previewId,'porch');
 editor.useInventoryOnArea('not-carried',lock);assert.equal(editor.narration.text,'A brass keyhole.');
 editor.useInventoryOnArea('slot1',lock);assert.equal(editor.narration.text,'You lack the lock-picking skill.');assert.equal(editor.inventory.length,3);editor.completeInventoryUse();
 editor.useInventoryOnArea('slot3',lock);assert.equal(editor.narration.text,'Nothing happens.');editor.completeInventoryUse();
 editor.useInventoryOnArea('slot2',lock);assert.equal(editor.previewId,'porch');assert.equal(editor.inventory.length,3);assert.equal(editor.narration.text,'The door unlocks.');
 editor.finishInventoryText(editor.pendingInventoryUse.key);t.mock.timers.tick(1199);assert.equal(editor.previewId,'porch');t.mock.timers.tick(1);
 assert.equal(editor.previewId,'open');assert.equal(editor.inventory.length,2);assert.equal(JSON.stringify(editor.document),original);
 editor.closePreview();
});

test('named area choices transition only after choosing, and dependencies are cleared on visual removal', (t)=>{
 t.mock.timers.enable({apis:['setTimeout']});
 const editor=sceneEditor({});editor.changed=()=>{};editor.$refs={deleteVisualDialog:{showModal(){},close(){}}};
 const door={id:'door',label:'Open door',action:'choices',choices:[{label:'Walk into the house',response:'You enter the hall.',target_id:'hall'}],inventory_rules:[{object_id:'key',target_id:'hall'}]};
 editor.document={visuals:[{id:'porch',kind:'scene',hotspots:[door]},{id:'hall',kind:'scene',hotspots:[]},{id:'key',kind:'object',hotspots:[]}]};editor.selected='porch';editor.startPreview();editor.activate(door);
 assert.equal(editor.previewId,'porch');editor.chooseAreaAction(99);assert.equal(editor.previewId,'porch');editor.chooseAreaAction(0);assert.equal(editor.previewId,'hall');t.mock.timers.tick(200);t.mock.timers.tick(250);assert.equal(editor.narration.text,'You enter the hall.');editor.closePreview();
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
 e.clearSceneTransition();e.activate({action:'next_page'});assert.equal(e.narration.label,'Story complete');
 e.startPreview();assert.equal(e.previewId,'outside');assert.equal(e.previewPageId,1);e.closePreview();
});

test('next page with no scene does not skip ahead', () => {
 const e=sceneEditor({});e.editorPageId=1;e.document={visuals:[{id:'outside',kind:'scene',hotspots:[]}]};e.selected='outside';e.previewPages=[{id:1,visuals:e.document.visuals},{id:2,visuals:[]}];e.startPreview();e.activate({action:'next_page'});assert.equal(e.previewPageId,1);assert.match(e.narration.text,/Add a scene/);e.closePreview();
});


test('image library and pickers include all pages but exclude archived images',()=>{
 const e=sceneEditor({});e.jobs=[{id:1,status:'completed',asset_ids:[1]},{id:2,status:'completed',asset_ids:[2]}];e.assets=[{id:1,page_ids:[1]},{id:2,page_ids:[2]},{id:3,archived:true}];
 assert.deepEqual(e.visibleJobs.map(j=>j.id),[1,2]);assert.deepEqual(e.pickerAssets.map(a=>a.id),[1,2]);assert.deepEqual(e.referenceAssets.map(a=>a.id),[1,2]);
});

test('test setup lists objects with descriptions and source pages and seeds only selected inventory', () => {
 const e=sceneEditor({});e.editorPageId=2;e.document={visuals:[{id:'scene',kind:'scene',hotspots:[]},{id:'envelope',kind:'object',name:'Envelope',description:'A sealed letter'}]};e.selected='scene';
 e.previewPages=[{id:1,position:1,title:'Garden',visuals:[{id:'key',kind:'object',name:'Key',description:'A brass key'}]},{id:2,position:2,title:'Hall',visuals:[]}];
 let opened=false,closed=false;e.$refs={previewInventoryDialog:{showModal(){opened=true;},close(){closed=true;}}};
 e.preparePreview();assert.equal(opened,true);assert.equal(e.preview,false);assert.equal(e.previewInventoryItems.length,2);assert.match(e.previewInventoryItems[0].sourcePage,/Page 1/);assert.equal(e.previewInventoryItems[1].description,'A sealed letter');
 e.previewInventorySelection=['key'];e.confirmPreviewInventory();assert.equal(closed,true);assert.deepEqual(e.inventory.map(i=>i.id),['key']);assert.equal(e.inventory[0].description,'A brass key');e.inventory[0].description='Test only';assert.equal(e.previewPages[0].visuals[0].description,'A brass key');
 e.closePreview();e.preparePreview();assert.deepEqual(e.previewInventorySelection,[]);e.confirmPreviewInventory();assert.deepEqual(e.inventory,[]);e.closePreview();
});


test('visual menu reorder preserves selection and links and only saves changed order',()=>{
 const e=sceneEditor({});let saves=0;e.changed=()=>saves++;e.document={visuals:[{id:'a',kind:'scene',name:'Porch',hotspots:[{object_id:'b',target_id:'c'}]},{id:'b',kind:'object',name:'Key'},{id:'c',kind:'scene',name:'Hall'}]};e.selected='a';e.hotspotId='area';
 e.moveVisual('a',-1);assert.equal(saves,0);e.moveVisual('b',1);assert.deepEqual(e.document.visuals.map(v=>v.id),['a','c','b']);
 e.draggedVisualId='b';e.dropVisual('a',{clientY:1},{getBoundingClientRect:()=>({top:0,height:20})});assert.deepEqual(e.document.visuals.map(v=>v.id),['b','a','c']);
 e.draggedVisualId='b';e.dropVisual('c',{clientY:19},{getBoundingClientRect:()=>({top:0,height:20})});assert.deepEqual(e.document.visuals.map(v=>v.id),['a','c','b']);
 assert.equal(e.selected,'a');assert.equal(e.hotspotId,'area');assert.equal(e.document.visuals[0].hotspots[0].object_id,'b');assert.equal(saves,3);assert.equal(e.visualOptionLabel(e.document.visuals[2]),'3. Object · Key');
});

test('image cards load full independent form details, save new drafts without overwriting completed images, and clear selection',async()=>{
 const before=globalThis.window;globalThis.window={location:{href:'http://localhost:8010/stories/2/pages/4/image-studio'},history:{replaceState(){}},dispatchEvent(){}};
 try{
  const editor=sceneEditor({generationMode:true,draftUrl:'/drafts'});editor.loading=false;editor.$nextTick=fn=>fn();
  editor.document={visuals:[{id:'scene',prompt:'Scene prompt',asset_id:7}]};
  editor.models=[{id:'image/model',imageEndpoints:[{pricing:[]}]}];editor.assets=[{id:7,name:'Original image'}];
  const original={id:3,status:'completed',name:'Original',prompt:'Original prompt',video_prompt:'Slow pan',model:'image/model',references:[7],settings:{quality:'high'},asset_ids:[7]};editor.jobs=[original];
  await editor.selectImageCard(original);
  assert.equal(editor.selectedImageId,3);assert.equal(editor.visual.prompt,'Original prompt');assert.equal(editor.visual.video_prompt,'Slow pan');assert.equal(editor.generationName,'Original image');assert.deepEqual(editor.references,['7']);assert.equal(editor.settings.quality,'high');
  let sent;editor.request=async(url,method,body)=>{sent=body;return {id:4,status:'draft'};};
  editor.visual.prompt='Revised prompt';await editor.saveGenerationDraft();
  assert.equal(sent.draft_id,undefined);assert.equal(editor.selectedImageId,4);assert.equal(editor.jobs.length,2);assert.equal(original.prompt,'Original prompt');assert.equal(editor.document.visuals[0].prompt,'Scene prompt');
  await editor.saveGenerationDraft();assert.equal(sent.draft_id,4);assert.equal(editor.jobs.length,2);
  await editor.createNewImage();assert.equal(editor.selectedImageId,null);assert.equal(editor.visual.prompt,'');assert.equal(editor.visual.video_prompt,'');assert.equal(editor.model,'');assert.equal(editor.generationName,'');assert.deepEqual(editor.references,[]);assert.deepEqual(editor.settings,{});
 }finally{globalThis.window=before;}
});

test('explicit page links select the named destination and return to live editor scenes with inventory intact',()=>{
 const editor=sceneEditor({});editor.preview=true;editor.editorPageId=1;editor.previewPageId=1;
 editor.document={visuals:[{id:'live',kind:'scene',hotspots:[]}]};editor.previewPages=[{id:1,visuals:[{id:'old',kind:'scene'}]},{id:2,visuals:[]},{id:3,visuals:[{id:'third',kind:'scene'}]}];
 editor.inventory=[{id:'key'}];const calls=[];editor.transitionScene=(id,page)=>{calls.push([id,page]);editor.previewPageId=page;};
 editor.activate({action:'page',target_page_id:3});editor.activate({action:'page',target_page_id:1});
 assert.deepEqual(calls,[['third',3],['live',1]]);assert.equal(editor.inventory[0].id,'key');
 editor.showObjectModal=message=>{editor.modal=message;};editor.activate({action:'page',target_page_id:2});assert.match(editor.modal.text,/Choose an available scene/);assert.equal(calls.length,2);
 editor.activate({action:'page',target_page_id:null});assert.match(editor.modal.text,/Choose an available/);
});

test('page destination picker includes every scene and preview opens the selected one',()=>{
 const editor=sceneEditor({});editor.changed=()=>{};editor.editorPageId=1;editor.selected='first';editor.hotspotId='door';
 editor.document={visuals:[{id:'first',kind:'scene',name:'Start',hotspots:[{id:'door',action:'page',target_page_id:2}]},{id:'new',kind:'scene',name:'New scene',hotspots:[]}]};
 editor.previewPages=[{id:1,position:1,title:'Start',visuals:[]},{id:2,position:2,title:'House',visuals:[{id:'hall',kind:'scene',name:'Hall'},{id:'stairs',kind:'scene',name:'Stairs'},{id:'key',kind:'object',name:'Key'}]}];
 assert.deepEqual(editor.pageSceneDestinations.map(d=>d.key),['1:first','1:new','2:hall','2:stairs']);
 assert.equal(editor.destinationValue(editor.hotspot),'2:hall');editor.selectPageScene('2:stairs');assert.equal(editor.hotspot.target_scene_id,'stairs');
 editor.preview=true;let destination;editor.transitionScene=(id,page)=>destination=[id,page];editor.activate(editor.document.visuals[0].hotspots[0]);assert.deepEqual(destination,['stairs',2]);
});


test('scene skip rules require every selected item, support cross-page jumps, and cap cycles at three',()=>{
 const e=sceneEditor({});e.editorPageId=1;
 const rule=(target_page_id,target_scene_id,object_ids=['key'])=>({object_ids,target_page_id,target_scene_id});
 e.document={visuals:[{id:'full',kind:'scene',skip_rules:[rule(2,'empty',['key','note'])]},{id:'key',kind:'object'},{id:'note',kind:'object'}]};
 e.previewPages=[{id:1,visuals:[]},{id:2,visuals:[{id:'empty',kind:'scene',skip_rules:[rule(1,'full')]}]}];
 e.inventory=[{id:'key'}];assert.deepEqual(e.resolveSceneEntry(1,'full'),{pageId:1,sceneId:'full'});
 e.inventory.push({id:'note'});assert.deepEqual(e.resolveSceneEntry(1,'full'),{pageId:2,sceneId:'empty'});assert.equal(e.skipJumpCount,3);
 e.document.visuals[0].skip_rules=[rule(2,'missing'),rule(2,'empty',[])];
 assert.deepEqual(e.resolveSceneEntry(1,'full'),{pageId:1,sceneId:'full'});assert.equal(e.skipJumpCount,0);
});
test('seeded preview applies scene rules and pickup evaluates destination after inventory changes',()=>{
 const e=sceneEditor({});e.editorPageId=1;e.selected='full';
 const r={object_ids:['key'],target_page_id:1,target_scene_id:'empty'};
 e.document={visuals:[{id:'full',kind:'scene',hotspots:[],skip_rules:[r]},{id:'empty',kind:'scene',hotspots:[]},{id:'key',kind:'object'}]};
 e.previewPages=[{id:1,visuals:e.document.visuals}];
 e.startPreview(['key']);assert.equal(e.previewId,'empty');
 e.startPreview();assert.equal(e.previewId,'full');
 e.activate({id:'pickup',action:'pickup',object_id:'key',target_id:'full'});assert.equal(e.previewId,'empty');assert.equal(e.inventory.length,1);
 e.closePreview();
});

test('area image is independent of objects, keeps alignment and supports removing selection',()=>{
 const e=sceneEditor({});e.document={visuals:[{id:'scene',kind:'scene',hotspots:[{id:'area',overlay:true,overlay_asset_id:2,overlay_alignment:'bottom'}]}]};e.selected='scene';e.hotspotId='area';e.assets=[{id:2,url:'/overlay.png'}];e.changed=()=>{};e.$refs={visualImagePicker:{close(){}}};
 assert.equal(e.areaImage(e.hotspot).url,'/overlay.png');assert.equal(e.areaImagePosition(e.hotspot),'center bottom');
 e.imagePickerTarget='area';e.selectPickerImage(null);assert.equal(e.areaImage(e.hotspot),undefined);
 e.selectPickerImage(2);assert.equal(e.document.visuals.length,1);assert.equal(e.hotspot.overlay_asset_id,2);
 e.hotspot.overlay=false;assert.equal(e.areaImage(e.hotspot),null);
});

test('test starts at first scene of edited page unless another page and scene is chosen',()=>{
 const e=sceneEditor({});e.editorPageId=2;e.selected='last';e.document={visuals:[{id:'first',kind:'scene',hotspots:[]},{id:'last',kind:'scene',hotspots:[]}]};e.previewPages=[{id:1,visuals:[{id:'earlier',kind:'scene',hotspots:[]}]},{id:2,visuals:[]}];
 e.startPreview();assert.equal(e.previewId,'first');assert.equal(e.previewPageId,2);
 e.startPreview([],'1:earlier');assert.equal(e.previewId,'earlier');assert.equal(e.previewPageId,1);e.closePreview();
});

test('area descriptions never inherit inventory text, including legacy opt-in',()=>{
 const e=sceneEditor({});e.document={visuals:[{id:'item',kind:'object',description:'Item text'}]};
 const h={object_id:'item',description:''};assert.equal(e.hoverDetails(h).description,'');
 h.description='Custom';assert.equal(e.hoverDetails(h).description,'Custom');h.use_item_description=true;assert.equal(e.hoverDetails(h).description,'Custom');
 h.use_item_description=false;assert.equal(e.hoverDetails(h).description,'Custom');
});

test('interaction page navigation saves and chooses first scene rather than first object',async()=>{
 const previous=globalThis.window;let destination;globalThis.window={location:{href:'http://localhost:8010/stories/2/edit?page=4&tab=visuals&visual=old',assign(url){destination=url;}}};
 try{const e=sceneEditor({});e.editorPageId=4;e.previewPages=[{id:14,visuals:[{id:'object',kind:'object'},{id:'first',kind:'scene'},{id:'second',kind:'scene'}]}];e.flush=async()=>true;
 await e.navigateInteractionPage('14');const url=new URL(destination);assert.equal(url.searchParams.get('page'),'14');assert.equal(url.searchParams.get('visual'),'first');assert.equal(url.searchParams.get('tab'),'visuals');
 await e.selectNavigationScene(14,'second');assert.equal(new URL(destination).searchParams.get('visual'),'second');
 destination=null;e.flush=async()=>false;await e.navigateInteractionPage(14);assert.equal(destination,null);assert.equal(e.switchingPage,false);
 }finally{globalThis.window=previous;}
});

test('hover text defaults on and can be disabled without changing area description or action',()=>{
 const e=sceneEditor({});const h={description:'Look here',action:'examine',use_item_description:false};
 assert.equal(e.hoverDetails(h).description,'Look here');h.show_hover_text=false;assert.equal(e.hoverDetails(h).description,'');assert.equal(h.description,'Look here');assert.equal(e.hoverDetails(h).action,'examine');h.show_hover_text=true;assert.equal(e.hoverDetails(h).description,'Look here');
});

test('examine clicks carry animation and rectangle placement settings into narration',()=>{
 const e=sceneEditor({});e.preview=true;e.document={visuals:[]};
 const h={id:'a',action:'examine',description:'Text',description_position:'area_top',x:.2,y:.3,width:.1,height:.2};
 e.activate(h);assert.equal(e.narration.animate,false);assert.equal(e.narration.position,'area_top');assert.equal(e.narration.area.width,.1);
 e.activate(h);assert.equal(e.narration,null);h.animate_hover_text=true;e.activate(h);assert.equal(e.narration.animate,true);
});

test('image selection dialogs exclude archived assets for all pages and uploads',()=>{
 const e=sceneEditor({});e.assets=[{id:1,page_ids:[4]},{id:2,page_ids:[4],archived:true},{id:3,page_ids:[],archived:true},{id:4,page_ids:[]}];
 e.showArchived=true;e.referencePage='all';assert.deepEqual(e.referenceAssets.map(a=>a.id),[1,4]);
 assert.deepEqual(e.referenceAssets.map(a=>a.id),[1,4]);
 assert.deepEqual(e.pickerAssets.map(a=>a.id),[1,4]);

 assert.equal(e.asset(2).id,2);
});

test('inventory rules can add and use an object from an earlier page',()=>{
 const e=sceneEditor({});e.editorPageId=2;e.previewPageId=2;e.selected='door';e.hotspotId='lock';e.changed=()=>{};
 const area={id:'lock',action:'inventory',inventory_rules:[]};e.document={visuals:[{id:'door',kind:'scene',hotspots:[area]}]};
 e.previewPages=[{id:1,position:1,title:'Garden',visuals:[{id:'key',kind:'object',name:'Key'}]},{id:2,position:2,title:'Door',visuals:[]}];
 e.addAllInventoryRules();assert.equal(area.inventory_rules[0].object_id,'key');e.addAllInventoryRules();assert.equal(area.inventory_rules.length,1);
 area.inventory_rules[0].response='Unlocked';e.preview=true;e.previewId='door';e.inventory=[{id:'key'}];e.modal={areaId:'lock',sceneId:'door'};e.useInventoryOnArea('key',area);assert.equal(e.narration.text,'Unlocked');e.cancelInventoryUse();
});

test('pickup with hover disabled describes on first click and collects on second',()=>{
 const e=sceneEditor({});e.editorPageId=1;e.document={visuals:[{id:'scene',kind:'scene',hotspots:[]},{id:'key',kind:'object',description:'A brass key',hotspots:[]}]};e.selected='scene';e.startPreview();
 const h={id:'key-area',action:'pickup',object_id:'key',show_hover_text:false,description:'A brass key'};
 e.activate(h);assert.equal(e.inventory.length,0);assert.equal(e.narration.text,'A brass key');assert.equal(e.pickupReady(h),true);assert.equal(e.collected.length,0);
 e.activate(h);assert.equal(e.inventory.length,1);assert.equal(e.narration,null);assert.equal(e.pickupReady(h),false);
 e.startPreview();e.activate({...h,use_item_description:false,description:''});assert.equal(e.inventory.length,1);e.closePreview();
});

test('pickup and inspection resolve the existing key from an earlier page',()=>{
 const e=sceneEditor({});e.editorPageId=2;e.selected='porch';
 e.document={visuals:[{id:'porch',kind:'scene',hotspots:[]}]};
 e.previewPages=[{id:1,title:'Garden',visuals:[{id:'key',kind:'object',name:'Brass key',description:'A brass key',asset_id:27}]}];
 e.assets=[{id:27,url:'/key.png'}];e.startPreview();
 const h={id:'key-area',action:'pickup',object_id:'key'};
 assert.equal(e.hoverDetails(h).description,'');e.activate(h);
 assert.equal(e.inventory[0].id,'key');assert.equal(e.inventory[0].asset_id,27);
 e.activate({...h,action:'inspect'});assert.equal(e.modal.url,'/key.png');
 e.closePreview();
});

test('overlapping area layers persist as scene order and preview uses the same stacking',()=>{
 const e=sceneEditor({});let saves=0;e.changed=()=>saves++;
 const door={id:'door',x:0,y:0,width:1,height:1},handle={id:'handle',x:.5,y:.5,width:.1,height:.1};
 e.document={visuals:[{id:'scene',kind:'scene',hotspots:[door,handle]}]};e.selected='scene';
 assert.equal(e.areaLayer(handle),2);e.moveAreaLayer('door',1);
 assert.deepEqual(e.visual.hotspots.map(h=>h.id),['handle','door']);assert.equal(e.areaLayer(door),2);assert.equal(e.hotspotId,'door');
 e.moveAreaLayer('door',1);assert.equal(saves,1);
 e.moveAreaLayer('door',-1);assert.equal(e.areaLayer(handle),2);assert.equal(saves,2);
 e.preview=true;e.previewId='scene';e.previewPageId=e.editorPageId;
 assert.equal(e.areaLayer(handle),2);e.moveAreaLayer('handle',-1);assert.equal(saves,2);
 e.collected=['door'];assert.equal(e.currentHotspots.length,1);assert.equal(e.areaLayer(handle),2);
});

test('examine ignores stale pickup objects and hidden scene transitions',()=>{
 const e=sceneEditor({});e.document={visuals:[{id:'door',kind:'scene',hotspots:[]},{id:'empty',kind:'scene',hotspots:[]}]};e.inventoryItems=[{id:'key',kind:'object',name:'Key'}];e.selected='door';e.startPreview();
 const h={id:'key-area',action:'examine',description:'A brass key.',object_id:'key',target_id:'empty'};
 e.activate(h);assert.equal(e.previewId,'door');assert.equal(e.inventory.length,0);assert.equal(e.collected.length,0);assert.equal(e.narration.text,'A brass key.');
 e.closePreview();e.document.visuals[0].hotspots=[h];e.hotspotId=h.id;e.changed=()=>{};e.changeAreaAction('examine');assert.equal(h.target_id,null);
});

test('world flags survive page revisits and consumption, route open/closed states, and reset for new play',()=>{
 const e=sceneEditor({});e.editorPageId=1;e.selected='wide';
 e.flagDefinitions=[{key:'stone_opened',initial:false},{key:'key_collected',initial:false}];
 const condition=(key,value)=>({key,value});
 const skip=(values,page,scene)=>({object_ids:[],flag_conditions:Object.entries(values).map(([key,value])=>condition(key,value)),target_page_id:page,target_scene_id:scene});
 e.document={visuals:[{id:'wide',kind:'scene',hotspots:[],skip_rules:[skip({stone_opened:true,key_collected:true},1,'wide-open')]},{id:'wide-open',kind:'scene',hotspots:[],skip_rules:[skip({stone_opened:false},1,'wide')]}]};
 e.previewPages=[{id:1,visuals:[]},{id:2,visuals:[{id:'closed',kind:'scene',hotspots:[],skip_rules:[skip({stone_opened:true,key_collected:true},2,'empty'),skip({stone_opened:true,key_collected:false},2,'key')]},{id:'key',kind:'scene',hotspots:[],skip_rules:[skip({key_collected:true},2,'empty')]},{id:'empty',kind:'scene',hotspots:[]}]}];
 e.inventoryItems=[{id:'key-item',kind:'object',name:'Key'}];e.startPreview();e.transitionScene('closed',2);e.clearSceneTransition();
 e.activate({id:'open',action:'transition',target_id:'key',flag_effects:[condition('stone_opened',true)]});assert.equal(e.previewId,'key');e.clearSceneTransition();
 e.activate({id:'leave',action:'page',target_page_id:1,target_scene_id:'wide',flag_conditions:[condition('key_collected',true)],unavailable_text:'Take the key first.'});assert.equal(e.previewId,'key');assert.equal(e.narration.text,'Take the key first.');
 e.activate({id:'pickup',action:'pickup',object_id:'key-item',target_id:'empty',flag_effects:[condition('key_collected',true)]});assert.equal(e.flags.key_collected,true);e.clearSceneTransition();
 e.inventory=[];e.transitionScene('wide',1);assert.equal(e.previewId,'wide-open');e.clearSceneTransition();
 e.transitionScene('closed',2);assert.equal(e.previewId,'empty');e.clearSceneTransition();
 e.activate({id:'close',action:'transition',target_id:'closed',flag_effects:[condition('stone_opened',false)]});e.clearSceneTransition();e.transitionScene('wide',1);assert.equal(e.previewId,'wide');e.clearSceneTransition();
 e.startPreview();assert.equal(e.flags.key_collected,false);assert.equal(e.flags.stone_opened,false);e.closePreview();
});
test('flag-only skips cap loops and hidden areas require all conditions',()=>{
 const e=sceneEditor({});e.editorPageId=1;e.previewPageId=1;e.flags={open:true};e.preview=true;e.previewId='a';
 e.document={visuals:[{id:'a',kind:'scene',hotspots:[{id:'hidden',unavailable_behavior:'hide',flag_conditions:[{key:'open',value:false}]}],skip_rules:[{object_ids:[],flag_conditions:[{key:'open',value:true}],target_page_id:1,target_scene_id:'b'}]},{id:'b',kind:'scene',hotspots:[],skip_rules:[{object_ids:[],flag_conditions:[{key:'open',value:true}],target_page_id:1,target_scene_id:'a'}]}]};
 assert.equal(e.currentHotspots.length,0);e.resolveSceneEntry(1,'a');assert.equal(e.skipJumpCount,3);
});
test('image ordering persists through the server and does not change scenes',async()=>{
 const e=sceneEditor({orderUrl:'/order'});e.jobs=[{id:3,status:'completed'},{id:2,status:'completed',sourcePage:'Page 2'},{id:1,status:'completed'}];e.assets=[];e.document={visuals:[{id:'scene'}]};
 e.request=async(url,method,body)=>{assert.equal(url,'/order');assert.equal(method,'PUT');return body;};
 await e.moveImageOrder(2,-1);assert.equal(e.movedImageId,2);assert.deepEqual(e.visibleJobs.map(j=>j.id),[2,3,1]);assert.equal(e.document.visuals[0].id,'scene');
 e.jobs.unshift({id:4,status:'draft'});assert.deepEqual(e.visibleJobs.map(j=>j.id),[4,2,3,1]);
});

test('availability is opt-in, preserves legacy conditions and can be disabled without deleting them',()=>{
 const e=sceneEditor({});e.flags={opened:false};e.inventory=[];
 assert.equal(e.availabilityEnabled({}),false);
 const area={flag_conditions:[{key:'opened',value:true}],required_items:[]};
 assert.equal(e.availabilityEnabled(area),true);assert.equal(e.conditionsMet(area),false);
 area.availability_enabled=false;assert.equal(e.availabilityEnabled(area),false);assert.equal(e.conditionsMet(area),true);
 assert.equal(area.flag_conditions.length,1);area.availability_enabled=true;assert.equal(e.conditionsMet(area),false);
 e.flags.opened=true;assert.equal(e.conditionsMet(area),true);
});

test('pickup opens tray and flies to the new item slot, then reveals the item',t=>{
 t.mock.timers.enable({apis:['setTimeout']});const e=sceneEditor({});e.preview=true;e.assets=[{id:1,url:'/key.png'}];
 const img={getBoundingClientRect:()=>({left:200,top:400,width:64,height:56})};
 const slot={dataset:{inventoryItem:'key'},getBoundingClientRect:()=>({right:280}),querySelector:()=>img};
 const tray={scrollLeft:0,querySelectorAll:()=>[slot],getBoundingClientRect:()=>({right:270}),closest:()=>({getBoundingClientRect:()=>({left:20,top:30,height:500})})};
 e.$refs={inventoryTray:tray};e.animatePickup({id:'key',asset_id:1},{x:.5,y:.5,width:.1,height:.1});
 assert.equal(e.inventoryOpen,true);assert.equal(e.pickupFlight.targetX,212);assert.equal(e.pickupFlight.targetY,398);assert.equal(e.pickupFlight.itemId,'key');assert.equal(tray.scrollLeft,18);
 t.mock.timers.tick(650);assert.equal(e.pickupFlight,null);
});
test('inventory clicks still inspect while drops use the area with standard narration',()=>{
 const e=sceneEditor({});const area={id:'lock',action:'inventory',label:'Lock',interaction_text:'Choose a key',fallback_text:'That does not fit.',inventory_rules:[],description_position:'area_top',x:.2,y:.3,width:.2,height:.2};
 e.document={visuals:[{id:'porch',kind:'scene',hotspots:[area]}]};e.selected='porch';e.previewId='porch';e.preview=true;e.inventory=[{id:'letter',inventoryId:'letter-slot',description:'A letter'}];
 e.openAreaInteraction(area);assert.equal(e.inventoryOpen,true);assert.equal(e.modal,null);assert.equal(e.narration.position,'area_top');
 e.selectInventoryItemForPlay(e.inventory[0]);assert.equal(e.modal.inventoryId,'letter-slot');assert.equal(e.inventoryOpen,false);
 e.modal=null;e.useInventoryOnArea('letter-slot',area);assert.equal(e.narration.text,'That does not fit.');assert.equal(e.narration.position,'area_top');assert.equal(e.modal,null);e.cancelInventoryUse();
});

test('repeated examine clicks toggle the same message and a different area replaces it',()=>{
 const e=sceneEditor({});e.preview=true;e.previewId='scene';e.previewPageId=1;
 const a={id:'a',action:'examine',description:'A rose'},b={id:'b',action:'examine',description:'A stone'};
 e.activate(a);assert.equal(e.narration.text,'A rose');e.activate(a);assert.equal(e.narration,null);
 e.activate(a);assert.equal(e.narration.text,'A rose');e.activate(b);assert.equal(e.narration.text,'A stone');
 e.narration=null;e.activate(b);assert.equal(e.narration.text,'A stone');
});

test('first pickup waits for the closed inventory tray to become measurable',t=>{
 t.mock.timers.enable({apis:['setTimeout']});const previous=globalThis.requestAnimationFrame;let frame,shown=false;
 globalThis.requestAnimationFrame=fn=>{frame=fn;};
 try{const e=sceneEditor({});e.preview=true;e.assets=[{id:1,url:'/envelope.png'}];
 const slot={dataset:{inventoryItem:'envelope'},getBoundingClientRect:()=>({right:220}),querySelector:()=>({getBoundingClientRect:()=>shown?{left:140,top:430,width:64,height:56}:{left:0,top:0,width:0,height:0}})};
 e.$refs={inventoryTray:{scrollLeft:0,querySelectorAll:()=>[slot],getBoundingClientRect:()=>({right:500}),closest:()=>({getBoundingClientRect:()=>({left:20,top:30,height:500})})}};
 e.animatePickup({id:'envelope',asset_id:1},{x:.5,y:.7,width:.1,height:.1});assert.equal(e.inventoryOpen,true);assert.equal(e.pickupFlight.url,null);
 shown=true;frame();assert.equal(e.pickupFlight.targetX,152);assert.equal(e.pickupFlight.targetY,428);assert.equal(e.pickupFlight.url,'/envelope.png');
 e.closePreview();
 }finally{globalThis.requestAnimationFrame=previous;}
});

test('pointer drag threshold preserves clicks and drop targets follow area layering',()=>{
 const e=sceneEditor({});e.preview=true;e.previewId='scene';e.selected='scene';const area={id:'lock',action:'inventory',x:.4,y:.4,width:.2,height:.2};
 e.document={visuals:[{id:'scene',kind:'scene',hotspots:[{id:'door',x:0,y:0,width:1,height:1},area]}]};e.inventory=[{id:'key'}];
 e.$refs={previewDialog:{querySelector:()=>({getBoundingClientRect:()=>({left:0,top:0,width:100,height:100})})}};
 e.startInventoryDrag({button:0,pointerId:1,clientX:10,clientY:90,currentTarget:{}},e.inventory[0]);e.moveInventoryDrag({pointerId:1,clientX:12,clientY:90});assert.equal(e.inventoryDrag.active,false);
 e.moveInventoryDrag({pointerId:1,clientX:50,clientY:50});assert.equal(e.inventoryDrag.active,true);
 e.endInventoryDrag({pointerId:1,clientX:50,clientY:50});assert.equal(e.pendingInventoryUse.area.id,'lock');assert.equal(e.suppressInventoryClick,true);e.cancelInventoryUse();
});
test('area cursors use validated overrides and useful automatic defaults',()=>{
 const e=sceneEditor({});assert.equal(e.areaCursor({action:'inventory'}),'alias');assert.equal(e.areaCursor({action:'pickup'}),'grab');assert.equal(e.areaCursor({action:'examine',cursor:'help'}),'help');assert.equal(e.areaCursor({action:'examine',cursor:'invalid'}),'pointer');
});

test('closing preview cancels pending inventory effects and transition',t=>{
 t.mock.timers.enable({apis:['setTimeout']});const e=sceneEditor({});e.preview=true;e.previewId='a';e.previewPageId=1;e.flags={unlocked:false};e.flagDefinitions=[{key:'unlocked'}];e.inventory=[{id:'key'}];
 e.useInventoryOnArea('key',{id:'lock',action:'inventory',inventory_rules:[{object_id:'key',consume:true,flag_effects:[{key:'unlocked',value:true}]}]});
 e.finishInventoryText(e.pendingInventoryUse.key);e.closePreview();t.mock.timers.tick(5000);assert.equal(e.flags.unlocked,false);assert.equal(e.pendingInventoryUse,null);
});

test('extracted layers stay hidden after inventory consumption and can return through flags',()=>{
 const e=sceneEditor({});const h={id:'area',action:'pickup',x:.1,y:.1,width:.2,height:.2,overlay_bounds:{x:.05,y:.05,width:.3,height:.3},availability_enabled:true,unavailable_behavior:'hide',flag_conditions:[{key:'collected_key',value:false}],flag_effects:[{key:'collected_key',value:true}]};
 e.document={visuals:[{id:'scene',kind:'scene',hotspots:[h]}]};e.selected='scene';e.preview=true;e.previewId='scene';e.editorPageId=1;e.previewPageId=1;e.flagDefinitions=[{key:'collected_key',initial:false}];e.flags={collected_key:false};
 assert.equal(e.currentHotspots.length,1);e.applyFlagEffects(h);e.collected=['area'];e.inventory=[];assert.equal(e.currentHotspots.length,0);
 e.applyFlagEffects({flag_effects:[{key:'collected_key',value:false}]});assert.equal(e.currentHotspots.length,1);
 const style=e.areaImageStyle(h);assert.equal(style.left,'-25%');assert.ok(Math.abs(parseFloat(style.width)-150)<.0001);
});

test('local switches toggle patches and v2 pickups never transition the full scene',()=>{
 const e=sceneEditor({});e.preview=true;e.previewId='room';e.editorPageId=1;e.previewPageId=1;e.document={visuals:[{id:'room',kind:'scene',hotspots:[]}]};e.assets=[{id:1,url:'/off'},{id:2,url:'/on'}];e.flags={switch_a:false};e.flagDefinitions=[{key:'switch_a'}];
 const h={id:'switch',action:'state_v2',state_flag:'switch_a',overlay:true,overlay_asset_id:1,state_asset_id:2};e.activate(h);assert.equal(e.areaImage(h).id,2);assert.equal(e.previewId,'room');e.activate(h);assert.equal(e.areaImage(h),null);assert.equal(e.inventory.length,0);
 e.inventoryItems=[{id:'key',kind:'object',name:'Key'}];e.animatePickup=()=>{};let transitions=0;e.transitionScene=()=>transitions++;
 e.activate({id:'v2',action:'pickup_v2',object_id:'key',target_id:'other'});assert.equal(e.inventory.length,1);assert.equal(transitions,0);
 e.activate({id:'legacy',action:'pickup',object_id:'key',target_id:'other'});assert.equal(transitions,1);
});

test('switch descriptions require two clicks independently for both states',()=>{
 const e=sceneEditor({});e.preview=true;e.previewId='room';e.previewPageId=1;e.editorPageId=1;e.document={visuals:[{id:'room',kind:'scene',hotspots:[]}]};e.flags={switch_a:false};e.assets=[{id:2,url:'/on'}];
 const h={id:'switch',action:'state_v2',state_flag:'switch_a',state_asset_id:2,description:'Unpressed button',state_description:'Pressed button',show_hover_text:false};
 e.activate(h);assert.equal(e.flags.switch_a,false);assert.equal(e.narration.text,'Unpressed button');assert.equal(e.pickupReady(h),true);
 e.activate(h);assert.equal(e.flags.switch_a,true);assert.equal(e.narration,null);assert.equal(e.pickupReady(h),false);
 e.activate(h);assert.equal(e.flags.switch_a,true);assert.equal(e.narration.text,'Pressed button');
 e.activate(h);assert.equal(e.flags.switch_a,false);assert.equal(e.narration,null);
 h.show_hover_text=true;assert.equal(e.hoverDetails(h).description,'Unpressed button');e.activate(h);assert.equal(e.flags.switch_a,true);assert.equal(e.hoverDetails(h).description,'Pressed button');
 e.preview=false;assert.equal(e.areaImage(h).id,2);
});

test('empty collectible slots accept returns, reject wrong items and remain available after pickup',()=>{
 const e=sceneEditor({});e.preview=true;e.previewId='box';e.previewPageId=1;e.editorPageId=1;e.flags={empty:true,enabled:false};e.flagDefinitions=[{key:'empty'},{key:'enabled'}];e.animatePickup=()=>{};
 const area={id:'groove',action:'pickup_v2',object_id:'medallion',initial_empty:true,collectible_flag:'empty',overlay:true,overlay_asset_id:10,overlay_bounds:{x:0,y:0,width:.2,height:.2},inventory_rules:[{object_id:'crystal',allowed:false,response:'It slides off.'}]};
 e.document={visuals:[{id:'box',kind:'scene',hotspots:[area]}]};e.inventoryItems=[{id:'medallion',kind:'object',asset_id:11},{id:'crystal',kind:'object',asset_id:12}];e.assets=[{id:10},{id:11},{id:12}];e.inventory=e.inventoryItems.map(i=>({...i}));
 assert.equal(e.areaImage(area),null);e.useInventoryOnArea('crystal',area);assert.equal(e.narration.text,'It slides off.');e.completeInventoryUse();assert.equal(e.inventory.length,2);assert.equal(e.flags.empty,true);
 e.useInventoryOnArea('medallion',area);e.completeInventoryUse();assert.equal(e.inventory.length,1);assert.equal(e.flags.empty,false);assert.equal(e.areaImage(area).id,10);
 e.useInventoryOnArea('crystal',area);assert.match(e.narration.text,/occupied/);e.completeInventoryUse();assert.equal(e.inventory.length,1);
 e.activate(area);assert.equal(e.flags.empty,true);assert.equal(e.inventory.length,2);assert.equal(e.currentHotspots.length,1);assert.equal(e.areaImage(area),null);
 area.inventory_rules=[{object_id:'crystal',allowed:true,response:'Try the crystal.',flag_conditions:[{key:'enabled',value:true}]}];
 e.useInventoryOnArea('crystal',area);e.completeInventoryUse();assert.equal(e.flags.empty,true);assert.equal(e.inventory.length,2);
 e.flags.enabled=true;e.useInventoryOnArea('crystal',area);e.completeInventoryUse();assert.equal(e.flags.empty,false);assert.equal(e.areaImage(area).id,12);e.activate(area);assert.equal(e.flags.empty,true);assert.equal(e.inventory.filter(i=>i.id==='crystal').length,1);
});
