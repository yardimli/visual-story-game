import test from 'node:test';
import assert from 'node:assert/strict';
import { gameRuntime } from '../resources/js/game-runtime.js';
import { animatedDescription } from '../resources/js/animated-description.js';
import { actionLabel } from '../resources/js/interaction-actions.js';

function player(h) {
    const p = gameRuntime();
    p.preview = true;
    p.previewId = 'hall';
    p.previewPageId = p.editorPageId = 1;
    p.document = { visuals: [{ id: 'hall', kind: 'scene', hotspots: [h] }] };
    p.soundPlayer = {
        speaking: false,
        speak() {},
        click() {},
        action() {},
        channel() {
            this.speaking = false;
        },
    };
    p.animatePickup = () => {};
    return p;
}

test('text first requires the explicit button; finishing or repeating text cannot take an item', () => {
    const h = {
        id: 'key',
        action: 'pickup_v2',
        object_id: 'key-item',
        description: 'A brass key.',
        click_behavior: 'text',
        placement_enabled: false,
    };
    const p = player(h);
    p.inventoryItems = [{ id: 'key-item', kind: 'object', name: 'Key' }];
    p.tapArea(h);
    assert.equal(p.narration.actionAreaId, 'key');
    assert.equal(p.inventory.length, 0);
    p.runPresentedAction();
    assert.equal(p.inventory.length, 0);
    p.descriptionCompleted(p.previewId + ':' + p.narrationVersion);
    p.soundPlayer.speaking = true;
    p.runPresentedAction();
    assert.equal(p.inventory.length, 0);
    p.soundPlayer.speaking = false;
    p.runPresentedAction();
    assert.equal(p.inventory.length, 1);
    p.runPresentedAction();
    assert.equal(p.inventory.length, 1);
});

test('unmet requirements keep the label and replace text with only the authored explanation', () => {
    const h = {
        id: 'door',
        action: 'examine',
        move_after_examine: true,
        description: 'A door.',
        click_behavior: 'text',
        action_preset: 'custom_single',
        action_label: 'Try the door',
        availability_enabled: true,
        flag_conditions: [{ key: 'unlocked', value: true }],
        unavailable_text: 'The handle will not turn.',
    };
    const p = player(h);
    let moved = 0;
    p.movePreviewPage = () => moved++;
    p.tapArea(h);
    p.descriptionCompleted(p.previewId + ':' + p.narrationVersion);
    p.runPresentedAction();
    assert.equal(p.narration.text, h.unavailable_text);
    assert.equal(p.narration.actionAreaId, null);
    assert.equal(moved, 0);
    assert.equal(p.buttonLabel(h), 'Try the door');
    p.flags.unlocked = true;
    assert.equal(p.buttonLabel(h), 'Try the door');
});

test('blank failure explanation has no invented message or side effects', () => {
    const h = {
        id: 'door',
        action: 'state_v2',
        state_flag: 'open',
        click_behavior: 'direct',
        availability_enabled: true,
        flag_conditions: [{ key: 'key', value: true }],
        unavailable_text: '',
    };
    const p = player(h);
    p.unavailableText = '';
    p.tapArea(h);
    assert.equal(p.narration, null);
    assert.equal(p.flags.open, undefined);
});

test('manual switches offer the opposite custom action and its description after changing state', () => {
    const h = {
        id: 'lever',
        action: 'state_v2',
        state_flag: 'pulled',
        state_asset_id: 2,
        description: 'Push position.',
        state_description: 'Pull position.',
        click_behavior: 'text',
        action_preset: 'custom_dual',
        action_label: 'Pull lever',
        changed_action_label: 'Push lever',
    };
    const p = player(h);
    p.tapArea(h);
    assert.equal(p.buttonLabel(h), 'Pull lever');
    p.descriptionCompleted(p.previewId + ':' + p.narrationVersion);
    p.runPresentedAction();
    assert.equal(p.flags.pulled, true);
    assert.equal(p.narration.text, 'Pull position.');
    assert.equal(p.buttonLabel(h), 'Push lever');
});

test('empty sockets have no take button and occupied sockets use occupied text', () => {
    const h = {
        id: 'socket',
        action: 'pickup_v2',
        initial_empty: true,
        collectible_flag: 'empty',
        description: 'An empty socket.',
        state_description: 'The crystal fits.',
        action_preset: 'custom_single',
        action_label: 'Remove',
    };
    const p = player(h);
    p.flags.empty = true;
    p.tapArea(h);
    assert.equal(p.narration.actionAreaId, null);
    p.narration = null;
    p.flags.empty = false;
    p.tapArea(h);
    assert.equal(p.narration.text, 'The crystal fits.');
    assert.equal(p.buttonLabel(h), 'Remove');
});

test('persistent text stays visible after skipping speech and after natural completion', (t) => {
    t.mock.timers.enable({ apis: ['setTimeout'] });
    const old = globalThis.window;
    const p = player({});
    globalThis.window = { vsgPlayer: p };
    try {
        const d = animatedDescription({ persistent: true });
        d.play('Some long spoken description', 'top', 'test');
        p.soundPlayer.speaking = true;
        d.skipSpeech();
        assert.equal(p.narrationSpeaking, false);
        assert.equal(d.count, d.words.length);
        t.mock.timers.tick(15000);
        assert.equal(d.visible, true);
        d.destroy();
    } finally {
        globalThis.window = old;
    }
});

test('restart action restores initial flags, empties inventory and clears placements', () => {
    const h = {
        id: 'reset',
        action: 'examine',
        move_after_examine: true,
        restart_game: true,
        target_page_id: 1,
        target_scene_id: 'hall',
    };
    const p = player(h);
    p.flagDefinitions = [{ key: 'solved', initial: false }];
    p.flags.solved = true;
    p.inventory = [{ id: 'key' }];
    p.placedObjects = { socket: 'key' };
    p.soundPlayer.start = () => {};
    p.soundPlayer.enter = () => {};
    p.executeAreaAction(h);
    assert.equal(p.flags.solved, false);
    assert.deepEqual(p.inventory, []);
    assert.deepEqual(p.placedObjects, {});
});

test('presets support single and dual labels independently of action effects', () => {
    assert.equal(actionLabel({ action_preset: 'open_close' }, false), 'Open');
    assert.equal(actionLabel({ action_preset: 'open_close' }, true), 'Close');
    assert.equal(actionLabel({ action_preset: 'custom_single', action_label: 'Try the door' }, true), 'Try the door');
});

test('another area interrupts spoken dialogue and presents its own text without executing the previous action', () => {
    const key = {id: 'key', action: 'pickup_v2', object_id: 'key-item', description: 'A key.', click_behavior: 'text', placement_enabled: false};
    const door = {id: 'door', action: 'examine', description: 'A locked door.', click_behavior: 'text'};
    const p = player(key);
    p.document.visuals[0].hotspots.push(door);
    p.inventoryItems = [{id: 'key-item', kind: 'object', name: 'Key'}];
    p.tapArea(key);
    p.soundPlayer.speaking = true;
    p.tapArea(door);
    assert.equal(p.soundPlayer.speaking, false);
    assert.equal(p.narration.sourceAreaId, 'door');
    assert.equal(p.narration.text, 'A locked door.');
    assert.equal(p.inventory.length, 0);
});

test('opening narration locks areas while area narration remains interruptible', () => {
    const h = {id: 'door', action: 'examine', description: 'A door.'};
    const p = player(h);
    p.soundPlayer.speaking = true;
    assert.equal(p.areaInputLocked(h), true);
    p.tapArea(h);
    assert.equal(p.narration, null);
    p.soundPlayer.speaking = false;
    assert.equal(p.areaInputLocked(h), false);
    p.tapArea(h);
    p.soundPlayer.speaking = true;
    assert.equal(p.areaInputLocked({id: 'other'}), false);
});
