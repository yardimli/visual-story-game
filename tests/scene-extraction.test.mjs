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
 editor.extractionQuotes.background={estimate:0.05};
 assert.equal(editor.extractionTotal,0.05);
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
