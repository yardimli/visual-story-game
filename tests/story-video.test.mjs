import test from 'node:test';
import assert from 'node:assert/strict';
import { storyVideo } from '../resources/js/story-video.js';

test('status polling requests only active jobs and preserves prompts while merging completion', async () => {
    const video=storyVideo('/videos','/assets');
    video.jobs=[{id:'old',status:'completed'},{id:'draft',status:'draft'},{id:'active',status:'running',prompt:'Keep full prompt',parameters:{seconds:5}}];
    const requests=[];
    video.request=async path=>{requests.push(path);return {jobs:[{id:'active',status:'completed',progress:100,url:'/result.mp4'}],missing_ids:[]};};
    await video.pollStatuses();
    assert.equal(requests.length,1);
    assert.equal(new URLSearchParams(requests[0].split('?')[1]).get('ids[]'),'active');
    assert.equal(video.jobs[2].prompt,'Keep full prompt');
    assert.equal(video.jobs[2].url,'/result.mp4');
    await video.pollStatuses();
    assert.equal(requests.length,1);
});

test('status polling batches active jobs and releases its lock after failure', async () => {
    const video=storyVideo('/videos','/assets');
    video.jobs=Array.from({length:51},(_,i)=>({id:String(i),status:'queued'}));
    const sizes=[];
    video.request=async path=>{sizes.push(new URLSearchParams(path.split('?')[1]).getAll('ids[]').length);return {jobs:[],missing_ids:[]};};
    await video.pollStatuses();
    assert.deepEqual(sizes,[50,1]);
    video.request=async()=>{throw new Error('Disconnected');};
    await video.pollStatuses();
    assert.equal(video.polling,false);
    assert.equal(video.error,'Disconnected');
});

test('old reference drafts get normal mode and no editable steps; lightning survives editing', () => {
    const video=storyVideo('/videos','/assets');
    video.engines={minimax_h3_reference:{parameters:{lightning:{type:'boolean',default:false}}}};
    video.$refs={videoFormDialog:{showModal(){}}};
    const job={id:'job',engine:'minimax_h3_reference',name:'Reference',prompt:'Move',parameters:{steps:25}};
    video.edit(job);
    assert.deepEqual(video.form.parameters,{lightning:false});
    video.edit({...job,parameters:{lightning:true}});
    assert.deepEqual(video.form.parameters,{lightning:true});
});

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

test('reference selection preserves order, enforces limit and survives draft editing', () => {
    const video = storyVideo('/videos', '/assets');
    video.engines = {fast: {parameters: {}, max_references: 0}, ref: {parameters: {}, max_references: 9}};
    video.form.engine = 'ref';
    for (let id = 1; id <= 10; id++) { video.framePicker = 'reference'; video.chooseFrame({id}); }
    assert.deepEqual(video.form.reference_asset_ids, [1,2,3,4,5,6,7,8,9]);
    video.addReference(1);
    assert.equal(video.form.reference_asset_ids.length, 9);
    video.$refs = {videoFormDialog: {showModal() {}}};
    const job = {...video.form, id: 'draft'};
    video.edit(job);
    video.form.reference_asset_ids.pop();
    assert.equal(job.reference_asset_ids.length, 9);
    video.reset();
    assert.equal(video.form.engine, 'fast');
    assert.deepEqual(video.form.reference_asset_ids, []);
});
