import test from 'node:test';
import assert from 'node:assert/strict';
import { gameRuntime } from '../resources/js/game-runtime.js';

function audio(paused = false) {
    return {
        paused,
        plays: 0,
        pauses: 0,
        pause() {
            this.paused = true;
            this.pauses++;
        },
        play() {
            this.paused = false;
            this.plays++;
            return Promise.resolve();
        },
    };
}
function runtime(pauseMusic, musicPaused = false) {
    const player = gameRuntime();
    Object.defineProperty(player, 'displayed', {
        value: { intro_video_id: 'intro', intro_video_pause_music: pauseMusic },
    });
    player.videos = [{ id: 'intro', url: '/intro.mp4' }];
    player.soundPlayer = { channels: { music: audio(musicPaused), voice: audio(), effects: audio() }, enter() {} };
    player.prepareSceneIntro = () => {};
    return player;
}
for (const setting of [undefined, false]) {
    test(`intro video leaves music uninterrupted when pause setting is ${setting}`, () => {
        const player = runtime(setting);
        const { music, voice, effects } = player.soundPlayer.channels;
        player.beginScenePresentation();
        assert.equal(music.paused, false);
        assert.equal(music.pauses, 0);
        assert.equal(voice.paused, true);
        assert.equal(effects.paused, true);
        player.finishIntroVideo();
        assert.equal(music.plays, 0);
        assert.equal(voice.plays, 1);
    });
}
test('opt-in music pause resumes once when video ends or is skipped', () => {
    const player = runtime(true);
    const music = player.soundPlayer.channels.music;
    player.beginScenePresentation();
    assert.equal(music.paused, true);
    player.finishIntroVideo();
    player.finishIntroVideo();
    assert.equal(music.paused, false);
    assert.equal(music.plays, 1);
});
test('video completion does not start music that was already paused', () => {
    const player = runtime(true, true);
    player.beginScenePresentation();
    player.finishIntroVideo();
    assert.equal(player.soundPlayer.channels.music.plays, 0);
    assert.equal(player.soundPlayer.channels.music.paused, true);
});
