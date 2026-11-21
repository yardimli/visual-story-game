import test from 'node:test';
import assert from 'node:assert/strict';
import { characterLibrary } from '../resources/js/character-library.js';
import { sceneEditor } from '../resources/js/scene-editor.js';

test('character view selection uses the image draft references and keeps other selections', () => {
    const editor = sceneEditor({});
    editor.characters = [{ id: 'mira', views: [{ asset_id: 2 }, { asset_id: 3 }, { asset_id: null }] }];
    editor.references = ['1', '3'];
    assert.deepEqual(editor.characterReferenceIds, ['2', '3']);
    assert.equal(editor.selectedCharacterViews, 1);
    editor.references.push('2');
    assert.equal(editor.selectedCharacterViews, 2);
    assert.ok(editor.references.includes('1'));
});

test('editing and cancelling a character does not mutate the saved profile', () => {
    const library = characterLibrary({});
    library.$refs = { characterDialog: { showModal() {} } };
    const character = { id: 'mira', name: 'Mira', views: [{ asset_id: 1, label: 'Front' }] };
    library.edit(character);
    library.draft.views[0].label = 'Profile';
    library.draft.name = 'Someone else';
    assert.equal(character.name, 'Mira');
    assert.equal(character.views[0].label, 'Front');
});

test('saving views sends only persistent fields and retains selected images', async () => {
    const library = characterLibrary({});
    library.draft = {
        id: 'mira',
        revision: 1,
        name: 'Mira',
        description: 'Braid',
        views: [{ label: 'Profile', asset_id: 7, image_id: 10, url: '/7', status: 'ready' }],
    };
    library.load = async () => {};
    library.$refs = { characterDialog: { close() {} } };
    library.request = async (path, method, body) => {
        assert.equal(path, '/mira');
        assert.equal(method, 'PUT');
        assert.deepEqual(body.views, [{ label: 'Profile', asset_id: 7, image_id: 10 }]);
        return { id: 'mira', revision: 2 };
    };
    assert.equal(await library.save(), true);
    assert.equal(library.draft.revision, 2);
});

test('duplicate reference views are not added twice', () => {
    const library = characterLibrary({});
    library.draft = { views: [] };
    const asset = { id: 1, name: 'Front', url: '/1' };
    library.addView(asset);
    library.addView(asset);
    assert.equal(library.draft.views.length, 1);
});
