import test from 'node:test';
import assert from 'node:assert/strict';
import {animatedDescription} from '../resources/js/animated-description.js';

test('words reveal progressively, click reveals all, then fade after five seconds', t=>{
 t.mock.timers.enable({apis:['setTimeout']});const d=animatedDescription();d.play('One two three','bottom','scene');assert.equal(d.count,1);assert.equal(d.placement,'bottom');t.mock.timers.tick(110);assert.equal(d.count,2);d.reveal();assert.equal(d.count,3);t.mock.timers.tick(4999);assert.equal(d.visible,true);t.mock.timers.tick(1);assert.equal(d.visible,false);
});
test('outside dismissal cancels typing and new messages restart, capped at 150px',t=>{
 t.mock.timers.enable({apis:['setTimeout']});const d=animatedDescription();d.$nextTick=fn=>fn();d.$refs={words:{scrollHeight:500},box:{scrollHeight:500,scrollTop:0}};d.play('One two three');assert.equal(d.height,150);d.dismiss();t.mock.timers.tick(1000);assert.equal(d.count,1);d.play('New message',undefined,'next');assert.equal(d.placement,'top');assert.equal(d.visible,true);d.destroy();t.mock.timers.tick(1000);assert.equal(d.count,1);
});
