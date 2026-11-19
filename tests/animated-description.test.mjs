import test from 'node:test';
import assert from 'node:assert/strict';
import { animatedDescription, hoverDescription, areaDescriptionBounds } from '../resources/js/animated-description.js';

test('words reveal progressively, click reveals all, then fade after ten seconds', (t) => {
    t.mock.timers.enable({ apis: ['setTimeout'] });
    const d = animatedDescription();
    d.play('One two three', 'bottom', 'scene');
    assert.equal(d.count, 1);
    assert.equal(d.placement, 'bottom');
    t.mock.timers.tick(110);
    assert.equal(d.count, 2);
    d.reveal();
    assert.equal(d.count, 3);
    t.mock.timers.tick(9999);
    assert.equal(d.visible, true);
    t.mock.timers.tick(1);
    assert.equal(d.visible, false);
});
test('outside dismissal cancels typing and new messages restart, capped at 250px', (t) => {
    t.mock.timers.enable({ apis: ['setTimeout'] });
    const d = animatedDescription();
    d.$nextTick = (fn) => fn();
    d.$refs = { words: { scrollHeight: 500 }, box: { scrollHeight: 500, scrollTop: 0 } };
    d.play('One two three');
    assert.equal(d.height, 250);
    d.dismiss();
    t.mock.timers.tick(1000);
    assert.equal(d.count, 1);
    d.play('New message', undefined, 'next');
    assert.equal(d.placement, 'top');
    assert.equal(d.visible, true);
    d.destroy();
    t.mock.timers.tick(1000);
    assert.equal(d.count, 1);
});

test('hover text stays beyond five seconds, hides on exit and returns instantly', (t) => {
    t.mock.timers.enable({ apis: ['setTimeout'] });
    const d = hoverDescription();
    const area = { id: 'a', description: 'One two three', action: 'page', animate_hover_text: true };
    d.hoverArea(area, 1, 'scene');
    assert.equal(d.count, 1);
    t.mock.timers.tick(110);
    t.mock.timers.tick(110);
    t.mock.timers.tick(6000);
    assert.equal(d.visible, true);
    d.leaveArea();
    assert.equal(d.visible, false);
    d.hoverArea(area, 1, 'scene');
    assert.equal(d.count, 3);
    assert.equal(d.visible, true);
    d.resetHover();
});
test('click completes typing despite pointer exit then runs action exactly once', (t) => {
    t.mock.timers.enable({ apis: ['setTimeout'] });
    const d = hoverDescription();
    let calls = 0;
    const area = { id: 'a', description: 'One two three', action: 'page', animate_hover_text: true };
    d.hoverArea(area, 1, 'scene');
    d.clickArea(area, () => calls++);
    d.leaveArea();
    assert.equal(d.visible, true);
    assert.equal(calls, 0);
    t.mock.timers.tick(110);
    t.mock.timers.tick(110);
    assert.equal(d.count, 3);
    assert.equal(d.visible, false);
    assert.equal(calls, 1);
});
test('hover without text does not block action and examine does not repeat hover text', (t) => {
    t.mock.timers.enable({ apis: ['setTimeout'] });
    const d = hoverDescription();
    let calls = 0;
    d.clickArea({ action: 'page' }, () => calls++);
    assert.equal(calls, 1);
    const area = { id: 'a', description: 'One two', action: 'examine', animate_hover_text: true };
    d.hoverArea(area, 1, 'scene');
    d.clickArea(area, () => calls++);
    t.mock.timers.tick(110);
    assert.equal(calls, 1);
    assert.equal(d.visible, false);
});

test('area hover boxes keep minimum width and clamp all edges inside scene image', () => {
    const area = { x: 0.95, y: 0, width: 0.02, height: 0.1 };
    assert.deepEqual(areaDescriptionBounds(area, 600, 400, 80, 'area_top'), {
        left: 400,
        width: 200,
        top: 0,
        height: 80,
    });
    assert.deepEqual(areaDescriptionBounds({ ...area, y: 0.95 }, 600, 400, 80, 'area_bottom'), {
        left: 400,
        width: 200,
        top: 320,
        height: 80,
    });
    assert.deepEqual(areaDescriptionBounds({ x: 0.2, y: 0.5, width: 0.5, height: 0.1 }, 600, 400, 80, 'area_top'), {
        left: 120,
        width: 300,
        top: 112,
        height: 80,
    });
    assert.deepEqual(areaDescriptionBounds(area, 100, 60, 80, 'area_bottom'), {
        left: 0,
        width: 100,
        top: 0,
        height: 60,
    });
});

test('hover text appears in full by default and only types when explicitly enabled', (t) => {
    t.mock.timers.enable({ apis: ['setTimeout'] });
    const d = hoverDescription();
    d.hoverArea({ id: 'a', description: 'One two three' }, 1, 'scene');
    assert.equal(d.count, 3);
    assert.equal(d.visible, true);
    d.hoverArea({ id: 'b', description: 'One two three', animate_hover_text: true }, 1, 'scene');
    assert.equal(d.count, 1);
    d.destroy();
});

test('movement inside a newly entered scene reveals hover text without restarting ongoing typing', (t) => {
    t.mock.timers.enable({ apis: ['setTimeout'] });
    const d = hoverDescription();
    const area = { id: 'area', description: 'One two three', animate_hover_text: true };
    d.hoverArea(area, 1, 'old');
    d.hoverArea(area, 2, 'new');
    assert.equal(d.count, 1);
    t.mock.timers.tick(110);
    assert.equal(d.count, 2);
    d.hoverArea(area, 2, 'new');
    assert.equal(d.count, 2);
    t.mock.timers.tick(110);
    assert.equal(d.count, 3);
    assert.equal(d.visible, true);
    d.destroy();
});

test('click narration respects disabled animation and area-relative positioning', (t) => {
    t.mock.timers.enable({ apis: ['setTimeout'] });
    const d = animatedDescription();
    d.boundsWidth = 600;
    d.boundsHeight = 400;
    d.play('One two three', 'area_bottom', 'click', false, { x: 0.95, y: 0.9, width: 0.02, height: 0.1 });
    d.height = 80;
    assert.equal(d.count, 3);
    assert.equal(d.boxStyle.left, '400px');
    assert.equal(d.boxStyle.top, '320px');
    d.destroy();
});

test('new descriptions replace scene text and cancel its animation immediately', (t) => {
    t.mock.timers.enable({ apis: ['setTimeout'] });
    const scene = animatedDescription({ independentScene: true }),
        item = hoverDescription();
    scene.play('The house stands silent', 'top', 'scene');
    item.hoverArea({ id: 'one', description: 'First item' }, 1, 'scene');
    assert.equal(scene.visible, false);
    assert.equal(scene.instantHide, true);
    t.mock.timers.tick(110);
    assert.equal(scene.count, 1);
    item.hoverArea({ id: 'two', description: 'Second item' }, 1, 'scene');
    assert.equal(item.words.join(''), 'Second item');
    assert.equal(scene.visible, false);
    scene.destroy();
    item.destroy();
});

test('replacing a speaking box with silent text stops only the old voice and cancels pending work', (t) => {
    t.mock.timers.enable({ apis: ['setTimeout'] });
    const previous = globalThis.window;
    const voice = {};
    let stopped = 0;
    const sound = {
        speaking: false,
        channels: { voice: null },
        speak(text) {
            this.channels.voice = text === 'Spoken words here' ? voice : null;
            this.speaking = !!this.channels.voice;
        },
        channel() {
            stopped++;
            this.speaking = false;
            this.channels.voice = null;
        },
    };
    globalThis.window = { vsgPlayer: { soundPlayer: sound } };
    try {
        const first = animatedDescription(),
            next = animatedDescription({ persistent: true });
        first.play('Spoken words here');
        next.play('Silent message', 'top', 'new', false);
        assert.equal(first.visible, false);
        assert.equal(next.visible, true);
        assert.equal(stopped, 1);
        assert.equal(sound.speaking, false);
        t.mock.timers.tick(500);
        assert.equal(first.count, 1);
        first.destroy();
        assert.equal(next.visible, true);
        next.destroy();
    } finally {
        globalThis.window = previous;
    }
});

test('full-text layout fixes height before words finish and keeps upward anchor steady', (t) => {
    t.mock.timers.enable({ apis: ['setTimeout'] });
    const d = animatedDescription();
    d.$nextTick = (fn) => fn();
    d.$refs = { words: { scrollHeight: 96 }, box: { scrollTop: 0 } };
    d.boundsWidth = 500;
    d.boundsHeight = 400;
    d.play('One two three four', 'area_top', 'fixed', true, { x: 0.2, y: 0.5, width: 0.4, height: 0.1 });
    const top = d.boxStyle.top;
    assert.equal(d.height, 120);
    assert.equal(d.measured, true);
    assert.equal(d.count, 1);
    t.mock.timers.tick(110);
    assert.equal(d.height, 120);
    assert.equal(d.boxStyle.top, top);
    d.reveal();
    assert.equal(d.height, 120);
    d.destroy();
});

test('description remeasures when a closed dialog opens and disconnects its observer', () => {
    const original = globalThis.ResizeObserver;
    let callback,
        disconnected = false;
    globalThis.ResizeObserver = class {
        constructor(fn) {
            callback = fn;
        }
        observe() {}
        disconnect() {
            disconnected = true;
        }
    };
    try {
        const d = animatedDescription();
        d.$nextTick = (fn) => fn();
        d.$refs = { words: { scrollHeight: 0 }, box: { scrollTop: 0 } };
        d.init();
        d.play('Dialog text', 'top', 'dialog', false);
        assert.equal(d.height, 24);
        d.$refs.words.scrollHeight = 90;
        callback();
        assert.equal(d.height, 114);
        d.destroy();
        assert.equal(disconnected, true);
    } finally {
        globalThis.ResizeObserver = original;
    }
});

test('inventory responses also expire after ten seconds without audio', (t) => {
    t.mock.timers.enable({ apis: ['setTimeout'] });
    const d = animatedDescription({ persistent: true });
    d.play('Try a key', 'top', 'intro', false);
    t.mock.timers.tick(10000);
    assert.equal(d.visible, false);
    d.play('Nothing happens', 'top', 'attempt-1', false);
    t.mock.timers.tick(10000);
    assert.equal(d.visible, false);
    assert.equal(d.words.join(''), 'Nothing happens');
    d.destroy();
});

test('dismissed hover text stays hidden on movement until pointer leaves', () => {
    const d = hoverDescription(),
        area = { id: 'rose', description: 'A rose', action: 'examine' };
    d.hoverArea(area, 1, 'scene');
    d.clickArea(area, () => {});
    assert.equal(d.visible, false);
    d.hoverArea(area, 1, 'scene');
    assert.equal(d.visible, false);
    d.leaveArea();
    d.hoverArea(area, 1, 'scene');
    assert.equal(d.visible, true);
    d.destroy();
});
test('description dismissal reports its identity so expired click narration can be cleared', (t) => {
    t.mock.timers.enable({ apis: ['setTimeout'] });
    const d = animatedDescription();
    let hidden;
    d.$dispatch = (event, detail) => {
        if (event === 'description-hidden') hidden = detail.key;
    };
    d.play('A rose', 'top', 'scene:2', false);
    t.mock.timers.tick(10000);
    assert.equal(hidden, 'scene:2');
});

test('text completion is emitted only after the final word and once per response', (t) => {
    t.mock.timers.enable({ apis: ['setTimeout'] });
    const d = animatedDescription({ persistent: true });
    const completed = [];
    d.$dispatch = (event, detail) => {
        if (event === 'description-complete') completed.push(detail.key);
    };
    d.play('One two three', 'top', 'response', true);
    assert.deepEqual(completed, []);
    t.mock.timers.tick(110);
    assert.deepEqual(completed, []);
    t.mock.timers.tick(110);
    assert.deepEqual(completed, ['response']);
    d.reveal();
    assert.deepEqual(completed, ['response']);
    d.destroy();
});

test('clicking narration reveals every word and stops speech', (t) => {
    t.mock.timers.enable({ apis: ['setTimeout'] });
    const previous = globalThis.window;
    let stopped = 0;
    globalThis.window = {
        vsgPlayer: {
            soundPlayer: {
                speak() {},
                channel(name, sound) {
                    assert.equal(name, 'voice');
                    assert.equal(sound, null);
                    stopped++;
                },
            },
            stopNarration() {},
        },
    };
    try {
        const d = animatedDescription();
        d.play('One two three');
        assert.equal(d.count, 1);
        d.skipSpeech();
        assert.equal(d.count, 3);
        assert.equal(stopped, 1);
        d.destroy();
    } finally {
        globalThis.window = previous;
    }
});

test('clicked text hides after three seconds even for persistent descriptions', (t) => {
    t.mock.timers.enable({ apis: ['setTimeout'] });
    const d = animatedDescription({ persistent: true });
    d.play('One two three');
    d.skipSpeech();
    assert.equal(d.count, 3);
    t.mock.timers.tick(2999);
    assert.equal(d.visible, true);
    t.mock.timers.tick(1);
    assert.equal(d.visible, false);
    assert.equal(d.instantHide, false);
    d.destroy();
});
test('second text click hides immediately and a new description resets the click state', (t) => {
    t.mock.timers.enable({ apis: ['setTimeout'] });
    const d = animatedDescription();
    d.play('First description');
    d.skipSpeech();
    d.skipSpeech();
    assert.equal(d.visible, false);
    assert.equal(d.instantHide, true);
    d.play('Next description');
    assert.equal(d.textClicked, false);
    assert.equal(d.instantHide, false);
    d.skipSpeech();
    assert.equal(d.visible, true);
    d.destroy();
});
test('clicked hover text times out and stays suppressed until leaving the area', (t) => {
    t.mock.timers.enable({ apis: ['setTimeout'] });
    const d = hoverDescription(),
        area = { id: 'rose', description: 'A rose', action: 'examine' };
    d.hoverArea(area, 1, 'scene');
    d.skipSpeech();
    t.mock.timers.tick(3000);
    assert.equal(d.visible, false);
    d.hoverArea(area, 1, 'scene');
    assert.equal(d.visible, false);
    d.leaveArea();
    d.hoverArea(area, 1, 'scene');
    assert.equal(d.visible, true);
    d.destroy();
});

test('all silent messages use ten seconds even with a legacy timeout override', (t) => {
    t.mock.timers.enable({ apis: ['setTimeout'] });
    const d = animatedDescription();
    d.hideAfterMs = 1000;
    d.play('Not yet', 'top', 'blocked', false);
    t.mock.timers.tick(9999);
    assert.equal(d.visible, true);
    t.mock.timers.tick(1);
    assert.equal(d.visible, false);
    d.destroy();
});

test('source-area clicks use the same reveal and dismiss state as text-box clicks', (t) => {
    t.mock.timers.enable({ apis: ['setTimeout'] });
    const d = animatedDescription();
    d.play('One two three', 'top', 'scene:1');
    d.clickFromArea('scene:2');
    assert.equal(d.count, 1);
    d.clickFromArea('scene:1');
    assert.equal(d.count, 3);
    assert.equal(d.visible, true);
    d.skipSpeech();
    assert.equal(d.visible, false);
    assert.equal(d.instantHide, true);
    d.destroy();
});

test('speech completion hides text including persistent scenes and marks unfinished animation read', (t) => {
    t.mock.timers.enable({ apis: ['setTimeout'] });
    const old = globalThis.window;
    const audio = {};
    const sound = { speaking: true, channels: { voice: audio }, speak() {} };
    globalThis.window = { vsgPlayer: { soundPlayer: sound } };
    try {
        for (const options of [{ persistent: true }, { independentScene: true }, {}]) {
            sound.speaking = true;
            const d = animatedDescription(options);
            let completed = false;
            d.$dispatch = (e) => {
                if (e === 'description-complete') completed = true;
            };
            d.play('One two three four five', 'top', 'spoken');
            assert.equal(d.visible, true);
            sound.speaking = false;
            t.mock.timers.tick(50);
            assert.equal(d.visible, false);
            assert.equal(completed, true);
            d.destroy();
        }
    } finally {
        globalThis.window = old;
    }
});
test('long speech outlasts silent timeout and manual reveal keeps its three second grace period', (t) => {
    t.mock.timers.enable({ apis: ['setTimeout'] });
    const old = globalThis.window;
    const sound = {
        speaking: true,
        channels: { voice: {} },
        speak() {},
        channel() {
            this.speaking = false;
            this.channels.voice = null;
        },
    };
    globalThis.window = { vsgPlayer: { soundPlayer: sound, narrationSpeaking: true } };
    try {
        const d = animatedDescription();
        d.play('Long speech', 'top', 'long', false);
        t.mock.timers.tick(20000);
        assert.equal(d.visible, true);
        d.skipSpeech();
        t.mock.timers.tick(2999);
        assert.equal(d.visible, true);
        t.mock.timers.tick(1);
        assert.equal(d.visible, false);
        d.destroy();
    } finally {
        globalThis.window = old;
    }
});
