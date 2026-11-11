# Visual Novel Creator Game Design Document

Source: https://chatgpt.com/space/page_2a58c5a302008191b1e3edf3894693fe

This design defines a Laravel platform where users create, publish, discover, and play cinematic interactive stories. Supported formats include single-page stories, short stories of 2–5 pages, and longer novels of approximately 40–50 pages. Each page combines a short video, one or more illustrated scenes, concise narrative text, and point-and-click interactions. Storyboard entries are pages; scene and object visuals belong within a page. Every format is fully preplanned before asset production.

The story follows one fixed route and ending. Players explore, collect items, use tools, and solve puzzles to advance along that route. Local scene changes and different orders of exploration do not create branching story paths.

The original requirements form the core below. Features identified as proposed additions or later releases extend that concept. This is a product and game design specification, including the planned interface; it is not an implemented application.

# 1 Product foundations

- **Story first:** complete the narrative, scene breakdown, and item dependencies before producing expensive media.

- **Cinematic introduction:** each page begins with a roughly 10–15 second video generated locally in ComfyUI, using either image-to-video with a starting image or first-frame and last-frame image inputs.

- **Readable interactive scene:** after the video, a deliberately authored static image becomes the authoritative playable scene.

- **Meaningful exploration:** objects respond with descriptions, inventory actions, puzzle interactions, and visible changes.

- **Linear progression:** multi-page stories advance through one fixed sequence. Single-page stories resolve within their sole page. Every format has one planned ending and no story forks.

- **Prepared assets:** generate and approve scene images, edits, item renders, and videos during authoring. Normal play does not wait for AI generation.

Creators and players use the same account. A user can publish their own games while maintaining separate progress in other creators’ games.

## Story lengths

| Format | Page count | Planning and completion |
| --- | --- | --- |
| Single-page story | 1 | Plan the full setup, interactions, local state changes, and ending within one scene |
| Short story | 2–5 | Plan every page and all cross-page item dependencies before generating media |
| Full novel | Approximately 40–50 | Plan the complete story, scene sequence, inventory dependencies, and ending |

These are production formats, not prototype restrictions. The underlying page model supports any positive page count. Every format requires the same complete storyboard, interaction plan, asset list, and completion checks. A single-page story must provide every required item within that page or through explicitly planned starting inventory. Its completion control shows Finish Story rather than advancing to a nonexistent page.

# 2 Platform and interface

## Public library and player area

The main navigation contains **Discover**, **My Library**, and **Creator Studio**.

Discover presents published games in a cover grid with title, author, short synopsis, genre, language, and page count. Include search, filters, and a game detail screen with a larger cover, description, content notices, and Start or Continue. My Library groups games into In Progress and Completed. Proposed later additions include bookmarks, ratings, and curated collections.

Sign-up and sign-in provide access to authoring and saved playthroughs. Proposed default: visitors can browse without signing in; accounts are required for persistent progress.

## Creator dashboard

Display each project with its cover, draft or published status, page count, last edit, and production progress. Primary actions are Create Game, Continue Editing, Preview, and Publish. Show story readiness separately from asset readiness, so a project can be fully written while its media is still in production.

## Project workspace

Use a desktop-oriented editor with the following workspace regions. The creator chat is dockable and resizable so it remains available alongside the story or scene canvas.

| Region | Contents |
| --- | --- |
| Top bar | Project title, save status, generation activity, Preview, Publish |
| Left sidebar | Ordered page list with thumbnails, titles, readiness indicators, and drag-to-reorder |
| Center workspace | Story text, scene canvas, media comparison, or playtest depending on selected tab |
| Right inspector | Selected object properties, action rules, state, prompts, and asset settings |
| Creator chat | Persistent conversation, LLM selector, attached references, proposed edits, generation actions, and job results |

Project-level navigation contains Story, Pages, Items, Assets, and Publish. The editor tabs are Page plan, Scenes, Image generation, Inventory, and Story bible. Reference images are uploaded and selected in the image-generation dialog; there is no separate References tab.

## Creator chat and model selection

The game editor includes a persistent chat window where the creator works with their selected OpenRouter LLM. The assistant can write a story from a premise, plan all pages, revise existing prose, adjust pacing, design interactions, check item dependencies, and prepare the complete asset plan.

The chat can generate and edit image prompts and video prompts, then initiate image generation and enqueue ComfyUI video generation through application actions. The LLM plans and orchestrates these actions; image models produce images and the Windows ComfyUI worker renders videos.

Provide separate selectors for the OpenRouter LLM and the OpenRouter image model. Let the creator choose any OpenRouter LLM available to their account rather than a fixed curated writing-model list. Show the selected model on each response and preserve conversation history when switching models. Show capability limitations explicitly; never silently substitute another model.

Chat context includes the current story bible, page order, current page and selection, relevant inventory dependencies, approved assets, reference attachments, and the current draft revision. Allow the creator to target the whole story, a page, an object, or a prompt. Keep an explicit context summary for long projects and retrieve relevant details rather than silently dropping established story facts.

Show proposed story and gameplay changes as reviewable edits with Apply and Undo. A direct creator command may apply changes within its stated scope, with a visible change summary and revision history. Chat edits and form edits update the same structured project data. Validate model output before applying changes; models without tool support can still draft content, with validated application actions handling structured edits and generation.

Creators can switch freely between planning and media creation. No complete-plan approval is required. Each generation action only requires its own prompt, compatible model, and necessary input assets. Show prompts, reference thumbnails, selected models or workflows, target assets, and estimated work in generation cards. An explicit Generate action starts the requested jobs. Return progress and completed previews to the conversation and asset library; a text reply alone must never imply a render completed.

The scene canvas supports zoom, polygon or rectangle hotspot drawing, object labels, and an overlay toggle. Selecting a hotspot opens a plain-language rule builder: **When the player uses [brass key] on [chest], if [chest is locked], then [open chest], [show updated scene], and [display text].**

Show scene states as named thumbnails, such as Original, Key Taken, Chest Open, and Chest Empty. Selecting a state shows its image and active hotspots. Display local transitions in a list; the primary story navigator remains a straight sequence.

## Player interface

The scene is the main visual surface. Place concise narrative text in a dismissible lower overlay, inventory in a bottom tray, and a small toolbar for journal, hints, settings, and pause. Keep Continue visible but disabled until required objectives are met, with an understandable reason.

Container contents and close-up puzzles open focused dialogs over the scene. On smaller screens, inventory becomes a drawer and dialogs fit the available width. Avoid cropping away playable areas; letterbox the scene when needed.

# 3 How a game is created

1. **Create the project.** Enter title, premise, genre, language, visual direction, and story length: one page, 2–5 pages, or a longer novel. Choose the OpenRouter LLM and image model; these choices can be changed later.

2. **Write the complete story.** Write manually or work with the selected OpenRouter LLM in the creator chat to draft, modify, and plan the complete story. Define the beginning, discoveries, obstacles, climax, and ending. Maintain a story bible containing characters, locations, recurring objects, tone, and visual references.

3. **Plan every page.** Use the chosen page count and assign each page its narrative purpose, location, video action, playable scene, text, interactions, and completion conditions. Single-page and 2–5-page stories follow the same preplanning process as 40–50-page novels.

4. **Plan dependencies.** Specify where each required item is obtained, where it is used, whether it is consumed, and which later pages depend on it. Distinguish optional discoveries from required actions.

5. **Iterate on the storyboard.** Review and revise the linear journey, interactions, and asset needs while creating scenes. Move between planning and production at any time; there is no full-plan approval step.

6. **Produce scene artwork.** Use the selected OpenRouter image model and creator-approved prompts. Upload one or more reference images for compatible image-edit models to guide composition, characters, objects, or style. Generate the base scene and the required video input images while maintaining visual consistency.

7. **Produce interactive assets.** Create removal edits, open or repaired object states, isolated inventory renders, and container contents. Define hotspots against the approved artwork.

8. **Produce motion.** Use chat or the video panel to prepare the motion prompt and select a ComfyUI workflow: image-to-video with one starting image, or first-frame and last-frame inputs. Queue the job on Ubuntu; the Windows worker pulls it, renders locally, and uploads the video to Ubuntu. Optionally generate ambient loops for approved scene states.

9. **Configure gameplay.** Attach descriptions, conditions, inventory effects, scene changes, puzzle actions, and the page completion rule.

10. **Playtest and validate.** Test from the beginning and from selected pages with controlled inventory and flags. Resolve missing media, dead ends, and visual mismatches.

11. **Publish a version.** Freeze a complete playable release. Subsequent edits remain in a draft until published as a new version.

# 4 Page specification

Every page should expose the following fields in the editor.

| Group | Required information |
| --- | --- |
| Story | Page number, working title, narrative purpose, location, on-screen prose |
| Video | ComfyUI workflow and version, input mode, start image, last image when required, motion prompt, settings, job status, approved clip targeting 10–15 seconds |
| Playable artwork | Authoritative static image and all reachable edited scene states |
| Atmosphere | Optional ambient loop, optional audio, applicable scene state |
| Objects | Stable object identity, label, hotspot geometry, description, visibility rules |
| Interactions | Trigger, prerequisites, effects, success text, unsuccessful attempt text |
| Inventory | Items gained, used, combined, retained, or consumed |
| Containers | Open conditions, contents, item artwork, emptied behavior |
| Completion | Required flags or actions; next sequential page or Finish Story on the final page |
| Production | Model selections, image and video prompts, reference uploads, asset versions, approvals, generation status, outstanding issues |

Suggested prose length is approximately 40–120 words per page, adjustable by the creator. Object descriptions should usually be one or two sentences. The introductory text appears after the video and remains available in a journal or text history.

For first-frame and last-frame video, the end reference and playable static image are separate assets. They may be the same image, but the playable image does not need to be the literal final video frame. Image-to-video requires only a starting image; its final frame is not constrained by a supplied last image. In both modes, review the transition to the approved playable image for visual continuity.

# 5 How a page is played

1. Load the page’s required scene artwork and interface resources.

2. Play its introductory video. Do not activate scene interactions during this sequence.

3. Transition to the approved playable image, using a short crossfade where appropriate.

4. Display the narrative overlay. Allow the player to read at their own pace and dismiss or revisit it.

5. Enter exploration mode. If enabled, play the ambient loop associated with the current scene state.

6. When the player begins an interaction, pause ambient motion and reveal the authoritative static image before resolving the action.

7. Evaluate the action, update inventory and flags, display feedback, and change scene artwork where required.

8. Save progress. Resume ambient motion only when a loop exists for the resulting state.

9. Enable Continue once the page’s completion conditions are satisfied. Continue opens the next sequential page. On the final page—including a single-page story—show Finish Story, present the planned ending, and mark the playthrough complete.

Proposed defaults: allow skipping or replaying introductory video, include reduced-motion mode, and fall back to the static scene if video cannot play. Replaying video is cosmetic and must not repeat item awards or reset puzzle progress.

A completed page may be reread through the journal without rewinding inventory. Full replay starts a separate playthrough. This prevents revisiting old pages from duplicating collected items.

# 6 Core interactions

| Interaction | Player experience | Authoring requirements |
| --- | --- | --- |
| Examine | Click an object to read a description | Hotspot and state-aware text |
| Look around | Click an authored background area or choose Look Around for environmental prose | Background description regions or page-level text |
| Pick up | Click a collectible to add it to inventory | Item render, inventory effect, removal image, disabled pickup hotspot |
| Use item | Drag an inventory item onto a target | Accepted item, prerequisites, effects, feedback |
| Open or unlock | Use a key, tool, or direct action | Locked and open states, appropriate artwork |
| Search a container | Open a game-style contents window | Contents list, isolated item images, collected-state tracking |
| Combine items | Combine collected parts into a useful object | Recipe, consumed inputs, output item render |
| Assemble or repair | Place parts onto an in-scene target | Required parts, intermediate or final state, completion rule |

Hovering or focusing an interactive object highlights its region and reveals a short label. Highlighting may use a polygon outline or authored mask; it should follow the object rather than imply every rectangle is a precise cutout.

Provide click-item-then-click-target as an alternative to dragging, along with touch and keyboard operation. Failed attempts produce useful contextual text and do not consume items.

Inventory persists across pages. Items can be reusable, consumable, or transformed. Do not impose an inventory capacity limit by default. Critical items cannot be discarded, and optional actions must not consume the only copy of a later required item.

## Containers without visible contents

An open chest need not reveal detailed contents in the scene illustration. It can simply look open. Clicking it opens a dialog with isolated renders, names, and descriptions of the items inside. Taking an item updates the dialog and inventory; it only changes the main scene when that item was actually visible there.

Support individual pickup and an optional Take All action. Once empty, the container displays an empty-state message and cannot award its contents again.

# 7 Additional interaction ideas

These are proposed additions compatible with a fixed story sequence.

| Addition | Example | Scope |
| --- | --- | --- |
| Close-up inspection | Zoom into a letter, carving, or mechanism | Early addition |
| Readable documents | Examine a diary and retain its text in the journal | Early addition |
| Switches and levers | Restore power or move a barrier | Early addition |
| Code and sequence puzzles | Enter symbols learned from an earlier clue | Later puzzle template |
| Rotate or align | Align gears, mirrors, or a constellation dial | Later puzzle template |
| Clean or reveal | Wipe dust from a sign to expose a clue | Later puzzle template |
| Repair workspace | Place collected parts into a small assembly panel | Later puzzle template |
| Evidence matching | Match a note to an object to record a deduction | Later puzzle template |
| Dialogue topics | Ask a character for background or clues | Later addition |
| Optional discoveries | Collect memories or lore entries | Later addition |

Dialogue topics and optional discoveries may change available descriptions or journal entries, but never change the next page or ending. Favor observation and reasoning over reflex challenges. Optional puzzle assistance can reveal progressively stronger hints without changing the story route.

# 8 Scene state and visual consistency

Each page has a defined set of reachable local states. An interaction changes flags, inventory, hotspot availability, and, when necessary, the selected scene image. These are local state transitions, not narrative branches.

For example, a chest can be Locked, Open With Contents, or Empty. A key pickup changes both inventory ownership and the corresponding scene visibility. The same logical state must always resolve to the same approved visual representation.

Independent visual changes can multiply the required artwork. Three independently removable objects can produce up to eight visible combinations. The editor must identify every reachable combination and whether it has a valid image.

For the initial release, keep visually significant changes manageable with explicit states and, where appropriate, an authored action order. Use container dialogs for small contents that do not need to appear in the scene. A later layered compositing system may reduce image counts, but it requires clean background plates and careful handling of shadows and occlusion.

Generate edits from approved references with constrained masks where supported. Review unchanged regions, perspective, character appearance, and object identity. Regenerating artwork marks related hotspots and dependent media for review; it must not silently replace approved assets.

Ambient loops belong to specific scene states. Never resume an earlier loop that restores a collected object or closes an opened chest. If the updated state has no matching loop, remain on its static image. Keep motion away from interactive object boundaries where possible.

# 9 AI asset production

OpenRouter is the required integration for creator-selected LLMs and image generation or editing models. Video generation runs in ComfyUI on an internet-connected Windows machine, using jobs managed by the hosted Laravel application on Ubuntu. fal.ai may remain a future optional image adapter; it is not required for the initial workflow.

## OpenRouter images and uploaded references

Provide a searchable image-model selector with project defaults and per-job overrides. Offer image generation and image editing according to the selected model’s verified capabilities. Creators can enter a prompt and upload one or more reference images to guide creation. Show reference thumbnails, allow removal and reordering, and let the creator label their intended role, such as character, environment, object, composition, or style. These labels guide prompt construction; they are not a promise of model-specific controls.

Retain original uploads and record exactly which references, in which order, were submitted with each prompt. Reuse approved references across pages to support visual consistency. Apply this workflow to full scenes, scene edits, isolated inventory items, and video input frames.

Validate input count, image formats, dimensions, and supported editing controls for the chosen model. If a model cannot accept the requested references, explain the limitation and let the creator select a compatible model or adjust the inputs. Do not silently discard references or treat an image-understanding model as an image-output model. Masks and transparent output are exposed only when supported; otherwise provide an explicit alternative workflow.

Store reference assets under the project’s access controls and transmit only the assets needed for the generation request. Model options should reflect the available OpenRouter catalog and account access; exact models and supported settings are verified during implementation rather than fixed by this specification.

## Asset workflow

1. Use creator chat or manual editing to prepare image and video prompts from the current story bible, available page plan, uploaded references, and camera instructions.

2. Generate candidate scene images with the selected OpenRouter image model, supplying the prompt and one or more references when using a compatible image-edit model; let the creator approve an output.

3. Generate the required edits: object removed, chest opened, mechanism repaired, or another authored visual change.

4. Generate isolated item renders using the approved scene object as a reference. Review identity and remove the background when necessary.

5. Generate container-only items from their own descriptions and consistent style references.

6. Queue the introductory video for local ComfyUI rendering using the selected image-to-video or first-frame and last-frame workflow. Review the uploaded result against the planned motion and playable scene.

7. Generate optional ambient loops through the Windows ComfyUI worker, explicitly associated with their playable scene state and reviewed for seamless looping.

8. Approve assets and bind them to the page’s interaction rules.

Creators can upload replacement artwork and videos as well as use generation. Each asset stores its purpose, source references, prompt, provider/model identifiers, settings, generation job, version, and approval state.

## Production controls

Show generation jobs as Queued, Waiting for Worker, Running, Uploading, Ready for Review, Approved, Failed, or Cancelled as applicable. Show the Windows worker’s availability and last contact so an offline machine is distinguishable from a rendering failure. Expose retry and cancellation with clear pending versus confirmed status. Preserve successful outputs when another job fails. Show available OpenRouter cost estimates and usage separately from local video queue time and render progress.

Approve still images before producing dependent videos. If a source asset changes, identify downstream assets that may need regeneration. Published games use stored, approved media; provider availability during authoring should not determine whether a published game remains playable.

# 10 Example page

**Page 12 — The abandoned workshop**

The video moves from a rain-streaked doorway toward a workbench and a locked chest. The playable image settles on the same room with clear targets.

Narrative: “The workshop smells of wet timber and cold iron. Someone left in a hurry. Beneath the bench, a thin brass edge catches the light.”

| Target | Action | Result |
| --- | --- | --- |
| Brass key beneath bench | Pick up | Add key; show scene with key removed |
| Locked chest | Examine | Describe a small brass lock |
| Locked chest | Use brass key | Set chest open; show open-chest image |
| Open chest | Search | Open contents dialog with copper coil and workshop note |
| Copper coil | Take | Add coil; remove it from container dialog |
| Workshop note | Read and take | Add note and journal entry; reveal repair clue |
| Broken transmitter | Use collected battery and coil | Consume parts; show repaired-transmitter image |
| Repaired transmitter | Activate | Play feedback; mark page complete |

The battery was collected on an earlier page. Publication validation checks that it must be available on reaching this puzzle. The key is retained unless explicitly marked consumable.

To limit artwork in this example, the key must be taken before the chest can open, and the transmitter requires the chest’s coil. Scene images cover Initial, Key Removed, Chest Open, and Transmitter Repaired. Taking hidden chest contents does not require additional full-scene images. Continue leads only to page 13.

# 11 Proposed Laravel architecture

Host Laravel on the online Ubuntu server for accounts, project ownership, authoring APIs, creator chat orchestration, OpenRouter requests, publishing, inventory validation, saved progress, and video job coordination. A proposed frontend is Vue with Inertia for the editor and player, with dedicated components for the scene and creator chat. The frontend choice remains an implementation decision.

Use a relational database for structured game data and Ubuntu background workers for OpenRouter requests, orchestration, and upload validation. A separate worker service on Windows drives the local ComfyUI instance. Upload completed videos to an authenticated endpoint on Ubuntu and store approved media in server-managed storage; object storage and a CDN can serve published assets. Keep OpenRouter credentials on the server and give the Windows worker its own scoped credential.

| Domain | Main records |
| --- | --- |
| Accounts | Users, creator profiles |
| Projects | Games, draft revisions, published versions |
| Story | Ordered pages, narrative text, story bible |
| Gameplay | Objects, hotspots, interactions, conditions, effects, scene states |
| Items | Item definitions, recipes, container contents |
| Production | Uploaded references, prompts, assets, asset versions, source dependencies, generation jobs and attempts |
| Creator chat | Conversations, messages, selected models, context summaries, proposed edits, applied revisions, action results |
| Video workers | Worker identity, capabilities, heartbeat, workflow versions, job leases, upload receipts |
| Players | Playthroughs, current page, inventory, flags, journal, action history |

Interaction rules should use a constrained set of supported actions rather than arbitrary creator-supplied code. Example effects include Add Item, Consume Item, Set Flag, Set Scene State, Show Text, Open Container, and Complete Page.

Apply each gameplay action atomically: verify the current state and prerequisites, update inventory and flags, and save the result together. Repeated clicks or retried requests must not duplicate rewards. Restrict project editing to authorized owners and keep each player’s progress separate.

Store hotspot coordinates relative to the source image so they scale with the rendered scene. Account for letterboxing and touch targets.

Publish immutable game versions. Existing playthroughs stay bound to the version they started unless an explicit migration is supported. This prevents a creator’s draft changes from invalidating player inventories or puzzle states.

## Windows ComfyUI video worker

The Windows machine initiates outbound authenticated HTTPS requests to Ubuntu. A small worker service polls or long-polls for compatible video jobs, drives ComfyUI locally, and uploads completed outputs. Ubuntu does not need to initiate a connection to Windows or expose the local ComfyUI interface to the internet.

1. Laravel creates a video job tied to a specific project, page, scene state, and draft revision. The payload includes the input mode, approved workflow identifier and version, prompt, supported settings, exact input asset versions, target duration, and output requirements.

2. The Windows worker registers its supported workflows and capabilities, reports availability, and requests work when it has capacity.

3. Ubuntu atomically assigns a compatible job with a time-limited lease and attempt token. The worker downloads its authorized input images and verifies they match the requested versions.

4. The worker fills a locally installed, approved ComfyUI workflow with the inputs and prompt. It uses either one starting image or first-frame and last-frame images, depending on the selected mode.

5. The worker submits the render locally, retains the local render identifier, and reports progress and heartbeats to Ubuntu. Workflow settings determine supported resolution, frame count, frame rate, and duration; the interface targets 10–15 seconds without promising unsupported values.

6. On completion, the worker uploads the video and output metadata to Ubuntu. Uploads should support retry or resumption without rerendering an already completed video.

7. Ubuntu validates the upload, including file type, size, checksum, and playable media metadata, then atomically records the asset and acknowledges the attempt. Only then does the job become Ready for Review.

8. The creator sees the result in chat and the page’s video panel, previews it, and approves it or requests a new version.

Use the same pipeline for introductory clips and optional ambient loops. Loop workflows must be explicitly marked as capable of producing loop candidates; every result still needs loop and scene-state review.

## Recovery and job ownership

If Windows is offline, jobs remain queued and the editor shows Waiting for Worker. If a heartbeat or lease expires, Ubuntu marks the attempt interrupted and can reassign it according to the retry policy. Only the current lease holder can finalize the job; stale attempts cannot overwrite newer results.

Persist local job and render identifiers so a worker restart can reconcile existing work before starting another render. Upload and completion requests use stable idempotency identifiers to prevent duplicate assets. Retain a completed local output until Ubuntu acknowledges receipt; cleanup follows a retention policy.

If source images, prompts, or the draft revision change while a job runs, keep its result associated with the original version and mark it outdated rather than automatically replacing current artwork. Cancelling a job prevents its result from becoming the active asset even if a local render finishes before cancellation reaches Windows.

Use scoped worker authentication, project-authorized downloads and uploads, and approved workflow templates with validated parameters. Creator chat may choose supported workflows and prompts, but must not submit arbitrary executable workflow definitions or machine commands to Windows.

# 12 Testing and publication

The Publish screen provides a readiness checklist with links to the affected page, object, or asset.

- Every page has approved playable artwork, narrative text, and a valid next sequential page, except the ending.

- Each required video is approved and has a static fallback.

- Every reachable state has valid artwork and correct hotspot visibility.

- Every required item can be obtained before use.

- Required consumable items cannot be spent in a way that makes completion impossible.

- Containers and pickups cannot award duplicate items.

- Completion conditions can be met, and no interaction points to a missing item, state, or asset.

- Active loops match their scene states.

- Every page can be traversed in a full playthrough for the selected length. Single-page stories finish correctly, 2–5-page stories preserve dependencies across pages, and longer novels remain consistent through the ending.

- Mouse, touch, keyboard access, text readability, and reduced motion work as intended.

- Creators can generate individual scenes at any story length without completing or approving the entire plan.

- Creator chat changes and manual edits stay synchronized, undo works, and model switching preserves story context.

- Single-reference and multiple-reference image jobs use the selected compatible model and retain all intended inputs.

- Both ComfyUI input modes complete the full queue, render, upload, review, and approval cycle.

- Worker disconnection, restart, expired leases, cancellation, duplicate completion requests, and interrupted uploads recover without duplicate rewards, assets, or stale replacements.

Automated checks validate references and dependencies. A complete manual playthrough remains necessary to assess puzzle clarity, story coherence, highlight accuracy, visual continuity, and the video-to-image transition.

Autosave after successful interactions and page advancement. On resume, restore the saved scene state and inventory without replaying rewards. Preload the next page when practical and recover gracefully from failed media loads.

# 13 Release scope

## Initial release

Deliver account registration, a browsable game library, single-page stories, 2–5-page short stories, longer linear novels, complete preplanning, a creator chat with selectable OpenRouter LLMs, selectable OpenRouter image models, prompt authoring, reference-image uploads for compatible edit models, and the Ubuntu-to-Windows pull-based ComfyUI job workflow supporting both video input modes. Include manual asset upload, scene-state editing, hotspots, descriptions, pickup, item use, containers, simple item combination, introductory video, optional state-specific ambient loops, inventory, autosave, preview, validation, and versioned publishing.

The interaction system must support the complete workshop example. Validate a complete single-page story, a 2–5-page short story, and a 40–50-page novel, including planning, generation, publishing, and playback. Exercise both ComfyUI input modes and multiple uploaded image references in the initial acceptance tests.

## Later releases

Add further providers, richer puzzle templates, advanced assembly, more capable visual masks and layers, dialogue topics, collaboration, localization, optional narration, creator analytics, and community discovery features.

## Decisions before implementation

Confirm the initial supported ComfyUI workflows and Windows GPU capacity, worker concurrency and retention policy, whether users bring OpenRouter keys or purchase platform credits, the first visual aspect ratio, and which additional puzzle templates ship initially. OpenRouter LLM and image-model selection, reference-image editing, all story lengths, and local ComfyUI video generation are part of the required scope. Proposed defaults remain a landscape scene, account-based saves, platform-managed jobs, and no competitive or time-limited mechanics.

Success means a creator can plan a complete linear novel, produce and review its assets, configure understandable interactions without code, publish it, and have a player finish it without missing assets, inconsistent inventory, or an unsolvable required puzzle.

# Implementation requirements

- Laravel 10 with Laravel Breeze for registration, login, password resets, and profile management.
- Tailwind CSS for the creator, discovery, and player interfaces.
- Preserve existing database values and OPEN_ROUTER_KEY in the project .env. Keep secrets out of source control.
- Platform-funded writing and image usage is limited to US $1 per user, interpreted as a lifetime allowance. Require a personal OpenRouter key to continue beyond it. Store personal keys encrypted and never return them to the browser.
- Enforce the allowance on the server with a usage ledger, concurrent-request protection, conservative reservations, and actual-cost reconciliation. An uncertain provider outcome must not release credit automatically.
- Image generation and LLM chat draw from one lifetime $1 trial allowance. Reserve image cost before queueing, then settle from catalog rates. Require a personal key once the shared allowance is exhausted.


## Persistent companion and explicit form submission
The authenticated studio includes a right-side creative companion on the dashboard, story workspace, AI settings, and profile. The desktop panel remains visible while the main page scrolls; small screens use an expanded stacked panel. Account chat persists across account pages, while each story retains its own contextual conversation. Public and future screens will adopt the companion as those flows are implemented.

The companion may suggest form drafts but cannot submit them. On the dashboard, a validated proposal fills story title, premise, genre, and page count. In the story workspace, a proposal fills the current page and/or story bible. The creator reviews or edits the fields, then presses Create draft, Save page, or Save story bible. Proposals for other pages remain in chat until that page is opened. Stale revision proposals cannot fill an updated story. Credentials and account security fields are excluded from form assistance.

Chat uses the same lifetime $1 allowance, encrypted personal-key routing, cost reservation, and reconciliation rules everywhere. The image model selector is separate from the writing model selector; selecting a model does not generate an image or incur a generation charge.

### Model policy and token accounting (updated)
`OPENROUTER_PROVIDERS=deepseek,anthropic,openai,moonshotai,google,qwen` controls the visible editorial catalog by model ID author prefix, not inference hosting provider. Changes apply after `php artisan config:clear` if configuration is cached. Text-output models are filtered by the editorial policy (legacy/coding/lite and small Qwen exclusions), with latest version/snapshot per product family; batch/free variants are omitted. Image-only models are excluded from this editorial catalog; a dedicated image catalog supplies generation models.

Dropdown prices are catalog USD per million input/output tokens. The app allowance now charges `(prompt_tokens × prompt_rate + completion_tokens × completion_rate)` in USD, rounded up to integer microUSD. Catalog API rates are per token. OpenRouter `usage.cost` is ignored, including for BYOK zero-cost responses. Applicable long-context and UTC schedule overrides are applied from a request-time pricing snapshot. Cache discounts and separate add-on charges do not alter this app token-rate formula; completion totals already include reasoning. Personal keys continue to bypass the platform allowance. Missing/invalid token counts retain the reservation for reconciliation. Historical settled records are not rewritten.

Image discovery update: the image selector now uses GET /api/v1/images/models and reads provider pricing/capabilities from /images/models/{author}/{slug}/endpoints. Text editorial exclusions do not apply; OPENROUTER_PROVIDERS and batch/free exclusions still apply. Display price lines by their image/megapixel/token units and variants. The image transport uses POST /api/v1/images with prompt/reference inputs, not chat completions. The creation queue, capability validation, and private asset storage are implemented; image jobs use shared trial credit or a saved personal key. Selecting a model makes no paid request.

Autosave update: existing story and page fields save automatically after a 400ms typing pause. Applying a companion proposal also autosaves. Writes are serialized across forms with revision checks; validation failures retain text, and ambiguous failures/conflicts stop automatic retries. The editor shows save status and waits for pending changes before navigation, chat or restore. Creation, uploads, and account/security actions remain explicit. This supersedes the earlier manual Save-button behavior for existing story/page forms.

## Scene artwork and interaction authoring
Each page owns a visual document protected by a concurrency revision with named scene states and object images. A scene state is a background and its clickable rectangles; an object image is an isolated prop or close-up, usable as an overlay, inspection window, or inventory preview. Images generated for the same visual remain separate assets until the creator chooses one.

For the Silent House example: author a porch background without the note, generate the note separately, and place its image in a hotspot rectangle. Clicking it opens the readable note close-up. A stone hotspot can open a separate close-up of the stone with a key, or switch to another scene state depicting the opened stone. A pickup hotspot may add an object to the preview inventory and switch to an empty/removed state. These are independent images, not automatic mutations of one background.

The Images & interactions tab provides named visuals, prompt/model/provider settings, private reference selection, generated versions, image assignment, normalized rectangle drawing and numeric coordinates, action editing, and a local resettable preview. Changes autosave independently of prose. Generate is explicit and queued; jobs snapshot input assets, prompt, validated settings, and pricing. Results never silently replace selected images or hotspot layouts. Image and chat requests share the free $1 trial credit; a saved personal key bypasses the trial balance. Published playthrough persistence and conditional crafting rules remain subsequent milestones.


Image routing update: the generation model dropdown displays endpoint catalog prices with image, megapixel, or million-token units and ranges across providers/settings. OpenRouter chooses the provider automatically; no `provider.only` constraint is sent. Optional settings are limited to common endpoint capabilities. Trial reservation uses the highest endpoint quote. Settlement uses the returned provider identity when available, or identical computed costs across endpoints; ambiguous provider-dependent costs retain their reservation for reconciliation rather than guessing. Existing queued jobs keep their original snapshots.


Image generation workspace: use the Image generation link for the current page. The dedicated page keeps the creative companion visible, shows generated images with their immutable request prompts/model names, and provides Generate scene, Generate object, and Edit this image. Editing selects the source render as a reference and produces a new version. The companion can propose the selected visual’s image prompt; applying it checks the visual ID/revision and autosaves only that prompt. Rendering remains an explicit action. The Interactions tab focuses on content, selected assets, clickable areas, and preview.


AI timeout policy update: reserved and uncertain requests expire after 180 seconds from enqueue/request creation, release their trial holds exactly once, and become failed. Late provider responses do not charge the released allowance. Completed billing remains intact. Background scheduler and page-request fallback enforce the deadline; upstream costs after timeout remain a platform risk. This supersedes indefinite retention of uncertain reservations.


### Companion context and creator review
The companion receives the complete saved authoring structure: story settings, all pages and their written fields, visual states and object prompts, clickable areas and links, and reference metadata. The active screen, page, visual, and selected area guide its focus. Image-generation job history and account credentials are excluded.

Replies propose changes rather than executing them. Each change appears as an expandable checklist item with its current and proposed values. Creators can uncheck changes before confirming. New proposals may target existing story, page, visual, and area fields across pages. Preparing a linked object image and opening its generation workspace are separately reviewed steps. Confirmed changes are validated against ownership and saved revisions, applied atomically, and recorded in revision history. Rendering remains an explicit separate action.

Draft interactions may temporarily lack an object or destination while the creator prepares assets. Existing links must still refer to valid visuals on the page. The editor retains its active tab in the URL, and an object-generation handoff opens the exact prepared visual.

### Image generation visual context
Opening image generation carries the selected scene/object visual in the URL. Its name and type are read-only in this workspace; naming remains in the content editor. Generated image cards show editable image names and links to every scene/object currently using the asset, including uses on other pages. Each link opens Interactions with that visual selected. Assignment is managed through the Interactions image picker, allowing one asset to serve multiple visuals. Generation history retains its original prompt and model.

### Inventory object transformations
Object visuals may define multiple named inventory actions. Each action replaces the selected inventory item with another object visual on the same page, including its image and available actions. Cyclic links are supported: a closed envelope can Open into an opened envelope, which can Close back into the closed one; Crumple and Uncrumple can connect further states. These local object states do not branch page progression. Creators author each object/image and link actions in the Interactions tab. The test dialog runs transformations in local inventory state without altering authored assets. Draft actions may lack a target; these actions are disabled in preview until linked.

Scene and object visuals each carry their own player-facing description. Inventory inspection opens a centered game dialog over the scene, displaying the current object's image and description with its available transformations at the bottom-left. Transformations update all three. Clickable-area descriptions are reserved for Examine actions; Inspect/Pickup use the linked object's description.

### Image-focused interaction preview
The test preview fits the scene image inside the viewport without surrounding header controls; outside click or Escape closes it. A bottom-left inventory icon toggles a horizontal tray over the scene. Selecting an item collapses the tray and opens its object dialog. Scene and object descriptions overlay their images in translucent dark boxes with white text, capped at 80px with scrolling. Scene descriptions sit above the inventory icon and hide while the tray is open. Scrollbars throughout the interface are thin and follow the selected Light, Dark, or Paper palette; image-picker dialogs scroll only their image grid.

Preview hotspots remain visually unoutlined, using the pointer cursor to indicate interaction. Scene changes fade the incoming image in over the outgoing image for 200ms. The outgoing image stays fully opaque underneath for the entire transition and is removed only when the incoming image finishes fading in. Object inspection triggered by that interaction waits 250ms after the scene transition completes, then fades in over 200ms. Closing or restarting preview cancels pending transitions and inspection.


### Image library organization
Image names are editable in generated cards and the reference picker, saved independently of scene/object names. Prompts initially show 20 words with Show all/Show less and Copy prompt. Archive hides an individual output from the generated list without deleting it or changing scene assignments. Show archived images at the bottom reveals archived outputs and their Restore image controls. Card expanded/collapsed preferences persist in local storage for that page's image studio.

### Using inventory on scene areas
An area can use the Use inventory on area action. Clicking it opens a game dialog with the creator's introductory text and an inventory button. The player selects a carried item; a rule matches its current object visual, so an opened envelope can behave differently from a closed one. Each rule defines a response, optional destination scene on the same page, and optional item consumption. Unmatched items show the configured fallback, defaulting to Nothing happens. A text-only failure leaves the scene and inventory intact. The editor can add all object states as rules for writing distinct responses.

Choose an action areas expose creator-defined buttons such as Walk into the house. Each choice has response text and an optional same-page scene destination. These are local scene interactions rather than story branches; cross-page travel is not implemented by this preview. Successful transitions retain the scene fade and delayed result-dialog timing. The companion can propose interaction text, fallback text, inventory rule arrays, and choice arrays using the existing reviewed hotspot-change flow. References are validated against the page's visuals before changes are saved.

Image generation accepts an optional image name, initially blank. A supplied name is used directly. When blank, Generate first asks the selected Creative companion writing model for a short name derived from the image prompt, then submits the image request. Naming uses the same trial allowance or personal key and is logged as an AI call. Failure to name stops generation and lets the creator enter a name instead. Names are saved independently of scene names and remain editable in the library.

### Manual retry deadline update
Failed image-generation cards offer Try again when revealed through Show failed generations. This creates a new job from the original prompt, name, model, settings, and references after validating current capabilities and available credit. The old failed entry remains and the current editor form is untouched. Retries are explicit and never automatic. Provider requests, image workers, and pending/uncertain reservation expiry now use a four-minute limit, superseding the earlier three-minute policy. Completed charges remain intact and expired reservations are released once.

### AI credit grants
The token_credits ledger records monetary AI credits in integer microUSD (1,000,000 units = $1). Every new account receives a $1 welcome grant. Existing accounts receive the same grant on migration while retaining all historical spending and reservations. Available credit equals cumulative grants minus spending and active reservations, floored at zero. Writing, image naming, and image generation share this balance; a saved personal OpenRouter key continues to bypass platform credits.

Server administrators can add credits using `php artisan ai:grant-credit EMAIL AMOUNT --description="REASON"`. AMOUNT is in USD. An optional `--reference` prevents duplicate grants for the same user and reference. AI settings lists the current user's grants, dates, sources, descriptions, and amounts with pagination. Credit administration is a server command, not a public grant endpoint.

### Next-page click action
Clickable areas support Move to next page (next_page), requiring no object or scene target. The test preview follows storyboard order to the next nondeleted page's first scene, preserving inventory and existing crossfade timing. It leaves the authoring selection and document untouched. A next page without a scene displays a setup message instead of skipping ahead. On the last page, the action displays Story complete. The companion can propose this action through the reviewed hotspot changes.

Before Test interactions opens, creators choose optional starting inventory from object visuals across all active pages. Each choice displays its thumbnail, name, description and source page. Start test copies selected objects into preview-only inventory; selecting none starts empty. Cancel does not start the preview, and authored objects remain unchanged.

### Animated descriptions and pickup feedback
Player descriptions use top or bottom placement, defaulting to top, configured on visuals, examine areas, interaction text and response rules/choices. A translucent dark box expands with word-by-word white text and a briefly brighter newest word; it has a rounded 2px white border, light shadow, and 150px scroll cap. Clicking the box (or Enter/Space while focused) reveals all text. It fades away on an outside click or five seconds after the reveal finishes. Reduced-motion preferences disable the word and pickup animations.

Picking up an object sends its thumbnail toward inventory, increases the count and pulses the inventory button. It does not open inspection automatically. An available object description appears over the scene; players can open the item from inventory to inspect its image and perform actions. Examine and text-only transition outcomes use the same animated scene description system. All preview effects remain local and do not change authored content.

### Document-style page planning
Page plan displays every page in one scrolling document list without a storyboard sidebar. Each page contains an editable title and adjacent reorder, remove and insert-after controls. Narrative overlay and Interaction plan use Quill editors in matching-height columns with a minimum 300px content area that expands with writing. Basic formatting and highlights are stored as validated Quill deltas alongside plain text for the companion and game; no arbitrary HTML or embedded media is accepted. Focusing either editor accents that page and updates the active page in the URL, companion context and creation/navigation targets. Page changes autosave and structural actions flush pending writes first.

Image and video prompts belong to visual documents and are edited in image studio, not on story pages. Existing page prompts migrate to matching or newly preserved scene visuals before the page prompt columns are removed. Completion condition is removed from the page-plan UI. Retired page prompt proposals require a fresh visual-scoped proposal.

The companion defaults to 432px width and offers a mouse/keyboard splitter with remembered sizing. Chat bubbles omit role labels. Review proposed changes opens a dialog occupying 65% of viewport width and 70% of viewport height (responsive on narrow screens), with expanded before/after details, opt-out checkboxes and an explicit confirmation footer.

### Reviewable page and image creation
The companion can propose inserting a new page after an existing page. The review item includes its title, narrative, interaction plan, and optional scene/object definitions with clickable areas. Applying it shifts later pages and preserves the previous structure in History. The creator may skip the whole creation independently of other edits.

Image studio uses an independent Images library, including unrendered prompt drafts. The companion can prepare these drafts for review without rendering or reserving credits. Selecting a card loads its name, image/video prompts, model, settings and reference selections. Editing a completed image starts an independent draft; generating a draft queues that card, while generating from completed artwork preserves the original and queues a new card. Create new image clears all inputs and selection. Artwork is assigned to scenes/objects separately in Interactions.

### Companion help
A Help control beside the credit balance opens the maintained capability guide. It distinguishes reviewable edits and creation operations from manual editor actions. A collapsed technical section documents request context, ten-message conversation history, excluded media history, proposal scopes and examples, validation, and selected-change application. Its edit-field lists come from CompanionChanges validation rules; update the guide whenever capabilities change.

Companion page prose supports semantic HTML for Narrative overlay and Interaction plan only. The server converts supported headings, lists, inline emphasis, quotes and highlights to validated Quill deltas and stores derived plain text for gameplay. Unsafe tags and attributes are discarded; raw model HTML is never injected. Field-level review shows a formatted preview built from the validated delta. Other proposal fields remain plain text.

### Streaming companion replies
Chat requests use OpenRouter SSE streaming. Prose appears incrementally while fenced JSON is replaced by an animated Preparing changes… label, including when the opening fence is split across chunks. The server accumulates the complete answer, bills final input/output token usage using catalog pricing, validates the proposal and saves the assistant message before enabling review. Incomplete/error streams provide no actionable proposal and are never automatically retried. Pending billing remains subject to the existing reconciliation and expiration policy. Reduced-motion preferences disable the animated dots.

Page navigation interactions now use an explicit destination page ID selected from a named-page dropdown. This stays fixed when pages are reordered, supports returning to earlier pages, and retains inventory in preview. Finish story is a separate action. Existing next-page interactions are migrated to their current destination or to Finish story when on the last page. Toast notifications appear at the top center.

Clickable areas are independent records: drawing or naming an area creates no object or scene. Renaming/deleting an area leaves separately authored visuals intact. Creators explicitly create/link objects, including through image selection, or approve companion object proposals when requested. Chat shows animated Thinking… while waiting, then streams prose and displays Preparing changes… for proposal JSON.

Move to page destinations include both target_page_id and target_scene_id. The combined dropdown lists every scene (not objects) using page number, page title and scene name. Preview opens the selected scene; legacy links without a scene ID fall back to the first scene. The server rejects scenes outside the selected destination page.

### Inventory-based scene entry rules
Scenes may have ordered skip rules. Each rule requires all selected inventory objects and points to a page/scene in the same story. Empty or incomplete rules are inactive. On entry, the first matching rule redirects automatically; a chain stops after three jumps at the reached scene. Pickup adds the item before destination rules run. Rules are edited below the scene after clicking its background; the checkbox dialog lists objects with thumbnails and source pages.

Clickable areas can optionally display an independent story image via overlay_asset_id, enabled by overlay. Images preserve proportions within the area bounds, aligned top/middle/bottom through overlay_alignment (middle by default). This does not create an inventory object. The editor and preview share the rendering behavior.

Game testing opens a starting-inventory dialog with a page/scene selector. It defaults to the first scene of the page being edited, independently of the selected visual, and allows starting at any authored scene. Entry skip rules still apply to the selected scene using the chosen starting inventory.

Area descriptions appear on hover in the preview and remain until exit or click. A click during typing finishes the text before executing the area action; subsequent hovers show that text immediately for the rest of the test. Pointer exit without clicking hides the text immediately. All area action types can have hover text.

Scenes store dialog_background, dialog_border and dialog_footer as hex colors. Auto derives these from alpha-weighted average scene pixels; readable text colors follow luminance. Inventory dialogs use the scene palette across editor themes, show static descriptions, and place actions in an accented footer. Hotspots use linked item descriptions by default; use_item_description can opt out, and manual/AI description edits disable it.

Clickable areas support show_hover_text, default true for new and existing areas. Disabling it suppresses hover text only; click actions and saved description text remain intact.

Area image overlays support overlay_always (default true). When false, the image appears only while the area is hovered or keyboard-focused; the invisible hit area remains clickable.

Hotspot hover text can use area_top or area_bottom in description_position. Boxes match area width with a 150px minimum (capped by image width), and are clamped to all image edges, including vertically as text grows; overlap of the area is allowed.

Hover descriptions now default to showing all text with a 150ms fade-in. The optional animate_hover_text flag (default false) enables word-by-word reveal; previously seen text still appears in full.

Area-triggered scene descriptions share the area’s Animate text and Position settings with hover text, including area-relative bounds. Inventory item inspection descriptions remain static.

Scene descriptions have an independent animation/lifetime from item hover and click descriptions. Clicking items does not dismiss or restart scene text; only the item-description channel is replaced. Scene changes start the next scene description.

In-game text boxes now use a 200px minimum width (limited by available image width) and 250px maximum height. Longer text scrolls, and area-relative descriptions stay within the image.

Inventory interaction rules can reference any object in the same story, including items collected on earlier pages. The picker labels objects with their source page; response scene targets remain local to the area’s page.

When a pickup area disables hover text but has an effective item/custom description, the first click shows that description using its text settings and arms the area. The cursor changes to a grab hand with a pickup hint; the second click collects the item and clears that description. Areas without text or with hover enabled keep direct pickup.

Companion area edits do not require selecting a hotspot. Named and bulk requests resolve targets from the full game structure. copy_areas proposals accept page_id, source_visual_id, destination visual_id and optional hotspot_ids, appending exact copies with fresh IDs to another scene on the same page. Each area is individually reviewable, and application validates the resulting document and stale revisions.


## Shared inventory library

Inventory is a game-level library, accessed from the Inventory tab immediately after Image generation. Each item has a stable UUID, name, assigned image, description, description position and replacement actions. Opened/closed or transformed states are separate shared items connected by actions. Items do not belong to individual pages.

The Inventory tab supports creating, editing and deleting items with autosave. Each item lists links to all pages/scenes that reference it in areas, inventory-use rules or scene skip rules. Referenced items cannot be deleted until those links and replacement actions are removed. Archived images are excluded from the image picker.

The Scenes tab contains scene definitions only. Areas select existing library items for inspect/pickup and inventory-use actions; selecting an image no longer creates an object. The same item can be referenced by multiple scenes and retains one inventory identity. Existing page objects are migrated with their original UUIDs, preserving all image, action and scene links.

The companion receives the shared inventory separately from pages. It can propose `create_inventory` with item fields or `inventory` with `item_id` and changed fields. Inventory proposals are reviewed before applying, and library revisions protect against stale writes. Image generation remains separate; generated images are assigned to items from Inventory.


## Persistent world state and image organization

Story bible defines named boolean flags with stable keys and initial values. A new test/playthrough starts from those values; flags persist across scene/page transitions within the run, independently of carried inventory. Starting-test controls can override flag values for testing later states. Collection flags remain true after an item is consumed.

Scene skip rules combine required inventory items and expected flag values using AND. At least one condition is required; matching rules redirect to a page and scene, with the existing maximum of three automatic jumps. Clickable areas can require flags/items and either hide or show a disabled explanation when unavailable. Areas set/clear flags only upon executing their action; pickup effects wait until collection actually happens. Inventory-use rules and named choices can set flags on their specific outcomes. Flags, conditions and effects are included in companion context and reviewable proposals.

The Silent House uses stone_opened, key_collected, envelope_collected, door_unlocked and door_key_recovered. Page one's third overview links to the empty open stone; closing/reopening its lid and returning selects the appropriate overview. The key cannot respawn after consumption. The opened stone cannot be left before collecting its key; closing afterward is optional. Porch back-navigation areas are removed, and entry to the porch requires collecting the key.

Image studio shows generation history from all game pages without page-loading toggles. Up/down arrows save a story-wide sidebar display order; new unranked generations appear first. This order changes no scene assignment or gameplay. Archived and failed cards remain optional, and other-page cards retain their reference-selection and read-only behavior.

Scenes navigation uses one dropdown grouped by page. Selecting a scene on another page saves pending changes before navigating directly to it. Reorder arrows and drag handles apply only within the active page; scenes cannot be moved between pages.

Area availability conditions are optional: enable them to edit flag/item requirements and unavailable behavior. Existing requirements remain enabled by default; disabling preserves their values but bypasses the gate. Action flag effects are independent and edited in a collapsed optional section.

The main studio header shows the brand, All stories link, story title, and live draft revision. Editor tabs occupy the second header. The Scenes navigation/edit toolbar stays outside the scrolling editor content beneath the tabs, and its selected label includes both page and scene number. Header height is measured to keep the workspace and companion within the viewport.

Picking up an item opens the inventory tray and animates its image from the clicked area into the new item slot. Inventory-use areas also open the tray. Clicking an item always inspects it; dragging it onto a scene area uses it. Responses appear in the standard area description box.

Inventory items are clicked to inspect or dragged onto scene areas to use. Inventory-use areas open the tray and show their introduction in the standard description box, without a special dialog. Drop responses use the area’s text placement and animation. Effects, consumption, and scene changes occur after the full response and a short reading pause; retained items fly back to their inventory slot. Pending responses prevent duplicate actions. The open tray otherwise leaves scene areas interactive. Areas support predefined system cursor choices, with automatic defaults for pickup and inventory-use actions.

Creative companion optionally includes the active scene image from the Scenes tab. The server resolves its assigned asset within the authorized story and sends a base64 image content part to an image-capable writing model. Only the current message carries the attachment; chat history stays text-only, and image bytes are redacted in AI request history. Image input receives a conservative token estimate independently of text payload bytes. Unsent chat text is saved locally per account/story/editor tab and restored on navigation/reload; accepted sends clear that draft, while rejected requests preserve it.

Companion proposals can add individual clickable areas to existing scenes with create_hotspot (or hotspot without hotspot_id). New IDs are server-generated; each area is independently reviewable. Invalid proposals identify the suggestion number, scene/area context and field; their review dialog opens automatically for new replies. No scene changes are applied before confirmation.

Images are shared story assets, independent of page/scene assignment. All pages can select the same images, and image studio exposes the same archive, rename, reference and draft controls for every image. Image pickers show all unarchived story images. Generation records retain legacy originating-page metadata for history, but it does not restrict editing, generation, retries or reuse.

### Collectible extraction workflow
Scenes offer Extract collectible items for pickup areas linked to shared Inventory items. A free preparation step preserves the selected original image, crops adjustable extraction rectangles (independent of click targets), and records an extraction plan with one clean-background output and separate scene-layer/inventory outputs per item. Plans can be reopened; subsequent plans can reuse the original image rather than the activated clean background.

Creators choose an image-edit model, review/edit prompts, estimate costs and explicitly generate individual outputs or all unaccepted outputs. Each generation uses the existing shared image job, provider, credit-reservation and settlement pipeline. Successful accepted versions survive retries; no automatic paid retries occur. Outputs with queued/running requests cannot be submitted again. Scene layers must contain actual transparency before acceptance. Image generation quality, silhouette edges and contact shadows require creator review.

The review dialog compares the preserved original against the accepted clean background plus layers, supports individual visibility toggles, and lets creators adjust layer placement. Activating all accepted outputs updates the scene and shared Inventory images, preserves click geometry, and removes selected pickup areas' old full-scene transitions. Each area gets a persistent collected flag with a hidden-when-collected condition. Flags, rather than current inventory contents, prevent consumed items from reappearing. A separate placement interaction can consume the inventory item and reset its collected flag to restore the layer. Activation checks scene/inventory revisions. Extraction events are recorded in chat history. Original images and all generated versions remain in the shared image library.

Local empty-background patches for difficult reflections or overlapping shadows are a later fallback; the implemented workflow uses transparent scene layers.

Extraction preparation now includes every pickup area. For selected areas without a valid shared Inventory link, it creates a new item from the area label and description and links it in the same transaction as the extraction plan. Existing links are reused; unselected areas remain unchanged. This free preparation step updates scene/inventory revisions and records created items in chat history.

Extraction review uses cropped thumbnails of the drawn pickup areas instead of numeric coordinate inputs. Creators resize areas in Scenes and prepare a new plan when a crop needs adjustment. Crop previews use the same pixel rounding and clipping as the server reference crops.

### Local v2 actions versus full-scene actions
Pick up object (pickup) and Change scene state (transition) retain their existing full-scene behavior. They are not selected by the new preparation tools. Pick up object v2 (pickup_v2) is exclusively for collectible extraction and never follows a full-scene target. Its shared Inventory item may be created during preparation.

Change state v2 (state_v2) uses Create switches. Each selected area preserves its source crop as the original state and prepares an editable prompt for a generated alternate rectangular patch. The changed-state appearance field describes the desired result (for example, a pressed button). No inventory item or clean whole-scene background is created. Accepted alternate patches are reviewed and retried independently. Activation retains the scene background and assigns original/alternate patches plus a persistent switch flag to each v2 area. Clicks toggle that flag and patch; other areas and Inventory are unaffected. Original and changed patches should preserve their shared frame and background edges. Switch plans and collectible plans are listed separately for the current scene. Existing area actions are never automatically converted to v2.

Collectible extraction background removal uses a numbered annotated copy of the scene. Before preparation, choose original + annotated reference (default) or annotated only. Prompts list numbered items without decimal coordinates and require removal of annotations. Item crops and original assets remain untouched; existing saved plans retain their references.

Image generation has a four-minute execution deadline measured from the worker's started_at timestamp. Waiting in the queue does not consume this deadline; credit remains reserved until execution settles or expires.

Extraction and Create switches add a single chat summary after accepted results are successfully activated in the scene. The summary identifies the page, scene and each applied item/switch change. Preparation, estimates and individual generation requests do not create chat messages.

Extraction/switch completed renders auto-accept on plan refresh, including opaque results for creator visual review. Unaccept preserves versions and does not modify the active scene; it remains unaccepted until a new render completes or the creator accepts a previous version. Re-activation applies replacements. Original switch crops remain preserved.

Click areas use their own Description only; removed the item-description checkbox and implicit inventory fallback. Local switches show their changed-state artwork in the editor, have a separate state_description, and require description then action clicks for each state when hover is disabled and text is present.
