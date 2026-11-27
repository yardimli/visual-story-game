import { gameRuntime } from './game-runtime.js';
import { containerPlans, containerVisible, containerGeometry } from './container-plans.js';
import { GameAudio } from './story-audio.js';
import { sceneExtraction } from './scene-extraction.js';
import { scenePalette } from './scene-colors.js';
import { imageCatalog } from './image-catalog.js';
import { notify, watchNotices } from './toasts.js';
import {
    selectableModels,
    rememberedModel,
    rememberModel,
    imagePriceLabel,
    imageModelLabel,
} from './model-preferences.js';
export function sceneEditor(options) {
    return Object.defineProperties(
        {
            cursorTooltips: {},
            unavailableTimeout: 1000,
            unavailableText: 'You cannot do that yet.',
            audioSettings: {},
            audioMuted: false,
            audioSounds: [],
            soundPlayer: null,
            flagDefinitions: [],
            flags: {},
            previewFlags: [],
            imageCategories: {
                scene: 'Scene',
                characters: 'Characters',
                inventory: 'Inventory',
                change_state: 'Change state',
            },
            imageCategoryFilter: '',
            newImageCategory: '',
            editingCategory: null,
            categoryNameDraft: '',
            categoryBusy: false,
            categoryUsed(key) {
                return this.jobs.some((job) => (job.category || 'scene') === key);
            },
            editCategory(key) {
                this.editingCategory = key;
                this.categoryNameDraft = this.imageCategories[key];
            },
            async finishCategoryEdit(key) {
                if (!this.categoryNameDraft.trim()) return;
                if (await this.saveImageCategories({ ...this.imageCategories, [key]: this.categoryNameDraft.trim() }))
                    this.editingCategory = null;
            },
            async deleteCategory(key) {
                if (this.categoryUsed(key) || this.categoryBusy) return;
                const categories = { ...this.imageCategories };
                delete categories[key];
                if (await this.saveImageCategories(categories)) {
                    if (this.imageCategoryFilter === key) this.imageCategoryFilter = '';
                    if (this.editingCategory === key) this.editingCategory = null;
                }
            },
            async saveImageCategories(categories = this.imageCategories) {
                if (this.categoryBusy) return false;
                this.categoryBusy = true;
                try {
                    const data = await this.request(options.categoriesUrl, 'PUT', { categories });
                    this.imageCategories = data.categories;

                    return true;
                } catch (error) {
                    this.error = error.message;
                    return false;
                } finally {
                    this.categoryBusy = false;
                }
            },
            async addImageCategory() {
                if (!this.newImageCategory.trim()) return;
                const categories = {
                    ...this.imageCategories,
                    ['custom_' + crypto.randomUUID()]: this.newImageCategory.trim(),
                };
                if (await this.saveImageCategories(categories)) this.newImageCategory = '';
            },
            savingImageCategory: {},
            async categorizeImage(job, category) {
                if (this.savingImageCategory[job.id]) return;
                this.savingImageCategory[job.id] = true;
                try {
                    const data = await this.request(options.categoriesUrl + '/' + job.id, 'PUT', { category });
                    // A polling refresh may have replaced the card while the save was running.
                    const current = this.jobs.find((item) => item.id === job.id);
                    if (current) current.category = data.category;
                    job.category = data.category;
                } catch (error) {
                    this.error = error.message;
                } finally {
                    delete this.savingImageCategory[job.id];
                }
            },
            imageOrder: [],
            orderingImages: false,
            movedImageId: null,
            deleteImageId: null,
            deletingImage: false,
            inventoryItems: [],
            visualMenuOpen: false,
            draggedVisualId: '',
            visualDropId: '',
            narration: null,
            narrationVersion: 0,
            inventoryPulse: false,
            pickupFlight: null,
            pickupTimer: null,
            previewInventorySelection: [],
            previewPages: [],
            editorPageId: null,
            previewPageId: null,
            generationMode: !!options.generationMode,
            generationName: '',
            characters: [],
            charactersLoading: false,
            get characterReferenceIds() {
                return [
                    ...new Set(
                        this.characters.flatMap((c) => c.views.map((v) => v.asset_id).filter(Boolean)).map(String),
                    ),
                ];
            },
            get selectedCharacterViews() {
                return this.references.filter((id) => this.characterReferenceIds.includes(String(id))).length;
            },
            async openCharacters() {
                this.$refs.characterReferencesDialog.showModal();
                this.charactersLoading = true;
                try {
                    const data = await this.request(options.url);
                    this.characters = data.characters || [];
                    this.assets = data.assets;
                } catch (error) {
                    this.error = error.message;
                } finally {
                    this.charactersLoading = false;
                }
            },
            selectedImageId: null,
            generationInput: { prompt: '', video_prompt: '' },
            namingImage: false,
            retryingJob: null,
            editingVisual: false,
            showArchived: false,
            showFailed: false,
            expandedPrompts: {},
            assetNames: {},
            assetSaving: {},
            copiedPrompt: null,
            document: { visuals: [] },
            revision: 1,
            assets: [],
            jobs: [],
            selected: '',
            hotspotId: '',
            models: [],
            model: '',
            endpoints: [],
            settings: {},
            references: [],
            renderClock: Date.now(),
            renderClockTimer: null,
            loading: true,
            dirty: false,
            saving: false,
            halted: false,
            status: 'Loading scene artwork…',
            error: '',
            timer: null,
            active: null,
            polling: null,
            generating: false,
            uploading: false,
            uploadStatus: '',
            requestKey: crypto.randomUUID(),
            typeChange: null,
            moveJob: null,
            moveTarget: '',
            movingImage: false,
            visualAction: 'scene',
            visualName: '',
            visualSourceId: '',
            deleteVisualId: '',
            areaDrag: null,
            renamingId: '',
            labelDraft: '',
            pickerKind: 'object',
            deleteAreaId: '',
            suppressImageClick: false,
            inventoryOpen: false,
            outgoingScene: null,
            sceneTransition: false,
            transitionTimer: null,
            modalTimer: null,
            pendingModal: null,
            preview: false,
            previewId: '',
            inventory: [],
            collected: [],
            modal: null,
            drawing: false,
            drag: null,
            drawRect: null,
            imagePriceLabel,
            imageModelLabel,
            renderStarted(job) {
                const date = new Date(job.started_at);
                return job.started_at && Number.isFinite(date.getTime())
                    ? date.toLocaleTimeString([], { hour: '2-digit', minute: '2-digit', second: '2-digit' })
                    : '';
            },
            renderElapsed(job) {
                const start = Date.parse(job.started_at);
                if (!Number.isFinite(start)) return '';
                const seconds = Math.max(0, Math.floor((this.renderClock - start) / 1000));
                return Math.floor(seconds / 60) + 'm ' + String(seconds % 60).padStart(2, '0') + 's';
            },
            introVideos: [],
            introVideosLoading: false,
            introVideosError: '',
            async loadIntroVideos() {
                if (!options.videoUrl) return;
                this.introVideosLoading = true;
                this.introVideosError = '';
                try {
                    const response = await fetch(options.videoUrl, { headers: { Accept: 'application/json' } });
                    if (!response.ok) throw new Error('Could not load scene videos. Try again.');
                    const data = await response.json();
                    this.introVideos = data.jobs.filter((job) => job.status === 'completed' && job.url);
                } catch (error) {
                    this.introVideosError = error.message;
                } finally {
                    this.introVideosLoading = false;
                }
            },
            openIntroVideoPicker() {
                this.$refs.introVideoPicker.showModal();
                this.loadIntroVideos();
            },
            selectIntroVideo(id) {
                this.visual.intro_video_id = id;
                this.changed();
                this.$refs.introVideoPicker.close();
            },
            async init() {
                this.loadIntroVideos();
                if (this.generationMode)
                    this.renderClockTimer = setInterval(() => {
                        this.renderClock = Date.now();
                    }, 1000);
                watchNotices(this, { status: 'success', error: 'error', uploadStatus: 'success' });
                window.vsgSceneEditor = this;
                this.uiThemeListener = (e) => { this.uiTheme = e.detail; };
                window.addEventListener('vsg-ui-theme', this.uiThemeListener);
                this.unavailableTextListener = (e) => {
                    this.unavailableText = e.detail.text || 'You cannot do that yet.';
                    this.unavailableTimeout = e.detail.timeout ?? 1000;
                };
                window.addEventListener('vsg-unavailable-text', this.unavailableTextListener);
                this.cursorTooltipsListener = (e) => {
                    this.cursorTooltips = e.detail || {};
                };
                window.addEventListener('vsg-cursor-tooltips', this.cursorTooltipsListener);
                this.soundPlayer = new GameAudio();
                this.audioSettingsListener = (e) => {
                    this.audioSettings = e.detail || {};
                };
                window.addEventListener('vsg-audio-settings', this.audioSettingsListener);
                this.audioTabListener = (e) => {
                    if (e.detail.tab !== 'visuals') this.soundPlayer?.stopAudition();
                };
                window.addEventListener('vsg-editor-tab', this.audioTabListener);
                this.audioListener = (e) => {
                    this.audioSounds = e.detail;
                };
                window.addEventListener('vsg-audio', this.audioListener);
                if (options.audioUrl)
                    this.request(options.audioUrl)
                        .then((d) => {
                            this.audioSounds = d.sounds;
                            this.audioSettings = d.settings || {};
                        })
                        .catch(() => {});
                this.flagsListener = (e) => {
                    this.flagDefinitions = e.detail;
                };
                window.addEventListener('vsg-flags', this.flagsListener);
                this.inventoryListener = (e) => {
                    this.inventoryItems = JSON.parse(JSON.stringify(e.detail));
                };
                window.addEventListener('vsg-inventory', this.inventoryListener);
                this.model = rememberedModel('vsg-image-model', options.model || '');
                this.unload = (e) => {
                    if (this.dirty || this.saving) {
                        e.preventDefault();
                        e.returnValue = '';
                    }
                };
                window.addEventListener('beforeunload', this.unload);
                try {
                    const data = await this.request(options.url);
                    this.document = data.document;
                    this.containerPlans = data.container_plans || [];
                    this.previewPages = data.preview_pages || [];
                    this.inventoryItems = data.inventory_items || [];
                    this.characters = data.characters || [];
                    this.editorPageId = data.page_id;
                    this.revision = data.revision;
                    this.assets = data.assets;
                    this.jobs = data.jobs;
                    this.flagDefinitions = data.game_flags || [];
                    this.uiTheme = data.ui_theme || {id: 'text', renderer: 'existing_css'};
                    this.cursorTooltips = data.cursor_tooltips || {};
                    this.unavailableText = data.unavailable_text || 'You cannot do that yet.';
                    this.unavailableTimeout = data.unavailable_timeout_ms ?? 1000;
                    this.imageOrder = data.image_order || [];
                    this.imageCategories = data.image_categories || this.imageCategories;
                    this.upgradePageLinks();
                    this.credit(data);
                    this.restoreVisual();
                    if (this.generationMode) {
                        const params = new URL(window.location.href).searchParams;
                        const job = this.jobs.find((j) => String(j.id) === params.get('image'));
                        if (job) {
                            this.fillImageCard(job);
                            if (params.get('open') === '1')
                                this.$nextTick(() => this.$refs.imageFormDialog.showModal());
                        } else if (params.get('visual')) {
                            const v = [...this.document.visuals, ...this.gameObjects].find(
                                (v) => v.id === (params.get('visual') || this.selected),
                            );
                            this.generationInput = { prompt: v?.prompt || '', video_prompt: v?.video_prompt || '' };
                        }
                    }
                    this.status = '';
                } catch (e) {
                    this.error = e.message;
                    this.halted = true;
                }
                this.loading = false;
                if (!this.generationMode && new URL(window.location.href).searchParams.get('test') === '1') {
                    const url = new URL(window.location.href);
                    url.searchParams.delete('test');
                    window.history.replaceState(null, '', url);
                    this.$nextTick(() => this.preparePreview());
                }
                try {
                    this.models = selectableModels((await imageCatalog()).models, 'image');
                    if (!this.models.some((m) => m.id === this.model)) this.model = '';
                    if (this.model) {
                        const settings = { ...this.settings };
                        await this.loadEndpoints();
                        this.settings = settings;
                    }
                } catch (e) {
                    this.error =
                        'Image catalog unavailable. You can still edit existing visuals; reload to try discovery again.';
                }
                this.polling = setInterval(() => this.refresh(), 5000);
            },
            destroy() {
                window.removeEventListener('vsg-unavailable-text', this.unavailableTextListener);
                window.removeEventListener('vsg-ui-theme', this.uiThemeListener);
                window.removeEventListener('vsg-cursor-tooltips', this.cursorTooltipsListener);
                window.removeEventListener('vsg-audio-settings', this.audioSettingsListener);
                window.removeEventListener('vsg-editor-tab', this.audioTabListener);
                this.soundPlayer?.stop();
                window.removeEventListener('vsg-audio', this.audioListener);
                clearInterval(this.renderClockTimer);
                clearInterval(this.extractionTimer);
                clearTimeout(this.containerTimer);
                this.cancelInventoryUse();
                window.removeEventListener('vsg-flags', this.flagsListener);
                window.removeEventListener('vsg-inventory', this.inventoryListener);
                clearTimeout(this.pickupTimer);
                this.clearSceneTransition();
                clearTimeout(this.timer);
                clearInterval(this.polling);
                window.removeEventListener('beforeunload', this.unload);
                delete window.vsgSceneEditor;
            },
            async request(url, method = 'GET', body = null) {
                const response = await fetch(url, {
                    method,
                    headers: {
                        Accept: 'application/json',
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                    },
                    ...(body ? { body: JSON.stringify(body) } : {}),
                });
                const data = await response.json();
                if (response.status === 429) {
                    const seconds = Number(response.headers.get('Retry-After'));
                    throw new Error(
                        seconds > 0
                            ? `Too many requests. Try again in ${Math.ceil(seconds)} seconds.`
                            : 'Too many requests. Please wait a minute and try again.',
                    );
                }
                if (!response.ok) {
                    const e = new Error(
                        Object.values(data.errors ?? {})
                            .flat()
                            .join(' ') ||
                            data.message ||
                            'Request failed.',
                    );
                    e.conflict = !!data.errors?.revision;
                    e.validation = response.status === 422;
                    throw e;
                }
                return data;
            },

            get visual() {
                if (this.generationMode) return this.generationInput;
                return this.document.visuals.find((v) => v.id === this.selected);
            },
            get visualDetailsVisible() {
                return !!this.visual && (!this.visual.asset_id || this.editingVisual);
            },
            visualOptionLabel(v) {
                return (
                    this.document.visuals.findIndex((item) => item.id === v.id) +
                    1 +
                    '. ' +
                    (v.kind === 'scene' ? 'Scene · ' : 'Object · ') +
                    v.name
                );
            },
            moveVisual(id, direction) {
                const from = this.document.visuals.findIndex((v) => v.id === id),
                    to = from + direction;
                if (from < 0 || to < 0 || to >= this.document.visuals.length) return;
                const [visual] = this.document.visuals.splice(from, 1);
                this.document.visuals.splice(to, 0, visual);
                this.changed();
            },
            dropVisual(targetId, event, row) {
                const id = this.draggedVisualId;
                this.draggedVisualId = '';
                this.visualDropId = '';
                if (!id || id === targetId) return;
                const from = this.document.visuals.findIndex((v) => v.id === id),
                    target = this.document.visuals.findIndex((v) => v.id === targetId);
                if (from < 0 || target < 0) return;
                const rect = row.getBoundingClientRect();
                let to = target + (event.clientY >= rect.top + rect.height / 2 ? 1 : 0);
                if (from < to) to--;
                if (to === from) return;
                const [visual] = this.document.visuals.splice(from, 1);
                this.document.visuals.splice(to, 0, visual);
                this.changed();
            },
            selectVisualOption(value) {
                if (value === 'new:scene' || value === 'new:object') {
                    this.requestVisualName(value.slice(4));
                } else this.choose(value);
            },
            get hotspot() {
                return this.visual?.hotspots.find((h) => h.id === this.hotspotId);
            },
            get generationVisual() {
                if (!this.preview && ['inspect', 'pickup'].includes(this.hotspot?.action))
                    return this.gameObjects.find((v) => v.id === this.hotspot.object_id);
                return this.visual;
            },
            async openGeneration(base, forInteraction = true) {
                try {
                    const target = forInteraction ? this.generationVisual : this.visual;
                    if (!target) {
                        if (window.vsgAutosave && !(await window.vsgAutosave.flush())) return;
                        if (!(await this.flush())) return;
                        window.location.assign(base);
                        return;
                    }
                    const id = target.id;
                    if (window.vsgAutosave && !(await window.vsgAutosave.flush())) return;
                    if (!(await this.flush())) return;
                    window.location.assign(base + '?visual=' + encodeURIComponent(id));
                } catch (e) {
                    this.error = e.message;
                }
            },

            get endpoint() {
                if (!this.endpoints.length) return null;
                const supported_parameters = {};
                for (const key of ['quality', 'resolution', 'aspect_ratio', 'background']) {
                    const caps = this.endpoints.map((e) => e.supported_parameters?.[key]);
                    if (caps.every((c) => c?.type === 'enum')) {
                        const values = caps[0].values.filter((v) => caps.every((c) => c.values.includes(v)));
                        if (values.length) supported_parameters[key] = { type: 'enum', values };
                    }
                }
                return {
                    supported_parameters,
                    pricing: [
                        ...new Map(
                            this.endpoints.flatMap((e) => e.pricing || []).map((l) => [JSON.stringify(l), l]),
                        ).values(),
                    ],
                };
            },

            async uploadReferences(input) {
                const files = [...input.files];
                if (!files.length || this.uploading) return;
                if (files.length > 8 || files.some((f) => f.size > 10 * 1024 * 1024)) {
                    this.error = 'Choose up to 8 images, no larger than 10 MB each.';
                    input.value = '';
                    return;
                }
                this.uploading = true;
                this.uploadStatus = 'Uploading references…';
                try {
                    const body = new FormData();
                    body.append('role', 'reference');
                    files.forEach((f) => body.append('images[]', f));
                    const response = await fetch(options.upload, {
                        method: 'POST',
                        headers: {
                            Accept: 'application/json',
                            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                        },
                        body,
                    });
                    const data = await response.json();
                    if (!response.ok)
                        throw new Error(
                            Object.values(data.errors ?? {})
                                .flat()
                                .join(' ') ||
                                data.message ||
                                'Upload failed.',
                        );
                    this.assets = [...data.assets, ...this.assets];
                    this.references = [...new Set([...this.references, ...data.assets.map((a) => String(a.id))])];
                    window.dispatchEvent(new CustomEvent('vsg-assets-uploaded', { detail: data.assets }));
                    this.uploadStatus = `${data.assets.length} reference image(s) uploaded and selected. Saved in References.`;
                } catch (e) {
                    this.uploadStatus = '';
                    this.error = e.message || 'Upload failed. Please try again.';
                } finally {
                    this.uploading = false;
                    input.value = '';
                }
            },
            paletteBusy: false,

            async autoDialogColors() {
                const visual = this.visual,
                    asset = this.asset(visual?.asset_id);
                if (!asset || this.paletteBusy) return;
                this.paletteBusy = true;
                try {
                    const img = new Image();
                    img.src = asset.url;
                    await img.decode();
                    const canvas = document.createElement('canvas');
                    canvas.width = 32;
                    canvas.height = 32;
                    const ctx = canvas.getContext('2d');
                    ctx.drawImage(img, 0, 0, 32, 32);
                    const p = scenePalette(ctx.getImageData(0, 0, 32, 32).data);
                    if (this.document.visuals.includes(visual)) {
                        visual.dialog_background = p.background;
                        visual.dialog_border = p.border;
                        visual.dialog_footer = p.footer;
                        this.changed();
                    }
                } catch (_) {
                    notify('Unable to read scene colors. Choose colors manually.', 'error');
                } finally {
                    this.paletteBusy = false;
                }
            },
            placedObjects: {},

            configurePlacement() {
                const h = this.hotspot;
                if (!h) return;
                h.inventory_rules = this.placementRules(h);
                this.changed();
            },
            armedPickup: null,

            visualAsset(id) {
                return this.asset([...this.document.visuals, ...this.gameObjects].find((v) => v.id === id)?.asset_id);
            },
            editorAreaAction(h) {
                return ['pickup', 'pickup_v2'].includes(h.action)
                    ? 'pickup_v2'
                    : ['state_v2', 'inventory'].includes(h.action)
                      ? h.action
                      : 'examine';
            },

            changeAreaAction(action) {
                if (!this.hotspot) return;
                this.hotspot.action = action;
                if (action !== 'examine') {
                    this.hotspot.restart_game = false;
                    this.hotspot.move_after_examine = false;
                    this.hotspot.target_page_id = null;
                    this.hotspot.target_scene_id = null;
                }
                if (!['pickup', 'inspect', 'transition'].includes(action)) this.hotspot.target_id = null;
                this.armedPickup = null;
                this.changed();
            },
            editingStateFlag: null,
            stateFlagName: '',
            stateFlagKey: '',
            stateFlagError: '',
            renamingStateFlag: false,
            editStateFlag(area) {
                this.editingStateFlag = area.state_flag;
                this.stateFlagKey = area.state_flag;
                this.stateFlagName = this.flagLabel(area.state_flag);
                this.stateFlagError = '';
            },
            async renameStateFlag() {
                if (this.renamingStateFlag) return;
                this.renamingStateFlag = true;
                this.stateFlagError = '';
                try {
                    if (!(await this.flush()))
                        throw new Error(this.error || 'Save the scene before renaming its flag.');
                    if (window.vsgFlags && !(await window.vsgFlags.flush()))
                        throw new Error('Save Story bible flags before renaming.');
                    const current = await this.request(options.flagsUrl);
                    const d = await this.request(
                        options.flagsUrl + '/' + encodeURIComponent(this.editingStateFlag),
                        'PUT',
                        { revision: current.revision, key: this.stateFlagKey.trim(), label: this.stateFlagName.trim() },
                    );
                    const page = d.pages.find((p) => p.id === this.editorPageId);
                    if (page) {
                        this.document = page.document;
                        this.revision = page.revision;
                    }
                    this.previewPages = this.previewPages.map((p) => ({
                        ...p,
                        visuals: d.pages.find((saved) => saved.id === p.id)?.document?.visuals || p.visuals,
                    }));
                    this.flagDefinitions = d.flags;
                    this.inventoryItems = d.inventory_items;
                    if (window.vsgFlags) {
                        window.vsgFlags.flags = d.flags;
                        window.vsgFlags.revision = d.revision;
                        window.vsgFlags.dirty = false;
                    }
                    window.dispatchEvent(new CustomEvent('vsg-flags', { detail: d.flags }));
                    window.dispatchEvent(new CustomEvent('vsg-inventory', { detail: d.inventory_items }));
                    this.editingStateFlag = null;
                    notify('Flag renamed and references updated.');
                } catch (e) {
                    this.stateFlagError = e.message;
                } finally {
                    this.renamingStateFlag = false;
                }
            },
            flagContextLabel(key) {
                const locations = [];
                const pages = this.previewPages.length
                    ? this.previewPages
                    : [{ id: this.editorPageId, position: 1, title: 'Current page' }];
                for (const page of pages)
                    for (const [index, scene] of (page.id === this.editorPageId
                        ? this.document.visuals
                        : page.visuals || []
                    )
                        .filter((v) => v.kind === 'scene')
                        .entries())
                        for (const area of scene.hotspots || []) {
                            if (area.state_flag === key || area.collectible_flag === key)
                                locations.push('Page ' + page.position + ' / Scene ' + (index + 1));
                        }
                return this.flagLabel(key) + (locations.length ? ' — ' + [...new Set(locations)].join('; ') : '');
            },
            flagLabel(key) {
                return this.flagDefinitions.find((f) => f.key === key)?.label || key;
            },
            availabilityEnabled(area) {
                return area?.availability_enabled ?? !!(area?.flag_conditions?.length || area?.required_items?.length);
            },

            addFlagRule(target, field) {
                target[field] ??= [];
                const flag = this.flagDefinitions.find((f) => !target[field].some((c) => c.key === f.key));
                if (flag) {
                    target[field].push({ key: flag.key, value: true });
                    this.changed();
                } else notify('Add a story flag in Story bible first.', 'info');
            },
            removeFlagRule(target, field, index) {
                target[field].splice(index, 1);
                this.changed();
            },

            moveAreaLayer(id, direction) {
                if (this.preview || !this.visual) return;
                const areas = this.visual.hotspots,
                    index = areas.findIndex((h) => h.id === id),
                    target = index + direction;
                if (index < 0 || target < 0 || target >= areas.length) return;
                const [area] = areas.splice(index, 1);
                areas.splice(target, 0, area);
                this.hotspotId = id;
                this.changed();
            },

            switchingPage: false,
            get sceneNavigationPages() {
                return this.previewPages.map((page) => ({
                    ...page,
                    scenes: (page.id === this.editorPageId ? this.document.visuals : page.visuals || []).filter(
                        (v) => v.kind === 'scene',
                    ),
                }));
            },
            get currentSceneLabel() {
                const page = this.previewPages.find((p) => p.id === this.editorPageId);
                return (
                    (page ? this.pageImageLabel(page) + ' — ' : '') +
                    (this.visual
                        ? this.scenes.findIndex((scene) => scene.id === this.selected) + 1 + '. ' + this.visual.name
                        : 'Select a scene')
                );
            },
            toggleVisualMenu() {
                this.visualMenuOpen = !this.visualMenuOpen;
                if (this.visualMenuOpen)
                    this.$nextTick(() => {
                        const list = this.$refs.visualMenuList;
                        const group = Array.from(list?.querySelectorAll('[data-page-id]') || []).find(
                            (el) => Number(el.dataset.pageId) === Number(this.editorPageId),
                        );
                        if (!group) return;
                        list.scrollTop += group.getBoundingClientRect().top - list.getBoundingClientRect().top - 4;
                        const selected = group.querySelector('.visual-select-current');
                        if (selected && selected.getBoundingClientRect().bottom > list.getBoundingClientRect().bottom)
                            list.scrollTop +=
                                selected.getBoundingClientRect().bottom - list.getBoundingClientRect().bottom + 4;
                    });
            },
            selectNavigationScene(pageId, sceneId) {
                if (pageId !== this.editorPageId) return this.navigateInteractionPage(pageId, sceneId);
                this.choose(sceneId);
                this.visualMenuOpen = false;
                this.$refs.visualMenuButton.focus();
            },
            async navigateInteractionPage(id, sceneId = null) {
                const page = this.previewPages.find((p) => Number(p.id) === Number(id));
                if (!page || this.switchingPage) return;
                this.switchingPage = true;
                try {
                    if (window.vsgAutosave && !(await window.vsgAutosave.flush())) return;
                    if (!(await this.flush())) return;
                    const visuals = page.id === this.editorPageId ? this.document.visuals : page.visuals;
                    const firstScene =
                        (visuals || []).find((v) => v.kind === 'scene' && v.id === sceneId) ||
                        (visuals || []).find((v) => v.kind === 'scene');
                    const url = new URL(window.location.href);
                    url.searchParams.set('page', page.id);
                    url.searchParams.set('tab', 'visuals');
                    url.searchParams.delete('test');
                    url.searchParams.delete('visual');
                    if (firstScene) url.searchParams.set('visual', firstScene.id);
                    window.location.assign(url.href);
                } finally {
                    this.switchingPage = false;
                }
            },
            restoreVisual() {
                const requested = new URL(window.location.href).searchParams.get('visual');
                this.selected = this.document.visuals.some((v) => v.id === requested)
                    ? requested
                    : this.generationMode && requested
                      ? ''
                      : this.document.visuals[0]?.id || '';
                if (!this.generationMode) this.syncVisualUrl();
            },
            syncVisualUrl() {
                if (typeof window === 'undefined' || !window.history?.replaceState || !window.location?.href) return;
                const url = new URL(window.location.href);
                if (this.selected) url.searchParams.set('visual', this.selected);
                else url.searchParams.delete('visual');
                window.history.replaceState(window.history.state, '', url);
            },
            choose(id) {
                this.cancelDraw();
                this.editingVisual = false;
                this.selected = id;
                this.hotspotId = '';
                this.preview = false;
                this.syncVisualUrl();
            },
            add(kind, name = null) {
                if (this.document.visuals.length >= 100) return;
                const v = {
                    id: crypto.randomUUID(),
                    kind,
                    name:
                        name ||
                        (kind === 'scene' ? 'Scene state ' : 'Object image ') +
                            (this.document.visuals.filter((v) => v.kind === kind).length + 1),
                    asset_id: null,
                    prompt: kind === 'scene' ? options.prompt || '' : '',
                    hotspots: [],
                };
                this.document.visuals.push(v);
                this.choose(v.id);
                this.changed();
            },
            selectGenerationVisual() {
                if (this.selected.startsWith('new:')) this.newGeneration(this.selected.slice(4));
                else {
                    this.choose(this.selected);
                    this.references = [];
                }
            },
            newGeneration(kind) {
                this.add(kind);
                this.references = [];
                this.$nextTick(() =>
                    this.$refs.generationForm?.scrollIntoView({ behavior: 'smooth', block: 'nearest' }),
                );
            },
            generationSnapshot() {
                return {
                    name: this.generationName,
                    prompt: this.generationInput.prompt,
                    video_prompt: this.generationInput.video_prompt,
                    model: this.model,
                    references: this.references.map(Number),
                    settings: Object.fromEntries(Object.entries(this.settings).filter(([, v]) => v)),
                };
            },
            fillImageCard(job) {
                this.selectedImageId = job.id;
                this.generationName =
                    (job.asset_ids?.length ? this.asset(job.asset_ids[0])?.name : null) || job.name || '';
                if (this.generationName === 'Untitled image') this.generationName = '';
                this.generationName = this.generationName.slice(0, 160);
                this.generationInput = { prompt: job.prompt || '', video_prompt: job.video_prompt || '' };
                this.model = job.model || this.model;
                this.endpoints = this.models.find((m) => m.id === this.model)?.imageEndpoints || [];
                this.settings = { ...(job.settings || {}) };
                this.references = (job.references || []).map(String);
                this.requestKey = crypto.randomUUID();
                const url = new URL(window.location.href);
                url.searchParams.set('image', job.id);
                url.searchParams.delete('visual');
                window.history.replaceState(null, '', url);
                this.$nextTick(() => window.dispatchEvent(new Event('textarea-refresh')));
            },
            referenceCategoryFilter: '',
            get selectedComposition() {
                return this.jobs.find((j) => j.id === this.selectedImageId)?.composition;
            },
            assetComposition(id) {
                return this.jobs.find((job) => job.asset_ids?.includes(id))?.composition;
            },
            compositionText(composition) {
                return (
                    composition?.description ||
                    (composition
                        ? 'Combined: ' + composition.sources.map((source) => source.name).join(' + ') + '.'
                        : '')
                );
            },
            async openImageForm(job = null) {
                if (job) {
                    if (!(await this.flush())) return;
                    this.fillImageCard(job);
                } else {
                    if (this.generating || !(await this.flush())) return;
                    await this.createNewImage();
                }
                this.$refs.imageFormDialog.showModal();
            },
            sceneCaptureBusy: false,
            sceneCapture: null,
            sceneCaptureName: '',
            sceneCaptureError: '',
            async captureSceneImage() {
                if (this.sceneCaptureBusy || !this.visual?.asset_id) return;
                this.sceneCaptureBusy = true;
                this.sceneCaptureError = '';
                try {
                    if (!(await this.flush())) return;
                    const payload = {
                        scene_id: this.visual.id,
                        revision: this.revision,
                        area_ids: this.visual.hotspots.filter((h) => this.editorAreaImage(h)).map((h) => h.id),
                    };
                    const result = await this.request(options.stateImagesUrl, 'POST', { ...payload, preview: true });
                    this.sceneCapture = { ...result, payload };
                    this.sceneCaptureName = result.name;
                    this.$refs.sceneCaptureDialog.showModal();
                } catch (error) {
                    this.error = error.message;
                } finally {
                    this.sceneCaptureBusy = false;
                }
            },
            async saveSceneCapture() {
                if (this.sceneCaptureBusy || !this.sceneCapture || !this.sceneCaptureName.trim()) return;
                this.sceneCaptureBusy = true;
                this.sceneCaptureError = '';
                try {
                    await this.request(options.stateImagesUrl, 'POST', {
                        ...this.sceneCapture.payload,
                        name: this.sceneCaptureName.trim(),
                    });
                    const data = await this.request(options.url);
                    this.assets = data.assets;
                    this.jobs = data.jobs;
                    this.$refs.sceneCaptureDialog.close();
                    this.sceneCapture = null;
                    this.status = 'Scene image saved to Images.';
                } catch (error) {
                    this.sceneCaptureError = error.message;
                } finally {
                    this.sceneCaptureBusy = false;
                }
            },
            async selectImageCard(job) {
                if (this.generating || this.uploading || job.localFailure || job.status === 'submitting') return;
                if (this.selectedImageId === job.id) {
                    await this.createNewImage();
                    return;
                }
                if (!(await this.flush())) return;
                this.fillImageCard(job);
            },
            async deleteImageDraft() {
                if (this.deletingImage || this.generating) return;
                const id = this.deleteImageId;
                if (!this.jobs.some((j) => j.id === id && j.status === 'draft')) return;
                this.deletingImage = true;
                try {
                    if (!(await this.flush())) return;
                    await this.request(options.deleteDraftBase + '/' + id, 'DELETE');
                    this.jobs = this.jobs.filter((j) => j.id !== id);
                    this.imageOrder = this.imageOrder.filter((value) => value !== id);
                    delete this.expandedPrompts[id];
                    if (this.selectedImageId === id) {
                        this.dirty = false;
                        await this.createNewImage();
                    }
                    this.$refs.deleteImageDialog.close();
                    this.deleteImageId = null;
                    notify('Image draft deleted.');
                } catch (e) {
                    this.error = e.message;
                } finally {
                    this.deletingImage = false;
                }
            },
            async createNewImage() {
                if (this.generating || !(await this.flush())) return;
                this.selectedImageId = null;
                this.generationName = '';
                this.generationInput = { prompt: '', video_prompt: '' };
                this.model = '';
                this.endpoints = [];
                this.settings = {};
                this.references = [];
                this.requestKey = crypto.randomUUID();
                const url = new URL(window.location.href);
                url.searchParams.delete('image');
                url.searchParams.delete('visual');
                window.history.replaceState(null, '', url);
                this.$nextTick(() => window.dispatchEvent(new Event('textarea-refresh')));
            },
            async saveGenerationDraft() {
                if (!this.generationInput.prompt.trim()) {
                    this.dirty = false;
                    return true;
                }
                const selected = this.jobs.find((j) => j.id === this.selectedImageId),
                    snapshot = this.generationSnapshot();
                const data = await this.request(options.draftUrl, 'POST', {
                    ...snapshot,
                    draft_id: selected?.status === 'draft' ? selected.id : undefined,
                });
                const job = { ...snapshot, id: data.id, status: 'draft', asset_ids: [] };
                this.jobs = [job, ...this.jobs.filter((j) => j.id !== data.id)];
                this.selectedImageId = data.id;
                const url = new URL(window.location.href);
                url.searchParams.set('image', data.id);
                url.searchParams.delete('visual');
                window.history.replaceState(null, '', url);
                return true;
            },
            get historyJobs() {
                return this.jobs;
            },

            unusedImageIds: [],
            flaggingUnusedImages: false,
            async flagUnusedImages() {
                if (this.flaggingUnusedImages) return;
                this.flaggingUnusedImages = true;
                try {
                    const data = await this.request(options.url);
                    const used = new Set(),
                        items = new Map((data.inventory_items || []).map((i) => [i.id, i])),
                        visited = new Set();
                    const visit = (value) => {
                        if (!value || typeof value !== 'object') return;
                        for (const [key, v] of Object.entries(value)) {
                            if ((key === 'asset_id' || key.endsWith('_asset_id')) && v) used.add(Number(v));
                            if ((key === 'object_id' || key === 'target_id') && items.has(v) && !visited.has(v)) {
                                visited.add(v);
                                visit(items.get(v));
                            }
                            if (v && typeof v === 'object') visit(v);
                        }
                    };
                    for (const page of data.preview_pages || [])
                        for (const visual of page.visuals || []) if (visual.kind === 'scene') visit(visual);
                    this.unusedImageIds = (data.assets || [])
                        .filter((a) => a.role !== 'extraction_reference' && !used.has(Number(a.id)))
                        .map((a) => Number(a.id));
                } catch (e) {
                    this.error = e.message;
                } finally {
                    this.flaggingUnusedImages = false;
                }
            },
            unusedImage(id) {
                return this.unusedImageIds.includes(Number(id));
            },
            get visibleJobs() {
                const rank = new Map(this.imageOrder.map((id, i) => [id, i]));
                return this.historyJobs
                    .filter(
                        (job) =>
                            (!this.imageCategoryFilter || (job.category || 'scene') === this.imageCategoryFilter) &&
                            (this.showFailed || job.status !== 'failed') &&
                            (!job.asset_ids?.length || this.jobAssets(job).length),
                    )
                    .sort((a, b) => (rank.get(a.id) ?? -1) - (rank.get(b.id) ?? -1));
            },
            draggedImageId: null,
            imageDropId: null,
            imageDropAfter: false,
            startImageDrag(job, event) {
                if (this.orderingImages || !Number.isInteger(job.id)) {
                    event.preventDefault();
                    return;
                }
                this.draggedImageId = job.id;
                event.dataTransfer.effectAllowed = 'move';
                event.dataTransfer.setData('text/plain', String(job.id));
            },
            endImageDrag() {
                this.draggedImageId = null;
                this.imageDropId = null;
                this.imageDropAfter = false;
            },
            overImage(job, event, card) {
                if (this.draggedImageId === null || this.orderingImages) return;
                event.preventDefault();
                event.dataTransfer.dropEffect = 'move';
                this.imageDropId = job.id;
                const bounds = card.getBoundingClientRect();
                this.imageDropAfter = event.clientY > bounds.top + bounds.height / 2;
                const list = this.$refs.imageResults,
                    viewport = list.getBoundingClientRect();
                if (event.clientY < viewport.top + 60) list.scrollTop -= 24;
                else if (event.clientY > viewport.bottom - 60) list.scrollTop += 24;
            },
            async dropImage(targetId) {
                const id = this.draggedImageId,
                    after = this.imageDropAfter;
                this.endImageDrag();
                if (this.orderingImages || id === null || id === targetId) return;
                const visible = this.visibleJobs.map((job) => job.id).filter(Number.isInteger);
                if (!visible.includes(id) || !visible.includes(targetId)) return;
                const reordered = visible.filter((value) => value !== id);
                reordered.splice(reordered.indexOf(targetId) + (after ? 1 : 0), 0, id);
                // Filtered-out cards keep their positions in the full library order.
                const rank = new Map(this.imageOrder.map((value, index) => [value, index]));
                let index = 0;
                const order = [...this.historyJobs]
                    .filter((job) => Number.isInteger(job.id))
                    .sort((a, b) => (rank.get(a.id) ?? -1) - (rank.get(b.id) ?? -1))
                    .map((job) => (visible.includes(job.id) ? reordered[index++] : job.id));
                await this.saveImageOrder(order, id);
            },
            async saveImageOrder(order, id) {
                this.orderingImages = true;
                this.movedImageId = null;
                try {
                    const data = await this.request(options.orderUrl, 'PUT', { order });
                    this.imageOrder = data.order;
                    this.movedImageId = id;
                } catch (error) {
                    this.error = error.message;
                } finally {
                    this.orderingImages = false;
                }
            },
            async moveImageOrder(id, direction) {
                if (this.orderingImages) return;
                const visible = this.visibleJobs.map((j) => j.id),
                    index = visible.indexOf(id),
                    target = index + direction;
                if (index < 0 || target < 0 || target >= visible.length) return;
                const order = [...this.visibleJobs, ...this.historyJobs.filter((j) => !visible.includes(j.id))]
                    .map((j) => j.id)
                    .filter((id) => Number.isInteger(id));
                const a = order.indexOf(id),
                    b = order.indexOf(visible[target]);
                if (a < 0 || b < 0) return;
                [order[a], order[b]] = [order[b], order[a]];
                await this.saveImageOrder(order, id);
            },
            get failedCount() {
                return this.jobs.filter((job) => job.status === 'failed').length;
            },
            jobAssets(job) {
                return (job.asset_ids || []).filter((id) => this.showArchived || !this.asset(id)?.archived);
            },
            get archivedCount() {
                return this.assets.filter(
                    (a) => a.archived && this.historyJobs.some((j) => j.asset_ids?.includes(a.id)),
                ).length;
            },
            jobTitle(job) {
                return (
                    (job.asset_ids || [])
                        .map((id) => this.asset(id)?.name)
                        .filter(Boolean)
                        .join(', ') || job.name
                );
            },
            promptShort(job) {
                const words = (job.prompt || '').trim().split(/\s+/);
                return words.slice(0, 20).join(' ') + (words.length > 20 ? '…' : '');
            },
            promptLong(job) {
                return (job.prompt || '').trim().split(/\s+/).length > 20;
            },
            async updateAsset(id, changes) {
                const asset = this.asset(id);
                if (!asset || this.assetSaving[id]) return;
                this.assetSaving = { ...this.assetSaving, [id]: true };
                try {
                    const data = await this.request(asset.update_url || options.assetBase + '/' + id, 'PATCH', changes);
                    Object.assign(asset, data);
                    delete this.assetNames[id];
                    notify(
                        'archived' in changes
                            ? changes.archived
                                ? 'Image archived.'
                                : 'Image restored.'
                            : 'Image name saved.',
                    );
                } catch (e) {
                    this.error = e.message;
                } finally {
                    this.assetSaving = { ...this.assetSaving, [id]: false };
                }
            },
            renameAsset(id) {
                const name = (this.assetNames[id] ?? this.asset(id)?.name ?? '').trim();
                if (!name) {
                    this.error = 'Enter an image name.';
                    return;
                }
                if (name !== this.asset(id)?.name) return this.updateAsset(id, { name });
            },
            async copyPrompt(job) {
                try {
                    await navigator.clipboard.writeText(job.prompt || '');
                    this.copiedPrompt = job.id;
                    notify('Prompt copied.');
                } catch (_) {
                    this.error = 'Could not copy the prompt. Select the prompt text and copy it manually.';
                }
            },
            duplicate(name = null) {
                if (!this.visual || this.document.visuals.length >= 100) return;
                const v = JSON.parse(JSON.stringify(this.visual));
                v.id = crypto.randomUUID();
                v.name = name || v.name + ' copy';
                const ids = new Map(v.hotspots.map((h) => [h.id, crypto.randomUUID()]));
                v.hotspots.forEach((h) => {
                    h.id = ids.get(h.id);
                    if (h.container_parent_id) h.container_parent_id = ids.get(h.container_parent_id);
                    delete h.container_plan_id;
                });
                (v.actions || []).forEach((a) => (a.id = crypto.randomUUID()));
                this.document.visuals.push(v);
                this.choose(v.id);
                this.changed();
            },
            applyInteractionProposal(proposal, pageId) {
                const target = proposal._interaction,
                    change = proposal.interaction;
                if (
                    target.page_id !== pageId ||
                    target.revision !== this.revision ||
                    target.visual_id !== this.selected ||
                    target.hotspot_id !== this.hotspotId ||
                    this.dirty ||
                    this.saving ||
                    !this.hotspot
                )
                    throw new Error('Select the original area and request a fresh suggestion; this area has changed.');
                if (
                    !change ||
                    (change.description !== undefined &&
                        (typeof change.description !== 'string' || change.description.length > 3000))
                )
                    throw new Error('Invalid area description.');
                if (change.action && !['examine', 'inspect', 'pickup'].includes(change.action))
                    throw new Error('Invalid interaction action.');
                let object = this.objects.find((v) => v.id === this.hotspot.object_id);
                if (change.object) {
                    if (
                        typeof change.object.name !== 'string' ||
                        !change.object.name.trim() ||
                        change.object.name.length > 160 ||
                        typeof change.object.prompt !== 'string' ||
                        !change.object.prompt.trim() ||
                        change.object.prompt.length > 10000
                    )
                        throw new Error('Invalid object image prompt.');
                    if (!object && this.document.visuals.length >= 100)
                        throw new Error('This page has reached its 100-visual limit.');
                    if (!object) {
                        object = {
                            id: crypto.randomUUID(),
                            kind: 'object',
                            name: '',
                            prompt: '',
                            asset_id: null,
                            hotspots: [],
                        };
                        this.document.visuals.push(object);
                    }
                    object.name = change.object.name;
                    object.prompt = change.object.prompt;
                    this.hotspot.object_id = object.id;
                }
                if (change.description !== undefined) this.hotspot.description = change.description;
                if (change.action) this.hotspot.action = change.action;
                this.changed();
                return change.object ? object.id : null;
            },
            requestVisualName(action) {
                if (this.document.visuals.length >= 100) {
                    this.error = 'This page has reached its 100-visual limit.';
                    return;
                }
                this.visualAction = action;
                this.visualSourceId = this.selected;
                this.visualName = action === 'duplicate' ? (this.visual?.name || 'Visual') + ' copy' : '';
                this.$refs.visualNameDialog.showModal();
                this.$nextTick(() => {
                    this.$refs.visualNameInput.focus();
                    this.$refs.visualNameInput.select();
                });
            },
            createNamedVisual() {
                const name = this.visualName.trim();
                if (!name || name.length > 160) {
                    notify('Enter a name between 1 and 160 characters.', 'error');
                    return;
                }
                if (this.visualAction === 'duplicate') {
                    this.choose(this.visualSourceId);
                    this.duplicate(name);
                } else this.add(this.visualAction, name);
                this.$refs.visualNameDialog.close();
            },
            requestVisualType(kind) {
                const visual = this.visual;
                if (!visual || !['scene', 'object'].includes(kind) || kind === visual.kind) return;
                const areas = kind === 'object' ? visual.hotspots.length : 0;
                const actions = kind === 'scene' ? (visual.actions || []).length : 0;
                const actionLinks =
                    kind === 'scene'
                        ? this.document.visuals.flatMap((v) => v.actions || []).filter((a) => a.target_id === visual.id)
                              .length
                        : 0;
                const ruleLinks = this.document.visuals
                    .flatMap((v) => v.hotspots || [])
                    .flatMap((h) => [...(h.inventory_rules || []), ...(h.choices || [])])
                    .filter((r) => (kind === 'object' ? r.target_id === visual.id : r.object_id === visual.id)).length;
                const links =
                    ruleLinks +
                    this.document.visuals
                        .flatMap((v) => v.hotspots || [])
                        .filter((h) => (kind === 'object' ? h.target_id === visual.id : h.object_id === visual.id))
                        .length;
                this.typeChange = {
                    id: visual.id,
                    kind,
                    message: `This will remove ${areas} clickable area(s) from this visual and remove ${actions} object action(s) and clear ${links + actionLinks} incompatible interaction link(s). Affected actions will become Examine.`,
                };
                if (areas || links || actions || actionLinks) this.$refs.visualTypeDialog.showModal();
                else this.confirmVisualType();
            },
            confirmVisualType() {
                const change = this.typeChange;
                if (!change) return;
                const visual = this.document.visuals.find((v) => v.id === change.id);
                if (!visual) return;
                if (change.kind === 'object') visual.hotspots = [];
                else {
                    visual.actions = [];
                    for (const v of this.document.visuals)
                        for (const a of v.actions || []) if (a.target_id === visual.id) a.target_id = null;
                }
                for (const v of this.document.visuals)
                    for (const h of v.hotspots || []) {
                        if (change.kind === 'object') {
                            for (const rule of [...(h.inventory_rules || []), ...(h.choices || [])])
                                if (rule.target_id === visual.id) rule.target_id = null;
                        } else if (h.inventory_rules) {
                            h.inventory_rules = h.inventory_rules.filter((rule) => rule.object_id !== visual.id);
                        }
                        if (change.kind === 'object' && h.target_id === visual.id) {
                            h.target_id = null;
                            if (h.action === 'transition') h.action = 'examine';
                        }
                        if (change.kind === 'scene' && h.object_id === visual.id) {
                            h.object_id = null;
                            if (!h.overlay_asset_id) h.overlay = false;
                            if (['inspect', 'pickup'].includes(h.action)) h.action = 'examine';
                        }
                    }
                if (change.kind === 'object') visual.skip_rules = [];
                this.clearSkipReferences(visual.id);
                visual.kind = change.kind;
                this.hotspotId = '';
                this.renamingId = '';
                this.areaDrag = null;
                this.preview = false;
                this.cancelDraw();
                this.typeChange = null;
                this.changed();
                this.$refs.visualTypeDialog?.close();
            },
            requestVisualDelete() {
                if (!this.visual) return;
                this.deleteVisualId = this.visual.id;
                this.$refs.deleteVisualDialog.showModal();
            },
            get deletingVisual() {
                return this.document.visuals.find((v) => v.id === this.deleteVisualId);
            },
            get dependentAreas() {
                return this.document.visuals
                    .filter((v) => v.id !== this.deleteVisualId)
                    .flatMap((v) => [
                        ...(v.hotspots || []),
                        ...(v.actions || []),
                        ...(v.hotspots || []).flatMap((h) => [...(h.inventory_rules || []), ...(h.choices || [])]),
                    ])
                    .filter(
                        (h) =>
                            h.target_scene_id === this.deleteVisualId ||
                            h.target_id === this.deleteVisualId ||
                            h.object_id === this.deleteVisualId,
                    ).length;
            },
            clearSkipReferences(id) {
                for (const v of this.document.visuals)
                    v.skip_rules = (v.skip_rules || []).filter(
                        (r) => r.target_scene_id !== id && !(r.object_ids || []).includes(id),
                    );
            },
            confirmVisualDelete() {
                const id = this.deleteVisualId;
                if (!this.deletingVisual) return;
                this.clearSkipReferences(id);
                this.document.visuals = this.document.visuals.filter((v) => v.id !== id);
                for (const v of this.document.visuals)
                    for (const a of v.actions || []) if (a.target_id === id) a.target_id = null;
                for (const v of this.document.visuals)
                    for (const h of v.hotspots) {
                        if (h.inventory_rules)
                            h.inventory_rules = h.inventory_rules.filter((rule) => rule.object_id !== id);
                        for (const rule of [...(h.inventory_rules || []), ...(h.choices || [])])
                            if (rule.target_id === id) rule.target_id = null;
                        if (h.target_scene_id === id) {
                            h.target_scene_id = null;
                            h.target_page_id = null;
                            h.move_after_examine = false;
                            if (h.action === 'page') h.action = 'examine';
                        }
                        if (h.target_id === id) {
                            h.target_id = null;
                            if (h.action === 'transition') h.action = 'examine';
                        }
                        if (h.object_id === id) {
                            h.object_id = null;
                            if (!h.overlay_asset_id) h.overlay = false;
                            if (['inspect', 'pickup'].includes(h.action)) h.action = 'examine';
                        }
                    }
                this.choose(this.document.visuals[0]?.id || '');
                this.drawing = false;
                this.drag = null;
                this.areaDrag = null;
                this.renamingId = '';
                this.modal = null;
                this.references = [];
                this.deleteVisualId = '';
                this.changed();
                this.$refs.deleteVisualDialog.close();
            },
            changed() {
                this.dirty = true;
                this.status = 'Unsaved visual changes';
                clearTimeout(this.timer);
                if (!this.halted) this.timer = setTimeout(() => this.flush(), 450);
            },
            async flush() {
                clearTimeout(this.timer);
                if (this.loading) return false;
                if (this.active) {
                    await this.active;
                    return !this.dirty && !this.error;
                }
                if (this.halted) return false;
                this.active = this.saveLoop();
                try {
                    return await this.active;
                } finally {
                    this.active = null;
                }
            },
            async saveLoop() {
                while (this.dirty) {
                    this.dirty = false;
                    this.saving = true;
                    this.status = 'Saving visuals…';
                    try {
                        if (this.generationMode) {
                            await this.saveGenerationDraft();
                            this.error = '';
                            this.status = 'Image draft saved';
                            continue;
                        }
                        const data = await this.request(options.url, 'PUT', {
                            revision: this.revision,
                            document: JSON.parse(JSON.stringify(this.document)),
                        });
                        this.revision = data.revision;
                        if (data.preview_pages) {
                            this.previewPages = data.preview_pages;
                            this.upgradePageLinks();
                        }
                        this.error = '';
                        this.status = 'All visual changes saved';
                    } catch (e) {
                        this.dirty = true;
                        this.error = e.message;
                        this.status = 'Visual changes not saved: ' + e.message;
                        this.halted = e.conflict || !e.validation;
                        return false;
                    } finally {
                        this.saving = false;
                    }
                }
                return true;
            },
            mergeJobs(jobs) {
                this.jobs = [...this.jobs.filter((job) => job.localFailure), ...jobs];
            },
            async refresh() {
                if (this.generating || !this.jobs.some((j) => ['queued', 'running'].includes(j.status))) return;
                try {
                    const d = await this.request(options.url);
                    for (const job of d.jobs) {
                        const previous = this.jobs.find((j) => j.id === job.id);
                        if (previous && previous.status !== job.status && ['completed', 'failed'].includes(job.status))
                            notify(
                                job.status === 'completed'
                                    ? job.name + ' image ready.'
                                    : job.error || job.name + ' generation failed.',
                                job.status === 'failed' ? 'error' : 'success',
                            );
                    }
                    this.assets = d.assets;
                    this.mergeJobs(d.jobs);
                    this.credit(d);
                } catch (_) {
                    /* A later poll can recover; no paid action is repeated. */
                }
            },
            credit(data) {
                if (typeof data.remaining === 'number')
                    window.dispatchEvent(new CustomEvent('vsg-credit', { detail: data.remaining }));
            },
            async loadEndpoints() {
                rememberModel('vsg-image-model', this.model);
                this.endpoints = [];
                this.settings = {};
                if (!this.model) return;
                const selected = this.model;
                try {
                    const endpoints = this.models.find((m) => m.id === selected)?.imageEndpoints || [];
                    if (selected === this.model) this.endpoints = endpoints;
                } catch (e) {
                    this.error = e.message;
                }
            },
            async generate() {
                if (this.generating) return;
                this.generating = true;
                this.error = '';
                let pendingId;
                try {
                    if (window.vsgAutosave && !(await window.vsgAutosave.flush())) return;
                    if (!(await this.flush()) || !this.visual) return;
                    if (!this.generationName.trim() && options.nameUrl) {
                        const writingModel = rememberedModel('vsg-writing-model', options.writingModel || '');
                        if (!writingModel)
                            throw new Error('Select a writing model in Creative companion, or enter an image name.');
                        this.namingImage = true;
                        try {
                            const named = await this.request(options.nameUrl, 'POST', {
                                prompt: this.visual.prompt,
                                model: writingModel,
                                request_key: crypto.randomUUID(),
                            });
                            this.generationName = named.name;
                            this.credit(named);
                        } finally {
                            this.namingImage = false;
                        }
                    }
                    if (this.generationMode) {
                        await this.saveGenerationDraft();
                    }
                    const draftId = this.generationMode ? this.selectedImageId : null;
                    pendingId = draftId || 'pending:' + this.requestKey;
                    const pending = {
                        id: pendingId,
                        visual_id: this.visual.id,
                        name: this.generationName.trim() || this.visual.name,
                        prompt: this.visual.prompt,
                        model: this.model,
                        status: 'submitting',
                        asset_ids: [],
                    };
                    this.jobs = [pending, ...this.jobs.filter((job) => job.id !== pendingId)];
                    this.$nextTick(() => {
                        if (this.$refs.imageResults) this.$refs.imageResults.scrollTop = 0;
                    });
                    const result = await this.request(options.generate, 'POST', {
                        name: this.generationName.trim() || undefined,
                        revision: this.revision,
                        visual_id: this.visual.id,
                        draft_id: draftId || undefined,
                        request_key: this.requestKey,
                        model: this.model,
                        references: this.references.map(Number),
                        settings: Object.fromEntries(Object.entries(this.settings).filter(([, v]) => v)),
                    });
                    this.jobs = this.jobs.map((job) =>
                        job.id === pendingId ? { ...pending, id: result.id, status: result.status } : job,
                    );
                    if (this.generationMode) this.selectedImageId = result.id;
                    else this.generationName = '';
                    this.requestKey = crypto.randomUUID();
                    this.status = 'Image queued. You can keep editing.';
                    // Keep the accepted job visible even if the follow-up status read fails.
                    try {
                        const d = await this.request(options.url);
                        this.mergeJobs(d.jobs);
                        this.assets = d.assets;
                        this.credit(d);
                    } catch (_) {}
                } catch (e) {
                    if (this.generationMode) {
                        try {
                            const d = await this.request(options.url);
                            this.mergeJobs(d.jobs);
                        } catch (_) {}
                    }
                    this.jobs = this.jobs.map((job) =>
                        job.id === pendingId && job.status !== 'draft'
                            ? {
                                  ...job,
                                  id: 'rejected:' + crypto.randomUUID(),
                                  status: 'failed',
                                  localFailure: true,
                                  error: e.message,
                              }
                            : job,
                    );
                    this.error = e.message;
                } finally {
                    this.generating = false;
                }
            },
            async retryImage(job) {
                if (this.generating || !job.retry_url || job.status !== 'failed') return;
                this.generating = true;
                this.retryingJob = job.id;
                this.error = '';
                job.retryKey ??= crypto.randomUUID();
                const pendingId = 'retry:' + job.retryKey;
                const pending = {
                    id: pendingId,
                    name: job.name,
                    prompt: job.prompt,
                    model: job.model,
                    status: 'submitting',
                    asset_ids: [],
                };
                this.jobs = [pending, ...this.jobs];
                this.$nextTick(() => {
                    if (this.$refs.imageResults) this.$refs.imageResults.scrollTop = 0;
                });
                try {
                    const result = await this.request(job.retry_url, 'POST', { request_key: job.retryKey });
                    this.jobs = this.jobs.map((item) =>
                        item.id === pendingId ? { ...pending, id: result.id, status: result.status } : item,
                    );
                    delete job.retryKey;
                    this.status = 'Image queued again with a four-minute limit.';
                    try {
                        const data = await this.request(options.url);
                        this.mergeJobs(data.jobs);
                        this.assets = data.assets;
                        this.credit(data);
                    } catch (_) {}
                } catch (e) {
                    this.jobs = this.jobs.filter((item) => item.id !== pendingId);
                    this.error = e.message;
                } finally {
                    this.generating = false;
                    this.retryingJob = null;
                }
            },
            useJobImage(job, id) {
                const target = this.document.visuals.find((v) => v.id === job.visual_id);
                if (target) {
                    target.asset_id = Number(id);
                    this.changed();
                }
            },
            openMoveImage(job) {
                this.moveJob = job;
                this.moveTarget = job.visual_id;
                this.$refs.moveImageDialog.showModal();
            },
            async moveImage() {
                if (this.movingImage || !this.moveJob) return;
                this.movingImage = true;
                try {
                    if (!(await this.flush())) return;
                    await this.request(options.moveBase + '/' + this.moveJob.id, 'PATCH', {
                        visual_id: this.moveTarget,
                        revision: this.revision,
                    });
                    const data = await this.request(options.url);
                    this.mergeJobs(data.jobs);
                    this.assets = data.assets;
                    this.$refs.moveImageDialog.close();
                    notify('Generated image moved.');
                } catch (e) {
                    this.error = e.message;
                } finally {
                    this.movingImage = false;
                }
            },
            useImage(id) {
                this.visual.asset_id = Number(id);
                this.editingVisual = false;
                this.changed();
            },
            newHotspot(rect = { x: 0.1, y: 0.1, width: 0.2, height: 0.2 }) {
                if (!this.visual || this.visual.kind !== 'scene' || this.visual.hotspots.length >= 100) return;
                const h = {
                    id: crypto.randomUUID(),
                    label: 'Clickable area',
                    description: '',
                    animate_hover_text: true,
                    ...rect,
                    action: 'examine',
                    target_id: null,
                    object_id: null,
                    overlay: false,
                };
                this.visual.hotspots.push(h);
                this.hotspotId = h.id;
                this.drawing = false;
                this.beginRename(h);
                this.changed();
            },
            requestAreaDelete(id) {
                if (this.preview) return;
                this.hotspotId = id;
                this.deleteAreaId = id;
                this.$refs.deleteAreaDialog.showModal();
            },
            confirmAreaDelete() {
                const visual = this.visual,
                    area = visual?.hotspots.find((h) => h.id === this.deleteAreaId);
                if (!area) return;
                visual.hotspots = visual.hotspots.filter((h) => h.id !== area.id && h.container_parent_id !== area.id);
                for (const h of visual.hotspots) {
                    const c = h.auto_state;
                    if (c?.placements?.some((r) => !visual.hotspots.some((a) => a.id === r.area_id))) {
                        c.placements = c.placements.filter((r) => visual.hotspots.some((a) => a.id === r.area_id));
                        c.enabled = false;
                    }
                }
                this.hotspotId = '';
                this.deleteAreaId = '';
                this.renamingId = '';
                this.areaDrag = null;
                this.changed();
                this.$refs.deleteAreaDialog.close();
            },
            beginRename(h) {
                if (this.preview) return;
                this.hotspotId = h.id;
                this.renamingId = h.id;
                this.labelDraft = h.label;
            },
            finishRename(h) {
                if (this.renamingId !== h.id) return;
                const label = this.labelDraft.trim();
                this.renamingId = '';
                if (!label) return;
                h.label = label;
                this.changed();
            },
            areaDragButtonStyle(h, width, height, buttonWidth = 28, buttonHeight = 28) {
                if (!width || !height) return { visibility: 'hidden' };
                const left = Math.max(0, Math.min(h.x * width, width - buttonWidth));
                const top = Math.max(0, Math.min((h.y + h.height) * height + 4, height - buttonHeight));
                return { left: left - h.x * width - 2 + 'px', top: top - h.y * height - 2 + 'px' };
            },
            resettingArea: false,
            async resetAreaPosition(h) {
                if (this.preview || this.resettingArea || !['pickup_v2', 'state_v2'].includes(h.action)) return;
                this.resettingArea = true;
                try {
                    let bounds = h.default_bounds,
                        overlay = h.default_overlay_bounds;
                    if (!bounds && options.extractionUrl) {
                        const data = await this.request(options.extractionUrl);
                        const assetId = h.action === 'state_v2' ? h.state_asset_id : h.overlay_asset_id;
                        const output = data.batches
                            .filter((b) => b.visual_id === this.selected)
                            .flatMap((b) => b.outputs)
                            .find(
                                (o) =>
                                    o.area_id === h.id &&
                                    ['layer', 'switch_on'].includes(o.type) &&
                                    (o.accepted_asset_id === assetId ||
                                        o.jobs?.some((j) => j.asset_ids?.includes(assetId))),
                            );
                        if (output) {
                            bounds = output.area_bounds || output.bounds;
                            overlay = output.bounds;
                        }
                    }
                    if (!bounds) {
                        notify('No saved default position is available for this area yet.', 'error');
                        return;
                    }
                    Object.assign(h, JSON.parse(JSON.stringify(bounds)));
                    if (overlay) h.overlay_bounds = { ...overlay };
                    else if (h.action === 'pickup_v2') h.image_bounds = { ...bounds };
                    this.syncContainerAreas(h);
                    h.default_bounds = { ...bounds };
                    if (overlay) h.default_overlay_bounds = { ...overlay };
                    this.changed();
                } catch (e) {
                    notify(e.message, 'error');
                } finally {
                    this.resettingArea = false;
                }
            },
            startArea(e, h, handle = 'move') {
                if (this.preview || this.drawing || this.renamingId === h.id || e.button !== 0) return;
                e.preventDefault();
                this.hotspotId = h.id;
                if (['pickup_v2', 'state_v2'].includes(h.action) && !h.default_bounds && !h.overlay_bounds)
                    h.default_bounds = { x: h.x, y: h.y, width: h.width, height: h.height };
                this.ensureAreaImageBounds(h);
                const rect = e.currentTarget.closest('[data-scene-canvas]').getBoundingClientRect();
                this.areaDrag = {
                    id: h.id,
                    handle,
                    startX: e.clientX,
                    startY: e.clientY,
                    rect,
                    original: {
                        x: h.x,
                        y: h.y,
                        width: h.width,
                        height: h.height,
                        ...(h.overlay_bounds ? { overlay_bounds: { ...h.overlay_bounds } } : {}),
                        ...(h.image_bounds ? { image_bounds: { ...h.image_bounds } } : {}),
                    },
                };
                e.currentTarget.setPointerCapture(e.pointerId);
            },
            moveArea(e) {
                const d = this.areaDrag;
                if (!d) return;
                const h = this.visual.hotspots.find((h) => h.id === d.id);
                if (!h) return;
                const dx = (e.clientX - d.startX) / d.rect.width,
                    dy = (e.clientY - d.startY) / d.rect.height,
                    o = d.original;
                const clamp = (n, min, max) => Math.max(min, Math.min(max, n));
                if (d.handle === 'move') {
                    h.x = clamp(o.x + dx, 0, 1 - o.width);
                    h.y = clamp(o.y + dy, 0, 1 - o.height);
                } else if (h.overlay && h.action !== 'pickup_v2') {
                    this.resizeAreaProportionally(h, o, d.handle, dx, dy);
                } else {
                    let left = o.x,
                        right = o.x + o.width,
                        top = o.y,
                        bottom = o.y + o.height;
                    if (d.handle.includes('w')) left = clamp(o.x + dx, 0, right - 0.005);
                    if (d.handle.includes('e')) right = clamp(right + dx, left + 0.005, 1);
                    if (d.handle.includes('n')) top = clamp(o.y + dy, 0, bottom - 0.005);
                    if (d.handle.includes('s')) bottom = clamp(bottom + dy, top + 0.005, 1);
                    Object.assign(h, { x: left, y: top, width: right - left, height: bottom - top });
                }
                this.transformAreaImage(h, o);
                this.syncContainerAreas(h);
            },
            syncContainerAreas(h) {
                if (h.is_container) {
                    for (const child of this.visual.hotspots.filter(
                        (a) => a.container_parent_id === h.id && a.container_bounds,
                    )) {
                        const b = containerGeometry(h, child.container_bounds);
                        Object.assign(child, b);
                        child.overlay_bounds = { ...b };
                    }
                } else if (h.container_parent_id) {
                    let p = this.visual.hotspots.find((a) => a.id === h.container_parent_id);
                    if (p) {
                        const base = p.overlay_bounds || p;
                        p = base;
                        h.width = Math.min(h.width, p.width);
                        h.height = Math.min(h.height, p.height);
                        h.x = Math.max(p.x, Math.min(h.x, p.x + p.width - h.width));
                        h.y = Math.max(p.y, Math.min(h.y, p.y + p.height - h.height));
                        h.container_bounds = {
                            x: (h.x - p.x) / p.width,
                            y: (h.y - p.y) / p.height,
                            width: h.width / p.width,
                            height: h.height / p.height,
                        };
                        if (h.action !== 'pickup_v2')
                            h.overlay_bounds = { x: h.x, y: h.y, width: h.width, height: h.height };
                    }
                }
            },
            resizeAreaProportionally(h, o, handle, dx, dy) {
                const west = handle.includes('w'),
                    north = handle.includes('n');
                const sx = (west ? -dx : dx) / o.width,
                    sy = (north ? -dy : dy) / o.height;
                const desired = 1 + (Math.abs(sx) >= Math.abs(sy) ? sx : sy);
                const minimum = Math.max(0.005 / o.width, 0.005 / o.height);
                const maximum = Math.min(
                    (west ? o.x + o.width : 1 - o.x) / o.width,
                    (north ? o.y + o.height : 1 - o.y) / o.height,
                );
                const scale = Math.max(minimum, Math.min(maximum, desired));
                h.width = o.width * scale;
                h.height = o.height * scale;
                h.x = west ? o.x + o.width - h.width : o.x;
                h.y = north ? o.y + o.height - h.height : o.y;
            },
            ensureAreaImageBounds(h) {
                if (h.action === 'pickup_v2' && !h.overlay_bounds && !h.image_bounds)
                    h.image_bounds = { x: h.x, y: h.y, width: h.width, height: h.height };
            },
            setAreaImageSize(h, value) {
                this.ensureAreaImageBounds(h);
                h.image_size = Math.max(1, Math.min(500, Number(value) || 100));
                this.changed();
            },

            transformAreaImage(h, o) {
                if (h.action === 'pickup_v2') {
                    const field = o.overlay_bounds ? 'overlay_bounds' : 'image_bounds',
                        b = o[field];
                    if (!b) return;
                    const resizing = h.width !== o.width || h.height !== o.height;
                    h[field] = { ...b, x: b.x + (resizing ? 0 : h.x - o.x), y: b.y + (resizing ? 0 : h.y - o.y) };
                    return;
                }
                if (!o.overlay_bounds) return;
                const b = o.overlay_bounds,
                    sx = h.width / o.width,
                    sy = h.height / o.height;
                h.overlay_bounds = {
                    x: h.x + (b.x - o.x) * sx,
                    y: h.y + (b.y - o.y) * sy,
                    width: b.width * sx,
                    height: b.height * sy,
                };
            },
            endArea(cancel = false) {
                const d = this.areaDrag;
                if (!d) return;
                const h = this.visual.hotspots.find((h) => h.id === d.id);
                if (h) {
                    if (cancel) {
                        Object.assign(h, d.original);
                        this.syncContainerAreas(h);
                    } else if (Object.keys(d.original).some((k) => h[k] !== d.original[k])) this.changed();
                }
                this.areaDrag = null;
            },
            areaKey(e, h) {
                if (this.preview || e.target !== e.currentTarget) return;
                if (e.key === 'Delete' || e.key === 'Backspace') {
                    e.preventDefault();
                    this.requestAreaDelete(h.id);
                    return;
                }
                if (e.key === 'F2') {
                    e.preventDefault();
                    this.beginRename(h);
                    return;
                }
                const delta = {
                    ArrowLeft: [-0.005, 0],
                    ArrowRight: [0.005, 0],
                    ArrowUp: [0, -0.005],
                    ArrowDown: [0, 0.005],
                }[e.key];
                if (!delta) return;
                e.preventDefault();
                this.hotspotId = h.id;
                this.ensureAreaImageBounds(h);
                const original = {
                    ...h,
                    image_bounds: h.image_bounds ? { ...h.image_bounds } : null,
                    overlay_bounds: h.overlay_bounds ? { ...h.overlay_bounds } : null,
                };
                if (e.shiftKey) {
                    if (h.overlay && h.action !== 'pickup_v2')
                        this.resizeAreaProportionally(h, original, 'se', delta[0], delta[1]);
                    else {
                        h.width += delta[0];
                        h.height += delta[1];
                    }
                } else {
                    h.x = Math.max(0, Math.min(1 - h.width, h.x + delta[0]));
                    h.y = Math.max(0, Math.min(1 - h.height, h.y + delta[1]));
                }
                this.clampArea();
                this.transformAreaImage(h, original);
                this.syncContainerAreas(h);
            },
            sceneLabel(v) {
                return (
                    'Scene ' +
                    (this.scenes.findIndex((s) => s.id === v.id) + 1) +
                    ' · ' +
                    v.name +
                    ' · ' +
                    (this.asset(v.asset_id)?.name || 'No image')
                );
            },
            referenceDialogOrder: [],
            get referenceAssets() {
                const rank = new Map(this.referenceDialogOrder.map((id, index) => [id, index]));
                return this.assets
                    .filter(
                        (a) =>
                            !a.archived &&
                            a.role !== 'extraction_reference' &&
                            (!this.referenceCategoryFilter ||
                                (this.jobs.find((j) => j.asset_ids?.includes(a.id))?.category || 'scene') ===
                                    this.referenceCategoryFilter),
                    )
                    .sort((a, b) => (rank.get(a.id) ?? Infinity) - (rank.get(b.id) ?? Infinity));
            },
            openReferences() {
                const selected = new Set(this.references.map(String));
                this.referenceDialogOrder = this.assets
                    .filter((a) => !a.archived && a.role !== 'extraction_reference')
                    .sort((a, b) => Number(selected.has(String(b.id))) - Number(selected.has(String(a.id))))
                    .map((a) => a.id);
                this.$refs.referenceDialog.showModal();
                this.$nextTick(() => {
                    this.$refs.referenceImagesScroll.scrollTop = 0;
                });
            },
            get pickerAssets() {
                return this.assets.filter((a) => !a.archived && a.role !== 'extraction_reference');
            },
            imagePickerTarget: 'visual',
            editorHoverAreaId: null,
            get editorVisibilityFlags() {
                const flags = Object.fromEntries(this.flagDefinitions.map((f) => [f.key, !!f.initial]));
                for (const h of this.visual?.hotspots || [])
                    if (h.state_flag) flags[h.state_flag] = !!h.overlay && h.overlay_always !== false;
                return flags;
            },
            editorAreaVisible(h) {
                if (this.areaDisabled(h, this.editorVisibilityFlags)) return false;
                if (!h.container_parent_id) return true;
                const parent = this.visual?.hotspots?.find((p) => p.id === h.container_parent_id);
                return !!parent?.is_container && !!parent.overlay && parent.overlay_always !== false;
            },
            editorAreaImage(h) {
                if (!h.overlay || !this.editorAreaVisible(h)) return null;
                if (h.action === 'state_v2') {
                    if (h.overlay_always === false) return h.is_container ? this.asset(h.overlay_asset_id) : null;
                    return this.asset(h.state_asset_id);
                }
                if (h.overlay_always === false && this.editorHoverAreaId !== h.id) return null;
                return this.areaImage(h);
            },
            editorAreaImageStyle(h) {
                return (
                    this.style(this.renderedAreaImageBounds(h)) +
                    ';z-index:' +
                    this.areaLayer(h) +
                    ';object-position:' +
                    this.areaImagePosition(h)
                );
            },

            get pickerSelectedAsset() {
                return this.imagePickerTarget === 'area' ? this.areaImage(this.hotspot)?.id : this.visual?.asset_id;
            },
            selectPickerImage(id) {
                if (this.imagePickerTarget === 'area') {
                    if (this.hotspot) {
                        if (this.hotspot.action === 'state_v2') this.hotspot.state_asset_id = id;
                        else this.hotspot.overlay_asset_id = id;
                        this.changed();
                    }
                } else if (this.visual) {
                    if (id) this.useImage(id);
                    else {
                        this.visual.asset_id = null;
                        this.changed();
                    }
                }
                this.$refs.visualImagePicker.close();
            },
            openAreaImagePicker() {
                this.imagePickerTarget = 'area';
                this.$refs.visualImagePicker.showModal();
            },
            openVisualImagePicker() {
                this.imagePickerTarget = 'visual';
                this.$refs.visualImagePicker.showModal();
            },
            openInteractionPicker(kind) {
                this.pickerKind = kind;
                this.$refs.interactionImagePicker.showModal();
            },
            selectInventoryItem(id) {
                if (!this.hotspot || !this.gameObjects.some((v) => v.id === id)) return;
                this.hotspot.object_id = id;
                this.changed();
                this.$refs.interactionImagePicker.close();
            },
            selectObjectImage(id) {
                const object = this.gameObjects.find((v) => v.asset_id === Number(id));
                if (object) this.selectInventoryItem(object.id);
                else this.error = 'Create this item in Inventory first.';
            },
            clampArea() {
                const h = this.hotspot;
                if (!h) return;
                h.x = Math.max(0, Math.min(0.995, Number(h.x) || 0));
                h.y = Math.max(0, Math.min(0.995, Number(h.y) || 0));
                h.width = Math.max(0.005, Math.min(1 - h.x, Number(h.width) || 0.005));
                h.height = Math.max(0.005, Math.min(1 - h.y, Number(h.height) || 0.005));
                this.changed();
            },
            point(e) {
                const r = e.currentTarget.getBoundingClientRect();
                return {
                    x: Math.max(0, Math.min(1, (e.clientX - r.left) / r.width)),
                    y: Math.max(0, Math.min(1, (e.clientY - r.top) / r.height)),
                };
            },
            enlarge(asset) {
                if (this.suppressImageClick) {
                    this.suppressImageClick = false;
                    return;
                }
                if (!this.drawing && !this.preview && asset)
                    window.dispatchEvent(new CustomEvent('vsg-image', { detail: asset }));
            },
            cancelDraw() {
                this.drag = null;
                this.drawRect = null;
                this.drawing = false;
            },
            toggleDrawing() {
                if (this.drawing) this.cancelDraw();
                else {
                    this.drawing = true;
                    this.renamingId = '';
                }
            },
            startDraw(e) {
                if (!this.drawing || this.preview || e.button !== 0) return;
                this.suppressImageClick = true;
                e.preventDefault();
                e.currentTarget.setPointerCapture(e.pointerId);
                this.drag = { ...this.point(e), pointerId: e.pointerId };
                this.moveDraw(e);
            },
            moveDraw(e) {
                if (!this.drag || e.pointerId !== this.drag.pointerId) return;
                const p = this.point(e),
                    a = this.drag;
                this.drawRect = {
                    x: Math.min(a.x, p.x),
                    y: Math.min(a.y, p.y),
                    width: Math.abs(a.x - p.x),
                    height: Math.abs(a.y - p.y),
                };
            },
            endDraw(e) {
                if (!this.drag || e.pointerId !== this.drag.pointerId) return;
                this.moveDraw(e);
                const rect = this.drawRect;
                this.drag = null;
                this.drawRect = null;
                if (e.currentTarget.hasPointerCapture?.(e.pointerId))
                    e.currentTarget.releasePointerCapture(e.pointerId);
                if (rect.width >= 0.005 && rect.height >= 0.005) this.newHotspot(rect);
                setTimeout(() => {
                    this.suppressImageClick = false;
                }, 0);
            },
            get inventoryStateTargets() {
                return (this.visual?.hotspots || [])
                    .filter((h) => h.state_flag && h.state_asset_id)
                    .map((h) => ({ id: h.id, label: h.label, flag: h.state_flag }));
            },
            inventoryStateEffects(rule) {
                return (rule.flag_effects || []).filter((effect) =>
                    this.inventoryStateTargets.some((target) => target.flag === effect.key),
                );
            },
            addInventoryStateTarget(rule, key) {
                const target = this.inventoryStateTargets.find((target) => target.flag === key);
                if (!target || this.inventoryStateEffects(rule).some((effect) => effect.key === key)) return;
                this.setInventoryState(rule, target, 'true');
            },
            changeInventoryStateTarget(rule, effect, key) {
                if (
                    !this.inventoryStateTargets.some((target) => target.flag === key) ||
                    this.inventoryStateEffects(rule).some((other) => other !== effect && other.key === key)
                )
                    return;
                effect.key = key;
                this.changed();
            },
            inventoryStateValue(rule, target) {
                const effect = (rule.flag_effects || []).find((effect) => effect.key === target.flag);
                return effect ? String(effect.value) : '';
            },
            setInventoryState(rule, target, value) {
                rule.flag_effects = (rule.flag_effects || []).filter((effect) => effect.key !== target.flag);
                if (value !== '') rule.flag_effects.push({ key: target.flag, value: value === 'true' });
                this.changed();
            },
            addInventoryRule(objectId = null) {
                if (!this.hotspot) return;
                this.hotspot.inventory_rules ??= [];
                if (this.hotspot.inventory_rules.length >= 100) return;
                this.hotspot.inventory_rules.push({
                    object_id: objectId,
                    response: '',
                    target_id: null,
                    consume: false,
                });
                this.changed();
            },
            addAllInventoryRules() {
                if (!this.hotspot) return;
                for (const object of this.previewInventoryItems)
                    if (!(this.hotspot.inventory_rules || []).some((r) => r.object_id === object.id))
                        this.addInventoryRule(object.id);
            },
            addAreaChoice() {
                if (!this.hotspot) return;
                this.hotspot.choices ??= [];
                if (this.hotspot.choices.length >= 20) return;
                this.hotspot.choices.push({ label: 'New action', response: '', target_id: null });
                this.changed();
            },

            inventoryDrag: null,
            suppressInventoryClick: false,
            pendingInventoryUse: null,
            inventoryUseTimer: null,

            addObjectAction() {
                if (this.visual?.kind !== 'object') return;
                this.visual.actions ??= [];
                if (this.visual.actions.length >= 50) return;
                this.visual.actions.push({ id: crypto.randomUUID(), label: 'New action', target_id: null });
                this.changed();
            },
            removeObjectAction(id) {
                this.visual.actions = (this.visual.actions || []).filter((a) => a.id !== id);
                this.changed();
            },

            transitionRequest: 0,

            skipRuleId: '',
            skipItemSelection: [],
            skipJumpCount: 0,
            selectSceneBackground() {
                if (!this.drawing && !this.suppressImageClick) this.hotspotId = '';
            },
            addSkipRule() {
                this.visual.skip_rules ??= [];
                this.visual.skip_rules.push({
                    id: crypto.randomUUID(),
                    object_ids: [],
                    target_page_id: null,
                    target_scene_id: null,
                });
                this.changed();
            },
            removeSkipRule(id) {
                this.visual.skip_rules = this.visual.skip_rules.filter((r) => r.id !== id);
                this.changed();
            },
            chooseSkipItems(rule) {
                this.skipRuleId = rule.id;
                this.skipItemSelection = [...rule.object_ids];
                this.$refs.skipItemsDialog.showModal();
            },
            saveSkipItems() {
                const rule = this.visual?.skip_rules?.find((r) => r.id === this.skipRuleId);
                if (rule) {
                    rule.object_ids = [...this.skipItemSelection];
                    this.changed();
                }
                this.$refs.skipItemsDialog.close();
            },
            skipItemsLabel(rule) {
                return (
                    this.previewInventoryItems
                        .filter((i) => rule.object_ids.includes(i.id))
                        .map((i) => i.name)
                        .join(', ') || 'Choose inventory items'
                );
            },
            setSkipDestination(rule, key) {
                const d = this.pageSceneDestinations.find((d) => d.key === key);
                rule.target_page_id = d?.pageId || null;
                rule.target_scene_id = d?.sceneId || null;
                this.changed();
            },

            previewStartKey: '',
            preparePreview() {
                if (!this.document.visuals.some((v) => v.kind === 'scene')) {
                    notify('Add a scene before testing scenes.', 'error');
                    return;
                }
                this.previewStartKey =
                    this.pageSceneDestinations.find((d) => d.pageId === this.editorPageId)?.key || '';
                this.previewInventorySelection = [];
                this.previewFlags = this.flagDefinitions.filter((f) => f.initial).map((f) => f.key);
                this.$refs.previewInventoryDialog.showModal();
            },
            async confirmPreviewInventory(skipFirstVideo = false) {
                const tab = window.open('about:blank', '_blank');
                if (!tab) {
                    notify('Allow popups to open the game preview.', 'error');
                    return;
                }
                tab.opener = null;
                try {
                    if (window.vsgAutosave && !(await window.vsgAutosave.flush())) {
                        tab.close();
                        return;
                    }
                    if (window.vsgFlags && !(await window.vsgFlags.flush())) {
                        tab.close();
                        return;
                    }
                    if (window.vsgInventory && !(await window.vsgInventory.flush())) {
                        tab.close();
                        return;
                    }
                    if (!(await this.flush())) {
                        tab.close();
                        return;
                    }
                    const start = this.pageSceneDestinations.find((d) => d.key === this.previewStartKey),
                        url = new URL(options.playUrl, window.location.origin);
                    if (skipFirstVideo) url.searchParams.set('skip_first_video', '1');
                    url.searchParams.set('page', start?.pageId ?? this.editorPageId);
                    if (start?.sceneId) url.searchParams.set('scene', start.sceneId);
                    this.previewInventorySelection.forEach((id) => url.searchParams.append('inventory[]', id));
                    this.previewFlags.forEach((key) => url.searchParams.append('flags[]', key));
                    tab.location.href = url.href;
                    this.$refs.previewInventoryDialog.close();
                } catch (e) {
                    tab.close();
                    notify(e.message || 'Unable to open preview.', 'error');
                }
            },

            destinationValue(area) {
                if (!area?.target_page_id) return '';
                const match = this.pageSceneDestinations.find(
                    (d) =>
                        Number(d.pageId) === Number(area.target_page_id) &&
                        (!area.target_scene_id || d.sceneId === area.target_scene_id),
                );
                return match?.key || '';
            },
            selectPageScene(key) {
                if (!this.hotspot) return;
                const destination = this.pageSceneDestinations.find((d) => d.key === key);
                this.hotspot.target_page_id = destination?.pageId || null;
                this.hotspot.target_scene_id = destination?.sceneId || null;
                this.changed();
            },
        },
        {
            ...Object.getOwnPropertyDescriptors(gameRuntime()),
            ...Object.getOwnPropertyDescriptors(sceneExtraction(options)),
            ...Object.getOwnPropertyDescriptors(containerPlans(options)),
        },
    );
}
