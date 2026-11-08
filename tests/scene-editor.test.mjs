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
