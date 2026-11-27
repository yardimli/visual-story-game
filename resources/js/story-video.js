export function storyVideo(url, uploadUrl) {
    return {
        engines: {},
        assets: [],
        imageCategories: {},
        framePicker: null,
        imageCategoryFilter: '',
        get frameImages() {
            return this.assets.filter(
                (image) => !this.imageCategoryFilter || image.category === this.imageCategoryFilter,
            );
        },
        chooseFrame(image) {
            if (!this.framePicker) return;
            if (this.framePicker === 'reference') this.addReference(image.id);
            else this.form[this.framePicker + '_asset_id'] = image.id;
            this.framePicker = null;
        },
        addReference(id) {
            const ids = this.form.reference_asset_ids;
            if (ids.length < (this.engines[this.form.engine]?.max_references || 0) && !ids.includes(id)) ids.push(id);
        },
        jobs: [],
        form: { name: '', prompt: '', engine: '', parameters: {}, first_asset_id: '', last_asset_id: '', reference_asset_ids: [] },
        editing: null,
        busy: false,
        loading: false,
        error: '',
        notice: '',
        now: Date.now(),
        zoom: null,
        init() {
            this.onTab = (event) => {
                if (event.detail.tab === 'video') this.load();
                else this.$el.querySelectorAll('video').forEach((video) => video.pause());
            };
            window.addEventListener('vsg-editor-tab', this.onTab);
            if (new URL(location.href).searchParams.get('tab') === 'video') this.load();
            this.timer = setInterval(() => {
                this.now = Date.now();
                if (window.vsgAutosave?.tab === 'video' && !document.hidden) this.load();
            }, 5000);
        },
        destroy() {
            clearInterval(this.timer);
            window.removeEventListener('vsg-editor-tab', this.onTab);
        },
        async request(path = '', method = 'GET', body = null) {
            const multipart = body instanceof FormData;
            const response = await fetch(path === uploadUrl ? uploadUrl : url + path, {
                method,
                headers: {
                    Accept: 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                    ...(multipart ? {} : { 'Content-Type': 'application/json' }),
                },
                body: body ? (multipart ? body : JSON.stringify(body)) : null,
            });
            if (response.status === 204) return null;
            const data = await response.json().catch(() => ({}));
            if (!response.ok) throw new Error(data.message || `Request failed (${response.status}).`);
            return data;
        },
        async load() {
            if (this.loading) return;
            this.loading = true;
            try {
                const data = await this.request();
                this.engines = data.engines;
                this.assets = data.assets;
                this.imageCategories = data.image_categories || {};
                this.jobs = data.jobs;
                if (!this.form.engine) {
                    this.form.engine = Object.keys(this.engines)[0] || '';
                    this.selectEngine();
                }
            } catch (error) {
                this.error = error.message;
            } finally {
                this.loading = false;
            }
        },
        selectEngine() {
            this.form.parameters = Object.fromEntries(
                Object.entries(this.engines[this.form.engine]?.parameters || {}).map(([key, value]) => [
                    key,
                    value.default,
                ]),
            );
            if (!this.engines[this.form.engine]?.supports_last_frame) this.form.last_asset_id = '';
            if (!this.engines[this.form.engine]?.max_references) this.form.reference_asset_ids = [];
        },
        asset(id) {
            return this.assets.find((asset) => asset.id == id);
        },
        async perform(action) {
            if (this.busy) return;
            this.busy = true;
            this.error = '';
            this.notice = '';
            try {
                await action();
                await this.load();
            } catch (error) {
                this.error = error.message;
            } finally {
                this.busy = false;
            }
        },
        save(queue = false) {
            return this.perform(async () => {
                const payload = { ...this.form, last_asset_id: this.form.last_asset_id || null };
                const job = await this.request(
                    this.editing ? `/${this.editing}` : '',
                    this.editing ? 'PATCH' : 'POST',
                    payload,
                );
                // Keep the saved draft selected if queueing fails, so retry cannot create a duplicate.
                this.editing = job.id;
                if (queue) await this.request(`/${job.id}/queue`, 'POST');
                this.notice = queue ? 'Video queued.' : 'Draft saved.';
                this.reset();
                this.$refs?.videoFormDialog?.close();
            });
        },
        createVideo() {
            this.reset();
            this.error = '';
            this.$refs.videoFormDialog.showModal();
        },
        reset() {
            this.editing = null;
            this.form.name = '';
            this.form.prompt = '';
            this.form.first_asset_id = '';
            this.form.last_asset_id = '';
            this.form.reference_asset_ids = [];
            this.form.engine = Object.keys(this.engines)[0] || '';
            this.selectEngine();
        },
        edit(job, copy = false) {
            this.editing = copy ? null : job.id;
            this.form = {
                name: job.name,
                prompt: job.prompt,
                engine: job.engine,
                parameters: Object.fromEntries(Object.entries(this.engines[job.engine]?.parameters || {}).map(
                    ([key, parameter]) => [key, job.parameters?.[key] ?? parameter.default],
                )),
                first_asset_id: job.first_asset_id,
                last_asset_id: job.last_asset_id || '',
                reference_asset_ids: [...(job.reference_asset_ids || [])],
            };
            this.error = '';
            this.$refs.videoFormDialog.showModal();
        },
        queue(job) {
            return this.perform(() => this.request(`/${job.id}/queue`, 'POST'));
        },
        queueAll() {
            return this.perform(async () => {
                for (const job of this.jobs.filter((item) => item.status === 'draft'))
                    await this.request(`/${job.id}/queue`, 'POST');
            });
        },
        remove(job) {
            if (
                !confirm(
                    `Delete “${job.name}”${job.status === 'completed' ? ' and its video? Scenes using it will have no intro video' : ''}?`,
                )
            )
                return;
            return this.perform(async () => {
                await this.request(`/${job.id}`, 'DELETE');
                if (this.editing === job.id) this.reset();
            });
        },
        upload(event, frame) {
            const file = event.target.files[0];
            if (!file) return;
            return this.perform(async () => {
                const body = new FormData();
                body.append('role', frame === 'reference' ? 'scene' : (frame === 'first' ? 'start' : 'end'));
                body.append('images[]', file);
                const data = await this.request(uploadUrl, 'POST', body);
                this.assets.unshift(data.assets[0]);
                if (frame === 'reference') this.addReference(data.assets[0].id);
                else this.form[`${frame}_asset_id`] = data.assets[0].id;
                event.target.value = '';
            });
        },
        elapsed(job) {
            if (!job.started_at) return '';
            const seconds = Math.max(
                0,
                Math.floor(
                    ((job.completed_at ? Date.parse(job.completed_at) : this.now) - Date.parse(job.started_at)) / 1000,
                ),
            );
            return `${Math.floor(seconds / 60)}m ${seconds % 60}s`;
        },
        status(job) {
            return (
                {
                    draft: 'Pending draft',
                    forwarding: 'Sending to video server',
                    queued: 'Queued for GPU',
                    claimed: 'Preparing workflow',
                    submitting: 'Queued in ComfyUI',
                    running: 'Rendering',
                    uploading: 'Uploading MP4',
                    downloading: 'Downloading MP4',
                    completed: 'Completed',
                    failed: 'Failed',
                }[job.status] || job.status
            );
        },
    };
}
