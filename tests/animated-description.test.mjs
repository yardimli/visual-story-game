import test from 'node:test';
import assert from 'node:assert/strict';
import {animatedDescription, hoverDescription, areaDescriptionBounds} from '../resources/js/animated-description.js';

test('words reveal progressively, click reveals all, then fade after five seconds', t=>{
 t.mock.timers.enable({apis:['setTimeout']});const d=animatedDescription();d.play('One two three','bottom','scene');assert.equal(d.count,1);assert.equal(d.placement,'bottom');t.mock.timers.tick(110);assert.equal(d.count,2);d.reveal();assert.equal(d.count,3);t.mock.timers.tick(4999);assert.equal(d.visible,true);t.mock.timers.tick(1);assert.equal(d.visible,false);
});
test('outside dismissal cancels typing and new messages restart, capped at 250px',t=>{
 t.mock.timers.enable({apis:['setTimeout']});const d=animatedDescription();d.$nextTick=fn=>fn();d.$refs={words:{scrollHeight:500},box:{scrollHeight:500,scrollTop:0}};d.play('One two three');assert.equal(d.height,250);d.dismiss();t.mock.timers.tick(1000);assert.equal(d.count,1);d.play('New message',undefined,'next');assert.equal(d.placement,'top');assert.equal(d.visible,true);d.destroy();t.mock.timers.tick(1000);assert.equal(d.count,1);
});


test('hover text stays beyond five seconds, hides on exit and returns instantly',t=>{
 t.mock.timers.enable({apis:['setTimeout']});const d=hoverDescription();const area={id:'a',description:'One two three',action:'page',animate_hover_text:true};
 d.hoverArea(area,1,'scene');assert.equal(d.count,1);t.mock.timers.tick(110);t.mock.timers.tick(110);t.mock.timers.tick(6000);assert.equal(d.visible,true);
 d.leaveArea();assert.equal(d.visible,false);d.hoverArea(area,1,'scene');assert.equal(d.count,3);assert.equal(d.visible,true);d.resetHover();
});
test('click completes typing despite pointer exit then runs action exactly once',t=>{
 t.mock.timers.enable({apis:['setTimeout']});const d=hoverDescription();let calls=0;const area={id:'a',description:'One two three',action:'page',animate_hover_text:true};
 d.hoverArea(area,1,'scene');d.clickArea(area,()=>calls++);d.leaveArea();assert.equal(d.visible,true);assert.equal(calls,0);
 t.mock.timers.tick(110);t.mock.timers.tick(110);assert.equal(d.count,3);assert.equal(d.visible,false);assert.equal(calls,1);
});
test('hover without text does not block action and examine does not repeat hover text',t=>{
 t.mock.timers.enable({apis:['setTimeout']});const d=hoverDescription();let calls=0;d.clickArea({action:'page'},()=>calls++);assert.equal(calls,1);
 const area={id:'a',description:'One two',action:'examine',animate_hover_text:true};d.hoverArea(area,1,'scene');d.clickArea(area,()=>calls++);t.mock.timers.tick(110);assert.equal(calls,1);assert.equal(d.visible,false);
});

test('area hover boxes keep minimum width and clamp all edges inside scene image',()=>{
 const area={x:.95,y:0,width:.02,height:.1};
 assert.deepEqual(areaDescriptionBounds(area,600,400,80,'area_top'),{left:400,width:200,top:0,height:80});
 assert.deepEqual(areaDescriptionBounds({...area,y:.95},600,400,80,'area_bottom'),{left:400,width:200,top:320,height:80});
 assert.deepEqual(areaDescriptionBounds({x:.2,y:.5,width:.5,height:.1},600,400,80,'area_top'),{left:120,width:300,top:112,height:80});
 assert.deepEqual(areaDescriptionBounds(area,100,60,80,'area_bottom'),{left:0,width:100,top:0,height:60});
});

test('hover text appears in full by default and only types when explicitly enabled',t=>{
 t.mock.timers.enable({apis:['setTimeout']});const d=hoverDescription();
 d.hoverArea({id:'a',description:'One two three'},1,'scene');assert.equal(d.count,3);assert.equal(d.visible,true);
 d.hoverArea({id:'b',description:'One two three',animate_hover_text:true},1,'scene');assert.equal(d.count,1);d.destroy();
});

test('movement inside a newly entered scene reveals hover text without restarting ongoing typing',t=>{
 t.mock.timers.enable({apis:['setTimeout']});const d=hoverDescription();const area={id:'area',description:'One two three',animate_hover_text:true};
 d.hoverArea(area,1,'old');d.hoverArea(area,2,'new');assert.equal(d.count,1);
 t.mock.timers.tick(110);assert.equal(d.count,2);d.hoverArea(area,2,'new');assert.equal(d.count,2);
 t.mock.timers.tick(110);assert.equal(d.count,3);assert.equal(d.visible,true);d.destroy();
});

test('click narration respects disabled animation and area-relative positioning',t=>{
 t.mock.timers.enable({apis:['setTimeout']});const d=animatedDescription();d.boundsWidth=600;d.boundsHeight=400;
 d.play('One two three','area_bottom','click',false,{x:.95,y:.9,width:.02,height:.1});d.height=80;
 assert.equal(d.count,3);assert.equal(d.boxStyle.left,'400px');assert.equal(d.boxStyle.top,'320px');d.destroy();
});

test('scene narration keeps typing independently while item descriptions are replaced',t=>{
 t.mock.timers.enable({apis:['setTimeout']});const scene=animatedDescription({independentScene:true}),item=hoverDescription();
 scene.play('The house stands silent','top','scene');item.hoverArea({id:'one',description:'First item'},1,'scene');t.mock.timers.tick(110);assert.equal(scene.count,2);
 item.hoverArea({id:'two',description:'Second item'},1,'scene');assert.equal(item.words.join(''),'Second item');assert.equal(scene.visible,true);t.mock.timers.tick(110);assert.equal(scene.count,3);scene.destroy();item.destroy();
});

test('full-text layout fixes height before words finish and keeps upward anchor steady',t=>{
 t.mock.timers.enable({apis:['setTimeout']});const d=animatedDescription();d.$nextTick=fn=>fn();d.$refs={words:{scrollHeight:96},box:{scrollTop:0}};d.boundsWidth=500;d.boundsHeight=400;
 d.play('One two three four','area_top','fixed',true,{x:.2,y:.5,width:.4,height:.1});
 const top=d.boxStyle.top;assert.equal(d.height,120);assert.equal(d.measured,true);assert.equal(d.count,1);
 t.mock.timers.tick(110);assert.equal(d.height,120);assert.equal(d.boxStyle.top,top);d.reveal();assert.equal(d.height,120);d.destroy();
});

test('description remeasures when a closed dialog opens and disconnects its observer',()=>{
 const original=globalThis.ResizeObserver;let callback,disconnected=false;
 globalThis.ResizeObserver=class{constructor(fn){callback=fn;}observe(){}disconnect(){disconnected=true;}};
 try{const d=animatedDescription();d.$nextTick=fn=>fn();d.$refs={words:{scrollHeight:0},box:{scrollTop:0}};d.init();d.play('Dialog text','top','dialog',false);assert.equal(d.height,24);
 d.$refs.words.scrollHeight=90;callback();assert.equal(d.height,114);d.destroy();assert.equal(disconnected,true);
 }finally{globalThis.ResizeObserver=original;}
});

test('inventory response remains visible until replaced or dialog closes',t=>{
 t.mock.timers.enable({apis:['setTimeout']});const d=animatedDescription({persistent:true});
 d.play('Try a key','top','intro',false);t.mock.timers.tick(30000);assert.equal(d.visible,true);
 d.play('Nothing happens','top','attempt-1',false);t.mock.timers.tick(30000);assert.equal(d.visible,true);assert.equal(d.words.join(''),'Nothing happens');
 d.destroy();
});

test('dismissed hover text stays hidden on movement until pointer leaves',()=>{
 const d=hoverDescription(),area={id:'rose',description:'A rose',action:'examine'};
 d.hoverArea(area,1,'scene');d.clickArea(area,()=>{});assert.equal(d.visible,false);
 d.hoverArea(area,1,'scene');assert.equal(d.visible,false);
 d.leaveArea();d.hoverArea(area,1,'scene');assert.equal(d.visible,true);d.destroy();
});
test('description dismissal reports its identity so expired click narration can be cleared',t=>{
 t.mock.timers.enable({apis:['setTimeout']});const d=animatedDescription();let hidden;
 d.$dispatch=(event,detail)=>{if(event==='description-hidden')hidden=detail.key;};
 d.play('A rose','top','scene:2',false);t.mock.timers.tick(5000);assert.equal(hidden,'scene:2');
});

test('text completion is emitted only after the final word and once per response',t=>{
 t.mock.timers.enable({apis:['setTimeout']});const d=animatedDescription({persistent:true});const completed=[];
 d.$dispatch=(event,detail)=>{if(event==='description-complete')completed.push(detail.key);};
 d.play('One two three','top','response',true);assert.deepEqual(completed,[]);t.mock.timers.tick(110);assert.deepEqual(completed,[]);t.mock.timers.tick(110);assert.deepEqual(completed,['response']);d.reveal();assert.deepEqual(completed,['response']);d.destroy();
});
