import test from 'node:test';
import assert from 'node:assert/strict';
import {chatDrafts} from '../resources/js/chat-drafts.js';
test('drafts survive tab changes and reloads, isolate users and stories, and clear only the sent tab',()=>{
 const prior=globalThis.localStorage;const values=new Map();globalThis.localStorage={getItem:k=>values.get(k)||null,setItem:(k,v)=>values.set(k,v),removeItem:k=>values.delete(k)};
 try{const d={...chatDrafts({userId:1,storyId:2}),prompt:''};d.prompt='Plan text';d.savePrompt();d.switchDraftTab('visuals');assert.equal(d.prompt,'');
 d.prompt='Scene text';d.savePrompt();const sentKey=d.draftKey();d.switchDraftTab('page');assert.equal(d.prompt,'Plan text');
 const reload={...chatDrafts({userId:1,storyId:2}),prompt:''};reload.loadPrompt();assert.equal(reload.prompt,'Plan text');
 d.clearSentDraft(sentKey,'Scene text');assert.equal(d.prompt,'Plan text');d.switchDraftTab('visuals');assert.equal(d.prompt,'');
 d.prompt='New draft';d.savePrompt();d.clearSentDraft(sentKey,'Old text');assert.equal(d.prompt,'New draft');
 const other={...chatDrafts({userId:2,storyId:2}),prompt:''};other.loadPrompt();assert.equal(other.prompt,'');
 d.clearSentDraft(sentKey,'New draft');assert.equal(values.has(sentKey),false);assert.equal(d.prompt,'');
 }finally{globalThis.localStorage=prior;}
});
test('draft storage failures do not stop chat',()=>{
 const prior=globalThis.localStorage;globalThis.localStorage={getItem(){throw Error();},setItem(){throw Error();},removeItem(){throw Error();}};
 try{const d={...chatDrafts({userId:1,storyId:2}),prompt:'Text'};assert.doesNotThrow(()=>{d.savePrompt();d.loadPrompt();d.clearSentDraft(d.draftKey(),'Text');});}finally{globalThis.localStorage=prior;}
});
