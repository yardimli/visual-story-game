import test from 'node:test';
import assert from 'node:assert/strict';
import { generationStatuses } from '../resources/js/generation-poll.js';

test('idle generation libraries make no request', async () => {
    const jobs = [{ id: 1, status: 'completed' }, { id: 2, status: 'draft' }];
    const result = await generationStatuses(jobs, () => assert.fail('idle request'));
    assert.deepEqual(result.jobs, jobs);
    assert.equal(result.finished, false);
});

test('polls active IDs in bounded batches and retains draft details', async () => {
    const jobs = Array.from({ length: 101 }, (_, id) => ({ id, status: 'running', prompt: 'private prompt' }));
    let calls = 0;
    const result = await generationStatuses(jobs, async path => {
        const params = new URL(path, 'http://localhost').searchParams;
        const ids = params.getAll('status_ids[]').map(Number);
        assert.ok(ids.length <= 50);
        assert.equal(params.has('prompt'), false);
        calls++;
        return { jobs: ids.filter(id => id !== 1).map(id => ({ id, status: id === 0 ? 'completed' : 'running' })), missing_ids: ids.includes(1) ? [1] : [] };
    }, '/images');
    assert.equal(calls, 3);
    assert.equal(result.finished, true);
    assert.equal(result.jobs.length, 100);
    assert.equal(result.jobs[0].prompt, 'private prompt');
});
