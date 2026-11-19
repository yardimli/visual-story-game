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
 p.activate(box);p.descriptionCompleted(p.previewId+':'+p.narrationVersion);assert.equal(p.clickAreaMessage(box),true);assert.equal(p.narration,null);p.activate(box);assert.equal(p.narration.text,'A box');
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


test('placement uses story defaults and timeouts while preserving custom responses',()=>{
 const old=globalThis.window;globalThis.window={};const p=gamePlayer(data());p.init();p.begin();p.animatePickup=()=>{};
 try{const h=p.displayed.hotspots[0];Object.assign(h,{placement_enabled:true,initial_empty:true,inventory_rules:[{object_id:'coin-item',allowed:true,response:'You place the item back.'}]});p.inventory=[{id:'coin-item'}];p.gameplayMessages={placed:{text:'Returned to the shelf.',timeout_ms:750},occupied:{text:'No room.',timeout_ms:100},nothing:{text:'No effect.',timeout_ms:500}};
 p.useInventoryOnArea('coin-item',h);assert.equal(p.narration.text,'Returned to the shelf.');assert.equal(p.narration.hideAfterMs,750);p.completeInventoryUse();assert.equal(p.collectibleEmpty(h),false);
 p.inventory=[{id:'coin-item'}];p.useInventoryOnArea('coin-item',h);assert.equal(p.narration.text,'No room.');p.cancelInventoryUse();
 delete p.placedObjects[h.id];h.inventory_rules[0].response='The coins fit perfectly.';p.useInventoryOnArea('coin-item',h);assert.equal(p.narration.text,'The coins fit perfectly.');p.cancelInventoryUse();
 h.inventory_rules[0].response='';p.gameplayMessages.placed.text='';p.useInventoryOnArea('coin-item',h);assert.equal(p.pendingInventoryUse,null);assert.equal(p.inventory.length,0);
 }finally{p.destroy();globalThis.window=old;}
});


test('inventory action sound waits for item use and local switches sound in both directions',()=>{
 const old=globalThis.window;globalThis.window={};const p=gamePlayer(data());p.init();p.begin();p.animatePickup=()=>{};
 try{const calls=[];p.soundPlayer={click:()=>calls.push('click'),action:(area,item)=>calls.push(item?.id||area.id),channel:()=>{},stop:()=>{}};
 const h=p.displayed.hotspots[1];Object.assign(h,{action:'inventory',description:'',inventory_rules:[{object_id:'coin-item',response:'It fits.'}]});p.inventory=[{id:'coin-item'}];
 p.activate(h);assert.deepEqual(calls,[]);assert.equal(p.inventoryOpen,true);p.useInventoryOnArea('coin-item',h);assert.deepEqual(calls,[]);p.completeInventoryUse();assert.deepEqual(calls,['coin-item']);
 Object.assign(h,{action:'state_v2',state_flag:'open',state_asset_id:2,state_description:''});p.activate(h);assert.equal(p.flags.open,true);p.activate(h);assert.equal(p.flags.open,false);assert.deepEqual(calls,['coin-item','box','box']);
 }finally{p.destroy();globalThis.window=old;}
});


test('unlisted placement items always get no-effect and return, including the original item',()=>{
 const old=globalThis.window;globalThis.window={};const p=gamePlayer(data());p.init();p.begin();const returned=[];p.animatePickup=item=>returned.push(item.id);
 try{const h=p.displayed.hotspots[0];Object.assign(h,{placement_enabled:true,initial_empty:true,inventory_rules:[],fallback_text:'Incorrect custom fallback'});p.inventory=[{id:'coin-item'},{id:'other'}];p.gameplayMessages={nothing:{text:'That does not work.',timeout_ms:500}};
 for(const id of ['coin-item','other']){p.useInventoryOnArea(id,h);assert.equal(p.pendingInventoryUse,null);assert.equal(p.narration.text,'That does not work.');p.completeInventoryUse();assert.equal(p.inventory.length,2);assert.equal(p.collectibleEmpty(h),true);}
 assert.deepEqual(returned,['coin-item','other']);h.inventory_rules=[{object_id:'coin-item',allowed:true,response:''}];p.useInventoryOnArea('coin-item',h);assert.equal(p.pendingInventoryUse,null);assert.equal(p.inventory.length,1);
 }finally{p.destroy();globalThis.window=old;}
});


test('rejected drop restores its visible inventory slot without waiting for message completion',t=>{
 t.mock.timers.enable({apis:['setTimeout']});const old=globalThis.window;globalThis.window={};const p=gamePlayer(data());p.init();p.begin();
 try{const h=p.displayed.hotspots[0];Object.assign(h,{placement_enabled:true,initial_empty:true,collectible_flag:'empty',inventory_rules:[]});p.flags.empty=true;p.inventory=[{id:'other',asset_id:2}];
 p.useInventoryOnArea('other',h,{x:.3,y:.3});assert.equal(p.pendingInventoryUse,null);assert.equal(p.pickupFlight.itemId,'other');assert.equal(p.narration.text,'Nothing happens.');
 t.mock.timers.tick(651);assert.equal(p.pickupFlight,null);assert.equal(p.inventory[0].id,'other');assert.equal(p.placedObjects[h.id],undefined);assert.equal(p.flags.empty,true);
 p.activate(h);assert.equal(p.inventory.length,1);assert.equal(p.collectibleEmpty(h),true);
 }finally{p.destroy();globalThis.window=old;}
});


test('accepted placement shows its layer before the response finishes',()=>{
 const old=globalThis.window;globalThis.window={};const p=gamePlayer(data());p.init();p.begin();
 try{const h=p.displayed.hotspots[0];Object.assign(h,{placement_enabled:true,initial_empty:true,collectible_flag:'empty',overlay:true,overlay_asset_id:2,inventory_rules:[{object_id:'coin-item',allowed:true,response:'You return the coins.'}]});p.flags.empty=true;p.inventory=[{id:'coin-item',asset_id:2}];
 p.useInventoryOnArea('coin-item',h);assert.equal(p.inventory.length,0);assert.equal(p.flags.empty,false);assert.equal(p.areaImage(h).id,2);assert.equal(p.pendingInventoryUse,null);assert.equal(p.narration.text,'You return the coins.');assert.notEqual(p.narration.revealed,true);
 }finally{p.destroy();globalThis.window=old;}
});


test('scene introductions appear once per scene and reset on a new playthrough',t=>{
 t.mock.timers.enable({apis:['setTimeout']});const old=globalThis.window;globalThis.window={};const input=data();input.pages[0].visuals[0].description='Welcome to the hall.';input.pages[0].visuals.push({id:'garden',kind:'scene',asset_id:1,description:'A quiet garden.',hotspots:[]});
 const p=gamePlayer(input);p.init();p.begin();
 try{assert.equal(p.sceneIntroText,'Welcome to the hall.');p.transitionScene('garden');t.mock.timers.tick(300);t.mock.timers.tick(50);t.mock.timers.tick(300);assert.equal(p.sceneIntroText,'A quiet garden.');p.transitionScene('hall');t.mock.timers.tick(300);t.mock.timers.tick(50);t.mock.timers.tick(300);assert.equal(p.sceneIntroText,'');p.transitionScene('garden');t.mock.timers.tick(300);t.mock.timers.tick(50);t.mock.timers.tick(300);assert.equal(p.sceneIntroText,'');p.closePreview();p.begin();assert.equal(p.sceneIntroText,'Welcome to the hall.');}finally{p.destroy();globalThis.window=old;}
});


test('automatic chest switch requires both placed items, reverses on pickup and supports sticky unlock',()=>{
 const old=globalThis.window;globalThis.window={};const d=data();const areas=d.pages[0].visuals[0].hotspots;
 Object.assign(areas[0],{initial_empty:true,collectible_flag:'empty1',placement_enabled:true,inventory_rules:[{object_id:'coin-item',allowed:true}]});
 areas.push({...areas[0],id:'second',collectible_flag:'empty2'});
 Object.assign(areas[1],{action:'state_v2',state_flag:'unlocked',state_asset_id:2,auto_state:{enabled:true,placements:[{area_id:'coins',object_id:'coin-item'},{area_id:'second',object_id:'coin-item'}],changed_target:'inside'}});
 d.flags=[{key:'empty1'},{key:'empty2'}];d.start.flags=['empty1','empty2'];const p=gamePlayer(d);p.init();p.begin();p.animatePickup=()=>{};
 try{
 assert.equal(!!p.flags.unlocked,false);
 p.placedObjects.coins='coin-item';p.flags.empty1=false;p.syncAutomaticStates();assert.equal(!!p.flags.unlocked,false);
 p.placedObjects.second='coin-item';p.flags.empty2=false;p.syncAutomaticStates();assert.equal(p.flags.unlocked,true);
 assert.equal(p.automaticDestination(areas[1]),'inside');
 const coin=p.displayed.hotspots[0];coin.description='';p.activate(coin);assert.equal(p.flags.unlocked,false);
 areas[1].auto_state.sticky=true;p.flags.empty1=false;p.syncAutomaticStates();assert.equal(p.flags.unlocked,true);
 p.flags.empty1=true;p.syncAutomaticStates();assert.equal(p.flags.unlocked,true);
 }finally{p.destroy();globalThis.window=old;}
});
test('automatic switch clicks examine or navigate without toggling the image',()=>{
 const old=globalThis.window;globalThis.window={};const d=data();const h=d.pages[0].visuals[0].hotspots[1];Object.assign(h,{action:'state_v2',state_flag:'open',auto_state:{enabled:true,flag_conditions:[{key:'ready',value:true}],changed_target:'inside'},state_description:''});
 d.flags=[{key:'ready'}];const p=gamePlayer(d);p.init();p.begin();try{
 p.activate(h);assert.equal(p.narration.text,'A box');assert.equal(!!p.flags.open,false);
 p.descriptionCompleted(p.previewId+':'+p.narrationVersion);assert.equal(p.clickAreaMessage(h),true);
 p.applyFlagEffects({flag_effects:[{key:'ready',value:true}]});assert.equal(p.flags.open,true);
 d.pages[0].visuals.push({id:'inside',kind:'scene',hotspots:[]});let destination;p.movePreviewPage=(page,scene)=>destination=scene;p.activate(h);assert.equal(destination,'inside');assert.equal(p.flags.open,true);
 }finally{p.destroy();globalThis.window=old;}
});


test('initially empty placement area describes before opening inventory',()=>{
 const old=globalThis.window;globalThis.window={};const d=data();
 const h=d.pages[0].visuals[0].hotspots[0];Object.assign(h,{initial_empty:true,placement_enabled:true,description:'A circular indentation.'});
 const p=gamePlayer(d);p.init();p.begin();try{
 assert.equal(p.areaCursor(h),'help');p.activate(h);assert.equal(p.narration.text,h.description);assert.equal(p.inventoryOpen,false);
 p.descriptionCompleted(p.previewId+':'+p.narrationVersion);assert.equal(p.clickAreaMessage(h),false);p.activate(h);assert.equal(p.inventoryOpen,true);assert.equal(p.narration,null);
 p.examineMode=true;p.activate(h);assert.equal(p.narration.text,h.description);
 }finally{p.destroy();globalThis.window=old;}
});


test('empty slots on later pages initialize even when playtest flags omit their defaults',t=>{
 t.mock.timers.enable({apis:['setTimeout']});const old=globalThis.window;globalThis.window={};const d=data();
 const slot={id:'socket',action:'pickup_v2',initial_empty:true,collectible_flag:'socket_empty',object_id:'coin-item',overlay:true,overlay_always:true,overlay_asset_id:2,placement_enabled:true,inventory_rules:[{object_id:'coin-item',allowed:true}]};
 const lock={id:'lock',action:'state_v2',state_flag:'unlocked',auto_state:{enabled:true,placements:[{area_id:'socket',object_id:'coin-item'}]}};
 d.pages.push({id:2,visuals:[{id:'chest',kind:'scene',asset_id:1,hotspots:[slot,lock]}]});d.flags=[{key:'socket_empty',initial:true},{key:'unlocked',initial:false}];d.start.inventory=['coin-item'];d.start.flags=[];
 const p=gamePlayer(d);p.init();p.begin();p.animatePickup=()=>{};
 try{
 assert.equal(p.flags.socket_empty,true);assert.equal(!!p.flags.unlocked,false);
 p.movePreviewPage(2,'chest');t.mock.timers.tick(300);t.mock.timers.tick(50);t.mock.timers.tick(300);
 assert.equal(p.areaImage(slot),null);assert.equal(p.inventory.length,1);assert.equal(p.collectibleEmpty(slot),true);
 p.useInventoryOnArea(p.inventory[0].inventoryId,slot);assert.equal(p.inventory.length,0);assert.equal(p.flags.socket_empty,false);assert.equal(p.flags.unlocked,true);assert.equal(p.areaImage(slot).url,'/coin.png');
 p.movePreviewPage(1,'hall');t.mock.timers.tick(300);t.mock.timers.tick(50);t.mock.timers.tick(300);p.movePreviewPage(2,'chest');t.mock.timers.tick(300);t.mock.timers.tick(50);t.mock.timers.tick(300);assert.equal(p.collectibleEmpty(slot),false);
 p.closePreview();p.begin();assert.equal(p.collectibleEmpty(slot),true);
 }finally{p.destroy();globalThis.window=old;}
});
