import test from 'node:test';
import assert from 'node:assert/strict';
import { sendChatWithContextConfirmation } from '../resources/js/chat-context.js';

for (const allow of [false, true]) test(`large chat context ${allow ? 'continues once' : 'cancels without sending'}`, async t => {
    const calls = [];
    const body = JSON.stringify({ message: 'Edit this scene', request_key: 'same-key', visual_id: 'porch' });
    t.mock.method(globalThis, 'fetch', async (url, options) => {
        calls.push(JSON.parse(options.body));
        return calls.length === 1
            ? new Response(JSON.stringify({ code: 'context_confirmation_required', context_bytes: 260000 }), { status: 409 })
            : new Response('accepted');
    });
    const response = await sendChatWithContextConfirmation('/chat', { method: 'POST', body }, async bytes => {
        assert.equal(bytes, 260000);
        return allow;
    });
    assert.equal(calls.length, allow ? 2 : 1);
    if (allow) {
        assert.deepEqual(calls[1], { ...calls[0], allow_large_context: true });
        assert.equal(await response.text(), 'accepted');
    } else assert.equal(response, null);
});

test('ordinary failures do not trigger a size confirmation or retry', async t => {
    t.mock.method(globalThis, 'fetch', async () => new Response('{}', { status: 422 }));
    const response = await sendChatWithContextConfirmation('/chat', {}, () => assert.fail('unexpected confirmation'));
    assert.equal(response.status, 422);
});
