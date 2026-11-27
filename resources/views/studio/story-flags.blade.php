<section
    class="mt-8 space-y-4 rounded-xl border p-4 dark:border-stone-700"
    x-data="storyFlags(@js(route('flags.show', $story)))"
>
    <div class="flex items-center justify-between">
        <h3 class="font-serif text-xl">Story flags</h3><button
            type="button"
            class="btn-secondary"
            @click="$busy($el, async () => (add()))"
            :disabled="loading || flags.length >= 100"
        >+ Flag</button>
    </div>
    <p class="text-sm text-stone-500">Remember world changes such as an opened stone or unlocked door. Flags persist
        throughout a playthrough. Use them in scene skip rules and clickable area conditions.</p>
    <div
        class="story-flags-scroll"
        x-ref="flagList"
    >
        <table class="story-flags-table">
            <colgroup>
                <col class="flag-name-column">
                <col class="flag-key-column">
                <col class="flag-initial-column">
                <col class="flag-actions-column">
            </colgroup>
            <thead>
                <tr>
                    <th scope="col">Name</th>
                    <th scope="col">Key</th>
                    <th scope="col">On at game start</th>
                    <th scope="col"><span class="sr-only">Actions</span></th>
                </tr>
            </thead>
            <tbody><template
                    x-for="(flag,index) in flags"
                    :key="index"
                >
                    <tr
                        :data-flag-row="index"
                        :class="highlightedFlag === index ? 'story-flag-new' : ''"
                    >
                        <td><input
                                aria-label="Flag name"
                                x-model="flag.label"
                                maxlength="160"
                                @input="changed()"
                            ></td>
                        <td><input
                                aria-label="Flag key"
                                x-model="flag.key"
                                maxlength="64"
                                pattern="[a-z][a-z0-9_]*"
                                @change="changed()"
                                placeholder="stone_opened"
                            ></td>
                        <td class="text-center"><input
                                type="checkbox"
                                :aria-label="'On at game start: ' + flag.label"
                                x-model="flag.initial"
                                @change="changed()"
                            ></td>
                        <td><button
                                type="button"
                                class="btn-secondary"
                                @click="$busy($el, async () => (requestDelete(flag)))"
                                :disabled="deleteBusy || loading"
                            >Remove</button></td>
                    </tr>
                </template></tbody>
        </table>
    </div>
    <p
        x-show="!loading && !flags.length"
        class="text-sm text-stone-500"
    >No flags yet. Create one here, then choose it in Scenes.</p>
    <template x-teleport="body">
        <dialog
            x-ref="deleteFlagDialog"
            class="reference-dialog rounded-xl border bg-white text-stone-900 dark:bg-stone-900 dark:text-stone-100"
        >
            <div class="flex flex-col">
                <header class="flex items-center justify-between border-b px-5 py-4">
                    <h3 class="font-serif text-xl">Delete story flag?</h3><button
                        type="button"
                        class="btn-secondary"
                        :disabled="deleteBusy"
                        @click="$refs.deleteFlagDialog.close()"
                    >Cancel</button>
                </header>
                <div class="min-h-0 overflow-y-auto p-5 space-y-4">
                    <p
                        class="font-semibold"
                        x-text="deletingFlag?.label"
                    ></p>
                    <p class="text-sm">The flag and the condition/effect rows listed below will be removed. Local switch
                        or collected-state links using it will also be cleared.</p>
                    <p
                        x-show="!flagUses.length"
                        class="text-sm"
                    >This flag is not used anywhere.</p>
                    <ul class="space-y-3"><template
                            x-for="(use,i) in flagUses"
                            :key="i"
                        >
                            <li class="rounded-lg border p-3 text-sm">
                                <div x-text="use.location"></div>
                                <div
                                    class="text-xs opacity-75"
                                    x-text="use.type+(use.value===undefined?'':': '+(use.value?'On':'Off'))"
                                ></div>
                            </li>
                        </template></ul>
                    <p
                        x-show="deleteError"
                        x-text="deleteError"
                        class="text-sm text-red-600"
                        role="alert"
                    ></p>
                </div>
                <footer class="flex justify-end border-t px-5 py-4"><button
                        type="button"
                        class="btn-primary"
                        :disabled="deleteBusy"
                        @click="$busy($el, async () => (confirmDelete()))"
                        x-text="deleteBusy?'Deleting…':'Delete flag and remove uses'"
                    ></button></footer>
            </div>
        </dialog>
    </template>
</section>
