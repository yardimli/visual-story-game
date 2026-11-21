import test from 'node:test';
import assert from 'node:assert/strict';
import { chatImages } from '../resources/js/chat-images.js';

test('chat picker retains explicit choices and library order while filtering categories', async () => {
    const original = globalThis.fetch;
    globalThis.fetch = async () => ({
        ok: true,
        json: async () => ({
            assets: [
                { id: 3, category: 'scene' },
                { id: 1, category: 'inventory' },
                { id: 2, category: 'scene' },
            ],
            image_categories: { scene: 'Scene', inventory: 'Inventory' },
        }),
    });
    try {
        const picker = { ...chatImages({ imagesUrl: '/images' }) };
        picker.$refs = { chatImagesDialog: { showModal() {} } };
        picker.chatImageIds = ['2', '99'];
        await picker.openChatImages();
        assert.deepEqual(picker.chatImageIds, ['2']);
        assert.deepEqual(
            picker.filteredChatImages().map((image) => image.id),
            [3, 1, 2],
        );
        picker.chatImageCategory = 'scene';
        assert.deepEqual(
            picker.filteredChatImages().map((image) => image.id),
            [3, 2],
        );
        picker.chatImageIds = [];
        await picker.openChatImages();
        assert.deepEqual(picker.chatImageIds, []);
    } finally {
        globalThis.fetch = original;
    }
});
