import test from 'node:test';
import assert from 'node:assert/strict';
import {sceneExtraction} from '../resources/js/scene-extraction.js';
import {sceneEditor} from '../resources/js/scene-editor.js';

test('integrated editor resolves selected extraction and output state dynamically', () => {
 const editor=sceneEditor({});
 assert.equal(editor.extractionBatch,undefined);
 editor.selected='scene';
 editor.extractionBatches=[{id:7,visual_id:'scene',outputs:[{id:'background',type:'background',accepted_asset_id:null,jobs:[]}]}];
 editor.extractionId=7;
 assert.equal(editor.extractionBatch.id,7);
 assert.equal(editor.sceneExtractions.length,1);
 assert.equal(editor.extractionMissing.length,1);
 editor.extractionBatch.outputs[0].accepted_asset_id=42;
 assert.equal(editor.extractionBackground,42);
 assert.equal(editor.extractionMissing.length,0);
});

test('polling only runs while the selected plan has unfinished generation jobs', () => {
 const editor=sceneEditor({});
 editor.$refs={extractionDialog:{open:true}};
 editor.extractionBatches=[{id:1,outputs:[{jobs:[]}]}];editor.extractionId=1;
 editor.scheduleExtractionRefresh();
 assert.equal(editor.extractionTimer,null);
 editor.extractionBatch.outputs[0].jobs=[{status:'running'}];
 editor.scheduleExtractionRefresh();
 assert.ok(editor.extractionTimer);
 editor.extractionBatch.outputs[0].jobs[0].status='completed';
 editor.scheduleExtractionRefresh();
 assert.equal(editor.extractionTimer,null);
});

test('preparation locks immediately, blocks duplicate submissions, and releases on save failure', async () => {
 const editor=sceneExtraction({});
 let finishSave, saves=0;
 editor.flush=()=>{saves++;return new Promise(resolve=>{finishSave=resolve;});};
 editor.error='Scene changed in another tab.';
 const pending=editor.prepareExtraction();
 assert.equal(editor.extractionPreparing,true);
 assert.equal(editor.extractionBusy,true);
 assert.match(editor.extractionProgress,/Saving scene/);
 await editor.prepareExtraction();
 assert.equal(saves,1);
 finishSave(false);
 await pending;
 assert.equal(editor.extractionPreparing,false);
 assert.equal(editor.extractionBusy,false);
 assert.equal(editor.extractionProgress,'');
 assert.equal(editor.extractionError,editor.error);
});

test('failed preparation displays its error and enables retry', async () => {
 const editor=sceneExtraction({extractionUrl:'/extractions'});
 editor.flush=async()=>true;
 editor.extractionAreas=[{id:'area',selected:true,bounds:{}}];
 editor.request=async()=>{
  assert.equal(editor.extractionBusy,true);
  assert.match(editor.extractionProgress,/Preparing cropped/);
  throw new Error('Source image could not be read.');
 };
 await editor.prepareExtraction();
 assert.equal(editor.extractionError,'Source image could not be read.');
 assert.equal(editor.extractionPreparing,false);
 assert.equal(editor.extractionBusy,false);
 assert.equal(editor.extractionProgress,'');
});

test('extracted image follows area movement and resizing, and cancellation restores it', () => {
 const editor=sceneEditor({});const h={id:'area',x:.2,y:.2,width:.2,height:.2,overlay_bounds:{x:.18,y:.18,width:.24,height:.24}};
 editor.document={visuals:[{id:'scene',hotspots:[h]}]};editor.selected='scene';editor.changed=()=>{};
 const event={button:0,clientX:20,clientY:20,pointerId:1,preventDefault(){},currentTarget:{closest:()=>({getBoundingClientRect:()=>({width:100,height:100})}),setPointerCapture(){}}};
 const before=editor.areaImageStyle(h);editor.startArea(event,h);editor.moveArea({clientX:40,clientY:50});
 assert.ok(Math.abs(h.overlay_bounds.x-.38)<1e-9);assert.ok(Math.abs(h.overlay_bounds.y-.48)<1e-9);
 assert.ok(Math.abs(parseFloat(editor.areaImageStyle(h).left)-parseFloat(before.left))<1e-9);
 editor.endArea(true);assert.equal(h.x,.2);assert.equal(h.overlay_bounds.x,.18);
 editor.startArea(event,h,'se');editor.moveArea({clientX:40,clientY:40});assert.ok(Math.abs(h.overlay_bounds.width-.48)<1e-9);editor.endArea(true);
});
test('image pickers hide internal extraction references but retain uploaded references', () => {
 const editor=sceneEditor({});editor.assets=[{id:1,role:'extraction_reference'},{id:2,role:'reference'},{id:3,role:'scene',archived:true}];
 assert.deepEqual(editor.pickerAssets.map(a=>a.id),[2]);assert.deepEqual(editor.referenceAssets.map(a=>a.id),[2]);
});

test('render timer measures elapsed time from worker start and ignores queue age', () => {
 const editor=sceneEditor({});editor.renderClock=Date.parse('2026-11-11T08:02:05Z');
 assert.equal(editor.renderElapsed({started_at:'2026-11-11T08:00:00Z',created_at:'2026-11-11T07:00:00Z'}),'2m 05s');
 assert.equal(editor.renderElapsed({started_at:null}),'');
 assert.equal(editor.renderElapsed({started_at:'2026-11-11T08:03:00Z'}),'0m 00s');
});

test('v2 area reset restores both hitbox and layer geometry from saved defaults', async()=>{
 const e=sceneEditor({});let saves=0;e.changed=()=>saves++;
 const h={action:'pickup_v2',x:.7,y:.8,width:.1,height:.1,overlay_bounds:{x:.7,y:.8,width:.1,height:.1},default_bounds:{x:.2,y:.3,width:.2,height:.2},default_overlay_bounds:{x:.18,y:.28,width:.24,height:.24}};
 await e.resetAreaPosition(h);assert.equal(h.x,.2);assert.equal(h.y,.3);assert.deepEqual(h.overlay_bounds,h.default_overlay_bounds);assert.equal(saves,1);assert.equal(e.resettingArea,false);
});
test('older v2 areas recover defaults from the matching extraction output', async()=>{
 const e=sceneEditor({extractionUrl:'/extractions'});e.selected='scene';e.changed=()=>{};
 e.request=async()=>({batches:[{visual_id:'scene',outputs:[{area_id:'switch',type:'switch_on',accepted_asset_id:8,bounds:{x:.1,y:.2,width:.3,height:.4}}]}]});
 const h={id:'switch',action:'state_v2',state_asset_id:8,x:.8,y:.8,width:.1,height:.1};await e.resetAreaPosition(h);
 assert.equal(h.x,.1);assert.equal(h.width,.3);assert.equal(h.overlay_bounds.height,.4);assert.equal(h.default_bounds.y,.2);
});

test('drag handle sits below the area and clamps fully inside scene edges',()=>{
 const e=sceneEditor({});const h={x:.2,y:.2,width:.2,height:.2};
 let style=e.areaDragButtonStyle(h,1000,500);assert.equal(style.left,'-2px');assert.equal(style.top,'102px');
 const bottom={x:.99,y:.95,width:.01,height:.05};style=e.areaDragButtonStyle(bottom,1000,500);
 assert.equal(bottom.x*1000+2+parseFloat(style.left),972);assert.equal(bottom.y*500+2+parseFloat(style.top),472);
 style=e.areaDragButtonStyle(bottom,400,200,32,32);assert.equal(bottom.x*400+2+parseFloat(style.left),368);assert.equal(bottom.y*200+2+parseFloat(style.top),168);
});

test('batch generation submits unaccepted outputs without an estimate step',async()=>{
 const e=sceneEditor({});e.extractionModel='image-model';e.extractionId=1;e.extractionBatches=[{id:1,outputs:[{id:'pending',jobs:[]},{id:'accepted',accepted_asset_id:8,jobs:[]},{id:'running',jobs:[{status:'running'}]}]}];
 const calls=[];e.extractionAction=async(output,action)=>calls.push([output.id,action]);await e.generateMissingExtraction();assert.deepEqual(calls,[['pending','generate']]);assert.equal(e.extractionRun,false);
});

test('image area resizing locks aspect ratio at every corner and scene boundary',()=>{
 const e=sceneEditor({});const original={x:.3,y:.3,width:.2,height:.1};
 for(const corner of ['nw','ne','sw','se']){
  const h={...original};e.resizeAreaProportionally(h,original,corner,corner.includes('w')?-.1:.1,0);
  assert.ok(Math.abs(h.width/h.height-2)<1e-10);
  assert.ok(Math.abs((corner.includes('w')?h.x+h.width:h.x)-(corner.includes('w')?.5:.3))<1e-10);
  assert.ok(Math.abs((corner.includes('n')?h.y+h.height:h.y)-(corner.includes('n')?.4:.3))<1e-10);
  e.resizeAreaProportionally(h,original,corner,corner.includes('w')?-10:10,corner.includes('n')?-10:10);
  assert.ok(Math.abs(h.width/h.height-2)<1e-10);assert.ok(h.x>=-1e-10 && h.y>=-1e-10 && h.x+h.width<=1+1e-10 && h.y+h.height<=1+1e-10);
 }
});
test('pointer resizing locks only areas with image display enabled',()=>{
 const e=sceneEditor({});const h={id:'a',x:.2,y:.2,width:.2,height:.1,overlay:true,overlay_bounds:{x:.2,y:.2,width:.2,height:.1}};
 e.document={visuals:[{id:'scene',hotspots:[h]}]};e.selected='scene';const original=JSON.parse(JSON.stringify(h));e.areaDrag={id:'a',handle:'se',startX:0,startY:0,rect:{width:1000,height:500},original};
 e.moveArea({clientX:100,clientY:0});assert.ok(Math.abs(h.width/h.height-2)<1e-10);assert.ok(Math.abs(h.overlay_bounds.width/h.overlay_bounds.height-2)<1e-10);
 Object.assign(h,original,{overlay:false});e.moveArea({clientX:100,clientY:0});assert.ok(Math.abs(h.width-.3)<1e-10);assert.ok(Math.abs(h.height-.1)<1e-10);
});


test('updating a saved plan selects only new areas and uses the accepted background',()=>{
 const editor=sceneExtraction({});editor.selected='scene';editor.visual={asset_id:1};
 editor.extractionPlanChoice=7;editor.extractionBatches=[{id:7,visual_id:'scene',outputs:[{type:'background',accepted_asset_id:22},{type:'layer',area_id:'old'}]}];
 editor.extractionAreas=[{id:'old',selected:true},{id:'bag',selected:true}];
 editor.$refs={extractionPlanDialog:{close(){}},extractionDialog:{showModal(){}}};
 editor.updateExtractionPlan();
 assert.equal(editor.extractionUpdateId,7);assert.equal(editor.extractionSource,22);
 assert.equal(editor.extractionAreas[0].selected,false);assert.equal(editor.extractionAreas[0].reused,true);
 assert.equal(editor.extractionAreas[1].selected,true);
});
