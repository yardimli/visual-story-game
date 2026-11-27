import test from 'node:test';
import assert from 'node:assert/strict';
import { sceneExtraction } from '../resources/js/scene-extraction.js';
import { sceneEditor } from '../resources/js/scene-editor.js';

test('container-backed switches explain their owner and open the assigned plan without changing gameplay', async () => {
    const e = sceneEditor({});
    const door = { id: 'door', action: 'state_v2', is_container: true, container_plan_id: 5 };
    const child = { id: 'key', action: 'pickup_v2', container_parent_id: 'door' };
    e.document.visuals = [{ id: 'scene', kind: 'scene', hotspots: [door, child] }];
    e.selected = 'scene';
    assert.deepEqual(e.currentSwitches, []);
    assert.deepEqual(e.containerSwitches, [door]);
    e.containerArea = 'door';
    e.containerPlans = [{ id: 5, visual_id: 'scene', area_id: 'door', stage: 'active' }];
    const calls = [];
    e.openContainers = async (...args) => { calls.push(args); return true; };
    e.$refs = { extractionDialog: { close: () => calls.push('close') } };
    e.chooseContainerPlan = existing => calls.push(['choose', existing]);
    await e.openSwitchContainer(door);
    assert.equal(e.containerPlanId, 5);
    assert.deepEqual(calls, [[false, 'door'], 'close', ['choose', true]]);
    assert.deepEqual(e.visual.hotspots, [door, child]);
    e.openContainers = async () => false;
    calls.length = 0;
    await e.openSwitchContainer(door);
    assert.deepEqual(calls, []);
});

test('integrated editor resolves selected extraction and output state dynamically', () => {
    const editor = sceneEditor({});
    assert.equal(editor.extractionBatch, undefined);
    editor.selected = 'scene';
    editor.extractionBatches = [
        {
            id: 7,
            visual_id: 'scene',
            outputs: [{ id: 'background', type: 'background', accepted_asset_id: null, jobs: [] }],
        },
    ];
    editor.extractionId = 7;
    assert.equal(editor.extractionBatch.id, 7);
    assert.equal(editor.sceneExtractions.length, 1);
    assert.equal(editor.extractionMissing.length, 1);
    editor.extractionBatch.outputs[0].accepted_asset_id = 42;
    assert.equal(editor.extractionBackground, 42);
    assert.equal(editor.extractionMissing.length, 0);
});

test('polling only runs while the selected plan has unfinished generation jobs', () => {
    const editor = sceneEditor({});
    editor.$refs = { extractionDialog: { open: true } };
    editor.extractionBatches = [{ id: 1, outputs: [{ jobs: [] }] }];
    editor.extractionId = 1;
    editor.scheduleExtractionRefresh();
    assert.equal(editor.extractionTimer, null);
    editor.extractionBatch.outputs[0].jobs = [{ status: 'running' }];
    editor.scheduleExtractionRefresh();
    assert.ok(editor.extractionTimer);
    editor.extractionBatch.outputs[0].jobs[0].status = 'completed';
    editor.scheduleExtractionRefresh();
    assert.equal(editor.extractionTimer, null);
});

test('preparation locks immediately, blocks duplicate submissions, and releases on save failure', async () => {
    const editor = sceneExtraction({});
    let finishSave,
        saves = 0;
    editor.flush = () => {
        saves++;
        return new Promise((resolve) => {
            finishSave = resolve;
        });
    };
    editor.error = 'Scene changed in another tab.';
    const pending = editor.prepareExtraction();
    assert.equal(editor.extractionPreparing, true);
    assert.equal(editor.extractionBusy, true);
    assert.match(editor.extractionProgress, /Saving scene/);
    await editor.prepareExtraction();
    assert.equal(saves, 1);
    finishSave(false);
    await pending;
    assert.equal(editor.extractionPreparing, false);
    assert.equal(editor.extractionBusy, false);
    assert.equal(editor.extractionProgress, '');
    assert.equal(editor.extractionError, editor.error);
});

test('failed preparation displays its error and enables retry', async () => {
    const editor = sceneExtraction({ extractionUrl: '/extractions' });
    editor.flush = async () => true;
    editor.extractionAreas = [{ id: 'area', selected: true, bounds: {} }];
    editor.request = async () => {
        assert.equal(editor.extractionBusy, true);
        assert.match(editor.extractionProgress, /Preparing cropped/);
        throw new Error('Source image could not be read.');
    };
    await editor.prepareExtraction();
    assert.equal(editor.extractionError, 'Source image could not be read.');
    assert.equal(editor.extractionPreparing, false);
    assert.equal(editor.extractionBusy, false);
    assert.equal(editor.extractionProgress, '');
});

test('extracted image follows area movement and resizing, and cancellation restores it', () => {
    const editor = sceneEditor({});
    const h = {
        id: 'area',
        x: 0.2,
        y: 0.2,
        width: 0.2,
        height: 0.2,
        overlay_bounds: { x: 0.18, y: 0.18, width: 0.24, height: 0.24 },
    };
    editor.document = { visuals: [{ id: 'scene', hotspots: [h] }] };
    editor.selected = 'scene';
    editor.changed = () => {};
    const event = {
        button: 0,
        clientX: 20,
        clientY: 20,
        pointerId: 1,
        preventDefault() {},
        currentTarget: {
            closest: () => ({ getBoundingClientRect: () => ({ width: 100, height: 100 }) }),
            setPointerCapture() {},
        },
    };
    const before = editor.areaImageStyle(h);
    editor.startArea(event, h);
    editor.moveArea({ clientX: 40, clientY: 50 });
    assert.ok(Math.abs(h.overlay_bounds.x - 0.38) < 1e-9);
    assert.ok(Math.abs(h.overlay_bounds.y - 0.48) < 1e-9);
    assert.ok(Math.abs(parseFloat(editor.areaImageStyle(h).left) - parseFloat(before.left)) < 1e-9);
    editor.endArea(true);
    assert.equal(h.x, 0.2);
    assert.equal(h.overlay_bounds.x, 0.18);
    editor.startArea(event, h, 'se');
    editor.moveArea({ clientX: 40, clientY: 40 });
    assert.ok(Math.abs(h.overlay_bounds.width - 0.48) < 1e-9);
    editor.endArea(true);
});
test('image pickers hide internal extraction references but retain uploaded references', () => {
    const editor = sceneEditor({});
    editor.assets = [
        { id: 1, role: 'extraction_reference' },
        { id: 2, role: 'reference' },
        { id: 3, role: 'scene', archived: true },
    ];
    assert.deepEqual(
        editor.pickerAssets.map((a) => a.id),
        [2],
    );
    assert.deepEqual(
        editor.referenceAssets.map((a) => a.id),
        [2],
    );
});

test('render timer measures elapsed time from worker start and ignores queue age', () => {
    const editor = sceneEditor({});
    editor.renderClock = Date.parse('2026-11-11T08:02:05Z');
    assert.equal(
        editor.renderElapsed({ started_at: '2026-11-11T08:00:00Z', created_at: '2026-11-11T07:00:00Z' }),
        '2m 05s',
    );
    assert.equal(editor.renderElapsed({ started_at: null }), '');
    assert.equal(editor.renderElapsed({ started_at: '2026-11-11T08:03:00Z' }), '0m 00s');
});

test('v2 area reset restores both hitbox and layer geometry from saved defaults', async () => {
    const e = sceneEditor({});
    let saves = 0;
    e.changed = () => saves++;
    const h = {
        action: 'pickup_v2',
        x: 0.7,
        y: 0.8,
        width: 0.1,
        height: 0.1,
        overlay_bounds: { x: 0.7, y: 0.8, width: 0.1, height: 0.1 },
        default_bounds: { x: 0.2, y: 0.3, width: 0.2, height: 0.2 },
        default_overlay_bounds: { x: 0.18, y: 0.28, width: 0.24, height: 0.24 },
    };
    await e.resetAreaPosition(h);
    assert.equal(h.x, 0.2);
    assert.equal(h.y, 0.3);
    assert.deepEqual(h.overlay_bounds, h.default_overlay_bounds);
    assert.equal(saves, 1);
    assert.equal(e.resettingArea, false);
});
test('older v2 areas recover defaults from the matching extraction output', async () => {
    const e = sceneEditor({ extractionUrl: '/extractions' });
    e.selected = 'scene';
    e.changed = () => {};
    e.request = async () => ({
        batches: [
            {
                visual_id: 'scene',
                outputs: [
                    {
                        area_id: 'switch',
                        type: 'switch_on',
                        accepted_asset_id: 8,
                        bounds: { x: 0.1, y: 0.2, width: 0.3, height: 0.4 },
                    },
                ],
            },
        ],
    });
    const h = { id: 'switch', action: 'state_v2', state_asset_id: 8, x: 0.8, y: 0.8, width: 0.1, height: 0.1 };
    await e.resetAreaPosition(h);
    assert.equal(h.x, 0.1);
    assert.equal(h.width, 0.3);
    assert.equal(h.overlay_bounds.height, 0.4);
    assert.equal(h.default_bounds.y, 0.2);
});

test('drag handle sits below the area and clamps fully inside scene edges', () => {
    const e = sceneEditor({});
    const h = { x: 0.2, y: 0.2, width: 0.2, height: 0.2 };
    let style = e.areaDragButtonStyle(h, 1000, 500);
    assert.equal(style.left, '-2px');
    assert.equal(style.top, '102px');
    const bottom = { x: 0.99, y: 0.95, width: 0.01, height: 0.05 };
    style = e.areaDragButtonStyle(bottom, 1000, 500);
    assert.equal(bottom.x * 1000 + 2 + parseFloat(style.left), 972);
    assert.equal(bottom.y * 500 + 2 + parseFloat(style.top), 472);
    style = e.areaDragButtonStyle(bottom, 400, 200, 32, 32);
    assert.equal(bottom.x * 400 + 2 + parseFloat(style.left), 368);
    assert.equal(bottom.y * 200 + 2 + parseFloat(style.top), 168);
});

test('batch generation submits unaccepted outputs without an estimate step', async () => {
    const e = sceneEditor({});
    e.extractionModel = 'image-model';
    e.extractionId = 1;
    e.extractionBatches = [
        {
            id: 1,
            outputs: [
                { id: 'pending', jobs: [] },
                { id: 'accepted', accepted_asset_id: 8, jobs: [] },
                { id: 'running', jobs: [{ status: 'running' }] },
            ],
        },
    ];
    const calls = [];
    e.extractionAction = async (output, action) => calls.push([output.id, action]);
    await e.generateMissingExtraction();
    assert.deepEqual(calls, [['pending', 'generate']]);
    assert.equal(e.extractionRun, false);
});

test('image area resizing locks aspect ratio at every corner and scene boundary', () => {
    const e = sceneEditor({});
    const original = { x: 0.3, y: 0.3, width: 0.2, height: 0.1 };
    for (const corner of ['nw', 'ne', 'sw', 'se']) {
        const h = { ...original };
        e.resizeAreaProportionally(h, original, corner, corner.includes('w') ? -0.1 : 0.1, 0);
        assert.ok(Math.abs(h.width / h.height - 2) < 1e-10);
        assert.ok(Math.abs((corner.includes('w') ? h.x + h.width : h.x) - (corner.includes('w') ? 0.5 : 0.3)) < 1e-10);
        assert.ok(Math.abs((corner.includes('n') ? h.y + h.height : h.y) - (corner.includes('n') ? 0.4 : 0.3)) < 1e-10);
        e.resizeAreaProportionally(
            h,
            original,
            corner,
            corner.includes('w') ? -10 : 10,
            corner.includes('n') ? -10 : 10,
        );
        assert.ok(Math.abs(h.width / h.height - 2) < 1e-10);
        assert.ok(h.x >= -1e-10 && h.y >= -1e-10 && h.x + h.width <= 1 + 1e-10 && h.y + h.height <= 1 + 1e-10);
    }
});
test('pointer resizing locks only areas with image display enabled', () => {
    const e = sceneEditor({});
    const h = {
        id: 'a',
        x: 0.2,
        y: 0.2,
        width: 0.2,
        height: 0.1,
        overlay: true,
        overlay_bounds: { x: 0.2, y: 0.2, width: 0.2, height: 0.1 },
    };
    e.document = { visuals: [{ id: 'scene', hotspots: [h] }] };
    e.selected = 'scene';
    const original = JSON.parse(JSON.stringify(h));
    e.areaDrag = { id: 'a', handle: 'se', startX: 0, startY: 0, rect: { width: 1000, height: 500 }, original };
    e.moveArea({ clientX: 100, clientY: 0 });
    assert.ok(Math.abs(h.width / h.height - 2) < 1e-10);
    assert.ok(Math.abs(h.overlay_bounds.width / h.overlay_bounds.height - 2) < 1e-10);
    Object.assign(h, original, { overlay: false });
    e.moveArea({ clientX: 100, clientY: 0 });
    assert.ok(Math.abs(h.width - 0.3) < 1e-10);
    assert.ok(Math.abs(h.height - 0.1) < 1e-10);
});

test('updating a saved plan selects only new areas and uses the accepted background', () => {
    const editor = sceneExtraction({});
    editor.selected = 'scene';
    editor.visual = { asset_id: 1 };
    editor.extractionPlanChoice = 7;
    editor.extractionBatches = [
        {
            id: 7,
            visual_id: 'scene',
            outputs: [
                { type: 'background', accepted_asset_id: 22 },
                { type: 'layer', area_id: 'old' },
            ],
        },
    ];
    editor.extractionAreas = [
        { id: 'old', selected: true },
        { id: 'bag', selected: true },
    ];
    editor.$refs = { extractionPlanDialog: { close() {} }, extractionDialog: { showModal() {} } };
    editor.updateExtractionPlan();
    assert.equal(editor.extractionUpdateId, 7);
    assert.equal(editor.extractionSource, 22);
    assert.equal(editor.extractionAreas[0].selected, false);
    assert.equal(editor.extractionAreas[0].reused, true);
    assert.equal(editor.extractionAreas[1].selected, true);
});

test('plan summaries distinguish untouched crops from completed or pending renders', () => {
    const e = sceneExtraction({});
    const batch = {
        id: 4,
        mode: 'switches',
        outputs: [
            { type: 'switch_off', accepted_asset_id: 1, jobs: [] },
            { type: 'switch_on', jobs: [] },
        ],
    };
    assert.match(e.extractionPlanLabel(batch), /Unrendered draft/);
    batch.outputs[1].jobs = [{ status: 'running' }];
    assert.match(e.planSummary(batch), /1 in progress/);
    assert.doesNotMatch(e.planSummary(batch), /Unrendered draft/);
    batch.outputs[1].jobs = [{ status: 'completed', asset_ids: [2] }];
    batch.outputs[1].accepted_asset_id = 2;
    batch.name = 'Porch door';
    assert.match(e.extractionPlanLabel(batch), /^Porch door/);
    assert.match(e.planSummary(batch), /1 rendered · 1 accepted/);
});

test('switch overview bypasses plan selection and never creates a plan', async () => {
    const editor = sceneExtraction({});
    editor.inventoryItems = [];
    editor.visual = { asset_id: 1, hotspots: [{ id: 'a', action: 'state_v2', state_prompt: 'Open the panel' }] };
    editor.selected = 'scene';
    editor.flush = async () => true;
    editor.refreshExtraction = async () => {};
    editor.extractionBatches = [{ id: 1, visual_id: 'scene', mode: 'switches', outputs: [] }];
    editor.request = async () => assert.fail('Must not create a plan');
    let opened = false;
    editor.$refs = {
        extractionDialog: {
            showModal() {
                opened = true;
            },
        },
        extractionPlanDialog: {
            showModal() {
                assert.fail('Must skip chooser');
            },
        },
    };
    await editor.openExtraction('switches');
    assert.equal(opened, true);
    assert.equal(editor.switchOverview, true);
    assert.equal(editor.extractionId, null);
    assert.equal(editor.currentSwitches[0].state_prompt, 'Open the panel');
});

test('current instruction edits flag old plans without changing their saved prompt', () => {
    const editor = sceneExtraction({});
    const area = {
        id: 'a',
        action: 'state_v2',
        label: 'Panel',
        state_prompt: 'Open it',
        state_description: 'Open panel',
    };
    editor.visual = { hotspots: [area] };
    const saved = editor.switchPrompt(area);
    const batch = {
        outputs: [
            {
                type: 'switch_on',
                area_id: 'a',
                prompt: saved,
                source_instructions: { state_description: 'Open panel' },
            },
        ],
    };
    assert.equal(editor.switchPlanChanged(batch), false);
    area.state_description = 'Blue lights glow';
    assert.equal(editor.switchPlanChanged(batch), true);
    area.state_prompt = 'Reveal blue lights';
    assert.equal(batch.outputs[0].prompt, saved);
    assert.match(editor.switchPrompt(area), /Reveal blue lights/);
    delete batch.outputs[0].source_instructions;
    assert.equal(editor.switchPlanChanged(batch), true);
});

test('updated switch setup preserves legacy crop and does not prepare automatically', async () => {
    const editor = sceneExtraction({});
    editor.visual = { asset_id: 2 };
    editor.extractionAreas = [{ id: 'a' }, { id: 'b' }];
    editor.$refs = { extractionDialog: { showModal() {} } };
    editor.request = async () => assert.fail('Must wait for explicit preparation');
    const bounds = { x: 0.1, y: 0.2, width: 0.3, height: 0.4 };
    const batch = { outputs: [{ type: 'switch_on', area_id: 'a', bounds }] };
    await editor.adjustExtractionPadding(batch);
    assert.deepEqual(editor.extractionAreas[0].bounds, bounds);
    assert.equal(editor.extractionAreas[0].useSavedBounds, true);
    assert.equal(editor.extractionAreas[1].selected, false);
    assert.equal(editor.extractionId, null);
    assert.equal(editor.switchOverview, false);
});
