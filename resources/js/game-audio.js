export const speechText = (text) =>
    String(text || '')
        .replace(/<[^>]*>/g, ' ')
        .replace(/\s+/g, ' ')
        .trim();
export class GameAudio {
    constructor() {
        this.channels = { music: null, voice: null, effects: null };
        this.preview = null;
        this.speaking = false;
        this.muted = false;
        this.defaults = {};
    }
    play(sound, volume = 1, loop = false) {
        if (!sound?.url || typeof Audio === 'undefined') return null;
        const audio = new Audio(this.resolveUrl?.(sound.url) || sound.url);
        audio.volume = 1;
        audio.muted = this.muted;
        audio.loop = loop;
        audio.play().catch(() => audio.onPlaybackFailure?.());
        return audio;
    }
    channel(name, sound, loop = false) {
        this.channels[name]?.pause();
        const audio = this.play(sound, 1, loop);
        this.channels[name] = audio;
        if (name === 'voice') {
            this.speaking = !!audio;
            if (audio) {
                const finished = () => {
                    if (this.channels.voice === audio) this.speaking = false;
                };
                audio.addEventListener?.('ended', finished);
                audio.addEventListener?.('error', finished);
                audio.onPlaybackFailure = finished;
            }
        }
        return audio;
    }
    toggleMute() {
        this.muted = !this.muted;
        for (const a of [...Object.values(this.channels), this.preview]) if (a) a.muted = this.muted;
        return this.muted;
    }
    audition(area, sounds) {
        this.stopAudition();
        this.preview = this.play(sounds.find((s) => s.id === Number(area?.sound_id)));
    }
    stopAudition() {
        this.preview?.pause();
        this.preview = null;
    }
    start(settings, sounds) {
        this.stop();
        this.defaults = settings?.defaults || {};
        this.channel(
            'music',
            sounds.find((s) => s.id === Number(settings?.music_id)),
            !!settings?.music_loop,
        );
    }
    enter(scene, sounds) {
        this.stopAudition();
        this.channel('voice', null);
        if (scene?.music_mode === 'stop') this.channel('music', null);
        else if (scene?.music_mode === 'replace')
            this.channel(
                'music',
                sounds.find((s) => s.id === Number(scene.music_id)),
                !!scene.music_loop,
            );
        if (scene?.sound_id)
            this.channel(
                'effects',
                sounds.find((s) => s.id === Number(scene.sound_id)),
            );
    }
    resolve(value, fallback) {
        return value === 'silent'
            ? null
            : value === undefined || value === null || value === ''
              ? fallback
              : Number(value);
    }
    click(area, sounds) {
        const id = this.resolve(
            area.sound_id,
            this.defaults[!area.action || area.action === 'inspect' ? 'examine' : area.action]?.click,
        );
        if (id)
            this.channel(
                'effects',
                sounds.find((s) => s.id === Number(id)),
            );
    }
    action(area, item, sounds) {
        const fallback =
            area.action === 'state_v2'
                ? null
                : this.resolve(item?.action_sound_id, this.defaults[area.action || 'inventory']?.action);
        const id = this.resolve(area.action_sound_id, fallback);
        if (id)
            this.channel(
                'effects',
                sounds.find((s) => s.id === Number(id)),
            );
    }
    speak(text, sounds) {
        const normalized = speechText(text);
        this.channel(
            'voice',
            normalized ? sounds.find((s) => s.source_text && speechText(s.source_text) === normalized) : null,
        );
    }
    stop() {
        this.speaking = false;
        this.stopAudition();
        for (const key of Object.keys(this.channels)) {
            this.channels[key]?.pause();
            this.channels[key] = null;
        }
    }
}
