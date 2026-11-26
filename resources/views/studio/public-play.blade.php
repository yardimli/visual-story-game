<section
    class="space-y-2 border-t border-stone-200 p-4 dark:border-stone-700"
    x-data="{
        url: @js($story->public_play_token ? route('public.play', $story->public_play_token) : null),
        busy: false,
        message: '',
        async share(method) {
            this.busy = true;
            this.message = '';
            try {
                const response = await fetch(@js(route('public.play.enable', $story)), {
                    method,
                    headers: { 'Accept': 'application/json', 'X-CSRF-TOKEN': @js(csrf_token()) }
                });
                if (!response.ok) throw new Error('Could not update the public link. Please try again.');
                this.url = (await response.json()).url;
            } catch (error) {
                this.message = error.message;
            } finally {
                this.busy = false;
            }
        },
        async copy() {
            try {
                await navigator.clipboard.writeText(this.url);
                this.message = 'Link copied.';
            } catch {
                this.message = 'Select the link and copy it manually.';
            }
        }
    }"
>
    <h3 class="font-semibold">Public test link</h3>
    <p class="text-sm">Anyone with this link can play from the beginning without signing in. Starting inventory and flag selections above do not apply.</p>
    <button type="button" class="btn-secondary" x-show="!url" :disabled="busy" @click="share('POST')">Create public link</button>
    <div x-show="url" class="flex flex-wrap items-center gap-2">
        <input type="text" readonly aria-label="Public test URL" :value="url" @click="$el.select()" class="min-w-0 flex-1">
        <button type="button" class="btn-secondary" @click="copy()">Copy link</button>
        <a :href="url" target="_blank" rel="noopener noreferrer" class="btn-secondary">Open</a>
        <button type="button" class="btn-secondary" :disabled="busy" @click="share('DELETE')">Disable link</button>
    </div>
    <p role="status" class="text-sm" x-text="message" x-show="message"></p>
</section>
