{{-- Keep this guide aligned with ChatController, CompanionChanges and ImageDrafts when adding capabilities. --}}
@php
$example = ['changes'=>[
    ['scope'=>'copy_areas','page_id'=>12,'source_visual_id'=>'EXISTING_SOURCE_SCENE_UUID','visual_id'=>'EXISTING_DESTINATION_SCENE_UUID'],
    ['scope'=>'page','page_id'=>12,'fields'=>['narrative'=>'<h2>The empty stone</h2><p>The hollow stone lies open and empty.</p>']],
    ['scope'=>'create_page','after_page_id'=>12,'fields'=>[
        'title'=>'Back at the House','narrative'=>'Beyond the roses, the silent house waits.',
        'interaction_plan'=>'Click the door to advance to the following page.',
        'visuals'=>[['name'=>'Empty stone','kind'=>'scene','asset_id'=>25,'prompt'=>'',
            'description'=>'The stone has surrendered its secret.',
            'hotspots'=>[['label'=>'Approach the door','action'=>'page','target_page_id'=>13,'x'=>0.5,'y'=>0.2,'width'=>0.2,'height'=>0.5]]]],
    ]],
    ['scope'=>'image','page_id'=>12,'fields'=>['name'=>'Moonlit roses','prompt'=>'A close view of roses and a hollow stone in moonlight.','video_prompt'=>'A gentle breeze stirs the leaves.','references'=>[25],'settings'=>[]]],
]];
@endphp
<template x-teleport="body">
<dialog x-ref="companionHelp" class="proposal-review-dialog rounded-xl border border-stone-200 bg-white text-stone-900 shadow-xl dark:border-stone-700 dark:bg-stone-900 dark:text-stone-100" aria-labelledby="companion-help-title" @click="if($event.target===$el)$el.close()">
<div class="flex h-full min-h-0 flex-col">
<header class="flex shrink-0 items-center justify-between gap-4 border-b border-stone-200 p-4 dark:border-stone-700">
    <h2 id="companion-help-title" class="font-serif text-xl">Creative companion help</h2>
    <button type="button" class="btn-secondary" @click="$refs.companionHelp.close()" autofocus>Close</button>
</header>
<div class="min-h-0 flex-1 space-y-6 overflow-y-auto p-5 text-sm leading-6">
    <p>Ask in ordinary language. The companion focuses on your active page, tab, visual and clickable area, and can also work across your game. An animated Thinking… status appears immediately while waiting for a response. Replies stream as they are written. While the companion builds its structured proposal, an animated “Preparing changes…” label replaces the JSON. Review controls appear after the full response is received and validated. Changes appear under <strong>Review proposed changes</strong>: the dialog opens automatically for new proposals, with all results visible. Uncheck anything you want to skip, then confirm. Use the review link at the end of the message to reopen it.</p>
    <section class="space-y-2">
        <h3 class="font-semibold">Plan and write your game</h3>
        <ul class="list-disc space-y-1 pl-5">
            <li>Brainstorm a single-page story, a short story, or a longer linear adventure; develop clues, puzzles, characters and pacing.</li>
            <li>Edit the story title, premise, genre, language, visual style and story bible.</li>
            <li>Rewrite, shorten, expand or translate page titles, narrative overlays and interaction plans. Narrative overlays and interaction plans support Quill headings, bullet/numbered lists, emphasis, quotes and highlights. Titles and other fields stay plain text.</li>
            <li>Update the story’s saved writing-model and image-model preferences using a supplied model ID. Choose the model for the current chat in its dropdown.</li>
        <li>Set scene skip rules based on carried objects from any page, with a page/scene destination. All checked items are required; automatic chains stop after three jumps. Rules use visual fields.skip_rules with id, object_ids, target_page_id and target_scene_id.</li><li>Show an image inside a clickable area, independently of its action: overlay enables it, overlay_asset_id selects a story image, and overlay_alignment sets top, middle or bottom.</li><li>Writing a custom area description disables “Show item description”; otherwise hover text can use the linked inventory item description.</li><li>Enable or disable hover text per clickable area with show_hover_text (enabled by default), preserving its click action.</li><li>Set overlay_always to false for area images that appear only on hover or keyboard focus; the default is always visible.</li><li>Position hover text above or below its clickable area with description_position area_top or area_bottom; text remains inside the scene image.</li><li>Use animate_hover_text to opt into word-by-word hover text. By default, the full description fades in over 150ms.</li><li>Inventory-use rules can reference items from any page in the game, including keys collected on earlier pages.</li><li>Edit all areas in a named scene without selecting one. Copy drawn areas between scenes on the same page, preserving positions, sizes and interaction settings. Each copied area has its own review checkbox.</li></ul>
    </section>
    <section class="space-y-2">
        <h3 class="font-semibold">Add pages</h3>
        <ul class="list-disc space-y-1 pl-5">
            <li>Insert a page after an existing page, including its title, narrative and interaction plan. Later pages move down automatically.</li>
            <li>Create and edit shared inventory items, their images, descriptions and replacement actions. Link existing item IDs from any scene without duplicating them.</li>
            <li>Include new scene definitions, existing artwork and clickable areas in that new page. Objects belong to the shared Inventory library. The whole page is one selectable review item.</li>
            <li>For example: “Add a page after Rose Bushes using the empty-stone image, with one door interaction that goes to the next page.” Review any proposed clickable-area placement on the image.</li>
        </ul>
    </section>
    <section class="space-y-2">
        <h3 class="font-semibold">Edit scenes, objects and clickable areas</h3>
<p class="mt-2 text-sm">Create and edit persistent story flags in Story bible. Propose flag and inventory conditions for areas, hide or disable unavailable areas with an explanation, set flags on successful actions or inventory responses, and route scene entry by flag state. Flags remember world changes even after an item is consumed.</p>
        <ul class="list-disc space-y-1 pl-5">
            <li>Edit existing scene/object names, descriptions, top/bottom description placement, image/video prompts and assigned image assets.</li>
            <li>Edit an existing clickable area’s label, examine description, rectangle, overlay option, linked object and resulting scene.</li>
            <li>Set actions: examine, inspect an object, pick up an object, change scene, use inventory, offer named choices, or move to a selected scene on any page or finish the story.</li>
            <li>Write introductory interaction text, a default “nothing happens” response, and responses for individual inventory objects. Rules can consume an item and change the scene.</li>
            <li>Write named choices such as “Walk into the house”, their response text, scene destinations and text placement.</li>
            <li>When requested, prepare a named object and image prompt linked to an existing clickable area, creating that object if it does not exist. Drawing, renaming or deleting an area does not create, rename or delete its linked visuals.</li>
        </ul>
    </section>
    <section class="space-y-2">
        <h3 class="font-semibold">Prepare images and videos</h3>
        <ul class="list-disc space-y-1 pl-5">
            <li>Create independent image draft cards with short names, image prompts, video prompts, a model, reference selections and generation settings.</li>
            <li>Refine the image form you are working on. An image proposal creates a new draft card and opens image studio, preserving existing images.</li>
            <li>Prepare multiple image drafts in one response, or propose opening the image workspace for a prepared visual/object.</li>
            <li>Use uploaded or generated artwork already in the game as references. Chat receives image metadata, not the image pixels.</li>
        </ul>
    </section>
    <section class="space-y-2">
        <h3 class="font-semibold">What you still do in the editor</h3>
        <p>The companion can discuss any design idea, but its apply actions are limited to the capabilities above. Upload references, start paid image/video generation, publish or play the game, delete or reorder existing pages/visuals, change visual type, create new areas on existing scenes, using the editor controls. Shared object replacement actions can also be proposed through inventory changes. It cannot change accounts, keys or credits.</p>
        <p>Writing a prompt or applying an image draft does not render it. Click <strong>Generate image</strong> when ready. Chat requests themselves use your selected writing model and AI credits or personal key. If the story changed since a proposal was made, request a fresh proposal.</p>
    </section>
    <details class="rounded-lg border border-stone-200 p-4 dark:border-stone-700">
        <summary class="cursor-pointer font-semibold">Technical details: LLM context and change format</summary>
        <div class="mt-4 space-y-5">
            <section class="space-y-2">
                <h4 class="font-semibold">What is sent to the LLM</h4>
                <ol class="list-decimal space-y-1 pl-5">
                    <li>System instructions describing the linear game, allowed edits, response schema and creator-review requirement.</li>
                    <li>The saved story fields and model preferences; all pages with IDs, positions, titles, prose, rich-text deltas, complete visual documents and visual revisions. Visual documents include scenes, objects, descriptions, prompts, areas, rules and actions.</li>
                    <li>Reference metadata (ID, name, role and MIME type), unrendered image drafts (ID, page, name, image/video prompts), and the current image form’s name, prompts, model, settings and reference IDs.</li>
                    <li>The full story flag definitions and shared inventory; the active tab and selected page; selected visual and clickable area when editing scenes. Pending editor changes are flushed before sending.</li>
                    <li>The previous <strong>10 messages</strong> from this story’s conversation, oldest first, followed by the new message. Both user and assistant messages count. Older messages are not summarized. The UI loads up to 40 messages.</li>
                </ol>
                <p>Completed/failed image-generation history, image binary data and API keys are not included in the chat prompt. The OpenRouter key authenticates the API request through its Authorization header. Outside an existing story, the companion receives the new-story form and the user’s general companion conversation instead.</p>
            </section>
            <section class="space-y-2">
                <h4 class="font-semibold">Expected response</h4>
                <p>Chat uses an SSE stream with start, delta, complete and error events. The server collects the complete response and final token usage, saves the message and validated proposal, then sends the complete event with the updated credit balance. Partial proposal JSON is never actionable. Interrupted requests are not automatically retried; unconfirmed costs remain pending reconciliation. Image generation is a separate queued request.</p>
                <p>Helpful prose followed by one fenced <code>json</code> block containing <code>{"changes": [...]}</code>. The application hides recognized proposal JSON from the chat text and builds the review dialog. It validates and saves selected operations only after confirmation; it never executes code from the response.</p>
                <p>Each operation has a <code>scope</code> and <code>fields</code>. Existing targets use their supplied IDs. Omit unchanged fields. Narrative and interaction_plan accept HTML using p, br, h1–h3, ul/ol/li, strong, em, u, s, blockquote and mark; all other fields remain plain text. The server strips unsafe content and converts these two fields into validated Quill deltas plus plain text for the game. No raw model HTML is rendered. These are the currently accepted edit fields, read directly from the server’s validation rules:</p>
                <dl class="space-y-3">
                    @foreach(\App\Services\CompanionChanges::RULES as $scope => $fields)
                    <div><dt class="font-semibold"><code>{{ $scope }}</code></dt><dd class="break-words font-mono text-xs">{{ implode(', ', array_keys($fields)) }}</dd></div>
                    @endforeach
                </dl>
                <ul class="list-disc space-y-2 pl-5">
                    <li><code>story</code>: no target IDs. <code>page</code>: requires <code>page_id</code>. <code>visual</code>: requires <code>page_id</code> and <code>visual_id</code>. <code>hotspot</code>: also requires <code>hotspot_id</code>.</li>
                    <li><code>object</code>: targets an existing area using all three IDs; fields are <code>name</code> and <code>prompt</code>. Creates or updates its linked object.</li>
                    <li><code>create_page</code>: requires <code>after_page_id</code>; fields are <code>title</code>, optional <code>narrative</code>, <code>interaction_plan</code> and <code>visuals</code>. New visual/area IDs are assigned by the server. Maximum 100 pages per story.</li>
                    <li><code>image</code>: requires <code>page_id</code>; fields are <code>name</code>, required <code>prompt</code>, <code>video_prompt</code>, <code>model</code>, <code>references</code> (asset IDs) and <code>settings</code> (quality, resolution, aspect_ratio, background). Creates an unrendered draft, with no generation charge.</li>
                    <li><code>open_image_generation: true</code> on a visual/object operation adds a reviewable navigation step. Object preparation must also be selected. Image proposals navigate to their new draft automatically.</li>
                    <li>Area actions: <code>examine | inspect | pickup | transition | inventory | choices | page | complete</code>. Rectangle values are normalized from 0 to 1 and must fit within the image. <code>page</code> uses <code>target_page_id</code> for an existing page in this story and <code>target_scene_id</code> for its destination scene (set both together); <code>complete</code> ends the story. <code>target_id</code> links to a scene; <code>object_id</code> links to an object on that page.</li>
                    <li><code>inventory_rules</code>: an array of <code>{object_id, response, target_id, consume, description_position}</code>. <code>choices</code>: an array of <code>{label, response, target_id, description_position}</code>. Preserve the whole array when editing it. Text placement is <code>top</code> or <code>bottom</code>, defaulting to top.</li>
                </ul>
            </section>
            <section class="space-y-2">
                <h4 class="font-semibold">Example proposal</h4>
                <p>Illustrative IDs only: real proposals must use IDs from the supplied project. The image draft below belongs to existing page 12; it does not target the page being created.</p>
                <pre class="max-h-96 overflow-auto rounded-lg bg-stone-100 p-3 text-xs leading-5 dark:bg-stone-950"><code>{{ json_encode($example, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) }}</code></pre>
            </section>
            <section class="space-y-2">
                <h4 class="font-semibold">Validation and application</h4>
                <p>The server converts edits into field-level review items. Object preparation, image drafts and page creation are grouped operations. Confirmation sends <code>{"selected": [0, 2]}</code> to the message’s changes endpoint. The server checks ownership, story revision, affected visual revisions, allowed fields and links, then applies the selected operations in a transaction and records a story snapshot. It returns the new revision and an optional navigation URL. Invalid or stale proposals are rejected.</p>
                <p>Older story/pages and selected-area proposal formats are supported for compatibility. New responses should use the <code>changes</code> format. New-story form suggestions use <code>{"form": {"title": "…", "premise": "…", "genre": "Mystery", "page_count": 3}}</code>; the creator submits that form separately.</p>
                <p>This guide is maintained in <code>resources/views/studio/companion-help.blade.php</code>. Update it whenever the companion’s supported operations or context change.</p>
            </section>
        </div>
    </details>
</div>
</div>
</dialog>
</template>
