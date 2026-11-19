import { notify } from './toasts.js';
import { rememberedModel } from './model-preferences.js';
import { containerVisible, containerGeometry } from './game-geometry.js';
export { containerVisible, containerGeometry };
export function containerCropBounds(b, w, h, p) {
    const x = Math.max(0, Math.floor(b.x * w) - (p.left || 0)),
        y = Math.max(0, Math.floor(b.y * h) - (p.top || 0));
    const right = Math.min(w, Math.ceil((b.x + b.width) * w) + (p.right || 0)),
        bottom = Math.min(h, Math.ceil((b.y + b.height) * h) + (p.bottom || 0));
    return { x: x / w, y: y / h, width: (right - x) / w, height: (bottom - y) / h };
}
export function containerPlans(options) {
    return {
        containerReuseState: false,
        containerRun: false,
        containerPlans: [],
        containerArea: '',
        containerPlanId: '',
        containerBusy: false,
        containerError: '',
        containerName: '',
        containerPrompt: '',
        containerItems: [],
        containerItemIndex: 0,
        containerDraw: null,
        containerTimer: null,
        containerModel: '',
        containerInventoryRevision: 0,
        containerView: 'open',
        containerHidden: [],
        containerPrompts: {},
        containerPadding: { top: 0, right: 0, bottom: 0, left: 0 },
        containerPaddingDraft: { top: 0, right: 0, bottom: 0, left: 0 },
        containerEqualPadding: true,
        containerSourceSize: null,
        containerUserPrompt: '',
        containerPromptModel: '',
        containerPromptBusy: false,
        get containerCrop() {
            const a = this.containerAreas.find((a) => a.id === this.containerArea);
            if (!a || !this.containerSourceSize) return null;
            return containerCropBounds(
                a,
                this.containerSourceSize.width,
                this.containerSourceSize.height,
                this.containerPadding,
            );
        },
        async loadContainerSource() {
            const url = this.asset(this.visual?.asset_id)?.url;
            this.containerSourceSize = null;
            if (!url) return;
            const image = new Image();
            image.onload = () => {
                if (this.asset(this.visual?.asset_id)?.url === url)
                    this.containerSourceSize = { width: image.naturalWidth, height: image.naturalHeight };
            };
            image.onerror = () => {
                this.containerError = 'Could not load the scene crop.';
            };
            image.src = url;
        },
        changeContainerPadding(side, value) {
            const n = Math.max(0, Math.min(500, Math.round(Number(value) || 0)));
            if (this.containerEqualPadding) this.containerPaddingDraft = { top: n, right: n, bottom: n, left: n };
            else this.containerPaddingDraft = { ...this.containerPaddingDraft, [side]: n };
        },
        commitContainerPadding() {
            this.containerPadding = { ...this.containerPaddingDraft };
        },
        linkContainerPadding(equal) {
            this.containerEqualPadding = equal;
            if (equal) {
                this.changeContainerPadding('top', this.containerPaddingDraft.top);
                this.commitContainerPadding();
            }
        },
        toggleContainerItem(item, checked) {
            if (checked) {
                if (!this.containerItems.some((c) => c.object_id === item.id))
                    this.containerItems.push({ object_id: item.id, name: item.name });
            } else this.containerItems = this.containerItems.filter((c) => c.object_id !== item.id);
        },
        copyContainerPlan() {
            const p = this.containerPlan;
            if (!p) return;
            const preview = this.containerOutputs.find((o) => o.type === 'container_preview');
            this.containerPrompt = this.containerPrompts[preview?.id] ?? preview?.prompt ?? p.prompt;
            this.containerItems = p.contents.map((c) => ({ object_id: c.object_id, name: c.name }));
            this.containerName = p.name + ' copy';
            this.containerPlanId = '';
            this.loadContainerSource();
        },
        async draftContainerPrompt() {
            if (this.containerPromptBusy || this.containerBusy) return;
            this.containerPromptBusy = true;
            this.containerError = '';
            try {
                if (!(await this.flush())) return;
                const model = rememberedModel('vsg-writing-model', options.writingModel || '');
                if (!model) throw new Error('Choose a writing model in chat first.');
                const d = await this.request(options.containerUrl + '/prompt', 'POST', {
                    visual_id: this.selected,
                    area_id: this.containerArea,
                    user_prompt: this.containerUserPrompt,
                    model,
                    request_key: crypto.randomUUID(),
                    contents: this.containerItems.map((c) => ({ object_id: c.object_id })),
                    padding: this.containerPadding,
                });
                this.containerPrompt = d.prompt;
                this.containerPromptModel = d.model;
                const output = this.containerOutputs.find((o) => o.type === 'container_preview');
                if (output) this.containerPrompts[output.id] = d.prompt;
                this.credit?.(d);
            } catch (e) {
                this.containerError = e.message;
            } finally {
                this.containerPromptBusy = false;
            }
        },
        get assignedContainerPlans() {
            return this.containerPlans.filter((p) => p.visual_id === this.selected && p.area_id === this.hotspot?.id);
        },
        get hasAssignedContainer() {
            return !!this.hotspot?.is_container || this.assignedContainerPlans.length > 0;
        },
        deleteContainerTarget: null,
        requestContainerDelete() {
            if (!this.hotspot) return;
            this.deleteContainerTarget = {
                visual_id: this.selected,
                area_id: this.hotspot.id,
                label: this.hotspot.label,
            };
            this.$refs.deleteContainerDialog.showModal();
        },
        async deleteAssignedContainer() {
            if (this.containerBusy || !this.deleteContainerTarget) return;
            this.containerBusy = true;
            try {
                if (!(await this.flush())) return;
                await this.request(options.containerUrl, 'DELETE', {
                    ...this.deleteContainerTarget,
                    revision: this.revision,
                });
                const d = await this.request(options.url);
                this.document = d.document;
                this.revision = d.revision;
                this.containerPlans = d.container_plans || [];
                this.flagDefinitions = d.game_flags || [];
                this.previewPages = d.preview_pages;
                this.containerPlanId = '';
                this.deleteContainerTarget = null;
                this.$refs.deleteContainerDialog.close();
                window.dispatchEvent(new CustomEvent('vsg-flags', { detail: this.flagDefinitions }));
                notify('Container deleted. Generated images were kept.');
            } catch (e) {
                notify(e.message, 'error');
            } finally {
                this.containerBusy = false;
            }
        },
        get containerAreas() {
            return (this.visual?.hotspots || []).filter((h) => !h.container_parent_id);
        },
        get selectedContainerPlans() {
            return this.containerPlans.filter((p) => p.visual_id === this.selected && p.area_id === this.containerArea);
        },
        containerPlanLabel(p) {
            return p.name + ' · ' + p.stage + ' · ' + this.planSummary(p.batch);
        },
        async renameContainerPlan(name) {
            if (!this.containerPlan) return;
            try {
                const d = await this.request(options.containerUrl + '/' + this.containerPlan.id, 'PATCH', { name });
                this.containerPlan.name = d.name;
                this.containerName = d.name;
                notify('Plan renamed.');
            } catch (e) {
                this.containerError = e.message;
            }
        },
        chooseContainerPlan(existing) {
            if (existing) this.selectContainerPlan();
            else this.newContainerPlan();
            this.$refs.containerPlanDialog.close();
            this.$refs.containerDialog.showModal();
        },
        get containerPlan() {
            return this.selectedContainerPlans.find((p) => p.id === Number(this.containerPlanId));
        },
        get containerOutputs() {
            return this.containerPlan?.batch?.outputs || [];
        },
        get containerPreviewAsset() {
            return this.containerOutputs.find((o) => o.type === 'container_preview')?.accepted_asset_id;
        },
        get containerReady() {
            return (
                this.containerPlan?.stage !== 'preview' &&
                this.containerOutputs.length > 2 &&
                this.containerOutputs.every((o) => o.accepted_asset_id && !this.containerPending(o))
            );
        },
        containerPending(o) {
            return o.jobs?.some((j) => ['queued', 'running'].includes(j.status));
        },
        async openContainers(reuseState = false) {
            this.containerReuseState = reuseState;
            if (!(await this.flush())) return;
            this.containerError = '';
            this.containerModel = this.model;
            this.containerArea =
                this.hotspot && !this.hotspot.container_parent_id ? this.hotspot.id : this.containerAreas[0]?.id || '';
            this.containerPlanId = '';
            await this.refreshContainers();
            this.newContainerPlan();
            if (this.containerPlans.some((p) => p.visual_id === this.selected)) {
                this.containerPlanId = this.selectedContainerPlans[0]?.id || '';
                this.$refs.containerPlanDialog.showModal();
            } else this.$refs.containerDialog.showModal();
        },
        closeContainers() {
            clearTimeout(this.containerTimer);
            this.$refs.containerDialog.close();
        },
        async refreshContainers() {
            clearTimeout(this.containerTimer);
            const d = await this.request(options.containerUrl);
            this.containerPlans = d.plans;
            this.containerInventoryRevision = d.inventory_revision;
            const images = await this.request(options.url);
            this.assets = images.assets;
            this.jobs = images.jobs;
            this.credit?.(images);
            if (this.$refs.containerDialog?.open && this.containerOutputs.some((o) => this.containerPending(o)))
                this.containerTimer = setTimeout(
                    () =>
                        this.refreshContainers().catch((e) => {
                            this.containerError = e.message;
                        }),
                    4000,
                );
        },
        newContainerPlan() {
            this.containerPlanId = '';
            this.containerItems = [];
            this.containerName =
                (this.containerAreas.find((a) => a.id === this.containerArea)?.label || 'Container') + ' plan';
            this.containerPrompt = '';
            this.containerUserPrompt = '';
            this.containerPromptModel = '';
            this.containerPadding = { top: 0, right: 0, bottom: 0, left: 0 };
            this.containerPaddingDraft = { ...this.containerPadding };
            this.containerEqualPadding = true;
            this.containerHidden = [];
            this.loadContainerSource();
        },
        selectContainerPlan() {
            const p = this.containerPlan;
            if (!p) {
                this.newContainerPlan();
                return;
            }
            this.containerItems = JSON.parse(JSON.stringify(p.contents));
            this.containerName = p.name;
            this.containerPrompt = p.prompt;
            this.containerUserPrompt = p.user_prompt || p.prompt;
            this.containerPromptModel = p.prompt_model || '';
            this.containerPadding = { top: 0, right: 0, bottom: 0, left: 0, ...p.padding };
            this.containerPaddingDraft = { ...this.containerPadding };
            this.containerEqualPadding = p.equal_padding !== false;
            this.containerPrompts = Object.fromEntries(p.batch.outputs.map((o) => [o.id, o.prompt]));
            this.containerItemIndex = 0;
            this.containerHidden = [];
            clearTimeout(this.containerTimer);
            if (this.containerOutputs.some((o) => this.containerPending(o)))
                this.containerTimer = setTimeout(
                    () => this.refreshContainers().catch((e) => (this.containerError = e.message)),
                    4000,
                );
        },
        async createContainerPlan() {
            if (this.containerBusy) return;
            this.containerBusy = true;
            this.containerError = '';
            try {
                if (!(await this.flush())) return;
                const p = await this.request(options.containerUrl, 'POST', {
                    reuse_changed_state: this.containerReuseState,
                    visual_id: this.selected,
                    area_id: this.containerArea,
                    name: this.containerName,
                    prompt: this.containerPrompt || null,
                    user_prompt: this.containerUserPrompt,
                    prompt_model: this.containerPromptModel,
                    padding: this.containerPadding,
                    equal_padding: this.containerEqualPadding,
                    contents: this.containerItems.map((c) => ({ object_id: c.object_id })),
                });
                this.containerPlanId = p.id;
                await this.refreshContainers();
                this.selectContainerPlan();
            } catch (e) {
                this.containerError = e.message;
            } finally {
                this.containerBusy = false;
            }
        },
        moveContainerLayer(direction) {
            if (this.containerDraw) return;
            const i = this.containerItemIndex,
                j = i + direction;
            if (j < 0 || j >= this.containerItems.length) return;
            const items = [...this.containerItems];
            [items[i], items[j]] = [items[j], items[i]];
            this.containerItems = items;
            this.containerItemIndex = j;
        },
        beginContainerDraw(e) {
            if (
                this.containerPlan?.stage !== 'preview' ||
                !this.containerPreviewAsset ||
                e.button !== 0 ||
                this.containerItems[this.containerItemIndex]?.bounds
            )
                return;
            this.startContainerGesture(e, this.containerItemIndex, 'draw');
        },
        beginContainerEdit(e, id, mode = 'move') {
            if (e.button !== 0) return;
            const index = this.containerItems.findIndex((c) => c.area_id === id);
            if (index < 0) return;
            this.containerItemIndex = index;
            this.startContainerGesture(e, index, mode);
        },
        startContainerGesture(e, index, mode) {
            e.preventDefault();
            const surface = this.$refs.containerCanvas,
                r = surface.getBoundingClientRect();
            this.containerDraw = {
                index,
                mode,
                original: this.containerItems[index].bounds ? { ...this.containerItems[index].bounds } : null,
                x: Math.max(0, Math.min(1, (e.clientX - r.left) / r.width)),
                y: Math.max(0, Math.min(1, (e.clientY - r.top) / r.height)),
            };
            surface.setPointerCapture(e.pointerId);
        },
        moveContainerDraw(e) {
            if (!this.containerDraw) return;
            const r = this.$refs.containerCanvas.getBoundingClientRect(),
                clamp = (v, min, max) => Math.max(min, Math.min(max, v)),
                x = clamp((e.clientX - r.left) / r.width, 0, 1),
                y = clamp((e.clientY - r.top) / r.height, 0, 1),
                d = this.containerDraw,
                b = d.original,
                min = 0.005;
            let bounds;
            if (d.mode === 'draw') {
                const left = Math.min(x, d.x, 1 - min),
                    top = Math.min(y, d.y, 1 - min);
                bounds = {
                    x: left,
                    y: top,
                    width: Math.max(min, Math.max(x, d.x) - left),
                    height: Math.max(min, Math.max(y, d.y) - top),
                };
            } else if (d.mode === 'move')
                bounds = { ...b, x: clamp(b.x + x - d.x, 0, 1 - b.width), y: clamp(b.y + y - d.y, 0, 1 - b.height) };
            else {
                let left = b.x,
                    top = b.y,
                    right = b.x + b.width,
                    bottom = b.y + b.height;
                if (d.mode.includes('w')) left = clamp(b.x + x - d.x, 0, right - min);
                if (d.mode.includes('e')) right = clamp(right + x - d.x, left + min, 1);
                if (d.mode.includes('n')) top = clamp(b.y + y - d.y, 0, bottom - min);
                if (d.mode.includes('s')) bottom = clamp(bottom + y - d.y, top + min, 1);
                bounds = { x: left, y: top, width: right - left, height: bottom - top };
            }
            this.containerItems[d.index].bounds = bounds;
        },
        endContainerDraw(cancel = false) {
            if (cancel && this.containerDraw) {
                const d = this.containerDraw;
                if (d.original) this.containerItems[d.index].bounds = d.original;
                else delete this.containerItems[d.index].bounds;
            }
            this.containerDraw = null;
        },
        async prepareContainerContents() {
            if (this.containerBusy) return;
            this.containerBusy = true;
            this.containerError = '';
            try {
                await this.request(options.containerUrl + '/' + this.containerPlan.id + '/prepare', 'POST', {
                    preview_asset_id: this.containerPreviewAsset,
                    contents: this.containerItems.map((c) => ({ area_id: c.area_id, bounds: c.bounds })),
                });
                await this.refreshContainers();
                this.selectContainerPlan();
            } catch (e) {
                this.containerError = e.message;
            } finally {
                this.containerBusy = false;
            }
        },
        async containerOutputAction(o, action, assetId = null) {
            if (this.containerBusy) return;
            this.containerBusy = true;
            this.containerError = '';
            try {
                const result = await this.request(
                    options.extractionUrl + '/' + this.containerPlan.extraction_id + '/outputs/' + o.id,
                    'POST',
                    {
                        action,
                        model: this.containerModel,
                        prompt: this.containerPrompts[o.id] ?? o.prompt,
                        container_user_prompt: this.containerUserPrompt,
                        container_prompt_model: this.containerPromptModel || null,
                        ...(assetId ? { asset_id: assetId } : {}),
                    },
                );
                for (const message of result.chat_messages || [])
                    window.dispatchEvent(new CustomEvent('vsg-chat-message', { detail: message }));
                await this.refreshContainers();
            } catch (e) {
                this.containerError = e.message;
            } finally {
                this.containerBusy = false;
            }
        },
        async generateContainerMissing() {
            if (this.containerRun || this.containerBusy) return;
            this.containerRun = true;
            try {
                for (const o of [...this.containerOutputs].filter(
                    (o) => !o.accepted_asset_id && !this.containerPending(o),
                )) {
                    await this.containerOutputAction(o, 'generate');
                    if (this.containerError) break;
                }
            } finally {
                this.containerRun = false;
            }
        },
        async activateContainer() {
            if (this.containerBusy) return;
            this.containerBusy = true;
            this.containerError = '';
            try {
                if (!(await this.flush())) return;
                await this.request(options.containerUrl + '/' + this.containerPlan.id + '/activate', 'POST', {
                    revision: this.revision,
                    inventory_revision: this.containerInventoryRevision,
                });
                const d = await this.request(options.url);
                this.document = d.document;
                this.revision = d.revision;
                this.containerPlans = d.container_plans || [];
                this.assets = d.assets;
                this.inventoryItems = d.inventory_items;
                this.flagDefinitions = d.game_flags;
                this.previewPages = d.preview_pages;
                window.dispatchEvent(new CustomEvent('vsg-inventory', { detail: this.inventoryItems }));
                window.dispatchEvent(new CustomEvent('vsg-flags', { detail: this.flagDefinitions }));
                this.closeContainers();
                notify('Container plan activated.');
            } catch (e) {
                this.containerError = e.message;
            } finally {
                this.containerBusy = false;
            }
        },
    };
}
