import { notify } from './toasts.js';
export const audioActions = {
    examine: 'Examine',
    pickup_v2: 'Pick up object — local layer',
    state_v2: 'Change state — local switch',
    inventory: 'Use inventory on area',
};
export { GameAudio, speechText } from './game-audio.js';
export function storyAudio(url) {
    return {
        editingJob: null,
        voiceModels: [],
        batchBusy: false,
        batchProgress: '',
        jobBusy: false,
        get pendingJobs() {
            return this.jobs.filter((j) => j.status === 'draft');
        },
        editableJob(j) {
            return ['draft', 'queued'].includes(j.status) && !j.submitted_at && !j.provider_request_id;
        },
        async startJob(j) {
            await this.request('/jobs/' + j.id + '/start', 'POST');
        },
        async startPending() {
            if (this.batchBusy) return;
            this.batchBusy = true;
            try {
                const jobs = [...this.pendingJobs];
                for (let i = 0; i < jobs.length; i++) {
                    this.batchProgress = 'Queuing ' + (i + 1) + ' of ' + jobs.length;
                    await this.startJob(jobs[i]);
                }
                notify('Pending audio queued.');
            } catch (e) {
                notify(e.message, 'error');
            } finally {
                this.batchBusy = false;
                this.batchProgress = '';
                await this.load();
            }
        },
        async editJob(j) {
            this.editingJob = JSON.parse(JSON.stringify(j));
            this.editingJob.settings ??= {};
            this.$refs.audioJobDialog.showModal();
            try {
                if (!this.voiceModels.length) this.voiceModels = (await this.request('/voices')).models;
            } catch (e) {
                notify(e.message, 'error');
            }
        },
        get draftVoices() {
            return this.voiceModels.find((m) => m.id === this.editingJob?.model)?.supported_voices || [];
        },
        async saveJob(generate = false) {
            if (this.jobBusy) return;
            this.jobBusy = true;
            try {
                const saved = await this.request('/jobs/' + this.editingJob.id, 'PUT', this.editingJob);
                // Queued requests may receive a new ID when saved back as drafts.
                this.editingJob = saved;
                if (generate) await this.startJob(saved);
                this.$refs.audioJobDialog.close();
                await this.load();
                notify(generate ? 'Audio generation queued.' : 'Saved as pending audio.');
            } catch (e) {
                notify(e.message, 'error');
            } finally {
                this.jobBusy = false;
            }
        },
        async deleteJob(j) {
            if (!confirm('Delete this pending audio request?')) return;
            try {
                await this.request('/jobs/' + j.id, 'DELETE');
                await this.load();
            } catch (e) {
                notify(e.message, 'error');
            }
        },
        async deleteSound(s) {
            try {
                const d = await this.request('/sounds/' + s.id + '/usage');
                const warning = d.uses.length
                    ? 'Used in:\n' + d.uses.join('\n') + '\n\nThese places will be set to no audio.\n\n'
                    : '';
                if (!confirm(warning + 'Delete ' + s.name + '?')) return;
                await this.request('/sounds/' + s.id, 'DELETE', { confirm: true });
                await this.load();
                notify('Audio deleted.');
            } catch (e) {
                notify(e.message, 'error');
            }
        },
        categories: {},
        categoryFilter: '',
        newCategory: '',
        editingCategory: null,
        categoryNameDraft: '',
        categoryBusy: false,
        categoryUsed(key) {
            return [...this.sounds, ...this.jobs].some((item) => (item.category || 'effects') === key);
        },
        editCategory(key) {
            this.editingCategory = key;
            this.categoryNameDraft = this.categories[key];
        },
        async finishCategoryEdit(key) {
            if (!this.categoryNameDraft.trim()) return;
            if (await this.saveCategories({ ...this.categories, [key]: this.categoryNameDraft.trim() }))
                this.editingCategory = null;
        },
        async deleteCategory(key) {
            if (this.categoryUsed(key) || this.categoryBusy) return;
            const categories = { ...this.categories };
            delete categories[key];
            if (await this.saveCategories(categories)) {
                if (this.categoryFilter === key) this.categoryFilter = '';
                if (this.editingCategory === key) this.editingCategory = null;
            }
        },
        async saveCategories(categories = this.categories) {
            if (this.categoryBusy) return false;
            this.categoryBusy = true;
            try {
                const data = await this.request('/categories', 'PUT', { categories });
                this.categories = data.categories;
                window.vsgAudioCategories = this.categories;
                window.dispatchEvent(new CustomEvent('vsg-audio-categories', { detail: this.categories }));
                return true;
            } catch (error) {
                notify(error.message, 'error');
                return false;
            } finally {
                this.categoryBusy = false;
            }
        },
        async addCategory() {
            if (!this.newCategory.trim()) return;
            const categories = { ...this.categories, ['custom_' + crypto.randomUUID()]: this.newCategory.trim() };
            if (await this.saveCategories(categories)) this.newCategory = '';
        },
        async categorize(sound, category) {
            try {
                await this.request('/sounds/' + sound.id, 'PUT', { category });
                sound.category = category;
                window.dispatchEvent(new CustomEvent('vsg-audio', { detail: this.sounds }));
            } catch (e) {
                notify(e.message, 'error');
            }
        },
        get filteredSounds() {
            return this.sounds.filter((s) => !this.categoryFilter || (s.category || 'effects') === this.categoryFilter);
        },
        get libraryJobs() {
            return this.jobs.filter(
                (job) =>
                    !this.editableJob(job) &&
                    job.status !== 'completed' &&
                    (!this.categoryFilter || (job.category || 'effects') === this.categoryFilter),
            );
        },
        uploading: false,
        async uploadAudio(event) {
            const file = event.target.files?.[0];
            if (!file || this.uploading) return;
            this.uploading = true;
            this.error = '';
            try {
                const body = new FormData();
                body.append('file', file);
                body.append(
                    'category',
                    this.categoryFilter ||
                        (this.categories.effects ? 'effects' : Object.keys(this.categories)[0]) ||
                        'effects',
                );
                await this.request('/upload', 'POST', body);
                await this.load();
                notify('Audio uploaded to project.');
            } catch (error) {
                this.error = error.message;
                notify(error.message, 'error');
            } finally {
                this.uploading = false;
                event.target.value = '';
            }
        },
        model: 'minimax/music-3',
        sounds: [],
        jobs: [],
        searches: [],
        results: [],
        query: '',
        maxDuration: 3,
        page: 1,
        hasMore: false,
        count: 0,
        busy: false,
        searching: false,
        generating: false,
        name: '',
        prompt: '',
        seconds: 60,
        lyrics: '[instrumental]',
        requestKey: null,
        timer: null,
        error: '',
        async init() {
            await this.load();
        },
        destroy() {
            clearTimeout(this.timer);
            this.$el?.querySelectorAll('audio').forEach((a) => a.pause());
        },
        async request(path = '', method = 'GET', body) {
            const r = await fetch(url + path, {
                method,
                headers: {
                    Accept: 'application/json',
                    ...(body instanceof FormData ? {} : { 'Content-Type': 'application/json' }),
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                },
                ...(body ? { body: body instanceof FormData ? body : JSON.stringify(body) } : {}),
            });
            const data = await r.json();
            if (!r.ok)
                throw new Error(Object.values(data.errors || {}).flat()[0] || data.message || 'Audio request failed.');
            return data;
        },
        async load() {
            clearTimeout(this.timer);
            try {
                const data = await this.request();
                this.sounds = data.sounds;
                this.categories = data.categories;
                window.vsgAudioCategories = data.categories;
                window.dispatchEvent(new CustomEvent('vsg-audio-categories', { detail: data.categories }));
                window.dispatchEvent(new CustomEvent('vsg-credit', { detail: data.remaining }));
                this.jobs = data.jobs;
                this.searches = data.searches;
                window.dispatchEvent(new CustomEvent('vsg-audio', { detail: this.sounds }));
                window.dispatchEvent(new CustomEvent('vsg-audio-settings', { detail: data.settings }));
                if (this.jobs.some((j) => ['queued', 'submitting', 'running'].includes(j.status)))
                    this.timer = setTimeout(() => this.load(), 5000);
            } catch (e) {
                this.error = e.message;
            }
        },
        async search(page = 1) {
            if (this.searching || !this.query.trim()) return;
            this.searching = true;
            this.error = '';
            try {
                const d = await this.request(
                    '/search?query=' +
                        encodeURIComponent(this.query.trim()) +
                        '&page=' +
                        page +
                        '&max_duration=' +
                        encodeURIComponent(this.maxDuration),
                );
                this.results = d.results;
                this.page = d.page;
                this.hasMore = d.has_more;
                this.count = d.count;
                if (!this.searches.includes(this.query.trim())) this.searches.unshift(this.query.trim());
            } catch (e) {
                this.error = e.message;
                notify(e.message, 'error');
            } finally {
                this.searching = false;
            }
        },
        added(id) {
            return this.sounds.some((s) => s.provider === 'freesound' && String(s.external_id) === String(id));
        },
        async add(id) {
            if (this.busy) return;
            this.busy = true;
            try {
                await this.request('/import', 'POST', { id });
                await this.load();
                notify('Sound added to project.');
            } catch (e) {
                notify(e.message, 'error');
            } finally {
                this.busy = false;
            }
        },
        async generate() {
            if (this.generating) return;
            this.generating = true;
            this.error = '';
            this.requestKey ??= crypto.randomUUID();
            try {
                await this.request('/generate', 'POST', {
                    request_key: this.requestKey,
                    name: this.name,
                    model: this.model,
                    prompt: this.prompt,
                    seconds: Number(this.seconds),
                    ...(this.model === 'minimax/music-3' ? { settings: { lyrics: this.lyrics } } : {}),
                });
                this.requestKey = null;
                await this.load();
                this.$refs?.audioGenerateDialog?.close();
                notify('Audio generation queued.');
            } catch (e) {
                this.error = e.message;
                notify(e.message, 'error');
            } finally {
                this.generating = false;
            }
        },
    };
}
