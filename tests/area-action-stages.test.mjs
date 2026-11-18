import test from 'node:test';
import assert from 'node:assert/strict';
import {sceneEditor} from '../resources/js/scene-editor.js';
function setup(action='examine'){
 const e=sceneEditor({});e.preview=true;e.previewId='scene';e.previewPageId=1;e.editorPageId=1;e.selected='scene';e.document={visuals:[{id:'scene',kind:'scene',hotspots:[]}]};e.flags={read:false,used:false,on:false};e.flagDefinitions=[{key:'read'},{key:'used'},{key:'on'}];e.assets=[{id:1,url:'/key.png'},{id:2,url:'/open.png'}];e.inventoryItems=[{id:'key',kind:'object',name:'Key',asset_id:1,description:'A brass key'}];e.showObjectModal=o=>{e.narration=o};e.animatePickup=()=>{};
 const h={id:'area',action,description:'Look at the stone.',show_hover_text:false,flag_effects:[{key:'used',value:true}],availability_enabled:true,flag_conditions:[{key:'read',value:true}],required_items:[],unavailable_text:'Read the envelope first.'};e.document.visuals[0].hotspots=[h];return [e,h];
}
test('examine describes first, gates navigation second, then moves to exact destination',()=>{
 const [e,h]=setup();h.move_after_examine=true;h.target_page_id=2;h.target_scene_id='roses';let destination=null;e.movePreviewPage=(...args)=>destination=args;
 e.activate(h);assert.equal(e.narration.text,h.description);assert.equal(e.flags.used,false);e.activate(h);assert.equal(e.narration.text,'Read the envelope first.');assert.equal(destination,null);
 e.flags.read=true;e.activate(h);assert.deepEqual(destination,[2,'roses']);assert.equal(e.flags.used,true);
});
test('hover enabled or blank descriptions skip first click but never bypass action requirements',()=>{
 for(const override of [{show_hover_text:true},{description:''}]){const [e,h]=setup();Object.assign(h,override,{move_after_examine:true,target_page_id:2});let moved=false;e.movePreviewPage=()=>moved=true;e.activate(h);assert.equal(moved,false);assert.equal(e.narration.text,'Read the envelope first.');e.flags.read=true;e.activate(h);assert.equal(moved,true);}
});
test('examine ignores obsolete object-image settings and shows only the area description',()=>{
 const [e,h]=setup();h.availability_enabled=false;h.show_object=true;h.object_id='key';e.activate(h);assert.equal(e.narration.text,h.description);assert.equal(e.narration.url,null);assert.equal(e.narration.inspect,undefined);assert.equal(e.flags.used,true);
});
test('pickup and switches gate actions while changed state describes immediately',()=>{
 for(const action of ['pickup_v2','state_v2']){const [e,h]=setup(action);Object.assign(h,{object_id:'key',state_flag:'on',state_asset_id:2,state_description:'The lid is open.',collectible_flag:'taken'});
 e.activate(h);assert.equal(e.narration.text,h.description);e.activate(h);assert.equal(e.inventory.length,0);assert.equal(e.flags.on,false);assert.equal(e.flags.used,false);
 e.flags.read=true;e.activate(h);if(action==='pickup_v2'){assert.equal(e.inventory.length,1);assert.equal(e.flags.taken,true);}else{assert.equal(e.flags.on,true);assert.equal(e.narration.text,h.state_description);e.activate(h);assert.equal(e.flags.on,false);}}
});
test('inventory opens on first description click and still blocks drops when requirements fail',()=>{
 const [e,h]=setup('inventory');e.inventory=[{id:'key',inventoryId:'carried'}];h.inventory_rules=[{object_id:'key',consume:true,response:'Unlocked'}];e.activate(h);assert.equal(e.inventoryOpen,true);assert.equal(e.narration.text,h.description);e.activate(h);assert.equal(e.inventoryOpen,true);
 e.useInventoryOnArea('carried',h);assert.equal(e.pendingInventoryUse,null);assert.equal(e.inventory.length,1);e.flags.read=true;e.activate(h);assert.equal(e.inventoryOpen,true);e.useInventoryOnArea('carried',h);assert.equal(e.pendingInventoryUse.rule.consume,true);
});
test('required inventory gates only the second action and disabled conditions are ignored',()=>{
 const [e,h]=setup();h.move_after_examine=true;h.flag_conditions=[];h.required_items=['key'];e.activate(h);assert.equal(e.narration.text,h.description);e.activate(h);assert.equal(e.flags.used,false);e.inventory=[{id:'key'}];e.activate(h);assert.equal(e.flags.used,true);
});

test('legacy navigation and inspection load as examine options without changing full-scene pickup data',()=>{
 const e=sceneEditor({});e.editorPageId=1;e.document={visuals:[{id:'scene',hotspots:[{action:'page',target_page_id:2,target_scene_id:'next'},{action:'transition',target_id:'other'},{action:'inspect',object_id:'envelope'},{action:'pickup',target_id:'empty'}]}]};e.previewPages=[{id:1},{id:2,visuals:[]}];e.upgradePageLinks();const [page,state,inspect,pickup]=e.document.visuals[0].hotspots;
 assert.equal(page.action,'examine');assert.equal(page.move_after_examine,true);assert.equal(page.target_scene_id,'next');assert.equal(state.target_page_id,1);assert.equal(state.target_scene_id,'other');assert.equal(state.target_id,null);assert.equal(inspect.show_object,undefined);assert.equal(inspect.object_id,'envelope');assert.equal(pickup.action,'pickup');assert.equal(pickup.target_id,'empty');
});

test('description and action use independent cursors and sounds; blocked actions stay silent',()=>{
 const [e,h]=setup('state_v2');Object.assign(h,{cursor:'help',action_cursor:'crosshair',state_description:'Open.',state_flag:'on',state_asset_id:2});const sounds=[];e.soundPlayer={click:()=>sounds.push('description'),action:()=>sounds.push('action')};
 assert.equal(e.areaCursor(h),'help');e.activate(h);assert.equal(e.areaCursor(h),'crosshair');e.activate(h);assert.deepEqual(sounds,['description']);e.flags.read=true;e.activate(h);assert.deepEqual(sounds,['description','action']);assert.equal(e.areaCursor(h),'crosshair');e.activate(h);assert.equal(e.flags.on,false);assert.equal(e.narration,null);assert.deepEqual(sounds,['description','action']);
});

test('cursor tooltips follow the current stage and never advertise blocked actions',()=>{
 const [e,h]=setup('pickup_v2');Object.assign(h,{object_id:'key',cursor:'help',action_cursor:'grab'});e.cursorTooltips={help:'Examine',grab:'Take item'};
 assert.equal(e.areaTooltip(h),'Examine');e.activate(h);assert.equal(e.areaTooltip(h),'');e.flags.read=true;assert.equal(e.areaTooltip(h),'Take item');e.sceneTransition=true;assert.equal(e.areaTooltip(h),'');e.sceneTransition=false;e.cursorTooltips={};assert.equal(e.areaTooltip(h),'');
});

test('inventory item rules link named local states and apply them after the response',()=>{
 const [e,h]=setup('inventory');const door={id:'door',label:'Door',action:'state_v2',state_flag:'door_open',state_asset_id:9};e.document.visuals[0].hotspots.push(door);e.selected=e.document.visuals[0].id;e.changed=()=>{};
 const target=e.inventoryStateTargets.find(t=>t.id==='door');assert.equal(target.label,'Door');const rule={object_id:'key',response:'The key turns.',consume:true,flag_effects:[{key:'other',value:true}]};e.setInventoryState(rule,target,'true');assert.equal(e.inventoryStateValue(rule,target),'true');assert.equal(rule.flag_effects.length,2);
 h.inventory_rules=[rule];e.flagDefinitions.push({key:'door_open'},{key:'other'});e.flags.read=true;e.flags.door_open=false;e.inventory=[{id:'key',inventoryId:'carried'}];e.animatePickup=()=>{};e.useInventoryOnArea('carried',h);assert.equal(e.flags.door_open,false);assert.equal(e.narration.text,rule.response);e.completeInventoryUse();assert.equal(e.flags.door_open,true);assert.equal(e.inventory.length,0);
 e.setInventoryState(rule,target,'false');assert.equal(e.inventoryStateValue(rule,target),'false');e.setInventoryState(rule,target,'');assert.deepEqual(rule.flag_effects,[{key:'other',value:true}]);
});


test('inventory outcomes navigate to another page only after the response completes',()=>{
 const [e,h]=setup('inventory');e.flags.read=true;e.previewPages=[{id:1,position:1,title:'Porch',visuals:[]},{id:2,position:2,title:'Inside',visuals:[{id:'hall',kind:'scene',name:'Hall',hotspots:[]}]}];
 h.inventory_rules=[{object_id:'key',target_id:'hall',response:'The door opens.',consume:false}];e.inventory=[{id:'key',inventoryId:'carried'}];let destination=null;e.transitionScene=(...args)=>destination=args;
 assert.ok(e.pageSceneDestinations.some(d=>d.sceneId==='hall'&&d.pageId===2));e.useInventoryOnArea('carried',h);assert.equal(destination,null);e.completeInventoryUse();assert.deepEqual(destination,['hall',2]);assert.equal(e.inventory.length,1);
});


test('inventory state targets are explicitly selected and can be changed without unrelated effects',()=>{
 const [e,h]=setup('inventory');e.changed=()=>{};e.document.visuals[0].hotspots.push({id:'door',label:'Door',state_flag:'door_open',state_asset_id:9},{id:'lamp',label:'Lamp',state_flag:'lamp_on',state_asset_id:10});
 const rule={flag_effects:[{key:'used',value:true}]};assert.deepEqual(e.inventoryStateEffects(rule),[]);
 e.addInventoryStateTarget(rule,'door_open');assert.equal(e.inventoryStateEffects(rule).length,1);const effect=e.inventoryStateEffects(rule)[0];effect.value=false;e.changeInventoryStateTarget(rule,effect,'lamp_on');assert.deepEqual(effect,{key:'lamp_on',value:false});
 e.addInventoryStateTarget(rule,'lamp_on');assert.equal(e.inventoryStateEffects(rule).length,1);e.addInventoryStateTarget(rule,'door_open');assert.equal(e.inventoryStateEffects(rule).length,2);assert.deepEqual(rule.flag_effects[0],{key:'used',value:true});
});


test('speaking pauses scene and inventory interactions until narration is stopped',()=>{
 const [e,h]=setup('inventory');e.flags.read=true;h.inventory_rules=[{object_id:'key',response:'Unlocked',consume:true}];e.inventory=[{id:'key',inventoryId:'carried'}];e.soundPlayer={speaking:true,channel(){this.speaking=false;},action(){}};
 e.activate(h);assert.equal(e.narration,null);e.useInventoryOnArea('carried',h);assert.equal(e.pendingInventoryUse,null);e.startInventoryDrag({button:0},e.inventory[0]);assert.equal(e.inventoryDrag,null);
 e.stopNarration();e.useInventoryOnArea('carried',h);assert.ok(e.pendingInventoryUse);e.soundPlayer.speaking=true;e.stopNarration();assert.equal(e.inventory.length,0);assert.equal(e.pendingInventoryUse,null);
});


test('completed locks pass clicks through and reject stale drops without descriptions',()=>{
 const [e,h]=setup('inventory');h.disabled_when=[{key:'used',value:true}];e.inventory=[{id:'key',inventoryId:'carried'}];e.flags.used=true;
 assert.equal(e.currentHotspots.includes(h),false);e.activate(h);assert.equal(e.narration,null);e.useInventoryOnArea('carried',h);assert.equal(e.pendingInventoryUse,null);
 e.flags.used=false;assert.equal(e.currentHotspots.includes(h),true);h.disabled_when=[];e.flags.used=true;assert.equal(e.areaDisabled(h),false);
});


test('entrance passes clicks through only while its door state is off',()=>{
 const [e,door]=setup('state_v2');Object.assign(door,{id:'door',state_flag:'on',state_asset_id:2,description:'',availability_enabled:false});
 const entrance={id:'entry',action:'examine',description:'',move_after_examine:true,target_page_id:2,target_scene_id:'hall',disabled_when:[{key:'on',value:false}],availability_enabled:true,flag_conditions:[{key:'on',value:true}]};e.document.visuals[0].hotspots.push(entrance);let moved=null;e.movePreviewPage=(...args)=>moved=args;
 assert.deepEqual(e.currentHotspots.map(h=>h.id),['door']);e.activate(door);assert.equal(e.flags.on,true);assert.equal(e.currentHotspots.at(-1),entrance);e.activate(entrance);assert.deepEqual(moved,[2,'hall']);assert.equal(e.flags.on,true);
 e.activate(door);assert.equal(e.flags.on,false);assert.deepEqual(e.currentHotspots.map(h=>h.id),['door']);
});


test('blocked action uses story message at the click and preserves area overrides',()=>{
 const [e,h]=setup('pickup_v2');h.show_hover_text=true;h.unavailable_text='';e.unavailableText='Find the key first.';
 e.activate(h,{x:.7,y:.4});assert.equal(e.narration.text,'Find the key first.');assert.equal(e.narration.position,'area_bottom');assert.deepEqual(e.narration.area,{x:.7,y:.4,width:0,height:0});
 h.unavailable_text='This door is locked.';e.activate(h,{x:.2,y:.8});assert.equal(e.narration.text,'This door is locked.');assert.equal(e.narration.area.x,.2);
});


test('area messages identify their source and use the story unavailable timeout',()=>{
 const [e,h]=setup();e.unavailableTimeout=1750;e.denyAreaAction(h);assert.equal(e.narration.hideAfterMs,1750);assert.equal(e.hasAreaMessage(h),true);assert.equal(e.hasAreaMessage({id:'other'}),false);
 const d=sceneEditor({});assert.equal(d.unavailableTimeout,1000);assert.equal(e.areaTextOptions(h).sourceAreaId,h.id);
});
