import test from 'node:test';
import assert from 'node:assert/strict';
import { recoverChatMessages, fetchChatHistory, historyPollDelay } from '../resources/js/chat-history.js';

test('unchanged history uses a short conditional GET and reads no JSON body', async t => {
    t.mock.method(globalThis, 'fetch', async (url, options) => {
        assert.equal(url, '/stories/2/chat');
        assert.equal(options.body, undefined);
        assert.equal(options.headers['If-None-Match'], '"version"');
        return new Response(null, { status: 304 });
    });
    assert.deepEqual(await fetchChatHistory('/stories/2/chat', '"version"'), { unchanged: true, etag: '"version"' });
});

test('changed history returns its snapshot and next conditional version', async t => {
    const snapshot = { messages: [{ id: 1, content: 'New' }], pending: [], remaining: 1 };
    t.mock.method(globalThis, 'fetch', async () => new Response(JSON.stringify(snapshot), { headers: { ETag: '"new"' } }));
    assert.deepEqual(await fetchChatHistory('/chat', '"old"'), { data: snapshot, etag: '"new"' });
    assert.equal(historyPollDelay(false), 30000);
    assert.equal(historyPollDelay(true), 3000);
    assert.equal(historyPollDelay(false, true), 10000);
});

test('navigation recovers a pending reply and completion removes its placeholder', () => {
    const user = { id: 1, role: 'user', content: 'Hello' };
    const pending = recoverChatMessages([], { messages: [user], pending: [{ id: 1, content: 'Thinking' }] });
    assert.equal(pending[1].streaming, true);
    assert.equal(pending[1].content, 'Thinking');
    const answer = { id: 2, role: 'assistant', content: 'Hello back', proposal: { bible: 'A world' } };
    const completed = recoverChatMessages(pending, { messages: [user, answer], pending: [] });
    assert.deepEqual(completed, [user, answer]);
    assert.deepEqual(recoverChatMessages(completed, { messages: [user, answer], pending: [] }), completed);
});

test('saved history replaces stale messages while retaining local review state', () => {
    const result = recoverChatMessages([{ id: 2, content: 'old', autoReview: true }, { id: 'stream:1' }], {
        messages: [{ id: 2, content: 'saved' }],
        pending: [],
    });
    assert.deepEqual(result, [{ id: 2, content: 'saved', autoReview: true }]);
});
