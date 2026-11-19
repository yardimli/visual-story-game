import test from 'node:test';
import assert from 'node:assert/strict';
import {gamePlayer} from '../resources/js/game-player.js';
import {animatedDescription} from '../resources/js/animated-description.js';
import fs from 'node:fs';
import path from 'node:path';
import {sceneEditor} from '../resources/js/scene-editor.js';

function data(){return {pages:[{id:1,position:1,title:'Hall',visuals:[{id:'hall',kind:'scene',asset_id:1,hotspots:[{id:'coins',label:'Coins',action:'pickup_v2',object_id:'coin-item',description:'Some old coins.',show_hover_text:false,placement_enabled:false,x:.2,y:.2,width:.1,height:.1},{id:'box',label:'Box',action:'examine',description:'A box',show_hover_text:false,x:.4,y:.2,width:.1,height:.1}]}]}],inventory:[{id:'coin-item',kind:'object',name:'Coins',asset_id:2}],assets:[{id:1,url:'/hall.png'},{id:2,url:'/coin.png'}],flags:[],cursorTooltips:{},unavailableText:'Not yet',unavailableTimeout:1000,audioSettings:{},sounds:[],start:{pageId:1,sceneId:'hall',inventory:[],flags:[]}};}

test('standalone player reveals speech then collects on the next area click',t=>{
 t.mock.timers.enable({apis:['setTimeout']});const old=globalThis.window;globalThis.window={};
 const p=gamePlayer(data());p.init();p.begin();p.animatePickup=()=>{};
 const h=p.displayed.hotspots[0];p.activate(h);assert.equal(p.inventory.length,0);
 const d=animatedDescription();d.$dispatch=(event,detail)=>{if(event==='description-complete')p.descriptionCompleted(detail.key);};d.play(p.narration.text,'top',p.previewId+':'+p.narrationVersion,true);
 p.soundPlayer.speaking=true;globalThis.window.dispatchEvent=event=>d.clickFromArea(event.detail.key);
 assert.equal(p.clickAreaMessage(h),true);assert.equal(p.soundPlayer.speaking,false);assert.equal(d.count,d.words.length);assert.equal(p.narration.revealed,true);
 assert.equal(p.clickAreaMessage(h),false);p.activate(h);assert.equal(p.inventory[0].id,'coin-item');assert.equal(p.narration,null);d.destroy();p.destroy();globalThis.window=old;
});
test('ready pickups survive inspecting other objects and reset for a new playthrough',()=>{
 const old=globalThis.window;globalThis.window={};const p=gamePlayer(data());p.init();p.begin();p.animatePickup=()=>{};
 const [coin,box]=p.displayed.hotspots;p.activate(coin);p.descriptionCompleted(p.previewId+':'+p.narrationVersion);box.action='state_v2';box.state_flag='box-open';box.state_asset_id=1;p.activate(box);assert.equal(p.descriptionStep(coin),false);assert.equal(p.areaCursor(coin),'grab');p.activate(coin);assert.equal(p.inventory[0].id,'coin-item');
 p.closePreview();p.begin();assert.equal(p.descriptionStep(coin),true);p.destroy();globalThis.window=old;
});
test('editor opens an isolated tab only after saving and passes starting inventory and flags',async()=>{
 const old=globalThis.window;const child={location:{},close(){this.closed=true;}};let saved=false;
 globalThis.window={open(){return child;},location:{origin:'http://localhost'},vsgAutosave:{async flush(){return true;}}};
 try{const e=sceneEditor({playUrl:'/stories/2/play'});e.editorPageId=1;e.previewInventorySelection=['key'];e.previewFlags=['unlocked'];e.previewStartKey='1:hall';e.document={visuals:[{id:'hall',kind:'scene'}]};e.previewPages=[{id:1,visuals:e.document.visuals}];e.flush=async()=>{saved=true;return true;};let closed=false;e.$refs={previewInventoryDialog:{close(){closed=true;}}};await e.confirmPreviewInventory();
 assert.equal(saved,true);assert.equal(closed,true);assert.equal(e.preview,false);assert.equal(child.opener,null);const url=new URL(child.location.href);assert.equal(url.pathname,'/stories/2/play');assert.deepEqual(url.searchParams.getAll('inventory[]'),['key']);assert.deepEqual(url.searchParams.getAll('flags[]'),['unlocked']);assert.equal(url.searchParams.get('scene'),'hall');
 }finally{globalThis.window=old;}
});


test('player entrypoint has no imports from builder or generation modules',()=>{
 const visited=new Set();function scan(file){file=path.resolve(file);if(visited.has(file))return;visited.add(file);const source=fs.readFileSync(file,'utf8');assert.doesNotMatch(file,/scene-editor|scene-extraction|container-plans|model-preferences|audio-settings/);for(const match of source.matchAll(/(?:import[^;]*?from|export[^;]*?from)\s*['"](\.[^'"]+)['"]/g)){let target=path.resolve(path.dirname(file),match[1]);if(!path.extname(target))target+='.js';scan(target);}}
 scan('resources/js/player.js');assert.ok([...visited].some(file=>file.endsWith('game-runtime.js')));
});


test('local switch advances completed text and reads each state only once',()=>{
 const old=globalThis.window;globalThis.window={};const p=gamePlayer(data());p.init();p.begin();
 const h=p.displayed.hotspots[1];Object.assign(h,{action:'state_v2',state_flag:'open',state_asset_id:2,state_description:'The box is open.'});
 assert.equal(p.areaCursor(h),'help');p.activate(h);assert.equal(p.flags.open,undefined);
 p.descriptionCompleted(p.previewId+':'+p.narrationVersion);assert.equal(p.clickAreaMessage(h),false);p.activate(h);
 assert.equal(p.flags.open,true);assert.equal(p.narration.text,'The box is open.');
 p.descriptionCompleted(p.previewId+':'+p.narrationVersion);assert.equal(p.areaCursor(h),'pointer');assert.equal(p.clickAreaMessage(h),false);p.activate(h);
 assert.equal(p.flags.open,false);assert.equal(p.narration,null);p.activate(h);assert.equal(p.flags.open,true);assert.equal(p.narration,null);
 p.examineMode=true;assert.equal(p.areaCursor(h),'help');p.activate(h);assert.equal(p.examineMode,false);assert.equal(p.flags.open,true);assert.equal(p.narration.text,'The box is open.');
 p.destroy();globalThis.window=old;
});

test('examine areas replay descriptions while one-shot examine never picks up an item',()=>{
 const old=globalThis.window;globalThis.window={};const p=gamePlayer(data());p.init();p.begin();const [coin,box]=p.displayed.hotspots;
 p.activate(box);p.descriptionCompleted(p.previewId+':'+p.narrationVersion);assert.equal(p.clickAreaMessage(box),false);p.activate(box);assert.equal(p.narration.text,'A box');
 p.activate(coin);p.descriptionCompleted(p.previewId+':'+p.narrationVersion);p.examineMode=true;p.activate(coin);assert.equal(p.inventory.length,0);assert.equal(p.examineMode,false);assert.equal(p.narration.text,coin.description);
 p.destroy();globalThis.window=old;
});


test('collected placement locations open inventory without describing the missing item',()=>{
 const old=globalThis.window;globalThis.window={};const p=gamePlayer(data());p.init();p.begin();p.animatePickup=()=>{};
 try{
 const h=p.displayed.hotspots[0];h.placement_enabled=true;p.activate(h);p.descriptionCompleted(p.previewId+':'+p.narrationVersion);p.clickAreaMessage(h);p.activate(h);
 assert.equal(p.inventory.length,1);assert.equal(p.collectibleEmpty(h),true);assert.ok(p.currentHotspots.includes(h));
 p.inventoryOpen=false;p.activate(h);assert.equal(p.inventoryOpen,true);assert.equal(p.narration,null);assert.equal(p.areaCursor(h),'alias');
 h.show_hover_text=true;assert.equal(p.hoverDetails(h).description,'');p.examineMode=true;p.activate(h);assert.equal(p.examineMode,false);assert.equal(p.narration,null);
 // An initially empty placement location also must not narrate the absent object.
 p.readDescriptions={};assert.equal(p.areaDescription(h),'');assert.equal(p.descriptionStep(h),false);
 }finally{p.destroy();globalThis.window=old;}
});
