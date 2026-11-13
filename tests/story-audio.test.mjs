import test from 'node:test';
import assert from 'node:assert/strict';
import {GameAudio} from '../resources/js/story-audio.js';
const sounds=[{id:1,url:'/1'},{id:2,url:'/2'},{id:3,url:'/3',source_text:'A locked door.'}];
function withPlayer(fn){const old=globalThis.Audio;globalThis.Audio=class {constructor(url){this.url=url;this.paused=false;}play(){return Promise.resolve();}pause(){this.paused=true;}};try{fn(new GameAudio());}finally{globalThis.Audio=old;}}
test('music persists across keep scenes, replaces or stops explicitly, at full volume',()=>withPlayer(p=>{
 p.start({music_id:1,music_loop:true},sounds);const first=p.channels.music;assert.equal(first.volume,1);assert.equal(first.loop,true);
 p.enter({},sounds);assert.equal(p.channels.music,first);assert.equal(first.paused,false);
 p.enter({music_mode:'replace',music_id:2,music_loop:false},sounds);assert.equal(first.paused,true);assert.equal(p.channels.music.url,'/2');assert.equal(p.channels.music.loop,false);
 p.enter({music_mode:'stop'},sounds);assert.equal(p.channels.music,null);
}));
test('voice and effects replace only their own channel; mute covers current and new sounds',()=>withPlayer(p=>{
 p.start({music_id:1},sounds);p.speak('A locked door.',sounds);p.click({sound_id:2,sound_volume:.2},sounds);
 const music=p.channels.music,voice=p.channels.voice,effect=p.channels.effects;assert.equal(effect.volume,1);
 p.click({sound_id:1},sounds);assert.equal(effect.paused,true);assert.equal(voice.paused,false);assert.equal(music.paused,false);
 p.toggleMute();assert.equal(music.muted,true);assert.equal(voice.muted,true);p.speak('A locked door.',sounds);assert.equal(voice.paused,true);assert.equal(p.channels.voice.muted,true);
 p.toggleMute();assert.equal(p.channels.voice.muted,false);p.stop();assert.ok(Object.values(p.channels).every(a=>a===null));
}));
test('defaults, inventory inheritance, explicit silence and local switch silence resolve correctly',()=>withPlayer(p=>{
 p.start({defaults:{pickup:{click:1,action:2},state_v2:{action:2}}},sounds);
 p.click({action:'pickup'},sounds);assert.equal(p.channels.effects.url,'/1');
 p.action({action:'pickup'},{action_sound_id:1},sounds);assert.equal(p.channels.effects.url,'/1');
 const current=p.channels.effects;p.action({action:'pickup',action_sound_id:'silent'},{action_sound_id:2},sounds);assert.equal(p.channels.effects,current);
 p.action({action:'state_v2'},null,sounds);assert.equal(p.channels.effects,current);
 p.action({action:'pickup'},null,sounds);assert.equal(p.channels.effects.url,'/2');
}));
