import test from 'node:test';
import assert from 'node:assert/strict';
import { runButtonAction } from '../resources/js/button-loading.js';

class Button extends EventTarget {
    attributes = new Map();
    classes = new Set();
    disabled = false;
    classList = { add: (s) => this.classes.add(s), remove: (s) => this.classes.delete(s) };
    getAttribute(name) { return this.attributes.get(name) ?? null; }
    setAttribute(name, value) { this.attributes.set(name, value); }
    removeAttribute(name) { this.attributes.delete(name); }
}

test('shows immediate feedback, blocks repeat activation, and clears after the action', async () => {
    const button = new Button();
    let finish, calls = 0;
    const task = () => { calls++; return new Promise(resolve => { finish = resolve; }); };
    const pending = runButtonAction(button, task);
    assert.equal(button.getAttribute('aria-busy'), 'true');
    assert.ok(button.classes.has('button-loading'));
    assert.equal(button.dispatchEvent(new Event('click', { cancelable: true })), false);
    await runButtonAction(button, task);
    assert.equal(calls, 1);
    button.disabled = true; // An existing Alpine binding may change independently.
    finish('ready');
    assert.equal(await pending, 'ready');
    assert.equal(button.getAttribute('aria-busy'), null);
    assert.equal(button.getAttribute('aria-disabled'), null);
    assert.equal(button.disabled, true);
    assert.equal(button.classes.size, 0);
    assert.equal(button.dispatchEvent(new Event('click', { cancelable: true })), true);
});

test('failure restores accessibility state and allows an explicit retry', async () => {
    const button = new Button();
    button.setAttribute('aria-busy', 'false');
    await assert.rejects(runButtonAction(button, () => { throw new Error('network failed'); }), /network failed/);
    assert.equal(button.getAttribute('aria-busy'), 'false');
    assert.equal(button.classes.size, 0);
    assert.equal(await runButtonAction(button, async () => 42), 42);
});

test('submitting without a submitter still executes the action', async () => {
    assert.equal(await runButtonAction(null, async () => 'sent'), 'sent');
});
