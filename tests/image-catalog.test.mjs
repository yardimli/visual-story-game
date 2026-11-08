import test from 'node:test';
import assert from 'node:assert/strict';
import {imageCatalog} from '../resources/js/image-catalog.js';
test('image catalog consumers share one bulk request and reload can refresh it',async()=>{
 const originalFetch=globalThis.fetch;let requests=[];
 globalThis.fetch=async url=>{requests.push(url);return {ok:true,json:async()=>({models:[{id:'a'},{id:'b'}],endpoints:{a:[{pricing:[]}],b:[]},unavailable:['b']})};};
 try {
  const [a,b]=await Promise.all([imageCatalog(),imageCatalog()]);
  assert.equal(a,b);assert.deepEqual(requests,['/ai/image-endpoints']);
  assert.deepEqual(a.models[0].imageEndpoints,[{pricing:[]}]);assert.deepEqual(a.models[1].imageEndpoints,[]);
  await imageCatalog();assert.equal(requests.length,1);
  await imageCatalog(true);assert.equal(requests.length,2);
 } finally {globalThis.fetch=originalFetch;}
});
