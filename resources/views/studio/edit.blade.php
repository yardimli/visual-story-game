<x-studio-layout
    :title="$story->title"
    :story="$story"
    :page="$page"
>
    <div
        class="story-editor-workspace"
        x-data="studioAutosave({{ $story->revision }}, { tabs: true, pageId: {{ $page->id }}, position: {{ $page->position }}, imageBase: '{{ url('/stories/' . $story->id . '/pages') }}' })"
        @input="changed($event)"
        @change="changed($event)"
        @companion-filled.window="if($event.detail.tab)switchTab($event.detail.tab)"
    >
        @include('studio.story-header')
        <div class="story-editor-grid grid grid-cols-1">
            <div class="story-editor-column min-w-0">
                <div
                    id="scene-toolbar"
                    x-show="tab==='visuals'"
                    x-cloak
                    class="editor-tabs scene-toolbar-slot min-w-0 px-5 lg:px-8"
                ></div>
                <main
                    class="story-editor-content min-w-0 p-5 lg:p-8"
                    :style="tab === 'visuals' ? 'padding-top: 0' : null"
                >
                    @if($activeTab === 'page')
                    @include('studio.page-documents')
                    @endif
                    @if($activeTab === 'story')
                    <section
                        x-show="tab==='story'"
                        x-cloak
                        x-data="{ bibleTab: new URL(window.location.href).searchParams.get('bible') === 'characters' ? 'characters' : 'story' }"
                    >

                        <nav
                            class="mb-5 flex flex-wrap gap-2 border-b border-stone-200 pb-3 dark:border-stone-700"
                            aria-label="Story bible sections"
                        >
                            <template
                                x-for="section in [{id:'story',label:'Story'},{id:'characters',label:'Characters'},{id:'gameplay',label:'Gameplay'},{id:'flags',label:'Flags'},{id:'audio',label:'Music & sounds'},{id:'voices',label:'Voices'},{id:'models',label:'AI models'}]"
                                :key="section.id"
                            ><button
                                    type="button"
                                    class="btn-secondary"
                                    :class="bibleTab === section.id ? 'font-bold underline underline-offset-4' : ''"
                                    :aria-pressed="bibleTab === section.id"
                                    @click="bibleTab=section.id"
                                    x-text="section.label"
                                ></button></template>
                        </nav>
                        <form
                            data-autosave
                            @submit.prevent="flush()"
                            data-companion-form="story"
                            method="POST"
                            action="{{ route('stories.update', $story) }}"
                            class="space-y-5"
                        >@csrf @method('PUT')<input
                                type="hidden"
                                name="revision"
                                value="{{ $story->revision }}"
                            >
                            <div
                                x-show="bibleTab==='story'"
                                class="space-y-5"
                            >
                                @include('studio.story-cover')
                                @foreach (['title' => 'Title', 'genre' => 'Genre', 'language' => 'Language'] as $field => $label)
                                    <label class="field">{{ $label }}<input
                                            name="{{ $field }}"
                                            @required($field !== 'genre')
                                            value="{{ $story->$field }}"
                                        ></label>
                                @endforeach
                                @foreach (['premise' => 'Premise', 'style' => 'Visual direction', 'bible' => 'Complete story bible'] as $field => $label)
                                    <label class="field">{{ $label }}
                                        <textarea
                                            name="{{ $field }}"
                                            rows="{{ $field === 'bible' ? 12 : 3 }}"
                                        >{{ $story->$field }}</textarea>
                                    </label>
                                @endforeach
                            </div>
                            <div
                                x-show="bibleTab==='gameplay'"
                                class="space-y-5"
                            >
                                @include('studio.gui-theme-preview')
                                <div class="gameplay-message-row flex items-end gap-3">
                                    <label class="field min-w-0 flex-1">Unavailable action message<input
                                            type="text"
                                            data-speech-input
                                            name="unavailable_text"
                                            maxlength="3000"
                                            value="{{ $story->unavailable_text ?? 'You cannot do that yet.' }}"
                                        ></label>
                                    <label class="field w-36 shrink-0">Timeout (ms)<input
                                            type="number"
                                            name="unavailable_timeout_ms"
                                            min="0"
                                            max="60000"
                                            step="1"
                                            required
                                            value="{{ $story->unavailable_timeout_ms ?? 1000 }}"
                                        ></label>
                                </div>
                                @foreach (\App\Services\GameplayMessages::forStory($story) as $key => $message)
                                    <div class="gameplay-message-row flex items-end gap-3">
                                        <label class="field min-w-0 flex-1">{{ $message['label'] }}<input
                                                type="text"
                                                data-speech-input
                                                name="gameplay_messages[{{ $key }}][text]"
                                                maxlength="3000"
                                                value="{{ $message['text'] }}"
                                            ></label>
                                        <label class="field w-36 shrink-0">Timeout (ms)<input
                                                type="number"
                                                name="gameplay_messages[{{ $key }}][timeout_ms]"
                                                min="0"
                                                max="60000"
                                                step="1"
                                                required
                                                value="{{ $message['timeout_ms'] }}"
                                            ></label>
                                    </div>
                                @endforeach

                            </div>
                            <div
                                x-show="bibleTab==='models'"
                                class="space-y-5"
                            ><label class="field">Default LLM model ID<input
                                        name="llm_model"
                                        value="{{ $story->llm_model }}"
                                        placeholder="Choose a model in the chat selector"
                                    ></label>
                                <div x-data="modelPicker(@js($story->image_model ?? ''), 'image')"><label class="field">Image model for this story<select
                                            name="image_model"
                                            x-model="selected"
                                            @change="remember()"
                                        >
                                            <option value="">Choose later</option><template
                                                x-for="m in models"
                                                :key="m.id"
                                            >
                                                <option
                                                    :value="m.id"
                                                    x-text="modelLabel(m)"
                                                ></option>
                                            </template>
                                        </select></label>
                                    <p class="mt-2 text-xs text-stone-400">Dedicated OpenRouter image catalog. Pricing
                                        below is per provider and may depend on quality, size, references, and token
                                        usage. This saves your preference; image generation is in development.</p>
                                    <div
                                        class="mt-3 space-y-3 text-xs"
                                        aria-live="polite"
                                    >
                                        <p x-show="pricingLoading">Loading image pricing…</p><button
                                            type="button"
                                            x-show="pricingError"
                                            @click="$busy($el, async () => (loadPricing()))"
                                            class="underline"
                                        >Retry pricing</button><template
                                            x-for="(endpoint,index) in endpoints"
                                            :key="index"
                                        >
                                            <div class="rounded-lg border border-stone-200 dark:border-stone-700 p-3">
                                                <p
                                                    class="font-semibold"
                                                    x-text="endpoint.provider_name || endpoint.provider_slug"
                                                ></p><template
                                                    x-for="(line,i) in (endpoint.pricing || [])"
                                                    :key="i"
                                                >
                                                    <p
                                                        class="mt-1"
                                                        x-text="imagePriceLabel(line)"
                                                    ></p>
                                                </template>
                                                <p
                                                    x-show="!endpoint.pricing?.length"
                                                    class="mt-1"
                                                >Pricing unavailable for this endpoint.</p>
                                            </div>
                                        </template>
                                        <p x-show="selected && !pricingLoading && !pricingError && !endpoints.length">
                                            No endpoint pricing available.</p>
                                    </div>
                                </div>
                            </div>
                            <p class="text-xs text-stone-400">Changes save automatically.</p>
                        </form>
                        <div x-show="bibleTab==='flags'">@include('studio.story-flags')</div>
                        <div x-show="bibleTab==='characters'">@include('studio.characters')</div>
                        @include('studio.audio-settings')
                    </section>
                    @endif
                    @if($activeTab === 'visuals')
                        @include('studio.visuals')
                    @elseif($activeTab === 'inventory')
                        @include('studio.inventory')
                    @elseif($activeTab === 'audio')
                        @include('studio.audio')
                    @elseif($activeTab === 'video')
                        @include('studio.video')
                    @endif
                    @include('studio.speech')

                </main>
            </div>
        </div>
    </div>
</x-studio-layout>
