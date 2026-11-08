import test from 'node:test';
import assert from 'node:assert/strict';
import {toastCenter} from '../resources/js/toasts.js';
test('notifications expire after four seconds, fade, deduplicate and release timers', t => {
 t.mock.timers.enable({apis:['setTimeout']});
 const center=toastCenter();let hidden=0;
 center.$nextTick=fn=>fn();center.$el={hidePopover:()=>hidden++};
 center.add({message:'Saved'});center.add({message:'Saved'});
 assert.equal(center.items.length,1);
 t.mock.timers.tick(3999);assert.equal(center.items[0].visible,true);
 t.mock.timers.tick(1);assert.equal(center.items[0].visible,false);
 t.mock.timers.tick(300);assert.equal(center.items.length,0);assert.equal(hidden,1);assert.equal(center.timers.size,0);
 center.add({message:'Failed',type:'error'});center.destroy();
 t.mock.timers.tick(5000);assert.equal(center.items[0].visible,true);
});
