import { guiThemeStyle } from './gui-theme.js';
import { SceneMediaCache, sceneMedia } from './scene-media-cache.js';
import { containerVisible } from './game-geometry.js';
import { contrastText } from './scene-colors.js';
import { notify } from './toasts.js';
import { actionLabel, defaultActionPreset } from './interaction-actions.js';

// Shared playback rules. No editor, generation or authoring dependencies.
export function gameRuntime() {
    const mediaCache = new SceneMediaCache();
    return {
        actionPreset(h) {
            return h.action_preset || defaultActionPreset(h);
        },
        dualAction(h) {
            return ['open_close', 'pull_push', 'turn_on_off', 'insert_remove', 'custom_dual'].includes(
                this.actionPreset(h),
            );
        },
        actionState(h) {
            return h.action === 'state_v2'
                ? !!this.flags[h.state_flag]
                : h.action === 'pickup_v2' && h.initial_empty
                  ? !this.collectibleEmpty(h)
                  : false;
        },
        buttonLabel(h) {
            return actionLabel(h, this.actionState(h));
        },
        canPresentAction(h) {
            if (h.action_video_id) return true;
            if (h.action === 'inventory') return false;
            if (h.action === 'pickup_v2' && this.collectibleEmpty(h)) return false;
            return this.hasAreaAction(h);
        },
        presentArea(h, text = this.areaDescription(h), action = true) {
            if (!text?.trim()) return;
            this.showObjectModal({
                ...this.areaTextOptions(h),
                text,
                label: h.label,
                url: null,
                sourceAreaId: h.id,
                persistent: true,
                animate: true,
                descriptionKey: this.pickupKey(h),
                readEffects: action && h.action === 'examine' && !h.move_after_examine && !h.action_video_id,
                actionAreaId: action && this.canPresentAction(h) ? h.id : null,
            });
        },
        tapArea(h) {
            if (this.introVideo || this.sceneTransition || this.pendingInventoryUse || this.inventoryDrag?.active) return;
            // Any area click first finishes the current message. Switching areas
            // is only allowed on a later click, after both text and audio finish.
            if (this.hasAreaMessage(h) || (this.narration?.sourceAreaId &&
                (!this.narration.revealed || this.narrationSpeaking))) {
                globalThis.window?.dispatchEvent(
                    new CustomEvent('description-area-click', {
                        detail: { key: this.previewId + ':' + this.narrationVersion },
                    }),
                );
                return;
            }
            if (
                this.areaDisabled(h) ||
                !containerVisible(h, this.displayed?.hotspots || [], this.flags)
            )
                return;
            if (this.narration?.sourceAreaId) {
                this.stopNarration();
                this.narration = null;
            } else if (this.narrationSpeaking) return;
            const text = this.areaDescription(h);
            if (h.click_behavior !== 'direct' && text.trim()) {
                this.soundPlayer?.click(h, this.audioSounds);
                this.presentArea(h, text);
            } else if (this.canPresentAction(h)) this.executeAreaAction(h);
            else if (text.trim()) this.presentArea(h, text, false);
        },
        areaInputLocked(h) {
            if (this.introVideo || this.sceneTransition) return true;
            if (this.pendingInventoryUse) return !this.hasAreaMessage(h);
            return this.narrationSpeaking && !this.narration?.sourceAreaId;
        },
        runPresentedAction() {
            if (!this.narration?.revealed || this.narrationSpeaking || this.sceneTransition || this.pendingInventoryUse)
                return;
            const h = this.currentHotspots.find((h) => h.id === this.narration.actionAreaId);
            if (h) this.executeAreaAction(h);
        },
        executeAreaAction(h, videoFinished = false) {
            if (this.introVideo || this.narrationSpeaking || this.sceneTransition || this.pendingInventoryUse || this.areaDisabled(h))
                return;
            if (!this.actionAllowed(h)) {
                const text =
                    (this.dualAction(h) && this.actionState(h) ? h.changed_unavailable_text : h.unavailable_text) || '';
                this.narration = null;
                if (text.trim()) this.presentArea(h, text, false);
                return;
            }
            this.narration = null;
            this.narrationVersion++;
            const video = !videoFinished && this.videos.find(v => v.id === h.action_video_id && v.url);
            if (video) {
                this.pendingVideoAction = { ...h };
                this.beginVideoPlayback(video, h.action_video_pause_music === true);
                return;
            }
            if (h.restart_game) {
                this.startPreview([], h.target_page_id + ':' + h.target_scene_id, null);
                return;
            }
            if (h.action === 'examine' && !h.move_after_examine && h.action_video_id) {
                this.applyFlagEffects(h);
                return;
            }
            this.activate({ ...h, _executeAction: true, show_hover_text: true });
            if (h.action === 'state_v2' && !h.auto_state?.enabled && !this.sceneTransition) this.presentArea(h);
        },
        sceneLoadProgress: { percent: 0, completed: 0, total: 0 },
        disposeMediaCache() {
            mediaCache.dispose();
        },
        videos: [],
        introVideo: null,
        pendingVideoAction: null,
        get videoSceneImage() {
            const action = this.pendingVideoAction;
            let scene = this.pendingVideoScene?.scene || this.displayed;
            if (action) {
                const targetId = action.action === 'state_v2' ? this.automaticDestination(action)
                    : action.target_scene_id || action.target_id;
                const pages = this.previewPages.map(page => ({ ...page,
                    visuals: page.id === this.editorPageId ? this.document.visuals : page.visuals }));
                if (targetId) scene = pages.flatMap(page => page.visuals || []).find(v => v.kind === 'scene' && v.id === targetId) || scene;
                else if (action.target_page_id) scene = pages.find(page => Number(page.id) === Number(action.target_page_id))?.visuals?.find(v => v.kind === 'scene') || scene;
            }
            return this.asset(scene?.asset_id)?.url || null;
        },
        initialSceneLoading: true,
        pendingVideoScene: null,
        introVideoLoading: true,
        seenSceneVideos: {},
        introVideoBlocked: false,
        introVideoFailed: false,
        pausedVideoAudio: [],
        beginScenePresentation(skipVideo = false) {
            const key = this.previewPageId + ':' + this.previewId;
            const alreadySeen = this.seenSceneVideos[key];
            const selected = this.videos.find((v) => v.id === this.displayed?.intro_video_id);
            if (selected) this.seenSceneVideos[key] = true;
            const video = !skipVideo && (!alreadySeen || this.displayed?.intro_video_repeat === true) && selected;
            if (!video?.url) {
                this.prepareSceneIntro();
                this.soundPlayer?.enter(this.displayed, this.audioSounds);
                return;
            }
            this.sceneIntroText = '';
            this.beginVideoPlayback(video, this.displayed?.intro_video_pause_music === true);
        },
        beginVideoPlayback(video, pauseMusic = false) {
            this.introVideoBlocked = false;
            this.introVideoFailed = false;
            this.introVideoLoading = true;
            this.pausedVideoAudio = Object.entries(this.soundPlayer?.channels || {})
                .filter(
                    ([channel, audio]) =>
                        audio &&
                        !audio.paused &&
                        (channel !== 'music' || pauseMusic),
                )
                .map(([, audio]) => audio);
            this.pausedVideoAudio.forEach((audio) => audio.pause());
            this.introVideo = { ...video, url: mediaCache.url(video.url) };
        },
        async playIntroVideo(element) {
            if (!this.introVideo) return;
            element.muted = !!this.audioMuted;
            try {
                await element.play();
                this.introVideoBlocked = false;
            } catch (_) {
                if (this.introVideo) this.introVideoBlocked = true;
            }
        },
        finishIntroVideo() {
            if (!this.introVideo) return;
            this.$refs?.introVideoPlayer?.pause();
            this.introVideo = null;
            this.pausedVideoAudio.forEach((audio) => {
                audio.play()?.catch(() => {});
            });
            this.pausedVideoAudio = [];
            if (this.pendingVideoAction) {
                const action = this.pendingVideoAction;
                this.pendingVideoAction = null;
                this.executeAreaAction(action, true);
                return;
            }
            if (this.pendingVideoScene) {
                this.previewPageId = this.pendingVideoScene.pageId;
                this.previewId = this.pendingVideoScene.scene.id;
                this.pendingVideoScene = null;
            }
            this.initialSceneLoading = false;
            this.prepareSceneIntro();
            this.soundPlayer?.enter(this.displayed, this.audioSounds);
            if (this.pendingModal) this.deliverPreviewMessage(this.pendingModal);
            this.pendingModal = null;
        },
        previewPages: [],
        editorPageId: null,
        document: { visuals: [] },
        preview: false,
        previewPageId: null,
        inventoryItems: [],
        previewId: '',
        flags: {},
        collected: [],
        assets: [],
        placedObjects: {},
        armedPickup: null,
        seenSceneIntros: {},
        sceneIntroText: '',
        automaticDestination(h) {
            const config = h.auto_state;
            if (h.action !== 'state_v2' || !config?.enabled) return null;
            return this.flags[h.state_flag] ? config.changed_target : config.original_target;
        },
        syncAutomaticStates() {
            const scenes = [
                ...this.document.visuals,
                ...this.previewPages.filter((p) => p.id !== this.editorPageId).flatMap((p) => p.visuals || []),
            ];
            const switches = scenes.flatMap((scene) =>
                (scene.hotspots || [])
                    .filter((h) => h.action === 'state_v2' && h.auto_state?.enabled && h.state_flag)
                    .map((h) => ({ scene, h })),
            );
            // Bound dependency propagation; conditions are evaluated against the current world state.
            for (let pass = 0; pass <= switches.length; pass++) {
                let updated = false;
                for (const { scene, h } of switches) {
                    const c = h.auto_state;
                    const rows = c.placements || [],
                        flags = c.flag_conditions || [];
                    const matches =
                        !!(rows.length + flags.length) &&
                        flags.every((f) => !!this.flags[f.key] === f.value) &&
                        rows.every((r) => {
                            const location = scene.hotspots.find((a) => a.id === r.area_id && a.action === 'pickup_v2');
                            return (
                                location &&
                                !this.collectibleEmpty(location) &&
                                (this.placedObjects[location.id] || location.object_id) === r.object_id
                            );
                        });
                    const next = matches || (!!c.sticky && !!this.flags[h.state_flag]);
                    if (!!this.flags[h.state_flag] !== next) {
                        this.flags[h.state_flag] = next;
                        updated = true;
                    }
                }
                if (!updated) break;
            }
        },
        prepareSceneIntro() {
            this.syncAutomaticStates();
            const key = this.previewPageId + ':' + this.previewId;
            this.sceneIntroText = this.seenSceneIntros[key] ? '' : this.displayed?.description || '';
            this.seenSceneIntros[key] = true;
        },
        gameplayMessages: {},
        gameplayMessage(key) {
            const defaults = {
                scene_load_error: ['Could not load the next scene image. Please try again.', 4000],
                no_scenes: ['Add a scene before testing scenes.', 4000],
                missing_pages: ['Reload the editor to load storyboard pages.', 4000],
                next_scene_missing: ['Add a scene to the next page before testing this transition.', 4000],
                destination_page_missing: [
                    'Choose an available destination page before testing this interaction.',
                    4000,
                ],
                destination_scene_missing: [
                    'Choose an available scene on the destination page before testing this transition.',
                    4000,
                ],
                placed: ['You place the item back.', 3000],
                occupied: ['This location is already occupied.', 3000],
                placement_denied: ['You cannot place an item here.', 3000],
                nothing: ['Nothing happens.', 3000],
                complete: ['You have reached the end of the story.', 5000],
            };
            const value = this.gameplayMessages[key];
            return {
                text: value ? value.text || '' : defaults[key][0],
                hideAfterMs: value?.timeout_ms ?? defaults[key][1],
            };
        },
        readyPickups: {},
        readDescriptions: {},
        examineMode: false,
        unavailableTimeout: 1000,
        unavailableText: 'You cannot do that yet.',
        inventory: [],
        flagDefinitions: [],
        modal: null,
        sceneTransition: false,
        transitionPhase: null,
        pendingInventoryUse: null,
        cursorTooltips: {},
        uiTheme: {id: 'text', renderer: 'existing_css'},
        soundPlayer: null,
        inventoryDrag: null,
        narration: null,
        suppressInventoryClick: false,
        inventoryUseTimer: null,
        audioSounds: [],
        inventoryOpen: false,
        narrationVersion: 0,
        modalTimer: null,
        pendingModal: null,
        transitionRequest: 0,
        transitionTimer: null,
        outgoingScene: null,
        skipJumpCount: 0,
        pickupTimer: null,
        pickupFlight: null,
        inventoryPulse: false,
        audioSettings: {},
        editorHoverAreaId: null,
        drawing: false,
        hotspotId: '',
        upgradePageLinks() {
            for (const page of this.previewPages) {
                const index = this.previewPages.indexOf(page),
                    next = this.previewPages[index + 1];
                for (const visual of page.id === this.editorPageId ? this.document.visuals : page.visuals || [])
                    for (const area of visual.hotspots || []) {
                        if (area.action === 'next_page') {
                            area.action = next ? 'page' : 'complete';
                            area.target_page_id = next?.id || null;
                        }
                        if (['inspect', 'transition', 'page'].includes(area.action)) {
                            const previous = area.action;
                            area.move_after_examine = previous === 'page' || !!area.target_id;
                            if (area.target_id) {
                                area.target_page_id = page.id;
                                area.target_scene_id = area.target_id;
                            }
                            area.target_id = null;
                            area.action = 'examine';
                        }
                        if (['choices', 'complete'].includes(area.action)) {
                            area.description ||= area.interaction_text || '';
                            area.action = 'examine';
                        }
                    }
            }
        },
        get playVisuals() {
            return this.preview && this.previewPageId !== this.editorPageId
                ? this.previewPages.find((p) => p.id === this.previewPageId)?.visuals || this.document.visuals
                : this.document.visuals;
        },
        get scenes() {
            return this.playVisuals.filter((v) => v.kind === 'scene');
        },
        get gameObjects() {
            return [
                ...new Map(
                    [
                        ...this.document.visuals,
                        ...this.previewPages.filter((p) => p.id !== this.editorPageId).flatMap((p) => p.visuals),
                        ...this.inventoryItems,
                    ]
                        .filter((v) => v.kind === 'object')
                        .map((v) => [v.id, v]),
                ).values(),
            ];
        },
        get objects() {
            return this.gameObjects;
        },
        get displayed() {
            return this.preview ? this.playVisuals.find((v) => v.id === this.previewId) : this.visual;
        },
        areaDisabled(h, flags = this.flags) {
            return (
                (h?.visible_when || []).some((c) => !!flags[c.key] !== c.value) ||
                (!!h?.disabled_when?.length && h.disabled_when.every((c) => !!flags[c.key] === c.value))
            );
        },
        get currentHotspots() {
            return (this.displayed?.hotspots || []).filter(
                (h) =>
                    !this.preview ||
                    (!this.areaDisabled(h) &&
                        containerVisible(h, this.displayed?.hotspots || [], this.flags) &&
                        !this.inactiveCollectible(h) &&
                        !(
                            h.action === 'pickup' &&
                            h.overlay_bounds &&
                            (h.flag_conditions || []).some(
                                (c) =>
                                    c.value === false &&
                                    this.flags[c.key] &&
                                    (h.flag_effects || []).some((f) => f.key === c.key && f.value === true),
                            )
                        ) &&
                        (h.action === 'pickup_v2' || h.overlay_bounds || !this.collected.includes(h.id))),
            );
        },
        asset(id) {
            const asset = this.assets.find((a) => a.id === Number(id));
            return asset ? { ...asset, url: mediaCache.url(asset.url) } : asset;
        },
        dialogPalette(v) {
            return {
                background: v?.dialog_background || '#292524',
                border: v?.dialog_border || '#a8a29e',
                footer: v?.dialog_footer || '#44403c',
            };
        },
        get gameDialogStyle() {
            const p = this.dialogPalette(this.displayed);
            return {
                ...guiThemeStyle(this.uiTheme),
                '--game-dialog-bg': p.background,
                '--game-dialog-border': p.border,
                '--game-dialog-footer': p.footer,
                '--game-dialog-text': contrastText(p.background),
                '--game-footer-text': contrastText(p.footer),
            };
        },
        collectibleFlag(h) {
            const key = 'collected_' + String(h.id).replaceAll('-', '');
            return h.collectible_flag || ((h.flag_effects || []).some((f) => f.key === key) ? key : null);
        },
        inactiveCollectible(h) {
            return h.action === 'pickup_v2' && h.placement_enabled === false && this.collectibleEmpty(h);
        },
        collectibleEmpty(h) {
            if (h.action !== 'pickup_v2') return false;
            const key = this.collectibleFlag(h);
            if (key) return !!this.flags[key];
            return this.placedObjects[h.id] ? false : this.collected.includes(h.id) || !!h.initial_empty;
        },
        collectibleObject(h) {
            return this.gameObjects.find((v) => v.id === (this.placedObjects[h.id] || h.object_id));
        },
        placementRules(h) {
            const rules = h.inventory_rules || [];
            return h.object_id && !rules.some((r) => r.object_id === h.object_id)
                ? [{ object_id: h.object_id, allowed: true, response: '' }, ...rules]
                : rules;
        },
        pickupKey(h) {
            return (
                this.previewPageId +
                ':' +
                this.previewId +
                ':' +
                h.id +
                (h.action === 'state_v2' ? ':' + !!this.flags[h.state_flag] : '')
            );
        },
        pickupReady(h) {
            if (['pickup', 'pickup_v2'].includes(h.action) && this.readyPickups[this.pickupKey(h)]) return true;
            return (
                ['examine', 'inventory', 'pickup', 'pickup_v2', 'state_v2'].includes(h.action) &&
                this.armedPickup === this.pickupKey(h)
            );
        },
        areaDescription(h) {
            if (h.action === 'pickup_v2' && h.initial_empty && !this.collectibleEmpty(h))
                return h.state_description || '';
            if (this.preview && this.collectibleEmpty(h) && !h.initial_empty) return '';
            return h.action === 'state_v2' && this.preview && this.flags[h.state_flag]
                ? h.state_description || ''
                : h.description || '';
        },
        descriptionRead(h) {
            return !!this.readDescriptions[this.pickupKey(h)];
        },
        showAreaDescription(h) {
            const text = this.areaDescription(h);
            if (!text.trim()) return;
            this.showObjectModal({
                ...this.areaTextOptions(h),
                label: h.label,
                text,
                url: null,
                persistent:
                    (h.action === 'examine' && !h.move_after_examine) ||
                    (!!h.auto_state?.enabled && !this.automaticDestination(h)),
                descriptionKey: this.pickupKey(h),
                pickupKey: this.pickupKey(h),
            });
        },
        examineArea(h) {
            if (!this.examineMode) return false;
            this.examineMode = false;
            this.soundPlayer?.click(h, this.audioSounds);
            this.showAreaDescription(h);
            return true;
        },
        hoverDetails(h) {
            return {
                ...h,
                description:
                    this.examineMode ||
                    h.show_hover_text === false ||
                    (h.action !== 'examine' && this.descriptionRead(h))
                        ? ''
                        : this.areaDescription(h),
            };
        },
        hasAreaAction(h) {
            if (h.action_video_id) return true;
            if (h.action === 'state_v2' && h.auto_state?.enabled)
                return this.preview
                    ? !!this.automaticDestination(h)
                    : !!(h.auto_state.original_target || h.auto_state.changed_target);
            return h.action !== 'examine' || !!h.move_after_examine;
        },
        descriptionStep(h) {
            if (h._executeAction) return false;
            return (
                this.hasAreaAction(h) &&
                h.show_hover_text === false &&
                !!this.areaDescription(h).trim() &&
                !(h.action === 'examine'
                    ? this.pickupReady(h) || (h.availability_enabled !== false && this.descriptionRead(h))
                    : this.descriptionRead(h))
            );
        },
        actionAllowed(h) {
            return !this.hasAreaAction(h) || this.conditionsMet(h);
        },
        denyAreaAction(h, point = null) {
            if (this.preview) {
                const text =
                    (this.dualAction(h) && this.actionState(h) ? h.changed_unavailable_text : h.unavailable_text) || '';
                if (text.trim()) this.presentArea(h, text, false);
                return;
            }
            this.showObjectModal({
                sourceAreaId: h.id,
                hideAfterMs: this.unavailableTimeout,
                animate: false,
                area: { x: point?.x ?? h.x + h.width / 2, y: point?.y ?? h.y + h.height / 2, width: 0, height: 0 },
                position: 'area_bottom',
                label: h.label,
                text: h.unavailable_text || this.unavailableText || 'You cannot do that yet.',
                url: null,
            });
        },
        conditionsMet(rule) {
            if (!containerVisible(rule, this.displayed?.hotspots || [], this.flags)) return false;
            if (rule.availability_enabled === false) return true;
            return (
                (rule.flag_conditions || [])
                    .filter(
                        (c) =>
                            !(
                                rule.action === 'pickup_v2' &&
                                rule.placement_enabled !== false &&
                                c.key === this.collectibleFlag(rule)
                            ),
                    )
                    .every((c) => !!this.flags[c.key] === c.value) &&
                (rule.required_items || rule.object_ids || []).every((id) =>
                    this.inventory.some((item) => item.id === id),
                )
            );
        },
        applyFlagEffects(rule) {
            for (const effect of rule?.flag_effects || [])
                if (this.flagDefinitions.some((f) => f.key === effect.key)) this.flags[effect.key] = effect.value;
            this.syncAutomaticStates();
        },
        areaLayer(h) {
            return (this.displayed?.hotspots || []).findIndex((area) => area.id === h.id) + 1;
        },
        style(h) {
            return `left:${h.x * 100}%;top:${h.y * 100}%;width:${h.width * 100}%;height:${h.height * 100}%`;
        },
        pageImageLabel(page) {
            const label = 'Page ' + page.position,
                title = (page.title || '').trim();
            return !title || title.toLowerCase() === label.toLowerCase() ? label : label + ' \u00b7 ' + title;
        },
        renderedAreaImageBounds(h) {
            const b = h.overlay_bounds || h.image_bounds || h,
                scale = (h.image_size ?? 100) / 100,
                anchor = { top: 0, middle: 0.5, bottom: 1 }[h.overlay_alignment || 'middle'] ?? 0.5;
            return {
                x: b.x + (b.width * (1 - scale)) / 2,
                y: b.y + b.height * (1 - scale) * anchor,
                width: b.width * scale,
                height: b.height * scale,
            };
        },
        areaImage(h) {
            if (h?.action === 'pickup_v2' && this.preview) {
                if (this.collectibleEmpty(h)) return null;
                if (this.placedObjects[h.id] && this.placedObjects[h.id] !== h.object_id)
                    return this.asset(this.collectibleObject(h)?.asset_id);
            }
            if (h?.action === 'state_v2')
                return !this.preview || this.flags[h.state_flag] ? this.asset(h.state_asset_id) : null;
            return h?.overlay
                ? this.asset(
                      h.overlay_asset_id !== undefined
                          ? h.overlay_asset_id
                          : this.gameObjects.find((v) => v.id === h.object_id)?.asset_id,
                  )
                : null;
        },
        areaImageStyle(h) {
            const b = this.renderedAreaImageBounds(h);
            return {
                position: 'absolute',
                left: ((b.x - h.x) / h.width) * 100 + '%',
                top: ((b.y - h.y) / h.height) * 100 + '%',
                width: (b.width / h.width) * 100 + '%',
                height: (b.height / h.height) * 100 + '%',
                maxWidth: 'none',
                objectFit: h.overlay_bounds ? 'fill' : 'contain',
                objectPosition: this.areaImagePosition(h),
                pointerEvents: 'none',
            };
        },
        areaImagePosition(h) {
            return (
                { top: 'center top', middle: 'center center', bottom: 'center bottom' }[h.overlay_alignment] ||
                'center center'
            );
        },
        get modalArea() {
            return this.playVisuals
                .find((v) => v.id === this.modal?.sceneId)
                ?.hotspots?.find((h) => h.id === this.modal?.areaId);
        },
        areaTextOptions(h) {
            return {
                sourceAreaId: h.id,
                animate: h.animate_hover_text === true,
                area: { x: h.x, y: h.y, width: h.width, height: h.height },
                position: h.description_position || 'top',
            };
        },
        areaTooltip(h) {
            if (
                this.narrationSpeaking ||
                this.sceneTransition ||
                this.pendingInventoryUse ||
                (this.hasAreaAction(h) && !this.descriptionStep(h) && !this.actionAllowed(h))
            )
                return '';
            return this.cursorTooltips[this.areaCursor(h)] || '';
        },
        areaCursor(h) {
            if (this.narrationSpeaking) return 'not-allowed';
            if (this.examineMode || (!this.descriptionRead(h) && this.areaDescription(h).trim())) return 'help';
            const action = this.hasAreaAction(h) && !this.descriptionStep(h);
            const value = action ? h.action_cursor : h.cursor;
            const allowed = ['pointer', 'grab', 'help', 'crosshair', 'zoom-in', 'alias', 'not-allowed', 'default'];
            if (allowed.includes(value)) return value;
            if (action && !this.actionAllowed(h)) return 'not-allowed';
            return action && this.collectibleEmpty(h)
                ? 'alias'
                : action && ['pickup', 'pickup_v2'].includes(h.action)
                  ? 'grab'
                  : action && h.action === 'inventory'
                    ? 'alias'
                    : 'pointer';
        },
        get narrationSpeaking() {
            return !!this.soundPlayer?.speaking;
        },
        stopNarration() {
            this.soundPlayer?.channel('voice', null);
            if (this.pendingInventoryUse) this.completeInventoryUse();
        },
        startInventoryDrag(event, item) {
            if (this.narrationSpeaking || event.button !== 0 || this.pendingInventoryUse || this.sceneTransition)
                return;
            this.inventoryDrag = {
                itemId: item.id,
                inventoryId: item.inventoryId || item.id,
                pointerId: event.pointerId,
                startX: event.clientX,
                startY: event.clientY,
                x: event.clientX,
                y: event.clientY,
                active: false,
                url: this.asset(item.asset_id)?.url,
            };
            event.currentTarget.setPointerCapture?.(event.pointerId);
        },
        moveInventoryDrag(event) {
            const drag = this.inventoryDrag;
            if (!drag || event.pointerId !== drag.pointerId) return;
            drag.x = event.clientX;
            drag.y = event.clientY;
            if (Math.hypot(drag.x - drag.startX, drag.y - drag.startY) > 6) {
                drag.active = true;
                this.modal = null;
                this.narration = null;
            }
        },
        endInventoryDrag(event, cancel = false) {
            const drag = this.inventoryDrag;
            if (!drag || event.pointerId !== drag.pointerId) return;
            this.inventoryDrag = null;
            if (!drag.active) return;
            this.suppressInventoryClick = true;
            setTimeout(() => {
                this.suppressInventoryClick = false;
            }, 0);
            const stage = this.$refs?.previewDialog?.querySelector('.preview-stage'),
                bounds = stage?.getBoundingClientRect();
            if (!bounds?.width || !bounds?.height) return;
            const point = {
                x: (event.clientX - bounds.left) / bounds.width,
                y: (event.clientY - bounds.top) / bounds.height,
                width: 0,
                height: 0,
            };
            const trayBounds = this.$refs?.previewDialog?.querySelector('.game-inventory')?.getBoundingClientRect();
            const overTray =
                trayBounds &&
                event.clientX >= trayBounds.left &&
                event.clientX <= trayBounds.right &&
                event.clientY >= trayBounds.top &&
                event.clientY <= trayBounds.bottom;
            const area =
                cancel || overTray
                    ? null
                    : [...this.currentHotspots]
                          .reverse()
                          .find(
                              (h) =>
                                  point.x >= h.x &&
                                  point.x <= h.x + h.width &&
                                  point.y >= h.y &&
                                  point.y <= h.y + h.height,
                          );
            const item = this.inventory.find((i) => (i.inventoryId || i.id) === drag.inventoryId);
            if (!item) return;
            if (
                area &&
                ['inventory', 'pickup_v2'].includes(area.action) &&
                containerVisible(area, this.displayed?.hotspots || [], this.flags)
            )
                this.useInventoryOnArea(drag.inventoryId, area, point);
            else this.animatePickup(item, point);
        },
        finishInventoryText(key) {
            const pending = this.pendingInventoryUse;
            if (!pending || pending.key !== key || this.inventoryUseTimer) return;
            const words = (this.narration?.text || '').split(/\s+/).length;
            this.inventoryUseTimer = setTimeout(
                () => this.waitForInventoryVoice(),
                this.narration?.hideAfterMs ??
                    (this.narration?.animate ? 1000 : Math.min(4000, Math.max(1200, words * 70))),
            );
        },
        waitForInventoryVoice() {
            const voice = this.soundPlayer?.channels?.voice;
            if (voice && !voice.ended && !voice.paused) {
                this.inventoryUseTimer = setTimeout(() => this.waitForInventoryVoice(), 150);
                return;
            }
            this.completeInventoryUse();
        },
        completeInventoryUse() {
            const pending = this.pendingInventoryUse;
            clearTimeout(this.inventoryUseTimer);
            this.inventoryUseTimer = null;
            this.pendingInventoryUse = null;
            if (
                !pending ||
                !this.preview ||
                pending.sceneId !== this.previewId ||
                pending.pageId !== this.previewPageId
            )
                return;
            if (pending.effectsApplied && !pending.placement && pending.rule?.target_id) {
                const destination = this.pageSceneDestinations.find((d) => d.sceneId === pending.rule.target_id);
                this.transitionScene(pending.rule.target_id, destination?.pageId ?? this.previewPageId);
            }
        },
        applyInventoryUseEffects(pending) {
            if (!pending || pending.effectsApplied) return;
            pending.effectsApplied = true;
            const index = this.inventory.findIndex((i) => (i.inventoryId || i.id) === pending.inventoryId);
            if (index < 0) return;
            const item = this.inventory[index];
            if (pending.placement) {
                const currentArea = this.displayed?.hotspots?.find((h) => h.id === pending.area.id);
                const currentRule = currentArea?.inventory_rules?.find((r) => r.object_id === item.id);
                if (
                    pending.allowed === true &&
                    currentArea?.placement_enabled !== false &&
                    currentRule &&
                    currentRule.allowed !== false &&
                    this.collectibleEmpty(currentArea) &&
                    this.conditionsMet(currentRule)
                ) {
                    this.soundPlayer?.action(currentArea, item, this.audioSounds);
                    this.inventory.splice(index, 1);
                    this.placedObjects[pending.area.id] = item.id;
                    this.collected = this.collected.filter((id) => id !== pending.area.id);
                    this.armedPickup = null;
                    const key = this.collectibleFlag(pending.area);
                    if (key) this.flags[key] = false;
                    this.applyFlagEffects(pending.rule);
                } else this.animatePickup(item, pending.point);
                return;
            }
            this.soundPlayer?.action(pending.area, item, this.audioSounds);
            if (pending.rule?.consume) this.inventory.splice(index, 1);
            this.applyFlagEffects(pending.area);
            this.applyFlagEffects(pending.rule);
            if (!pending.rule?.consume) this.animatePickup(item, pending.point);
        },
        cancelInventoryUse() {
            clearTimeout(this.inventoryUseTimer);
            this.inventoryUseTimer = null;
            this.pendingInventoryUse = null;
            this.inventoryDrag = null;
        },
        openAreaInteraction(h) {
            if (h.action === 'inventory') {
                this.inventoryOpen = true;
                this.modal = null;
                this.narration = {
                    ...this.areaTextOptions(h),
                    label: h.label,
                    text: h.description || '',
                    inventoryAreaId: h.id,
                };
                this.narrationVersion++;
                return;
            }
            this.inventoryOpen = false;
            this.showObjectModal({
                ...this.areaTextOptions(h),
                areaId: h.id,
                sceneId: this.previewId,
                label: h.label,
                text: h.interaction_text || '',
                position: h.description_position || 'top',
            });
        },
        useInventoryOnArea(inventoryId, area = this.modalArea, point = null) {
            if (
                this.areaDisabled(area) ||
                this.narrationSpeaking ||
                !this.preview ||
                this.pendingInventoryUse ||
                this.sceneTransition ||
                !['inventory', 'pickup_v2'].includes(area?.action) ||
                !containerVisible(area, this.displayed?.hotspots || [], this.flags)
            )
                return;
            const item = this.inventory.find((i) => (i.inventoryId || i.id) === inventoryId);
            if (!item) return;
            if (!this.actionAllowed(area)) {
                this.denyAreaAction(area, point);
                if (point) this.animatePickup(item, point);
                return;
            }
            if (area.action === 'pickup_v2') {
                const empty = this.collectibleEmpty(area),
                    rule = (area.inventory_rules || []).find((r) => r.object_id === item.id);
                const allowed =
                    empty &&
                    area.placement_enabled !== false &&
                    !!rule &&
                    rule.allowed !== false &&
                    this.conditionsMet(rule);
                this.inventoryOpen = true;
                this.modal = null;
                const custom = rule?.response?.trim() === 'You place the item back.' ? '' : rule?.response?.trim();
                const fallback = this.gameplayMessage(
                    !empty
                        ? 'occupied'
                        : area.placement_enabled === false
                          ? 'placement_denied'
                          : allowed
                            ? 'placed'
                            : 'nothing',
                );
                const override =
                    empty && area.placement_enabled !== false && rule ? custom || area.fallback_text?.trim() : '';
                this.narration = {
                    ...this.areaTextOptions(area),
                    ...(override ? { text: override } : fallback),
                    inventoryAreaId: area.id,
                    persistent: true,
                    animate: true,
                };
                this.narrationVersion++;
                if (!allowed) {
                    this.animatePickup(item, point || area);
                    return;
                }
                this.pendingInventoryUse = {
                    placement: true,
                    allowed,
                    inventoryId,
                    rule: rule ? JSON.parse(JSON.stringify(rule)) : null,
                    area: JSON.parse(JSON.stringify(area)),
                    point: point || { x: area.x + area.width / 2, y: area.y + area.height / 2 },
                    sceneId: this.previewId,
                    pageId: this.previewPageId,
                    key: this.previewId + ':' + this.narrationVersion,
                };
                this.applyInventoryUseEffects(this.pendingInventoryUse);
                if (!this.narration.text) this.completeInventoryUse();
                return;
            }
            const rule = (area.inventory_rules || []).find((r) => r.object_id === item.id);
            this.inventoryOpen = true;
            this.modal = null;
            const response = rule?.response?.trim() || area.fallback_text?.trim();
            this.narration = {
                ...this.areaTextOptions(area),
                ...(response ? { text: response } : this.gameplayMessage('nothing')),
                inventoryAreaId: area.id,
                persistent: true,
                animate: true,
            };
            this.narrationVersion++;
            this.pendingInventoryUse = {
                inventoryId,
                rule: rule ? JSON.parse(JSON.stringify(rule)) : null,
                area: JSON.parse(JSON.stringify(area)),
                point: point || { x: area.x + area.width / 2, y: area.y + area.height / 2, width: 0, height: 0 },
                sceneId: this.previewId,
                pageId: this.previewPageId,
                key: this.previewId + ':' + this.narrationVersion,
            };
            this.applyInventoryUseEffects(this.pendingInventoryUse);
            if (!this.narration.text) this.completeInventoryUse();
        },
        chooseAreaAction(index) {
            const area = this.modalArea;
            if (!this.preview || area?.action !== 'choices' || this.modal.sceneId !== this.previewId) return;
            const choice = area.choices?.[index];
            if (choice) {
                this.soundPlayer?.action(area, null, this.audioSounds);
                this.applyFlagEffects(choice);
                this.areaOutcome(area, choice.response || '', choice.target_id, choice.description_position);
            }
        },
        areaOutcome(area, text, targetId, position = area.description_position || 'top') {
            if (targetId && targetId !== this.previewId && this.scenes.some((v) => v.id === targetId)) {
                this.transitionScene(targetId);
                if (text)
                    this.showObjectModal({
                        ...this.areaTextOptions(area),
                        label: area.label,
                        text,
                        position,
                        url: null,
                    });
            } else {
                this.modal = {
                    ...this.modal,
                    ...this.areaTextOptions(area),
                    text,
                    position,
                    version: (this.modal.version || 0) + 1,
                    showItems: false,
                };
            }
        },
        deliverPreviewMessage(value) {
            if (value && !value.url && !value.areaId && !value.inventoryId && !value.inspect) {
                this.modal = null;
                this.narration = { ...value, position: value.position || 'top' };
                this.narrationVersion++;
            } else {
                this.narration = null;
                this.modal = value;
            }
        },
        showObjectModal(value) {
            if (this.sceneTransition || this.modalTimer !== null) {
                this.pendingModal = value;
            } else {
                this.deliverPreviewMessage(value);
            }
        },
        clearSceneTransition() {
            mediaCache.cancel();
            this.transitionRequest++;
            clearTimeout(this.transitionTimer);
            clearTimeout(this.modalTimer);
            this.transitionTimer = null;
            this.modalTimer = null;
            this.sceneTransition = false;
            this.transitionPhase = null;
            this.outgoingScene = null;
            this.pendingModal = null;
            this.pendingVideoScene = null;
        },
        resolveSceneEntry(pageId, sceneId) {
            const sceneAt = (p, id) =>
                (p === this.editorPageId
                    ? this.document.visuals
                    : this.previewPages.find((page) => page.id === p)?.visuals || []
                ).find((v) => v.kind === 'scene' && v.id === id);
            let scene = sceneAt(pageId, sceneId);
            this.skipJumpCount = 0;
            while (scene && this.skipJumpCount < 3) {
                const rule = (scene.skip_rules || []).find(
                    (r) =>
                        (r.object_ids?.length || r.flag_conditions?.length) &&
                        this.conditionsMet(r) &&
                        sceneAt(r.target_page_id, r.target_scene_id),
                );
                if (!rule) break;
                pageId = rule.target_page_id;
                sceneId = rule.target_scene_id;
                scene = sceneAt(pageId, sceneId);
                this.skipJumpCount++;
            }
            return { pageId, sceneId };
        },
        preloadScene(scene) {
            if (typeof document !== 'undefined' && typeof fetch === 'function') {
                if (this.soundPlayer) this.soundPlayer.resolveUrl = (url) => mediaCache.url(url);
                const request = this.transitionRequest;
                return mediaCache.load(sceneMedia(this, scene), (progress) => {
                    if (request === this.transitionRequest) this.sceneLoadProgress = progress;
                });
            }
            if (typeof Image === 'undefined') return null;
            const ids = [
                scene.asset_id,
                ...(scene.hotspots || []).flatMap((h) => [
                    h.overlay_asset_id,
                    h.state_asset_id,
                    this.gameObjects.find((o) => o.id === h.object_id)?.asset_id,
                    this.gameObjects.find((o) => o.id === this.placedObjects[h.id])?.asset_id,
                ]),
            ];
            return Promise.all(
                [...new Set(ids.map((id) => this.asset(id)?.url).filter(Boolean))].map(
                    (url) =>
                        new Promise((resolve, reject) => {
                            const image = new Image();
                            image.onload = () => {
                                if (image.decode) image.decode().then(resolve, reject);
                                else resolve();
                            };
                            image.onerror = () => reject(new Error('Scene image could not be loaded.'));
                            image.src = url;
                        }),
                ),
            );
        },
        transitionScene(id, nextPageId = this.previewPageId) {
            const destination = this.resolveSceneEntry(nextPageId, id);
            id = destination.sceneId;
            nextPageId = destination.pageId;
            const target = (
                nextPageId === this.editorPageId
                    ? this.document.visuals
                    : this.previewPages.find((p) => p.id === nextPageId)?.visuals || []
            ).find((v) => v.kind === 'scene' && v.id === id);
            if (!target || (id === this.previewId && nextPageId === this.previewPageId)) return;
            if (this.sceneTransition || this.introVideo) return;
            this.armedPickup = null;
            this.clearSceneTransition();
            const request = this.transitionRequest;
            this.sceneTransition = true;
            this.transitionPhase = 'loading';
            const commit = () => {
                if (request !== this.transitionRequest || !this.preview) return;
                const key = nextPageId + ':' + id;
                const video = this.videos.find(v => v.id === target.intro_video_id);
                if (video?.url && (!this.seenSceneVideos[key] || target.intro_video_repeat === true)) {
                    this.seenSceneVideos[key] = true;
                    this.pendingVideoScene = { pageId: nextPageId, scene: target };
                    this.modal = null;
                    this.narration = null;
                    this.narrationVersion++;
                    this.sceneTransition = false;
                    this.transitionPhase = null;
                    this.beginVideoPlayback(video, target.intro_video_pause_music === true);
                    return;
                }
                this.transitionPhase = 'out';
                this.transitionTimer = setTimeout(() => {
                    if (request !== this.transitionRequest || !this.preview) return;
                    this.modal = null;
                    this.narration = null;
                    this.narrationVersion++;
                    this.previewPageId = nextPageId;
                    this.previewId = id;
                    this.beginScenePresentation();
                    // Keep black over the DOM swap for a frame before revealing the new scene.
                    this.transitionTimer = setTimeout(() => {
                        this.transitionPhase = 'in';
                        this.transitionTimer = setTimeout(() => {
                            this.sceneTransition = false;
                            this.transitionPhase = null;
                            this.transitionTimer = null;
                            this.modalTimer = setTimeout(() => {
                                this.modalTimer = null;
                                if (!this.introVideo) {
                                    if (this.preview && this.pendingModal)
                                        this.deliverPreviewMessage(this.pendingModal);
                                    this.pendingModal = null;
                                }
                            }, 250);
                        }, 300);
                    }, 50);
                }, 300);
            };
            const loading = this.preloadScene(target);
            if (!loading) {
                commit();
                return;
            }
            return loading.then(commit).catch(() => {
                if (request !== this.transitionRequest) return;
                this.clearSceneTransition();
                notify(
                    this.gameplayMessage('scene_load_error').text,
                    'error',
                    this.gameplayMessage('scene_load_error').hideAfterMs,
                );
            });
        },
        selectInventoryItemForPlay(item) {
            if (
                !this.narrationSpeaking &&
                !this.suppressInventoryClick &&
                !this.inventoryDrag?.active &&
                !this.pendingInventoryUse
            )
                this.openInventory(item);
        },
        openInventory(item, click = true) {
            if (click) this.soundPlayer?.click({ ...item, action: 'inspect' }, this.audioSounds);
            this.inventoryOpen = false;
            this.showObjectModal({
                label: item.name,
                text: item.description || '',
                position: item.description_position || 'top',
                url: this.asset(item.asset_id)?.url,
                inventoryId: item.inventoryId || item.id,
            });
        },
        get inventoryActions() {
            const item = this.inventory.find((v) => (v.inventoryId || v.id) === this.modal?.inventoryId);
            return item ? this.objects.find((v) => v.id === item.id)?.actions || [] : [];
        },
        performObjectAction(id) {
            if (this.narrationSpeaking) return;
            if (!this.preview || !this.modal?.inventoryId) return;
            const index = this.inventory.findIndex((v) => (v.inventoryId || v.id) === this.modal.inventoryId);
            if (index < 0) return;
            const item = this.inventory[index],
                action = (this.objects.find((v) => v.id === item.id)?.actions || []).find((a) => a.id === id);
            const target = this.objects.find((v) => v.id === action?.target_id);
            if (!target) return;
            this.soundPlayer?.action({ action: 'inventory' }, item, this.audioSounds);
            const next = { ...target, inventoryId: item.inventoryId || item.id };
            this.inventory.splice(index, 1, next);
            this.applyFlagEffects(action);
            this.openInventory(next, false);
        },
        get previewInventoryItems() {
            const pages = this.previewPages.length
                ? this.previewPages
                : [{ id: this.editorPageId, position: 1, title: '', visuals: [] }];
            return [
                ...this.inventoryItems.map((v) => ({ ...v, sourcePage: 'Shared inventory' })),
                ...pages.flatMap((page) =>
                    (page.id === this.editorPageId ? this.document.visuals : page.visuals)
                        .filter((v) => v.kind === 'object')
                        .map((v) => ({ ...v, sourcePage: this.pageImageLabel(page) })),
                ),
            ].filter((v, index, all) => all.findIndex((i) => i.id === v.id) === index);
        },
        startPreview(initialItems = [], startKey = null, initialFlags = null, skipFirstVideo = false) {
            this.seenSceneIntros = {};
            this.seenSceneVideos = {};
            this.sceneIntroText = '';
            this.readyPickups = {};
            this.readDescriptions = {};
            this.examineMode = false;
            this.cancelInventoryUse();
            this.placedObjects = {};
            this.flags = Object.fromEntries(
                this.flagDefinitions.map((f) => [
                    f.key,
                    initialFlags === null ? !!f.initial : initialFlags.includes(f.key),
                ]),
            );
            for (const scene of [
                ...this.document.visuals,
                ...this.previewPages.filter((p) => p.id !== this.editorPageId).flatMap((p) => p.visuals || []),
            ])
                for (const h of scene.hotspots || []) {
                    const key = this.collectibleFlag(h);
                    if (
                        h.action === 'pickup_v2' &&
                        key &&
                        h.initial_empty !== undefined &&
                        (initialFlags === null || h.initial_empty === true)
                    )
                        this.flags[key] = !!h.initial_empty;
                }
            this.armedPickup = null;
            clearTimeout(this.pickupTimer);
            this.pickupFlight = null;
            this.inventoryPulse = false;
            this.narration = null;
            this.narrationVersion++;
            this.clearSceneTransition();
            this.previewPageId = this.editorPageId;
            if (!this.scenes.length) {
                notify(this.gameplayMessage('no_scenes').text, 'error', this.gameplayMessage('no_scenes').hideAfterMs);
                return;
            }
            const start = this.pageSceneDestinations.find((d) => d.key === startKey);
            this.previewPageId = start?.pageId ?? this.editorPageId;
            this.previewId = start?.sceneId ?? this.document.visuals.find((v) => v.kind === 'scene')?.id;
            this.preview = !!this.previewId;
            this.inventory = this.previewInventoryItems
                .filter((item) => initialItems.includes(item.id))
                .map((item) => ({ ...JSON.parse(JSON.stringify(item)), inventoryId: 'test:' + item.id }));
            const entry = this.resolveSceneEntry(this.previewPageId, this.previewId);
            this.previewPageId = entry.pageId;
            this.previewId = entry.sceneId;
            this.initialSceneLoading = true;
            const request = this.transitionRequest;
            const present = () => {
                if (request !== this.transitionRequest || !this.preview) return;
                this.sceneTransition = false;
                this.transitionPhase = null;
                this.soundPlayer?.start(this.audioSettings, this.audioSounds);
                this.beginScenePresentation(skipFirstVideo);
                if (!this.introVideo) this.initialSceneLoading = false;
            };
            const loading = this.preloadScene(this.displayed);
            if (loading) {
                this.sceneTransition = true;
                this.transitionPhase = 'loading';
                loading.then(present).catch(() => {
                    if (request !== this.transitionRequest) return;
                    this.closePreview();
                    this.$refs?.previewDialog?.close();
                    notify(this.gameplayMessage('scene_load_error').text, 'error');
                });
            } else present();
            this.inventoryOpen = false;
            this.collected = [];
            this.modal = null;
            this.cancelDraw?.();
            if (this.preview && !this.$refs?.previewDialog?.open) this.$refs?.previewDialog?.showModal();
        },
        closePreview() {
            this.$refs?.introVideoPlayer?.pause();
            this.introVideo = null;
            this.pendingVideoAction = null;
            this.pausedVideoAudio = [];
            this.seenSceneIntros = {};
            this.seenSceneVideos = {};
            this.sceneIntroText = '';
            this.readyPickups = {};
            this.readDescriptions = {};
            this.examineMode = false;
            this.soundPlayer?.stop();
            this.cancelInventoryUse();
            this.armedPickup = null;
            clearTimeout(this.pickupTimer);
            this.pickupFlight = null;
            this.inventoryPulse = false;
            this.narration = null;
            this.clearSceneTransition();
            this.inventoryOpen = false;
            this.preview = false;
            this.modal = null;
            this.inventory = [];
            this.collected = [];
            this.flags = {};
            this.placedObjects = {};
            this.editorHoverAreaId = null;
        },
        nextPreviewPage() {
            if (this.sceneTransition) return;
            const index = this.previewPages.findIndex((p) => p.id === this.previewPageId),
                next = this.previewPages[index + 1];
            if (index < 0) {
                this.showObjectModal({ label: 'Next page', ...this.gameplayMessage('missing_pages'), url: null });
                return;
            }
            if (!next) {
                this.showObjectModal({ label: 'Story complete', ...this.gameplayMessage('complete'), url: null });
                return;
            }
            const scene = next.visuals.find((v) => v.kind === 'scene');
            if (!scene) {
                this.showObjectModal({
                    label: next.title || 'Next page',
                    ...this.gameplayMessage('next_scene_missing'),
                    url: null,
                });
                return;
            }
            this.inventoryOpen = false;
            this.transitionScene(scene.id, next.id);
        },
        get pageSceneDestinations() {
            return this.previewPages.flatMap((page) =>
                (page.id === this.editorPageId ? this.document.visuals : page.visuals || [])
                    .filter((v) => v.kind === 'scene')
                    .map((scene) => ({
                        key: page.id + ':' + scene.id,
                        pageId: page.id,
                        sceneId: scene.id,
                        label: this.pageImageLabel(page) + ' — ' + scene.name,
                    })),
            );
        },
        movePreviewPage(id, sceneId = null) {
            if (this.sceneTransition) return;
            const page = this.previewPages.find((p) => Number(p.id) === Number(id));
            if (!page) {
                this.showObjectModal({
                    label: 'Move to page',
                    ...this.gameplayMessage('destination_page_missing'),
                    url: null,
                });
                return;
            }
            const visuals = page.id === this.editorPageId ? this.document.visuals : page.visuals;
            const scene = visuals.find((v) => v.kind === 'scene' && (!sceneId || v.id === sceneId));
            if (!scene) {
                this.showObjectModal({
                    label: page.title,
                    ...this.gameplayMessage('destination_scene_missing'),
                    url: null,
                });
                return;
            }
            this.inventoryOpen = false;
            this.transitionScene(scene.id, page.id);
        },
        animatePickup(object, area) {
            clearTimeout(this.pickupTimer);
            this.inventoryOpen = true;
            this.inventoryPulse = false;
            const flight = { itemId: object.id, url: null };
            this.pickupFlight = flight;
            const start = (attempt = 0) => {
                if (!this.preview || this.pickupFlight?.itemId !== flight.itemId || this.pickupFlight?.url) return;
                const tray = this.$refs?.inventoryTray || this.$refs?.previewDialog?.querySelector('.inventory-tray');
                const stage = tray?.closest('.preview-stage');
                const slot = Array.from(tray?.querySelectorAll('[data-inventory-item]') || []).find(
                    (el) => el.dataset.inventoryItem === object.id,
                );
                if (slot && tray) {
                    const itemBounds = slot.getBoundingClientRect(),
                        trayBounds = tray.getBoundingClientRect();
                    if (itemBounds.right > trayBounds.right) tray.scrollLeft += itemBounds.right - trayBounds.right + 8;
                }
                const bounds = stage?.getBoundingClientRect(),
                    target = slot?.querySelector('img')?.getBoundingClientRect();
                const ready =
                    bounds?.height > 0 &&
                    target?.width > 0 &&
                    target?.height > 0 &&
                    target.top >= bounds.top &&
                    target.top + target.height <= bounds.top + bounds.height + 1;
                // x-show reveals a previously closed tray on a frame, after Alpine's nextTick.
                if (!ready && attempt < 8 && typeof requestAnimationFrame === 'function') {
                    requestAnimationFrame(() => start(attempt + 1));
                    return;
                }
                this.inventoryPulse = true;
                this.pickupFlight = {
                    itemId: object.id,
                    url: this.asset(object.asset_id)?.url,
                    x: ((area.x || 0) + (area.width || 0) / 2) * 100,
                    y: ((area.y || 0) + (area.height || 0) / 2) * 100,
                    targetX: ready ? target.left + target.width / 2 - bounds.left : 100,
                    targetY: ready ? target.top + target.height / 2 - bounds.top : null,
                };
                this.pickupTimer = setTimeout(() => {
                    this.inventoryPulse = false;
                    this.pickupFlight = null;
                }, 650);
            };
            if (this.$nextTick) this.$nextTick(() => start());
            else start();
        },
        descriptionCompleted(key) {
            if (key !== this.previewId + ':' + this.narrationVersion || !this.narration) return;
            this.narration.revealed = true;
            if (this.narration.descriptionKey) this.readDescriptions[this.narration.descriptionKey] = true;
            const h = this.displayed?.hotspots?.find((h) => h.id === this.narration.sourceAreaId);
            if (h && this.narration.readEffects) {
                this.narration.readEffects = false;
                this.applyFlagEffects(h);
            }
            if (h && ['pickup', 'pickup_v2'].includes(h.action) && this.narration.pickupKey)
                this.readyPickups[this.pickupKey(h)] = true;
            this.finishInventoryText(key);
        },
        hasAreaMessage(h) {
            return !!this.narration?.text && this.narration.sourceAreaId === h.id;
        },
        clickAreaMessage(h) {
            if (this.examineMode || !this.hasAreaMessage(h)) return false;
            if (this.narration.revealed && !this.narrationSpeaking) {
                if (this.narration.descriptionKey) this.readDescriptions[this.narration.descriptionKey] = true;
                this.narration = null;
                this.narrationVersion++;
                return (
                    (h.action === 'examine' && !h.move_after_examine) ||
                    (!!h.auto_state?.enabled && !this.automaticDestination(h))
                );
            }
            globalThis.window?.dispatchEvent(
                new CustomEvent('description-area-click', {
                    detail: { key: this.previewId + ':' + this.narrationVersion },
                }),
            );
            return true;
        },
        activate(h, point = null) {
            if (!this.preview) {
                if (!this.drawing) this.hotspotId = h.id;
                return;
            }
            if (
                this.sceneTransition ||
                this.narrationSpeaking ||
                this.pendingInventoryUse ||
                this.inventoryDrag?.active
            )
                return;
            if (
                this.areaDisabled(h) ||
                !containerVisible(h, this.displayed?.hotspots || [], this.flags) ||
                this.inactiveCollectible(h)
            )
                return;
            if (this.examineArea(h)) return;
            if (h.action === 'inventory') this.inventoryOpen = true;
            if (!this.hasAreaAction(h) || this.descriptionStep(h)) this.soundPlayer?.click(h, this.audioSounds);
            if (this.hasAreaAction(h)) {
                const text = this.areaDescription(h),
                    key = this.pickupKey(h);
                if (this.descriptionStep(h)) {
                    this.armedPickup = key;
                    this.showObjectModal({
                        ...this.areaTextOptions(h),
                        label: h.label,
                        text,
                        url: null,
                        pickupKey: key,
                        examineKey: key,
                        descriptionKey: key,
                    });
                    return;
                }
                if (!this.actionAllowed(h)) {
                    this.denyAreaAction(h, point);
                    return;
                }
                this.armedPickup = null;
                // The description stage has already run; legacy handlers must not start it again.
                h = { ...h, show_hover_text: true };
            }
            if (!['pickup', 'pickup_v2', 'examine', 'inspect', 'inventory', 'choices'].includes(h.action))
                this.applyFlagEffects(h);
            if (h.action === 'state_v2') {
                if (h.auto_state?.enabled) {
                    const target = this.automaticDestination(h);
                    if (target) {
                        const d = this.pageSceneDestinations.find((d) => d.sceneId === target);
                        if (d) {
                            this.soundPlayer?.action(h, null, this.audioSounds);
                            this.movePreviewPage(d.pageId, d.sceneId);
                        }
                    } else this.showAreaDescription(h);
                    return;
                }
                if (!h.state_flag || !h.state_asset_id) return;
                this.armedPickup = null;
                this.narration = null;
                this.narrationVersion++;
                this.pendingModal = null;
                const changed = !this.flags[h.state_flag];
                this.flags[h.state_flag] = changed;
                this.syncAutomaticStates();
                this.soundPlayer?.action(h, null, this.audioSounds);
                this.soundPlayer?.channel?.('voice', null);
                if (!h._executeAction && !this.descriptionRead(h)) this.showAreaDescription(h);
                return;
            }
            if (h.action === 'page') {
                this.soundPlayer?.action(h, null, this.audioSounds);
                this.movePreviewPage(h.target_page_id, h.target_scene_id);
                return;
            }
            if (h.action === 'complete') {
                this.soundPlayer?.action(h, null, this.audioSounds);
                this.showObjectModal({ label: 'Story complete', ...this.gameplayMessage('complete'), url: null });
                return;
            }
            if (h.action === 'next_page') {
                this.nextPreviewPage();
                return;
            }
            if (['inventory', 'choices'].includes(h.action)) {
                if (h.action === 'choices') this.soundPlayer?.action(h, null, this.audioSounds);
                this.applyFlagEffects(h);
                this.openAreaInteraction(h);
                return;
            }
            if (h.action === 'pickup' && !h.overlay_bounds && this.collected.includes(h.id)) return;
            if (h.action === 'pickup_v2' && this.collectibleEmpty(h)) {
                this.inventoryOpen = true;
                return;
            }
            const object =
                h.action === 'pickup_v2'
                    ? this.collectibleObject(h)
                    : this.gameObjects.find((v) => v.id === h.object_id);
            if (['pickup', 'pickup_v2'].includes(h.action) && object) {
                if (this.narration?.pickupKey === this.pickupKey(h)) {
                    this.narration = null;
                    this.narrationVersion++;
                }
                if (this.pendingModal?.pickupKey === this.pickupKey(h)) this.pendingModal = null;
                this.armedPickup = null;
            }
            if (['examine', 'inspect'].includes(h.action)) this.applyFlagEffects(h);
            if (['pickup', 'pickup_v2'].includes(h.action) && object) {
                this.soundPlayer?.action(h, object, this.audioSounds);
                if (!this.inventory.some((v) => v.id === object.id)) {
                    this.inventory.push({ ...object, inventoryId: h.id });
                    this.animatePickup(object, h);
                }
                this.collected.push(h.id);
                if (h.action === 'pickup_v2') {
                    const key = this.collectibleFlag(h);
                    if (key) this.flags[key] = true;
                    delete this.placedObjects[h.id];
                }
                this.applyFlagEffects(h);
            }
            if (h.action === 'transition') this.soundPlayer?.action(h, null, this.audioSounds);
            if (['pickup', 'inspect', 'transition'].includes(h.action) && h.target_id)
                this.transitionScene(h.target_id);
            if (h.action === 'examine' && h.move_after_examine && h.target_page_id) {
                this.soundPlayer?.action(h, null, this.audioSounds);
                this.movePreviewPage(h.target_page_id, h.target_scene_id);
                return;
            }
            if (h.action === 'examine') this.showAreaDescription(h);
            if (h.action === 'inspect' && object)
                this.showObjectModal({
                    inspect: true,
                    label: object.name,
                    text: object.description || '',
                    position: object.description_position || 'top',
                    url: this.asset(object.asset_id)?.url,
                });
        },
    };
}
