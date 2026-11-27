import test from 'node:test';
import assert from 'node:assert/strict';
import { SceneMediaCache, sceneMedia } from '../resources/js/scene-media-cache.js';

test('complete downloads are reused and progress reaches 100 only when ready', async (t) => {
    let requests = 0;
    t.mock.method(globalThis, 'fetch', async () => {
        requests++;
        return new Response(new Uint8Array([1, 2, 3]), { headers: { 'Content-Length': '3', 'Content-Type': 'audio/mpeg' } });
    });
    const cache = new SceneMediaCache();
    const progress = [];
    try {
        await cache.load([{ url: '/voice', kind: 'audio' }, { url: '/voice', kind: 'audio' }], (p) => progress.push(p));
        assert.equal(requests, 1);
        assert.match(cache.url('/voice'), /^blob:/);
        assert.deepEqual(progress.at(-1), { total: 1, completed: 1, percent: 100 });
        await cache.load([{ url: '/voice', kind: 'audio' }], () => {});
        assert.equal(requests, 1);
    } finally { cache.dispose(); }
    assert.equal(cache.url('/voice'), '/voice');
});

test('failed media is not cached and can be retried', async (t) => {
    let fail = true;
    t.mock.method(globalThis, 'fetch', async () => new Response('data', { status: fail ? 500 : 200 }));
    const cache = new SceneMediaCache();
    try {
        await assert.rejects(cache.load([{ url: '/video', kind: 'video' }], () => {}));
        assert.equal(cache.url('/video'), '/video');
        fail = false;
        await cache.load([{ url: '/video', kind: 'video' }], () => {});
        assert.match(cache.url('/video'), /^blob:/);
    } finally { cache.dispose(); }
});

test('closing during loading cancels downloads', async (t) => {
    t.mock.method(globalThis, 'fetch', (_, { signal }) => new Promise((resolve, reject) => {
        signal.addEventListener('abort', () => reject(new Error('aborted')), { once: true });
    }));
    const cache = new SceneMediaCache();
    const loading = cache.load([{ url: '/video', kind: 'video' }], () => {});
    cache.cancel();
    await assert.rejects(loading);
    assert.equal(cache.files.size, 0);
});

test('manifest includes scene layers, inventory, default effects, newest speech and video', () => {
    const player = {
        assets: [1, 2, 3].map((id) => ({ id, url: '/image' + id })),
        gameObjects: [{ asset_id: 3, description: 'A key.' }],
        audioSettings: { music_id: 1, defaults: { examine: { click: 2 } } },
        gameplayMessages: { nothing: { text: 'Nothing happens.' } },
        audioSounds: [
            { id: 1, url: '/music' }, { id: 2, url: '/click' },
            { id: 4, url: '/new', source_text: 'The hall.' },
            { id: 3, url: '/old', source_text: 'The hall.' },
            { id: 5, url: '/key', source_text: 'A key.' },
            { id: 6, url: '/no-effect', source_text: 'Nothing happens.' },
            { id: 7, url: '/other', source_text: 'Other scene.' },
        ],
        videos: [{ id: 'intro', url: '/intro.mp4' }],
    };
    const files = sceneMedia(player, { asset_id: 1, description: 'The hall.', intro_video_id: 'intro', hotspots: [{ state_asset_id: 2 }] });
    assert.deepEqual(files.map((f) => f.url), ['/image1', '/image2', '/image3', '/music', '/click', '/new', '/key', '/no-effect', '/intro.mp4']);
});

test('scene action videos preload once, including conditional areas, without loading other scenes', async (t) => {
    const requested = [];
    t.mock.method(globalThis, 'fetch', async (url) => {
        requested.push(url);
        return new Response('video bytes', { headers: { 'Content-Type': 'video/mp4' } });
    });
    const player = {
        assets: [], gameObjects: [], audioSounds: [],
        videos: [
            { id: 'intro', url: '/intro.mp4' },
            { id: 'reset', url: '/reset.mp4' },
            { id: 'other-scene', url: '/other.mp4' },
            { id: 'unfinished', url: null },
        ],
    };
    const scene = { intro_video_id: 'intro', hotspots: [
        { action_video_id: 'reset', conditions: [{ flag: 'ready', value: true }] },
        { action_video_id: 'reset' },
        { action_video_id: 'intro' },
        { action_video_id: 'unfinished' },
        { action_video_id: 'missing' },
    ] };
    const cache = new SceneMediaCache();
    try {
        const progress = [];
        await cache.load(sceneMedia(player, scene), (p) => progress.push(p));
        assert.deepEqual(requested.sort(), ['/intro.mp4', '/reset.mp4']);
        assert.match(cache.url('/reset.mp4'), /^blob:/);
        assert.deepEqual(progress.at(-1), { total: 2, completed: 2, percent: 100 });
        await cache.load(sceneMedia(player, scene), () => {});
        assert.equal(requested.length, 2);
        assert.deepEqual(sceneMedia(player, { hotspots: [{ action_video_id: 'reset' }] }), [{ url: '/reset.mp4', kind: 'video' }]);
    } finally { cache.dispose(); }
});
