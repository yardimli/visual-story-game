import { speechText } from './game-audio.js';

// Keep complete downloads for this player session. Blob URLs avoid a second
// network request when media elements start playing, including video ranges.
export class SceneMediaCache {
    constructor() {
        this.files = new Map();
        this.controller = null;
    }

    url(url) {
        return this.files.get(url) || url;
    }

    cancel() {
        this.controller?.abort();
        this.controller = null;
    }

    dispose() {
        this.cancel();
        for (const url of this.files.values()) URL.revokeObjectURL(url);
        this.files.clear();
    }

    async load(entries, progress) {
        this.cancel();
        const controller = new AbortController();
        this.controller = controller;
        const files = [...new Map(entries.map((entry) => [entry.url, entry])).values()];
        const fractions = files.map((entry) => this.files.has(entry.url) ? 1 : 0);
        const report = () => progress({
            total: files.length,
            completed: fractions.filter((n) => n === 1).length,
            percent: files.length ? Math.floor(fractions.reduce((a, b) => a + b, 0) / files.length * 100) : 100,
        });
        report();
        let cursor = 0;
        const worker = async () => {
            while (cursor < files.length) {
                const index = cursor++;
                const file = files[index];
                if (this.files.has(file.url)) continue;
                const timeout = setTimeout(() => controller.abort(), 180000);
                let objectUrl;
                try {
                    const response = await fetch(file.url, { signal: controller.signal, credentials: 'same-origin' });
                    if (!response.ok) throw new Error('Scene media could not be downloaded.');
                    const length = Number(response.headers.get('Content-Length'));
                    let blob;
                    if (response.body?.getReader) {
                        const reader = response.body.getReader();
                        const chunks = [];
                        let received = 0;
                        while (true) {
                            const { done, value } = await reader.read();
                            if (done) break;
                            chunks.push(value);
                            received += value.byteLength;
                            if (length > 0) fractions[index] = Math.min(0.99, received / length);
                            report();
                        }
                        blob = new Blob(chunks, { type: response.headers.get('Content-Type') || '' });
                    } else {
                        blob = await response.blob();
                    }
                    if (controller.signal.aborted) throw new Error('Scene download cancelled.');
                    objectUrl = URL.createObjectURL(blob);
                    if (file.kind === 'image') {
                        const image = new Image();
                        image.src = objectUrl;
                        await image.decode();
                    }
                    if (controller.signal.aborted) throw new Error('Scene download cancelled.');
                    this.files.set(file.url, objectUrl);
                    objectUrl = null;
                    fractions[index] = 1;
                    report();
                } finally {
                    clearTimeout(timeout);
                    if (objectUrl) URL.revokeObjectURL(objectUrl);
                }
            }
        };
        try {
            await Promise.all(Array.from({ length: Math.min(4, files.length) }, worker));
        } catch (error) {
            controller.abort();
            throw error;
        } finally {
            if (this.controller === controller) this.controller = null;
        }
    }
}

export function sceneMedia(player, scene) {
    const images = new Set();
    const sounds = new Set();
    const text = new Set();
    const visit = (value, key = '') => {
        if (Array.isArray(value)) return value.forEach((item) => visit(item, key));
        if (value && typeof value === 'object') return Object.entries(value).forEach(([k, v]) => visit(v, k));
        if (key.endsWith('asset_id') && value) images.add(Number(value));
        if (['sound_id', 'action_sound_id', 'music_id', 'click', 'action'].includes(key) && Number(value)) sounds.add(Number(value));
        if (typeof value === 'string') text.add(speechText(value));
    };
    visit(scene);
    // Inventory is usable in every scene, including objects obtained elsewhere.
    visit(player.gameObjects);
    visit(player.audioSettings);
    visit(player.gameplayMessages);
    text.add(speechText(player.unavailableText));
    const entries = player.assets.filter((asset) => images.has(asset.id)).map((asset) => ({ url: asset.url, kind: 'image' }));
    const speech = new Set();
    for (const sound of player.audioSounds || []) {
        const normalized = speechText(sound.source_text);
        const narration = normalized && text.has(normalized) && !speech.has(normalized);
        if (narration) speech.add(normalized);
        if (narration || sounds.has(sound.id)) entries.push({ url: sound.url, kind: 'audio' });
    }
    // Actions may become available after flags or inventory change. Load every
    // video assigned to this scene before enabling play, not just its intro.
    const videoIds = new Set([
        scene.intro_video_id,
        ...(scene.hotspots || []).map((area) => area.action_video_id),
    ].filter(Boolean));
    for (const video of player.videos || []) {
        if (videoIds.has(video.id) && video.url) entries.push({ url: video.url, kind: 'video' });
    }
    return entries.filter((entry) => entry.url);
}
