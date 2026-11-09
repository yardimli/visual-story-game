import test from 'node:test';
import assert from 'node:assert/strict';
import {streamingMessage,readChatStream} from '../resources/js/chat-stream.js';

test('JSON remains hidden at every character boundary, including partial fences',()=>{
 const prose='Here is the plan.\n';const json='```json\n{"changes":[{"fields":{"title":"SECRET"}}]}\n```';
 for(let i=1;i<=json.length;i++){
  const visible=streamingMessage(prose+json.slice(0,i));
  assert.equal(visible.text,'Here is the plan.');
  assert.ok(!visible.text.includes('SECRET'));
 }
 assert.deepEqual(streamingMessage(prose+json+'\nReview when ready.'),{text:'Here is the plan.\n\nReview when ready.',preparing:true});
 assert.equal(streamingMessage('Example:\n```js\nhello()\n```').text,'Example:\n```js\nhello()\n```');
});

function response(text){const bytes=new TextEncoder().encode(text);return {body:new ReadableStream({start(controller){for(const byte of bytes)controller.enqueue(new Uint8Array([byte]));controller.close();}})};}

test('stream reader decodes split UTF-8 and SSE frames with final completion',async()=>{
 const events=[];
 await readChatStream(response(': heartbeat\r\n\r\nevent: delta\r\ndata: {"text":"雨🌹"}\r\n\r\nevent: complete\r\ndata: {"remaining":0.9}\r\n\r\n'),(event,data)=>events.push([event,data]));
 assert.deepEqual(events,[['delta',{text:'雨🌹'}],['complete',{remaining:.9}]]);
});

test('stream reader rejects interruptions and provider errors without retrying',async()=>{
 await assert.rejects(readChatStream(response('event: delta\ndata: {"text":"Partial"}\n\n'),()=>{}),/interrupted/);
 await assert.rejects(readChatStream(response('event: error\ndata: {"message":"Provider unavailable"}\n\n'),()=>{}),/Provider unavailable/);
});
