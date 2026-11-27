import { gameRuntime } from './game-runtime.js';
import { GameAudio } from './game-audio.js';

// The player consumes a self-contained story payload and never calls editor APIs.
export function gamePlayer(game) {
    const player = gameRuntime();
    return Object.defineProperties(
        player,
        Object.getOwnPropertyDescriptors({
            audioMuted: false,
            init() {
                this.videos = game.videos || [];
                this.previewPages = game.pages;
                this.editorPageId = game.start.pageId;
                this.document = { visuals: game.pages.find((p) => p.id === this.editorPageId)?.visuals || [] };
                this.inventoryItems = game.inventory;
                this.assets = game.assets;
                this.flagDefinitions = game.flags;
                this.gameplayMessages = game.messages || {};
                this.uiTheme = game.uiTheme;
                this.cursorTooltips = game.cursorTooltips;
                this.unavailableText = game.unavailableText;
                this.unavailableTimeout = game.unavailableTimeout;
                this.audioSettings = game.audioSettings;
                this.audioSounds = game.sounds;
                this.soundPlayer = new GameAudio();
                this.upgradePageLinks();
                window.vsgPlayer = this;
            },
            begin() {
                this.startPreview(
                    game.start.inventory,
                    game.start.pageId + ':' + game.start.sceneId,
                    game.start.flags,
                    game.start.skipFirstVideo,
                );
            },
            destroy() {
                this.closePreview();
                this.disposeMediaCache();
                if (window.vsgPlayer === this) delete window.vsgPlayer;
            },
        }),
    );
}
