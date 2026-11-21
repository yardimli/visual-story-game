import { containerCropBounds } from './container-plans.js';
import { notify } from './toasts.js';
const cropSources = new Map();
function cropSource(url) {
    if (!cropSources.has(url))
        cropSources.set(
            url,
            new Promise((resolve, reject) => {
                const image = new Image();
                image.onload = () => resolve(image);
                image.onerror = () => {
                    cropSources.delete(url);
                    reject(new Error('Could not load the scene image for crop previews.'));
                };
                image.src = url;
            }),
        );
    return cropSources.get(url);
}

export function sceneExtraction(options) {
    return {
        extractionUpdateId: null,
        switchOverview: false,
        get currentSwitches() {
            return (this.visual?.hotspots || []).filter(
                (h) => h.action === 'state_v2' && !h.is_container && !h.container_parent_id,
            );
        },
        switchPrompt(area) {
            return (
                'Edit only the local state of ' +
                area.label +
                ' in this cropped reference: ' +
                (area.state_prompt ?? 'Show it pressed or activated.') +
                '. Preserve the exact framing, dimensions, surrounding background, lighting and perspective. Nothing is removed. Do not change anything outside the mechanism. Output a full rectangular patch with matching edges, not a transparent cutout.'
            );
        },
        switchPlanChanged(batch) {
            return batch.outputs
                .filter((o) => o.type === 'switch_on')
                .some((o) => {
                    const area = this.currentSwitches.find((h) => h.id === o.area_id);
                    return (
                        !area ||
                        o.prompt !== this.switchPrompt(area) ||
                        (o.source_instructions &&
                            o.source_instructions.state_description !== (area.state_description ?? ''))
                    );
                });
        },
        showSwitchOverview() {
            this.extractionId = null;
            this.extractionUpdateId = null;
            this.switchOverview = true;
            this.$refs.extractionDialog.showModal();
        },
        openSavedSwitchPlan(batch) {
            this.extractionPlanChoice = batch.id;
            this.chooseExtractionPlan(true);
        },
        extractionPreview: 'composition',
        extractionReferenceMode: 'original_and_annotated',
        extractionMode: 'collectibles',
        extractionPlanChoice: null,
        extractionRun: false,
        extractionSource: null,
        extractionBatches: [],
        extractionId: null,
        extractionAreas: [],
        extractionBusy: false,
        extractionPreparing: false,
        extractionRefreshing: false,
        extractionProgress: '',
        extractionError: '',
        extractionModel: '',
        extractionBounds: {},
        extractionPrompts: {},
        extractionHidden: [],
        extractionInventoryRevision: 0,
        extractionTimer: null,
        planSummary(batch) {
            const outputs = batch?.outputs || [];
            const jobs = outputs.flatMap((o) => o.jobs || []);
            const rendered = outputs.filter((o) =>
                (o.jobs || []).some((j) => j.status === 'completed' && j.asset_ids?.length),
            ).length;
            const accepted = outputs.filter((o) => o.type !== 'switch_off' && o.accepted_asset_id).length;
            const running = jobs.filter((j) => ['queued', 'running', 'submitting'].includes(j.status)).length;
            return `${outputs.length} outputs · ${rendered} rendered · ${accepted} accepted${running ? ' · ' + running + ' in progress' : ''}${!jobs.length && !accepted ? ' · Unrendered draft' : ''}${batch?.applied_at ? ' · Applied to scene' : ''}`;
        },
        extractionPlanLabel(b) {
            return `${b.name || (b.mode === 'switches' ? 'Switches' : 'Collectibles') + ' #' + b.id} · ${this.planSummary(b)}`;
        },
        async renameExtractionPlan(batch, name) {
            if (!batch || name === batch.name) return;
            try {
                const d = await this.request(options.extractionUrl + '/' + batch.id, 'PATCH', { name });
                batch.name = d.name;
                notify('Plan renamed.');
            } catch (e) {
                this.extractionError = e.message;
                notify(e.message, 'error');
            }
        },
        get extractionBatch() {
            return this.extractionBatches.find((b) => b.id === Number(this.extractionId));
        },
        get sceneExtractions() {
            return this.extractionBatches.filter(
                (b) => b.visual_id === this.selected && (b.mode || 'collectibles') === this.extractionMode,
            );
        },
        get extractionBackground() {
            return this.extractionBatch?.outputs.find((o) => o.type === 'background')?.accepted_asset_id;
        },
        get extractionLayers() {
            return (
                this.extractionBatch?.outputs.filter(
                    (o) => ['layer', 'switch_on'].includes(o.type) && o.accepted_asset_id,
                ) || []
            );
        },
        async drawExtractionCrop(canvas, bounds, sourceId) {
            const url = this.asset(sourceId)?.url;
            if (!url) return;
            const key = JSON.stringify([url, bounds]);
            if (canvas.dataset.cropKey === key) return;
            canvas.dataset.cropKey = key;
            try {
                const source = await cropSource(url);
                if (canvas.dataset.cropKey !== key) return;
                const x = Math.floor(bounds.x * source.naturalWidth),
                    y = Math.floor(bounds.y * source.naturalHeight);
                const width = Math.max(
                        1,
                        Math.min(source.naturalWidth - x, Math.round(bounds.width * source.naturalWidth)),
                    ),
                    height = Math.max(
                        1,
                        Math.min(source.naturalHeight - y, Math.round(bounds.height * source.naturalHeight)),
                    );
                canvas.width = width;
                canvas.height = height;
                canvas.getContext('2d').drawImage(source, x, y, width, height, 0, 0, width, height);
            } catch (e) {
                delete canvas.dataset.cropKey;
                this.extractionError = e.message;
            }
        },
        changeExtractionPadding(a, side, value) {
            const n = Math.max(0, Math.min(500, Math.round(Number(value) || 0)));
            a.paddingDraft = a.equalPadding
                ? { top: n, right: n, bottom: n, left: n }
                : { ...a.paddingDraft, [side]: n };
        },
        async commitExtractionPadding(a) {
            try {
                const source = await cropSource(this.asset(this.extractionSource).url);
                a.padding = { ...a.paddingDraft };
                a.useSavedBounds = false;
                a.bounds = containerCropBounds(a.areaBounds, source.naturalWidth, source.naturalHeight, a.padding);
            } catch (e) {
                this.extractionError = e.message;
            }
        },
        linkExtractionPadding(a, equal) {
            a.equalPadding = equal;
            if (equal) {
                this.changeExtractionPadding(a, 'top', a.paddingDraft.top);
                this.commitExtractionPadding(a);
            }
        },
        async openExtraction(mode = 'collectibles') {
            this.extractionMode = mode;
            if (!(await this.flush())) return;
            this.extractionError = '';
            this.extractionProgress = '';
            this.extractionModel = this.model;
            this.extractionSource = this.visual.asset_id;
            this.extractionAreas = (this.visual?.hotspots || [])
                .filter(
                    (h) =>
                        !h.is_container &&
                        !h.container_parent_id &&
                        h.action === (mode === 'switches' ? 'state_v2' : 'pickup_v2'),
                )
                .map((h) => ({
                    id: h.id,
                    label: h.label,
                    eligible: this.inventoryItems.some((i) => i.id === h.object_id),
                    initial_empty:
                        h.initial_empty ??
                        !!(this.inventoryItems.find((i) => i.id === h.object_id)?.asset_id && !h.overlay),
                    selected: true,
                    padding: { top: 0, right: 0, bottom: 0, left: 0 },
                    paddingDraft: { top: 0, right: 0, bottom: 0, left: 0 },
                    equalPadding: true,
                    areaBounds: { x: h.x, y: h.y, width: h.width, height: h.height },
                    bounds: { x: h.x, y: h.y, width: h.width, height: h.height },
                }));
            await this.refreshExtraction(true);
            if (this.extractionError) {
                notify(this.extractionError, 'error');
                return;
            }
            if (mode === 'switches') {
                this.showSwitchOverview();
            } else if (this.sceneExtractions.length) {
                this.extractionPlanChoice = this.sceneExtractions[0].id;
                this.$refs.extractionPlanDialog.showModal();
            } else this.chooseExtractionPlan(false);
        },
        async deleteExtractionPlan(id) {
            if (this.extractionBusy || this.extractionRun || !id) return;
            if (
                !window.confirm(
                    'Delete this plan? Generated images and settings already applied to the scene will be kept.',
                )
            )
                return;
            this.extractionBusy = true;
            this.extractionError = '';
            try {
                await this.request(options.extractionUrl + '/' + id, 'DELETE');
                this.extractionBatches = this.extractionBatches.filter((b) => b.id !== Number(id));
                if (Number(this.extractionId) === Number(id)) {
                    this.extractionId = null;
                    this.extractionUpdateId = null;
                    this.extractionProgress = 'Plan deleted.';
                }
                this.extractionPlanChoice = this.sceneExtractions[0]?.id ?? null;
                notify('Plan deleted.');
            } catch (e) {
                this.extractionError = e.message;
                notify(e.message, 'error');
            } finally {
                this.extractionBusy = false;
            }
        },
        async adjustExtractionPadding(batch = this.extractionBatch) {
            if (this.extractionBusy || this.extractionRun) return;
            if (!batch) return;
            const outputs = batch.outputs;
            this.chooseExtractionPlan(false);
            for (const a of this.extractionAreas) {
                const output = outputs.find((o) => o.area_id === a.id && ['layer', 'switch_on'].includes(o.type));
                a.selected = !!output;
                a.padding = { top: 0, right: 0, bottom: 0, left: 0, ...output?.padding };
                a.paddingDraft = { ...a.padding };
                a.equalPadding = output?.equal_padding !== false;
                if (output?.initial_empty !== undefined) a.initial_empty = output.initial_empty;
                if (output?.padding) await this.commitExtractionPadding(a);
                else if (output) {
                    a.bounds = { ...output.bounds };
                    a.useSavedBounds = true;
                }
            }
            this.extractionProgress =
                'Crop settings copied. Review the current instructions and crop, then prepare a new plan. Your previous plan and renders are kept.';
            this.$nextTick?.(() => this.$refs.extractionDialog.querySelector('.overflow-y-auto')?.scrollTo({ top: 0 }));
        },
        updateExtractionPlan() {
            const batch = this.sceneExtractions.find((b) => b.id === Number(this.extractionPlanChoice));
            if (!batch) return;
            this.chooseExtractionPlan(false);
            this.extractionUpdateId = batch.id;
            const existing = new Set(batch.outputs.map((o) => o.area_id).filter(Boolean));
            this.extractionAreas.forEach((a) => {
                a.reused = existing.has(a.id);
                a.selected = !a.reused;
            });
            this.extractionSource =
                batch.outputs.find((o) => o.type === 'background')?.accepted_asset_id || this.visual.asset_id;
        },
        chooseExtractionPlan(existing) {
            this.switchOverview = false;
            this.extractionUpdateId = null;
            this.extractionAreas.forEach((a) => {
                a.reused = false;
                a.selected = true;
            });
            const batch = existing
                ? this.sceneExtractions.find((b) => b.id === Number(this.extractionPlanChoice))
                : null;
            if (existing && !batch) return;
            this.extractionId = batch?.id ?? null;
            this.extractionSource = batch?.source_asset_id ?? this.visual.asset_id;
            this.extractionBounds = {};
            this.extractionHidden = [];
            this.extractionPreview = 'composition';
            this.$refs.extractionPlanDialog?.close();
            this.$refs.extractionDialog.showModal();
            this.scheduleExtractionRefresh();
        },
        closeExtraction() {
            clearInterval(this.extractionTimer);
            this.$refs.extractionDialog.close();
        },
        scheduleExtractionRefresh() {
            clearTimeout(this.extractionTimer);
            this.extractionTimer = null;
            if (
                this.$refs.extractionDialog?.open &&
                this.extractionBatch?.outputs.some((o) => this.extractionPending(o))
            )
                this.extractionTimer = setTimeout(() => this.refreshExtraction(), 5000);
        },
        async refreshExtraction(force = false) {
            if ((!force && !this.$refs.extractionDialog?.open) || this.extractionBusy || this.extractionRefreshing)
                return;
            this.extractionRefreshing = true;
            try {
                const data = await this.request(options.extractionUrl);
                this.extractionBatches = data.batches.map((b) => ({
                    ...b,
                    outputs: b.outputs.map((o) => ({ ...o, bounds: this.extractionBounds[o.id] ?? o.bounds })),
                }));
                this.extractionInventoryRevision = data.inventory_revision;
                const images = await this.request(options.url);
                this.assets = images.assets;
                this.jobs = images.jobs;
                this.credit?.(images);
            } catch (e) {
                this.extractionError = e.message;
            } finally {
                this.extractionRefreshing = false;
                if (!this.extractionError) this.scheduleExtractionRefresh();
            }
        },
        async prepareExtraction() {
            if (this.extractionBusy || this.extractionPreparing) return;
            this.extractionPreparing = true;
            this.extractionBusy = true;
            this.extractionError = '';
            this.extractionProgress = 'Saving scene changes…';
            try {
                await this.$nextTick?.();
                if (!(await this.flush())) {
                    this.extractionError =
                        this.error || 'Scene changes could not be saved. Please resolve the save error and try again.';
                    return;
                }
                this.extractionProgress = 'Preparing cropped references and the extraction plan…';
                const batch = await this.request(options.extractionUrl, 'POST', {
                    ...(this.extractionUpdateId ? { update_plan_id: this.extractionUpdateId } : {}),
                    background_references: this.extractionReferenceMode,
                    mode: this.extractionMode,
                    visual_id: this.selected,
                    revision: this.revision,
                    areas: this.extractionAreas
                        .filter((a) => a.selected)
                        .map((a) => ({
                            id: a.id,
                            bounds: a.bounds,
                            ...(a.useSavedBounds ? {} : { padding: a.padding }),
                            equal_padding: a.equalPadding,
                            initial_empty: !!a.initial_empty,
                        })),
                });
                this.document = batch.document;
                this.revision = batch.revision;
                this.inventoryItems = batch.inventory_items;
                this.extractionInventoryRevision = batch.inventory_revision;
                window.dispatchEvent(new CustomEvent('vsg-inventory', { detail: this.inventoryItems }));
                this.extractionAreas.forEach(
                    (a) =>
                        (a.eligible = this.inventoryItems.some(
                            (i) => i.id === this.visual.hotspots.find((h) => h.id === a.id)?.object_id,
                        )),
                );
                this.extractionBatches = this.extractionBatches.filter((b) => b.id !== batch.id);
                this.extractionBatches.unshift(batch);
                this.extractionId = batch.id;
                this.extractionProgress = 'Loading the plan and image previews…';
                const images = await this.request(options.url);
                this.assets = images.assets;
                this.jobs = images.jobs;
                this.credit?.(images);
                this.extractionProgress = 'Plan ready. Review the outputs below before generating images.';
            } catch (e) {
                this.extractionError = e.message;
            } finally {
                this.extractionPreparing = false;
                this.extractionBusy = false;
                if (this.extractionError) this.extractionProgress = '';
            }
        },
        async toggleSwitchReference(output, id, checked) {
            const ids = output.references.slice(1).filter((value) => value !== id);
            if (checked) ids.push(id);
            await this.extractionAction(output, 'switch_references', null, null, ids);
        },
        async extractionAction(output, action, assetId = null, referenceMode = null, extraReferences = null) {
            if (this.extractionBusy) return;
            this.extractionBusy = true;
            this.extractionError = '';
            try {
                const data = await this.request(
                    options.extractionUrl + '/' + this.extractionId + '/outputs/' + output.id,
                    'POST',
                    {
                        action,
                        ...(action === 'switch_references' ? { extra_references: extraReferences } : {}),
                        ...(action === 'references'
                            ? { reference_mode: referenceMode || output.reference_mode || 'original_and_annotated' }
                            : {}),
                        model: this.extractionModel || undefined,
                        prompt: this.extractionPrompts[output.id] ?? output.prompt,
                        ...(assetId ? { asset_id: assetId } : {}),
                        ...(action === 'align' ? { bounds: this.extractionBounds[output.id] ?? output.bounds } : {}),
                    },
                );
                this.extractionBatches = this.extractionBatches.map((b) => (b.id === data.id ? data : b));
                if (action === 'align') delete this.extractionBounds[output.id];
                if (action === 'references') delete this.extractionPrompts[output.id];
            } catch (e) {
                this.extractionError = e.message;
            } finally {
                this.extractionBusy = false;
                await this.refreshExtraction();
            }
        },
        extractionPending(output) {
            return output.jobs?.some((j) => ['queued', 'running'].includes(j.status));
        },
        get extractionMissing() {
            return (
                this.extractionBatch?.outputs.filter((o) => !o.accepted_asset_id && !this.extractionPending(o)) || []
            );
        },
        async generateMissingExtraction() {
            if (this.extractionRun || this.extractionBusy || !this.extractionModel || !this.extractionMissing.length)
                return;
            const outputs = [...this.extractionMissing];
            this.extractionRun = true;
            try {
                for (const o of outputs) {
                    await this.extractionAction(o, 'generate');
                    if (this.extractionError) break;
                }
            } finally {
                this.extractionRun = false;
            }
        },
        async activateExtraction() {
            if (this.extractionBusy) return;
            this.extractionBusy = true;
            this.extractionError = '';
            try {
                if (!(await this.flush())) return;
                const applied = await this.request(
                    options.extractionUrl + '/' + this.extractionId + '/activate',
                    'POST',
                    { revision: this.revision, inventory_revision: this.extractionInventoryRevision },
                );
                if (applied.chat_message)
                    window.dispatchEvent(new CustomEvent('vsg-chat-message', { detail: applied.chat_message }));
                const data = await this.request(options.url);
                this.document = data.document;
                this.revision = data.revision;
                this.assets = data.assets;
                this.inventoryItems = data.inventory_items;
                this.flagDefinitions = data.game_flags;
                this.previewPages = data.preview_pages;
                window.dispatchEvent(new CustomEvent('vsg-inventory', { detail: this.inventoryItems }));
                window.dispatchEvent(new CustomEvent('vsg-flags', { detail: this.flagDefinitions }));
                notify('Accepted extraction activated.');
                this.closeExtraction();
            } catch (e) {
                this.extractionError = e.message;
            } finally {
                this.extractionBusy = false;
            }
        },
    };
}
