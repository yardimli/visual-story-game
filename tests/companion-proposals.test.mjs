import test from 'node:test';
import assert from 'node:assert/strict';
import {proposalTargets, displayMessage} from '../resources/js/companion-proposals.js';

test('existing nested bible premise fills premise without replacing bible', () => {
    const targets = proposalTargets({bible:{premise:'PAGE 1\n\nThe invitation.'}}, 4, 1);
    assert.equal(targets[0][0], 'story');
    assert.equal(targets[0][1].premise, 'PAGE 1\n\nThe invitation.');
    assert.equal(targets[0][1].bible, undefined);
    assert.ok(targets[0][2].includes('premise'));
});
test('partial story and page proposals target only the selected page', () => {
    const targets = proposalTargets({story:{premise:'Formatted'}, pages:[{position:1,narrative:'First'}, {position:2,title:'Second'}]}, 4, 2);
    assert.deepEqual(targets[0][1], {premise:'Formatted'});
    assert.deepEqual(targets[1][1], {position:2,title:'Second'});
    assert.ok(!targets[0][2].includes('openrouter_key'));
});
test('legacy string bible remains supported', () => {
    assert.equal(proposalTargets({bible:'World description'}, 4, 1)[0][1].bible, 'World description');
});
test('proposal JSON is hidden but prose and other code remain', () => {
    const item = {role:'assistant', proposal:{bible:{premise:'Formatted'}}, content:'## The Silent House\n\nReadable prose.\n```json\n{"bible":{"premise":"Formatted"}}\n```\n```text\nExample\n```'};
    const shown = displayMessage(item);
    assert.ok(shown.includes('Readable prose.'));
    assert.ok(shown.includes('```text'));
    assert.ok(!shown.includes('```json'));
    assert.equal(item.proposal.bible.premise, 'Formatted');
});
test('user messages and invalid JSON are preserved', () => {
    assert.equal(displayMessage({role:'user',content:'```json {} ```'}),'```json {} ```');
    assert.equal(displayMessage({role:'assistant',proposal:{},content:'```json invalid ```'}),'```json invalid ```');
});
