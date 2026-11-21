import { notify } from './toasts.js';

export function characterLibrary(options) {
    return {
        characters: [],
        assets: [],
        draft: null,
        busy: false,
        loading: false,
        error: '',
        viewLabel: 'Front view',
        assetSearch: '',
        async init() {
            await this.load();
        },
        async request(path = '', method = 'GET', body) {
            const multipart = body instanceof FormData;
            const response = await fetch(path === options.uploadUrl ? path : options.url + path, {
                method,
                headers: {
                    Accept: 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                    ...(multipart ? {} : { 'Content-Type': 'application/json' }),
                },
                ...(body ? { body: multipart ? body : JSON.stringify(body) } : {}),
            });
            const data = await response.json();
            if (!response.ok)
                throw new Error(
                    Object.values(data.errors || {}).flat()[0] || data.message || 'Character request failed.',
                );
            return data;
        },
        async load() {
            this.loading = true;
            try {
                const data = await this.request();
                this.characters = data.characters;
                this.assets = data.assets;
            } catch (error) {
                this.error = error.message;
            } finally {
                this.loading = false;
            }
        },
        edit(character = null) {
            this.error = '';
            this.draft = character
                ? JSON.parse(JSON.stringify(character))
                : { name: '', description: '', views: [], revision: 0 };
            this.viewLabel = 'Front view';
            this.$refs.characterDialog.showModal();
        },
        async save(close = true) {
            if (this.busy || !this.draft) return false;
            this.busy = true;
            this.error = '';
            try {
                const data = await this.request(
                    this.draft.id ? '/' + this.draft.id : '',
                    this.draft.id ? 'PUT' : 'POST',
                    {
                        revision: this.draft.revision,
                        name: this.draft.name,
                        description: this.draft.description,
                        views: this.draft.views.map((v) => ({
                            label: v.label,
                            asset_id: v.asset_id || null,
                            image_id: v.image_id || null,
                        })),
                    },
                );
                Object.assign(this.draft, data);
                await this.load();
                if (close) this.$refs.characterDialog.close();
                notify('Character saved. Available to image generation and chat.');
                return true;
            } catch (error) {
                this.error = error.message;
                return false;
            } finally {
                this.busy = false;
            }
        },
        async upload(event) {
            const files = [...(event.target.files || [])];
            if (!files.length || this.busy) return;
            this.busy = true;
            this.error = '';
            try {
                if (files.length > 8 || this.draft.views.length + files.length > 16)
                    throw new Error('Upload up to 8 images at once, with up to 16 views per character.');
                const body = new FormData();
                body.append('role', 'character');
                files.forEach((file) => body.append('images[]', file));
                const data = await this.request(options.uploadUrl, 'POST', body);
                this.assets.unshift(...data.assets);
                data.assets.forEach((asset) => this.addView(asset));
            } catch (error) {
                this.error = error.message;
            } finally {
                this.busy = false;
                event.target.value = '';
            }
        },
        addView(asset) {
            if (this.draft.views.some((v) => v.asset_id === asset.id) || this.draft.views.length >= 16) return;
            this.draft.views.push({ asset_id: asset.id, label: asset.name, url: asset.url, status: 'ready' });
        },
        async prepareView() {
            if (!this.viewLabel.trim() || !(await this.save(false))) return;
            this.busy = true;
            this.error = '';
            try {
                const data = await this.request('/' + this.draft.id + '/draft', 'POST', {
                    revision: this.draft.revision,
                    page_id: options.pageId,
                    label: this.viewLabel,
                });
                window.location.assign(data.url);
            } catch (error) {
                this.error = error.message;
                this.busy = false;
            }
        },
        async remove(character) {
            if (
                this.busy ||
                !confirm('Delete ' + character.name + '? Reference images and existing image drafts will be kept.')
            )
                return;
            this.busy = true;
            this.error = '';
            try {
                await this.request('/' + character.id, 'DELETE', { revision: character.revision });
                await this.load();
            } catch (error) {
                this.error = error.message;
            } finally {
                this.busy = false;
            }
        },
    };
}
