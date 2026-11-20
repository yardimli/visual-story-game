import test from 'node:test';
import assert from 'node:assert/strict';
import { storyVideo } from '../resources/js/story-video.js';

test('job actions use the story API and uploads use the separate upload endpoint', async () => {
    const originalFetch = globalThis.fetch;
    const originalDocument = globalThis.document;
    const requests = [];
    globalThis.document = { querySelector: () => ({ content: 'csrf' }) };
    globalThis.fetch = async (url, options) => {
        requests.push([url, options.method]);
        return { ok: true, status: 200, json: async () => ({}) };
    };
    try {
        const video = storyVideo('https://local/stories/2/videos', 'https://local/stories/2/assets');
        await video.request('/job-id/queue', 'POST');
        await video.request('https://local/stories/2/assets', 'POST', new FormData());
        assert.deepEqual(requests, [
            ['https://local/stories/2/videos/job-id/queue', 'POST'],
            ['https://local/stories/2/assets', 'POST'],
        ]);
    } finally {
        globalThis.fetch = originalFetch;
        globalThis.document = originalDocument;
    }
});

test('queue failure retains the saved draft and retry updates it instead of creating twice', async () => {
    const video = storyVideo('/videos', '/assets');
    const requests = [];
    video.form = {
        name: 'Test',
        prompt: 'Move',
        engine: 'engine',
        parameters: {},
        first_asset_id: 1,
        last_asset_id: '',
    };
    video.load = async () => {};
    let fail = true;
    video.request = async (path, method) => {
        requests.push([path, method]);
        if (path.endsWith('/queue') && fail) throw new Error('Disconnected');
        return { id: 'saved-draft' };
    };
    await video.save(true);
    assert.equal(video.editing, 'saved-draft');
    assert.equal(video.error, 'Disconnected');
    fail = false;
    await video.save(true);
    assert.deepEqual(requests, [
        ['', 'POST'],
        ['/saved-draft/queue', 'POST'],
        ['/saved-draft', 'PATCH'],
        ['/saved-draft/queue', 'POST'],
    ]);
});

test('engine selection uses its own parameters and clears unsupported last frame', () => {
    const video = storyVideo('/videos', '/assets');
    video.engines = { another: { supports_last_frame: false, parameters: { steps: { default: 8 } } } };
    video.form.engine = 'another';
    video.form.last_asset_id = 2;
    video.selectEngine();
    assert.deepEqual(video.form.parameters, { steps: 8 });
    assert.equal(video.form.last_asset_id, '');
});
