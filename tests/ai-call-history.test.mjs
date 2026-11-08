import test from 'node:test';
import assert from 'node:assert/strict';
import {aiCallHistory} from '../resources/js/ai-call-history.js';
test('AI call details load only on expansion and are reused',async()=>{
 const original=globalThis.fetch;let requests=0,finish;
 globalThis.fetch=()=>{requests++;return new Promise(resolve=>{finish=()=>resolve({ok:true,json:async()=>({request:{prompt:'Hello'},images:[]})});});};
 try {
  const card=aiCallHistory('/ai/calls/1');assert.equal(requests,0);
  await card.load(false);assert.equal(requests,0);
  const loading=card.load();await card.load();assert.equal(requests,1);
  finish();await loading;assert.equal(card.detail.request.prompt,'Hello');await card.load();assert.equal(requests,1);
 }finally{globalThis.fetch=original;}
});
