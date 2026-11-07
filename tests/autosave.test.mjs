import test from 'node:test';
import assert from 'node:assert/strict';
import {studioAutosave} from '../resources/js/autosave.js';

test('serializes page and story saves and uses returned revisions', async () => {
    const original = globalThis.FormData;
    globalThis.FormData = class { constructor(form) { this.values=new Map(Object.entries(form.fields)); } set(k,v){this.values.set(k,v);} get(k){return this.values.get(k);} };
    globalThis.document = {querySelectorAll:()=>[]};
    const calls=[];
    globalThis.fetch = async (url,options) => { calls.push([url,options.body.get('revision')]); return {ok:true,json:async()=>({revision:options.body.get('revision')+1})}; };
    const state=studioAutosave(1);
    state.pending.add({action:'/page',fields:{title:'Page'},checkValidity:()=>true});
    state.pending.add({action:'/story',fields:{title:'Story'},checkValidity:()=>true});
    assert.equal(await state.flush(),true);
    assert.deepEqual(calls,[['/page',1],['/story',2]]);
    assert.equal(state.revision,3);
    assert.equal(state.pending.size,0);
    globalThis.FormData=original;
});
test('invalid fields remain unsaved without a request', async () => {
    globalThis.fetch=()=>{throw Error('Must not send');};
    const state=studioAutosave(1);
    state.pending.add({checkValidity:()=>false});
    assert.equal(await state.flush(),false);
    assert.equal(state.pending.size,1);
    assert.equal(state.status,'Not saved');
});
test('lost response preserves edits and stops automatic retries', async () => {
    const original=globalThis.FormData;
    globalThis.FormData=class {set(){}};
    let calls=0;
    globalThis.fetch=async()=>{calls++;throw new TypeError('Connection lost');};
    const state=studioAutosave(1);
    state.pending.add({action:'/page',checkValidity:()=>true});
    assert.equal(await state.flush(),false);
    assert.equal(await state.flush(),false);
    assert.equal(calls,1);
    assert.equal(state.pending.size,1);
    assert.equal(state.stopped,true);
    globalThis.FormData=original;
});
