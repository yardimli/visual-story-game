import test from 'node:test';
import assert from 'node:assert/strict';
import {GameAudio} from '../resources/js/story-audio.js';
test('scene audio loops at its volume, clicks survive transitions and closing stops everything',()=>{
 const previous=globalThis.Audio;globalThis.Audio=class {constructor(url){this.url=url;this.paused=false;}play(){return Promise.resolve();}pause(){this.paused=true;}};
 try{const p=new GameAudio(),sounds=[{id:1,url:'/audio/1'},{id:2,url:'/audio/2'}];
 p.enter({sound_id:1,sound_volume:0.3,sound_loop:true},sounds);const first=p.scene;assert.equal(first.volume,0.3);assert.equal(first.loop,true);
 p.click({sound_id:2,sound_volume:0.7},sounds);const effect=[...p.effects][0];assert.equal(effect.volume,0.7);
 p.enter({sound_id:2},sounds);assert.equal(first.paused,true);assert.equal(effect.paused,false);const second=p.scene;
 p.stop();assert.equal(effect.paused,true);assert.equal(second.paused,true);assert.equal(p.effects.size,0);
 }finally{globalThis.Audio=previous;}
});
