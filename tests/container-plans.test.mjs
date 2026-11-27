import test from 'node:test';
import assert from 'node:assert/strict';
import { sceneEditor } from '../resources/js/scene-editor.js';
import {
    containerVisible,
    containerGeometry,
    containerCropBounds,
    containerPlans,
} from '../resources/js/container-plans.js';

test('recreating a container carries its settings but recrops rather than reusing the old switch image', () => {
    const e = sceneEditor({});
    e.selected = 'scene';
    e.containerArea = 'door';
    e.containerPlanId = 5;
    e.containerReuseState = true;
    const plan = { id: 5, visual_id: 'scene', area_id: 'door', name: 'Door', stage: 'active',
        padding: { top: 8, left: 20, right: 9, bottom: 4 }, equal_padding: false,
        prompt: 'Open the door', user_prompt: 'Door with key', prompt_model: 'writing-model',
        contents: [{ object_id: 'key', area_id: 'key-area', name: 'Key', bounds: { x: .2, y: .2, width: .1, height: .1 } }],
        batch: { outputs: [{ id: 'preview', type: 'container_preview', prompt: 'Saved image prompt', accepted_asset_id: 77 },
            {id:'empty',type:'switch_on',prompt:'Keep empty prompt'}, {id:'layer',type:'layer',area_id:'key-area',prompt:'Old layer prompt'}] } };
    e.containerPlans = [plan];
    const original = JSON.stringify(plan);
    e.loadContainerSource = () => {};
    e.containerPrompts = { layer: 'Edited key layer prompt' };
    e.copyContainerPlan();
    assert.equal(e.containerPlanId, '');
    assert.equal(e.containerReuseState, false);
    assert.equal(e.containerRevisionName, 'Door');
    assert.equal(e.containerPrompt, 'Saved image prompt');
    assert.deepEqual(e.containerPaddingDraft, plan.padding);
    assert.equal(e.containerEqualPadding, false);
    assert.equal(e.containerUserPrompt, 'Door with key');
    assert.deepEqual(e.containerItems, [{ object_id: 'key', name: 'Key' }]);
    assert.deepEqual(e.containerCopiedPrompts, [
        {type:'switch_on',object_id:null,prompt:'Keep empty prompt'},
        {type:'layer',object_id:'key',prompt:'Edited key layer prompt'},
    ]);
    e.changeContainerPadding('left', 35); e.commitContainerPadding();
    assert.equal(e.containerPadding.left, 35);
    assert.equal(JSON.stringify(plan), original);
});
const parent = () => ({
    id: 'stone',
    is_container: true,
    state_flag: 'stone_open',
    action: 'state_v2',
    state_asset_id: 2,
    overlay: true,
    x: 0.1,
    y: 0.2,
    width: 0.5,
    height: 0.5,
});
const child = () => ({
    id: 'key',
    container_parent_id: 'stone',
    container_bounds: { x: 0.2, y: 0.2, width: 0.2, height: 0.3 },
    action: 'pickup_v2',
    collectible_flag: 'key_taken',
    overlay: true,
    overlay_asset_id: 3,
    x: 0.2,
    y: 0.3,
    width: 0.1,
    height: 0.15,
});
test('container child visibility depends on open state and collection survives closing', () => {
    const editor = sceneEditor({});
    const p = parent(),
        c = child();
    editor.document = { visuals: [{ id: 'scene', kind: 'scene', hotspots: [p, c] }] };
    editor.selected = 'scene';
    editor.previewId = 'scene';
    editor.preview = true;
    editor.assets = [{ id: 2 }, { id: 3 }];
    editor.flags = { stone_open: false, key_taken: false };
    assert.equal(containerVisible(c, [p, c], editor.flags), false);
    assert.equal(editor.currentHotspots.length, 1);
    editor.flags.stone_open = true;
    assert.equal(editor.currentHotspots.length, 2);
    assert.equal(editor.areaImage(c).id, 3);
    editor.flags.key_taken = true;
    assert.equal(editor.areaImage(c), null);
    editor.flags.stone_open = false;
    assert.equal(editor.currentHotspots.length, 1);
    editor.flags.stone_open = true;
    assert.equal(editor.areaImage(c), null);
    assert.equal(containerVisible(c, [], { stone_open: true }), false);
});
test('moving parent carries children and cancel restores them', () => {
    const editor = sceneEditor({});
    const p = parent(),
        c = child();
    editor.document = { visuals: [{ id: 'scene', hotspots: [p, c] }] };
    editor.selected = 'scene';
    editor.changed = () => {};
    const original = { x: p.x, y: p.y, width: p.width, height: p.height };
    editor.areaDrag = { id: p.id, original };
    p.x = 0.3;
    p.y = 0.4;
    editor.syncContainerAreas(p);
    assert.deepEqual(c.overlay_bounds, containerGeometry(p, c.container_bounds));
    assert.equal(c.x, 0.4);
    editor.endArea(true);
    assert.equal(c.x, 0.2);
    assert.equal(c.y, 0.30000000000000004);
    c.x = 1;
    c.y = -1;
    editor.syncContainerAreas(c);
    assert.ok(c.x + c.width <= p.x + p.width + 0.00001);
    assert.equal(c.y, p.y);
});

test('padding clamps to image edges and only commits on release', () => {
    const c = containerPlans({});
    c.changeContainerPadding('left', 25);
    assert.deepEqual(c.containerPaddingDraft, { top: 25, right: 25, bottom: 25, left: 25 });
    assert.equal(c.containerPadding.left, 0);
    c.commitContainerPadding();
    assert.equal(c.containerPadding.left, 25);
    c.linkContainerPadding(false);
    c.changeContainerPadding('top', 500);
    assert.equal(c.containerPaddingDraft.left, 25);
    c.commitContainerPadding();
    assert.deepEqual(
        containerCropBounds({ x: 0.1, y: 0.1, width: 0.5, height: 0.5 }, 100, 100, {
            top: 500,
            right: 20,
            bottom: 5,
            left: 5,
        }),
        { x: 0.05, y: 0, width: 0.75, height: 0.65 },
    );
    const p = parent();
    p.overlay_bounds = { x: 0, y: 0, width: 0.8, height: 0.9 };
    assert.deepEqual(containerGeometry(p, { x: 0, y: 0, width: 0.5, height: 0.5 }), {
        x: 0,
        y: 0,
        width: 0.4,
        height: 0.45,
    });
    assert.equal(p.x, 0.1);
});

test('prompt writer uses the current chat choice and exposes failed request details', async () => {
    const previous = globalThis.localStorage;
    let selected = 'chat-model';
    globalThis.localStorage = { getItem: () => selected };
    try {
        const c = containerPlans({ containerUrl: '/containers', writingModel: 'old-story-model' });
        c.flush = async () => true;
        c.containerItems = [{ object_id: 'key' }];
        c.containerUserPrompt = 'Open the stone';
        c.request = async (url, method, body) => {
            assert.equal(body.model, selected);
            throw new Error('Specific model validation failure');
        };
        await c.draftContainerPrompt();
        assert.equal(c.containerError, 'Specific model validation failure');
        assert.equal(c.containerPromptBusy, false);
        selected = 'new-chat-model';
        c.request = async (url, method, body) => {
            assert.equal(body.model, selected);
            return { prompt: 'Open lid', model: body.model };
        };
        await c.draftContainerPrompt();
        assert.equal(c.containerError, '');
        assert.equal(c.containerPromptModel, selected);
    } finally {
        if (previous === undefined) delete globalThis.localStorage;
        else globalThis.localStorage = previous;
    }
});

test('container editing moves and resizes within crop, cancels, and preserves selected layer', () => {
    const c = containerPlans({});
    c.containerItems = [
        { area_id: 'a', bounds: { x: 0.1, y: 0.2, width: 0.3, height: 0.4 } },
        { area_id: 'b', bounds: { x: 0.2, y: 0.2, width: 0.2, height: 0.2 } },
    ];
    c.$refs = {
        containerCanvas: {
            getBoundingClientRect: () => ({ left: 0, top: 0, width: 100, height: 100 }),
            setPointerCapture: () => {},
        },
    };
    const event = (x, y) => ({ button: 0, clientX: x, clientY: y, pointerId: 1, preventDefault: () => {} });
    c.beginContainerEdit(event(20, 30), 'a');
    c.moveContainerDraw(event(100, 100));
    assert.equal(c.containerItems[0].bounds.x, 0.7);
    assert.equal(c.containerItems[0].bounds.y, 0.6);
    c.endContainerDraw(true);
    assert.equal(c.containerItems[0].bounds.x, 0.1);
    c.beginContainerEdit(event(40, 60), 'a', 'se');
    c.moveContainerDraw(event(60, 80));
    assert.ok(Math.abs(c.containerItems[0].bounds.width - 0.5) < 1e-9);
    assert.ok(Math.abs(c.containerItems[0].bounds.height - 0.6) < 1e-9);
    c.endContainerDraw();
    c.moveContainerLayer(1);
    assert.equal(c.containerItems[1].area_id, 'a');
    assert.equal(c.containerItemIndex, 1);
    c.moveContainerLayer(1);
    assert.equal(c.containerItems[1].area_id, 'a');
    c.beginContainerEdit(event(10, 20), 'a', 'nw');
    c.moveContainerDraw(event(100, 100));
    assert.ok(c.containerItems[1].bounds.width >= 0.004999);
    assert.ok(c.containerItems[1].bounds.height >= 0.004999);
    c.endContainerDraw(true);
    assert.equal(c.containerItems[1].bounds.x, 0.1);
});

test('editor container state hides child artwork and areas without changing gameplay flags', () => {
    const e = sceneEditor({});
    const p = parent(),
        c = child();
    p.overlay_asset_id = 1;
    p.overlay_bounds = { x: 0, y: 0, width: 0.9, height: 0.9 };
    e.assets = [{ id: 1 }, { id: 2 }, { id: 3 }];
    e.document = { visuals: [{ id: 'scene', hotspots: [p, c] }] };
    e.selected = 'scene';
    e.flags = { stone_open: true };
    assert.equal(e.editorAreaImage(p).id, 2);
    assert.equal(e.editorAreaImage(c).id, 3);
    assert.equal(e.editorAreaVisible(c), true);
    p.overlay_always = false;
    assert.equal(e.editorAreaImage(p).id, 1);
    assert.equal(e.editorAreaImage(c), null);
    assert.equal(e.editorAreaVisible(c), false);
    e.editorHoverAreaId = p.id;
    assert.equal(e.editorAreaVisible(c), false);
    assert.equal(e.flags.stone_open, true);
    p.overlay_always = true;
    assert.equal(e.editorAreaVisible(c), true);
    assert.equal(e.editorAreaImage(c).id, 3);
    assert.match(e.editorAreaImageStyle(p), /left:0%;top:0%;width:90%;height:90%/);
    assert.equal(p.x, 0.1);
    p.overlay = false;
    assert.equal(e.editorAreaImage(p), null);
    assert.equal(e.editorAreaVisible(c), false);
});

test('collected container contents stop receiving clicks unless placement is enabled', () => {
    const e = sceneEditor({});
    const p = parent(),
        c = { ...child(), placement_enabled: false, description: 'A brass key', show_hover_text: false };
    e.document = { visuals: [{ id: 'scene', kind: 'scene', hotspots: [p, c] }] };
    e.selected = 'scene';
    e.previewId = 'scene';
    e.preview = true;
    e.flags = { stone_open: true, key_taken: false };
    assert.equal(e.currentHotspots.length, 2);
    e.flags.key_taken = true;
    assert.deepEqual(
        e.currentHotspots.map((h) => h.id),
        ['stone'],
    );
    let described = false;
    e.showObjectModal = () => {
        described = true;
    };
    e.activate(c);
    assert.equal(described, false);
    assert.equal(e.armedPickup, null);
    c.placement_enabled = true;
    assert.equal(e.currentHotspots.length, 2);
    c.placement_enabled = false;
    e.flags.key_taken = false;
    assert.equal(e.currentHotspots.length, 2);
});

test('unlocking a chest, taking its key and closing preserves unlock and collected contents', () => {
    const e = sceneEditor({});
    const p = {
        ...parent(),
        description: '',
        availability_enabled: true,
        flag_conditions: [{ key: 'unlocked', value: true }],
    };
    const items = Array.from({ length: 6 }, (_, i) => ({
        id: i === 0 ? 'key-item' : 'item-' + i,
        name: 'Item ' + i,
        kind: 'object',
    }));
    const children = items.map((item, i) => ({
        ...child(),
        id: 'child-' + i,
        object_id: item.id,
        collectible_flag: 'taken_' + i,
        placement_enabled: false,
        description: '',
    }));
    const lock = {
        id: 'lock',
        action: 'inventory',
        description: '',
        disabled_when: [{ key: 'unlocked', value: true }],
        inventory_rules: [
            {
                object_id: 'key-item',
                consume: true,
                response: 'Unlocked',
                flag_effects: [
                    { key: 'unlocked', value: true },
                    { key: 'stone_open', value: true },
                ],
            },
        ],
    };
    e.document = { visuals: [{ id: 'scene', kind: 'scene', hotspots: [p, lock, ...children] }] };
    e.selected = 'scene';
    e.previewId = 'scene';
    e.previewPageId = 1;
    e.preview = true;
    e.inventoryItems = items;
    e.inventory = [{ ...items[0], inventoryId: 'carried' }];
    e.flagDefinitions = ['unlocked', 'stone_open', ...children.map((c) => c.collectible_flag)].map((key) => ({ key }));
    e.flags = {};
    e.animatePickup = () => {};
    e.useInventoryOnArea('carried', lock);
    e.completeInventoryUse();
    assert.equal(e.inventory.length, 0);
    assert.equal(e.flags.unlocked, true);
    assert.equal(e.flags.stone_open, true);
    assert.equal(e.currentHotspots.includes(lock), false);
    e.activate(children[0]);
    e.activate(children[1]);
    assert.equal(e.inventory.length, 2);
    assert.equal(e.flags.taken_0, true);
    assert.equal(e.flags.taken_1, true);
    e.activate(p);
    assert.equal(e.flags.stone_open, false);
    assert.equal(e.flags.unlocked, true);
    assert.deepEqual(
        e.currentHotspots.map((h) => h.id),
        ['stone'],
    );
    e.activate(p);
    assert.equal(e.flags.stone_open, true);
    assert.equal(e.currentHotspots.length, 5);
    assert.equal(e.inventory.length, 2);
});

test('editor visibility follows authoring switches and ignores game test flags', () => {
    const e = sceneEditor({}),
        p = parent(),
        c = child();
    p.overlay_always = false;
    const entrance = { id: 'entry', disabled_when: [{ key: 'stone_open', value: false }] },
        lock = { id: 'lock', visible_when: [{ key: 'stone_open', value: false }] };
    e.document = { visuals: [{ id: 'scene', kind: 'scene', hotspots: [p, c, entrance, lock] }] };
    e.selected = 'scene';
    e.flagDefinitions = [{ key: 'stone_open', initial: false }];
    e.flags = { stone_open: true, key_taken: true };
    assert.equal(e.editorAreaVisible(c), false);
    assert.equal(e.editorAreaVisible(entrance), false);
    assert.equal(e.editorAreaVisible(lock), true);
    p.overlay_always = true;
    assert.equal(e.editorAreaVisible(c), true);
    assert.equal(e.editorAreaVisible(entrance), true);
    assert.equal(e.editorAreaVisible(lock), false);
    e.preview = true;
    e.previewId = 'scene';
    e.closePreview();
    assert.deepEqual(e.flags, {});
    assert.deepEqual(e.placedObjects, {});
    assert.equal(e.editorAreaVisible(c), true);
    p.overlay_always = false;
    assert.equal(e.editorAreaVisible(c), false);
});

 test('reused changed-state recreation uses the original preview prompt rather than stale removal instructions', () => {
    const e = sceneEditor({});
    e.selected='scene'; e.containerArea='door'; e.containerPlanId=1;
    e.containerUserPrompt='Remove only the key.';
    e.containerPlans=[{id:1,visual_id:'scene',area_id:'door',name:'Door',prompt:'Extract the selected items from the existing changed state.',user_prompt:null,contents:[],batch:{outputs:[{id:'preview',type:'container_preview',prompt:'Extract the selected items from the existing changed state.',recreation_prompt:'Open the door and add the key.'}]}}];
    e.containerPrompts={preview:'Extract the selected items from the existing changed state.'};
    e.loadContainerSource=()=>{};
    e.copyContainerPlan();
    assert.equal(e.containerPrompt,'Open the door and add the key.');
    assert.equal(e.containerUserPrompt,'Open the door and add the key.');
 });
