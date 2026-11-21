import test from 'node:test';
import assert from 'node:assert/strict';
import { GameAudio, storyAudio } from '../resources/js/story-audio.js';
const sounds = [
    { id: 1, url: '/1' },
    { id: 2, url: '/2' },
    { id: 3, url: '/3', source_text: 'A locked door.' },
];
function withPlayer(fn) {
    const old = globalThis.Audio;
    globalThis.Audio = class {
        constructor(url) {
            this.url = url;
            this.paused = false;
        }
        play() {
            return Promise.resolve();
        }
        pause() {
            this.paused = true;
        }
    };
    try {
        fn(new GameAudio());
    } finally {
        globalThis.Audio = old;
    }
}
test('music persists across keep scenes, replaces or stops explicitly, at full volume', () =>
    withPlayer((p) => {
        p.start({ music_id: 1, music_loop: true }, sounds);
        const first = p.channels.music;
        assert.equal(first.volume, 1);
        assert.equal(first.loop, true);
        p.enter({}, sounds);
        assert.equal(p.channels.music, first);
        assert.equal(first.paused, false);
        p.enter({ music_mode: 'replace', music_id: 2, music_loop: false }, sounds);
        assert.equal(first.paused, true);
        assert.equal(p.channels.music.url, '/2');
        assert.equal(p.channels.music.loop, false);
        p.enter({ music_mode: 'stop' }, sounds);
        assert.equal(p.channels.music, null);
    }));
test('voice and effects replace only their own channel; mute covers current and new sounds', () =>
    withPlayer((p) => {
        p.start({ music_id: 1 }, sounds);
        p.speak('A locked door.', sounds);
        p.click({ sound_id: 2, sound_volume: 0.2 }, sounds);
        const music = p.channels.music,
            voice = p.channels.voice,
            effect = p.channels.effects;
        assert.equal(effect.volume, 1);
        p.click({ sound_id: 1 }, sounds);
        assert.equal(effect.paused, true);
        assert.equal(voice.paused, false);
        assert.equal(music.paused, false);
        p.toggleMute();
        assert.equal(music.muted, true);
        assert.equal(voice.muted, true);
        p.speak('A locked door.', sounds);
        assert.equal(voice.paused, true);
        assert.equal(p.channels.voice.muted, true);
        p.toggleMute();
        assert.equal(p.channels.voice.muted, false);
        p.stop();
        assert.ok(Object.values(p.channels).every((a) => a === null));
    }));
test('defaults, inventory inheritance, explicit silence and local switch silence resolve correctly', () =>
    withPlayer((p) => {
        p.start({ defaults: { pickup: { click: 1, action: 2 }, state_v2: { action: 2 } } }, sounds);
        p.click({ action: 'pickup' }, sounds);
        assert.equal(p.channels.effects.url, '/1');
        p.action({ action: 'pickup' }, { action_sound_id: 1 }, sounds);
        assert.equal(p.channels.effects.url, '/1');
        const current = p.channels.effects;
        p.action({ action: 'pickup', action_sound_id: 'silent' }, { action_sound_id: 2 }, sounds);
        assert.equal(p.channels.effects, current);
        p.action({ action: 'state_v2' }, null, sounds);
        assert.equal(p.channels.effects, current);
        p.action({ action: 'pickup' }, null, sounds);
        assert.equal(p.channels.effects.url, '/2');
    }));

test('voice pause clears on completion, failure and stop without stopping music', () =>
    withPlayer((p) => {
        p.start({ music_id: 1 }, sounds);
        p.speak('A locked door.', sounds);
        assert.equal(p.speaking, true);
        p.channels.voice.onPlaybackFailure();
        assert.equal(p.speaking, false);
        assert.equal(p.channels.music.paused, false);
        p.speak('A locked door.', sounds);
        const previous = p.channels.voice;
        p.speak('A locked door.', sounds);
        previous.onPlaybackFailure();
        assert.equal(p.speaking, true);
        p.channel('voice', null);
        assert.equal(p.speaking, false);
    }));

test('prepared audio opens for review without starting generation', async () => {
    const audio = storyAudio('/audio');
    let opened = false;
    audio.$refs = {
        audioJobDialog: {
            showModal() {
                opened = true;
            },
        },
    };
    audio.voiceModels = [{ id: 'voice' }];
    audio.request = async () => {
        throw new Error('No submission expected');
    };
    const job = { id: 1, prompt: 'Door opens', settings: {} };
    await audio.editJob(job);
    assert.equal(opened, true);
    audio.editingJob.prompt = 'Door closes';
    assert.equal(job.prompt, 'Door opens');
});

test('audio dialog saves edits before starting the returned draft ID', async () => {
    const audio = storyAudio('/audio');
    const calls = [];
    let closed = false;
    audio.editingJob = { id: 1, prompt: 'Updated sound' };
    audio.$refs = {
        audioJobDialog: {
            close() {
                closed = true;
            },
        },
    };
    audio.load = async () => {};
    audio.request = async (path, method, body) => {
        calls.push([path, method, body?.prompt]);
        return { id: 2, prompt: 'Updated sound' };
    };
    await audio.saveJob(true);
    assert.deepEqual(calls, [
        ['/jobs/1', 'PUT', 'Updated sound'],
        ['/jobs/2/start', 'POST', undefined],
    ]);
    assert.equal(closed, true);
    assert.equal(audio.jobBusy, false);
});

test('invalid audio edits do not start generation or close the dialog', async () => {
    const audio = storyAudio('/audio');
    audio.editingJob = { id: 1 };
    const calls = [];
    audio.$refs = {
        audioJobDialog: {
            close() {
                assert.fail('Must stay open');
            },
        },
    };
    audio.request = async (path) => {
        calls.push(path);
        throw new Error('Invalid prompt');
    };
    await audio.saveJob(true);
    assert.deepEqual(calls, ['/jobs/1']);
    assert.equal(audio.jobBusy, false);
});

test('sound library status cards exclude completed audio and respect categories', () => {
    const audio = storyAudio('/audio');
    audio.jobs = [
        { id: 1, status: 'completed' },
        { id: 2, status: 'running', category: 'music' },
        { id: 3, status: 'failed', category: 'effects' },
        { id: 4, status: 'draft' },
    ];
    assert.deepEqual(
        audio.libraryJobs.map((j) => j.id),
        [2, 3],
    );
    audio.categoryFilter = 'music';
    assert.deepEqual(
        audio.libraryJobs.map((j) => j.id),
        [2],
    );
});

test('audio uploads send multipart data without forcing a JSON content type', async () => {
    const oldFetch = globalThis.fetch;
    const oldDocument = globalThis.document;
    globalThis.document = { querySelector: () => ({ content: 'csrf' }) };
    const body = new FormData();
    body.append('category', 'effects');
    globalThis.fetch = async (url, options) => {
        assert.equal(url, '/audio/upload');
        assert.equal(options.body, body);
        assert.equal(options.headers['Content-Type'], undefined);
        assert.equal(options.headers['X-CSRF-TOKEN'], 'csrf');
        return { ok: true, json: async () => ({ id: 1 }) };
    };
    try {
        await storyAudio('/audio').request('/upload', 'POST', body);
    } finally {
        globalThis.fetch = oldFetch;
        globalThis.document = oldDocument;
    }
});
