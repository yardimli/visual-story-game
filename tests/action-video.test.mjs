import test from 'node:test';
import assert from 'node:assert/strict';
import { gameRuntime } from '../resources/js/game-runtime.js';

function player() {
    const p = gameRuntime();
    p.videos = [{id:'clip',url:'/clip.mp4'}];
    p.areaDisabled = () => false;
    p.actionAllowed = () => true;
    p.asset = () => ({url:'/current.png'});
    p.prepareSceneIntro = () => assert.fail('Action video must not replay the scene introduction');
    p.soundPlayer = {channels:{},enter:()=>assert.fail('Action video must not replay scene audio')};
    return p;
}

test('video-only action stays in place and applies effects once after playback or skip', () => {
    const p=player();let effects=0;
    p.applyFlagEffects=()=>effects++;
    const h={id:'a',action:'examine',action_video_id:'clip'};
    assert.equal(p.canPresentAction(h),true);
    p.executeAreaAction(h);
    assert.equal(p.introVideo.id,'clip');
    assert.equal(p.loadingSceneImage,'/current.png');
    assert.equal(effects,0);
    assert.equal(p.areaInputLocked(h),true);
    p.executeAreaAction(h);
    p.finishIntroVideo();
    assert.equal(effects,1);
    assert.equal(p.introVideo,null);
    assert.equal(p.pendingVideoAction,null);
    p.finishIntroVideo();
    assert.equal(effects,1);
});

test('navigation and state actions are deferred until the video finishes', () => {
    for(const action of ['page','state_v2','pickup_v2']) {
        const p=player();const executed=[];
        p.activate=h=>executed.push(h);
        p.presentArea=()=>{};
        p.executeAreaAction({id:'a',action,action_video_id:'clip',target_page_id:2,target_scene_id:'next'});
        assert.equal(executed.length,0);
        p.finishIntroVideo();
        assert.equal(executed.length,1);
        assert.equal(executed[0].target_scene_id,'next');
        assert.equal(executed[0]._executeAction,true);
    }
});

test('unmet conditions never start video and missing videos do not block actions', () => {
    const p=player();let executed=0,denied=0;
    p.activate=()=>executed++;
    p.presentArea=()=>denied++;
    p.actionAllowed=()=>false;
    p.executeAreaAction({action:'page',action_video_id:'clip',unavailable_text:'Find the key'});
    assert.equal(p.introVideo,null);
    assert.equal(denied,1);
    p.actionAllowed=()=>true;
    p.executeAreaAction({action:'page',action_video_id:'missing'});
    assert.equal(executed,1);
});

test('action videos optionally pause and restore music', () => {
    const p=player();let paused=0,resumed=0;
    p.soundPlayer.channels.music={paused:false,pause(){paused++;},play(){resumed++;}};
    p.applyFlagEffects=()=>{};
    p.executeAreaAction({action:'examine',action_video_id:'clip',action_video_pause_music:true});
    assert.equal(paused,1);
    p.finishIntroVideo();
    assert.equal(resumed,1);
});

test('closing the game cancels the queued action rather than completing it', () => {
    const p=player();let effects=0;
    p.applyFlagEffects=()=>effects++;
    p.soundPlayer.stop=()=>{};
    p.executeAreaAction({action:'examine',action_video_id:'clip'});
    p.closePreview();
    p.finishIntroVideo();
    assert.equal(p.pendingVideoAction,null);
    assert.equal(effects,0);
});
