@php
    $cover = $story->coverImage;
    $coverOptions = [
        'url' => route('videos.index', $story),
        'selected' => $cover
            ? ['id' => $cover->id, 'name' => $cover->name, 'url' => route('assets.show', $cover)]
            : null,
    ];
@endphp
<div
    x-data="storyCover(@js($coverOptions))"
    class="space-y-3"
>
    <input
        type="hidden"
        name="cover_asset_id"
        x-ref="coverInput"
        value="{{ $story->cover_asset_id }}"
    >
    <p class="text-sm font-semibold">Story graphic</p>
    <template x-if="selected"><img
            loading="lazy"
            decoding="async"
            :src="$thumbnail(selected.url)"
            :alt="selected.name"
            class="max-h-48 max-w-full rounded-lg object-contain"
        ></template>
    <div class="flex gap-2">
        <button
            type="button"
            class="btn-secondary"
            @click="$busy($el, async () => (open()))"
        >Choose image</button>
        <button
            type="button"
            class="btn-secondary"
            x-show="selected"
            @click="choose(null)"
        >Remove image</button>
    </div>
    <dialog
        x-ref="coverDialog"
        class="image-form-dialog dialog-panel rounded-xl p-5"
        aria-label="Choose story graphic"
    >
        <header class="mb-4 flex items-center justify-between gap-3">
            <h2 class="font-serif text-xl">Choose story graphic</h2>
            <select
                class="min-w-0 rounded-lg"
                x-model="category"
                aria-label="Image category"
                @change.stop
            >
                <option value="">All categories</option>
                <template
                    x-for="(label, key) in categories"
                    :key="key"
                >
                    <option
                        :value="key"
                        x-text="label"
                    ></option>
                </template>
            </select>
            <button
                type="button"
                class="btn-secondary"
                @click="$refs.coverDialog.close()"
            >Close</button>
        </header>
        <div class="dialog-scroll-body">
            <p x-show="loading">Loading images�</p>
            <p
                x-show="error"
                x-text="error"
                role="alert"
                class="text-red-600"
            ></p>
            <p x-show="!loading && !error && !images.length">No project images available.</p>
            <div class="grid grid-cols-2 gap-3 md:grid-cols-3">
                <template
                    x-for="image in images.filter(image => !category || image.category === category)"
                    :key="image.id"
                >
                    <button
                        type="button"
                        class="rounded-lg border p-2 text-left"
                        :class="selected?.id === image.id ? 'ring-2 ring-amber-600' : ''"
                        @click="choose(image)"
                    >
                        <img
                            :src="$thumbnail(image.url)"
                            :alt="image.name"
                            class="h-36 w-full object-contain"
                            loading="lazy"
                        >
                        <span
                            class="text-sm"
                            x-text="image.name"
                        ></span>
                    </button>
                </template>
            </div>
        </div>
    </dialog>
</div>
