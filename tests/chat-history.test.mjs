import test from 'node:test';
import assert from 'node:assert/strict';
import { recoverChatMessages } from '../resources/js/chat-history.js';

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
