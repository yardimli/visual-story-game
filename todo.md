# Implementation work list

Source: [design document](./design-document.md). Updated as implementation progresses.
Status: [ ] pending, [x] completed. Unchecked acceptance criteria remain outstanding.

## Delivery order and assumptions
Build foundations, authoring, AI billing, assets, worker integration, then publishing and player interactions. Use Laravel 10, Breeze Blade, Tailwind, and Alpine. The $1 allowance is lifetime per user; image jobs and chat share that balance; saved personal keys bypass it. Provider calls are mocked in automated tests. Never overwrite existing database settings or OPEN_ROUTER_KEY.

## S01 Project foundation
- [x] Install Laravel 10 and compatible Breeze with Tailwind.
- [x] Preserve existing .env database credentials and OPEN_ROUTER_KEY; provide safe .env.example.
- [x] Configure app, queue, storage, tests, and local setup documentation.
- [x] Verify dependencies, asset compilation, and application boot.

## S02 Accounts and personal keys
As a user I can register, sign in, reset my password, and manage my profile.
- [x] Use Breeze authentication and password confirmation.
- [x] Provide encrypted personal OpenRouter key save, replace, remove; never expose stored key.
- [x] Show allowance spent, reserved, and remaining and explain personal-key billing.
- [ ] Authorize all projects, assets, chat, playthroughs, and worker endpoints.
- [x] Test isolation and sensitive-field serialization.

## S03 Project library
As a creator I can create and manage single-page, short, and full-length stories.
- [x] Project create/edit with title, premise, genre, language, visual style, and page count.
- [x] Generate ordered blank pages for 1, 2–5, or custom longer lengths.
- [x] Studio dashboard with readiness and production status.
- [ ] Public discovery with search and filters; published game details.
- [x] Validate page count and ownership.

## S04 Story planning
As a creator I can plan the complete story before generating assets.
- [x] Story bible, beginning, climax, ending, character/location references.
- [x] Page prose, visual brief, video prompt, goals and completion flags.
- [x] Free movement between planning and creation; no full-plan approval gate.
- [x] Ordered page navigation, add/move/recoverable removal, and full page-history restoration.
- [ ] Structured item/state dependency validation when S09 rule data exists; currently show review guidance for free-text plans.
- [x] Reviewable draft changes, revision history, undo.

## S05 Creator editor
As a creator I can work with scene, page list, inspector, and persistent chat together.
- [ ] Responsive Tailwind studio shell, page navigation, story/visuals/interactions/states/preview tabs.
- [x] Editable story and page forms with validation and save feedback.
- [x] Docked creator chat with project and selected-page context.
- [x] Text/image model selectors, endpoint pricing, remembered choices, and allowance indicator.
- [x] Image job status polling: queued, running, completed, failed.
- [ ] Video job progress and streaming partial-image previews.
- [ ] Keyboard-accessible controls and useful empty/error states.

## S06 OpenRouter catalog and chat
As a creator I choose an available OpenRouter LLM to plan, write, or revise my story.
- [x] Server-side model catalog and separate text/image model selection.
- [x] Persist conversations/messages and model used per answer.
- [ ] Include story bible, page plan, inventory rules, and relevant selected page.
- [x] Validate proposed structured changes; apply and undo without arbitrary execution.
- [ ] Context limits and explicit long-story summarization/retrieval.
- [x] Handle unavailable models, timeouts, and provider failures visibly.

## S07 One-dollar shared AI allowance
As a user I can use up to $1 of shared writing/image credit then use my own key.
- [x] Integer-denominated ledger and $1 default lifetime limit.
- [x] Lock user balance and reserve conservative maximum before provider request.
- [x] Price-aware token limits; reject unknown/unbounded pricing for platform key.
- [x] Calculate settled cost from catalog rates and returned input/output tokens (BYOK-aware); no double charging on retries.
- [x] Retain uncertain reservations for reconciliation; no automatic credit release after ambiguous failures.
- [x] Personal-key calls bypass platform spending and never silently fall back to platform key.
- [x] Test exhausted budget, sequential reservation protection, idempotency, uncertain calls, BYOK token accounting, and personal-key routing.
- [ ] Dedicated multiprocess database concurrency tests.

## S08 Reference and asset library
As a creator I can upload and reuse images and video assets.
- [x] Private project-scoped upload/download with type and size validation.
- [ ] Reference roles, ordering, prompts, source asset versions and approval.
- [ ] Preview image/video assets and select playable/start/end images.
- [x] Image model capability checks, one/multiple input references.
- [x] Queued OpenRouter image generation and reference-guided scene editing.
- [ ] Track job failure/retry; preserve originals and mark dependencies outdated.
- [x] Shared image/chat trial balance and personal-key funding.

## S09 Objects and scene states
As a creator I can define a playable scene and its visual changes.
- [x] Named scene states with explicitly selected images (no approval gate).
- [x] Normalized rectangle hotspot editor with numeric keyboard alternatives.
- [ ] Polygon regions and masks.
- [ ] Object descriptions, highlight masks/regions, visibility conditions.
- [ ] Item definitions, isolated renders, reusable/consumable behavior.
- [ ] Containers, contents, and empty states.
- [ ] Conditions/effects rule builder and recipes.
- [ ] Reachable-state and required-item dependency checks.

## S10 Video production API
As a creator I enqueue local ComfyUI renders from available prompts and required input assets.
- [ ] Image-to-video and first/last-frame job schema.
- [ ] Immutable source versions, workflow/version, prompt and settings.
- [ ] Scoped worker authentication and compatible atomic job claiming.
- [ ] Leases, heartbeat, attempt fencing and availability.
- [ ] Authenticated downloads, validated uploads, checksum and media metadata.
- [ ] Idempotent completion, retry, cancellation, expired lease recovery.
- [ ] Outdated-source detection and review before activating output.
- [ ] Test duplicate/stale/unauthorized worker requests.

## S11 Windows worker
As an operator I run a Windows worker that polls Ubuntu and uses local ComfyUI.
- [ ] Configurable outbound polling, worker token, approved workflow directory.
- [ ] Local ComfyUI submission/status/output retrieval for both video modes.
- [ ] Durable job journal, heartbeat, restart reconciliation.
- [ ] Retry uploads without rerendering; retain output until acknowledged.
- [ ] Workflow parameter mapping, capacity limits, cancellation.
- [ ] Windows installation and Ubuntu deployment documentation.

## S12 Publishing
As a creator I validate and publish an immutable game version.
- [ ] Readiness checklist for story, media, states, items and ending.
- [ ] Snapshot pages/rules/assets on publish and retain previous versions.
- [ ] Existing playthroughs stay pinned to their version.
- [ ] Public listing visibility and creator-controlled publication.
- [ ] Tests for invalid drafts and snapshot isolation.

## S13 Player
As a player I watch, read, explore, collect and solve a linear story.
- [ ] Intro clip, skip/replay, static fallback, narrative overlay.
- [ ] State-specific ambient loop paused for interaction.
- [ ] Highlighted hotspots, examine/look-around descriptions.
- [ ] Inventory pickup, drag or select-and-use, combine/repair.
- [ ] Container dialog, item pickup, duplicate prevention.
- [ ] Server-authoritative atomic conditions/effects and completion.
- [ ] Autosave/resume and cross-page inventory.
- [ ] Final page Finish Story including single-page games.
- [ ] Journal, reduced motion, responsive/touch/keyboard access.

## S14 Verification and operations
- [ ] Feature tests for ownership, billing, publishing, worker protocol, player state.
- [x] Breeze authentication tests and frontend production build.
- [ ] Sample single-page and 2–5-page story for local review.
- [ ] Long-novel validation and both video workflows with actual operator setup.
- [ ] Queue/scheduler/storage setup, secrets, HTTPS and backups documented.
- [ ] Track checks passed and externally blocked integration tests below.

## Progress log
- Planning: saved the current design document and this detailed story backlog before application implementation.


- Foundation milestone: Laravel 10.50.4, Breeze 1.29, Blade/Alpine/Tailwind installed. Vite upgraded to 7.3 with matching Laravel plugin; npm audit reports zero vulnerabilities.
- Added private projects, selectable page counts, page editor, story bible, planning approval, revision/restore, private image uploads, and persistent model-selectable chat with reviewable structured edits.
- Added encrypted personal keys, lifetime $1 platform allowance, integer usage ledger, conservative reservations, provider price ceilings, actual-cost settlement, duplicate submission rejection, and operator reconciliation command.
- Existing database was reachable and empty; migrations applied without replacing DB settings or OPEN_ROUTER_KEY.
- Verification: 41 automated tests / 135 assertions passed before final regression additions; production Vite build and Blade compilation passed. Provider responses mocked; no paid OpenRouter requests or ComfyUI renders performed.
- Remaining within early milestones: page add/reorder/delete, automated long-context summarization, and dedicated multiprocess concurrency tests. Image-model catalog UI and dashboard readiness indicators are now complete.
- Remaining product milestones: image generation/edits, visual hotspot/state authoring, ComfyUI protocol and Windows runner, publication/discovery, and gameplay. These are not implemented yet.
- Deployment limitation: composer audit reports five Laravel 10 framework advisories. Kept the user-requested version, disabled debug, hardened email inputs, and documented upgrade/backport review before public deployment.

- Final verification: 44 tests / 142 assertions passing. Browser checked login, three-page project creation, page save, revision history, and live model catalog. Temporary QA account and story removed. Screenshot retained in .cache/editor-preview.png. No billable provider calls made.

## Interface expansion and VSG branding
- [x] Expand index with feature overview, story formats, creation process, and clear development status.
- [x] Rename visible branding and app configuration to Visual Story Game / VSG.
- [x] Restyle login, registration, reset, confirmation, verification, and profile layouts.
- [x] Add persistent system-aware light/dark mode across public and authenticated layouts.
- [x] Create requested demo account and verify login.
- [x] Verify theme persistence, authentication pages, build, and regression tests.

- VSG interface verification: 44 tests / 142 assertions and production build passed. Browser verified light/dark homepage, registration and login layouts, password recovery, successful demo sign-in, and theme persistence across navigation and reload. Demo account seeded explicitly; normal registration retains its password requirements. Preview saved in .cache/vsg-home-dark.png.

## S15 Persistent creative companion
- [x] Shared right-side companion on dashboard and authenticated account pages; retain contextual story chat.
- [x] Dashboard suggestions populate the create-story form without submitting or saving.
- [x] Story suggestions populate story/page forms when applied; applied changes autosave.
- [x] Persist private account conversation, model choice, errors, and allowance handling.
- [x] Test isolation, billing, proposal validation, and draft-only behavior.
- [x] Continue backlog: dashboard planning readiness and production status.
- [ ] Extend contextual form assistance to future asset, interaction, and production screens as implemented.

- Companion milestone: shared panel on dashboard, story editor, AI settings, and profile. Desktop panel stays alongside the form; narrow screens keep an expanded panel below content. Account conversation is user-scoped; story conversation remains project-scoped. Public/guest screens and future production screens remain to be integrated.
- Suggestions fill allowlisted browser form fields. Existing story/page changes now autosave; Create remains explicit. Server revision checks protect writes. Account passwords and keys are never read into companion context.
- Continued S03/S06: dashboard counts complete page plans and bible readiness, shows production as not started, and story settings now offer a separate image-output model catalog selector. This does not enable image rendering yet.
- Validation: 50 tests / 172 assertions passed, production build and Blade compilation passed. Browser verified a locally seeded proposal fills title/premise/genre/page count while projects remain at zero. No paid model calls. Temporary QA message removed after verification.

- [x] Fix premature additional-fees personal-key prompt: account for cache-write and reasoning costs, ignore unused media/search price entries for text chat, and request a personal key only when free credit is spent. Unknown pricing uses a model-availability error; oversized requests retain the spending guard.

- [x] Correct GPT-6.1 Sol pricing parsing: handle the catalog pricing.overrides array separately from scalar rates; reserve higher reachable context/scheduled rates and retain actual-cost settlement. Verified against the app catalog without a paid request; 56 tests / 186 assertions passed. Existing localhost:8010 server left untouched.

- [x] Remember writing/image dropdown choices immediately in localStorage, restore after catalog loading, remove models labeled (batch)/(free), and sort by company prefix then model name. Hidden or unavailable stored choices are cleared.

- [x] Configure visible catalog providers with OPENROUTER_PROVIDERS in .env; preserve database and API key settings.
- [x] Apply provider, text-output, editorial legacy/coding/lite, Qwen size, batch/free, and version/snapshot deduplication filters server-side for listing and chat selection.
- [x] Show catalog USD per million input/output beside dropdown model names.
- [x] Replace provider-reported cost accounting with catalog input/output rates times returned prompt/completion tokens; store request pricing snapshots and token counts, handle context/time tiers, and retain uncertain reservations when token usage is missing. This supersedes the earlier provider-cost settlement policy.

- Model policy/token-accounting verification: 60 tests / 195 assertions passed, per-million label checks and production build passed. New accounting migration applied. No paid requests or changes to the user-run localhost:8010 server.

- [x] Fix companion premise drafts: support legacy bible.premise, canonical story fields, and partial current-page edits. Hide structured proposal JSON from displayed assistant replies (including existing history), preserving prose and draft-only submission.

- [x] Discover images through /images/models separately from text editorial filters, retain provider allowlist, and fetch selected-model endpoint pricing/capabilities. Display billing units/tiers without assuming a flat per-image rate. Add dedicated /images transport; queued generation and asset workflow remain pending.

- [x] Autosave existing story/page forms after a 400ms typing pause; remove Save buttons, serialize revisions across forms, display save/error status, preserve failed edits, flush before navigation/chat/approval, and autosave applied companion proposals. Create/upload/security actions remain explicit. Supersedes earlier manual story/page save policy.

- [x] Widen desktop storyboard sidebar to 240px, keep its heading on one line, and constrain page rows to prevent horizontal overflow.

- [x] Auto-expand all textareas for initial content, typing, companion edits, tab visibility, and viewport changes; remove textarea scrollbars and add a fallback for browsers without native content sizing.

## Current continuation: page management
- [x] Add pages up to the 100-page limit and move pages earlier/later.
- [x] Recoverable page removal, preserve at least one page, and maintain contiguous order.
- [x] Revision-safe structure changes, and full history restoration of page membership/order.
- [x] Flush autosave before page actions; test ownership, stale writes, bounds, and recovery.
- [ ] Structured item/state dependency validation remains pending S09; free-text interaction plans require creator review after reordering.

Recent UI status: story/page autosave, expanding textareas, 240px storyboard, hidden companion JSON, legacy premise proposals, separate image discovery, provider allowlist, and catalog token billing are implemented. Latest baseline: 65 PHP tests; production assets rebuilt. Historical log entries describe the behavior at their milestone; current checklists supersede them.

- Page management completed: pages retain IDs/content through removal and History recovery; order changes are atomic and preserve revision history. Verification: 69 PHP tests / 245 assertions, 8 JavaScript tests, and production build passed. Soft-delete migration applied. User-run localhost:8010 server left untouched; no paid API calls or changes to existing stories.

- [x] Remove full-plan approval button, route, status badges, and assistant instruction. Planning readiness remains informational; future generation validates only inputs needed for the requested asset. Retained historical database field without using it as a gate.

- [x] Chat Enter submits via form validation; Shift+Enter inserts a newline. Empty-input placeholder explains both shortcuts. IME composition and held-key repeats do not trigger sending.

## S16 Scene artwork and clickable areas
- [x] Per-page named scene states and separate object/close-up images; preserve all generated versions.
- [x] Image generation queue with explicit Generate, shared trial/personal-key billing, endpoint capability checks, prompt/reference snapshots, and visible failure status.
- [x] Assign generated/uploaded assets to visuals without overwriting existing versions.
- [x] Rectangle hotspot drawing plus numeric keyboard controls; autosave with conflict detection.
- [x] Hotspot descriptions, object overlays/close-ups, scene transitions, and pickup preview.
- [x] Local preview for note inspection and opening a stone to reveal a key; published gameplay remains separate.
- [x] Ownership, request deduplication, invalid references, provider failure, and scene-document tests; setup documentation.

- Remaining follow-up: companion-generated visual documents, conditional/item-use rules, crafting, visual history restoration, and the published player. Current interaction testing is local to the creator preview.

- S16 verification: 76-test regression suite passed (274 assertions), then an additional reference/payload/invalid-output test passed (8 scene tests / 35 assertions). 10 JavaScript tests, production build, and Blade compilation passed. Browser checked localhost:8010: note image inspection, opened-stone transition, key pickup, rectangle drawing, autosave across reload, and GPT Image 2.6 Sunburst endpoint pricing. Provider renders were mocked; no paid calls. Temporary QA project removed; existing stories untouched.


## Shared trial credit for images
- [x] Share the existing lifetime $1 allowance between writing and image generation.
- [x] Atomically reserve credit with image enqueueing and keep funding fixed across key changes.
- [x] Settle using captured catalog image/reference/megapixel/token rates, ignoring BYOK-reported cost.
- [x] Preserve uncertain reservations, release unsubmitted jobs, and prevent duplicate billing.
- [x] Refresh the companion balance after queue/status changes; update UI and setup documentation.
- [x] Verify regression tests, migration, and frontend build.

Shared-credit verification: 83 PHP tests / 307 assertions, 10 JavaScript tests, production build, and Blade compilation passed. Migration applied; localhost:8010 returned 200 with shared writing/image credit copy. No paid requests or existing balance/key changes.

- [x] Click generated, selected scene, reference, or inspected object images to open a full-viewport image dialog; preserve aspect ratio, close with Escape or Close, and keep hotspot drawing separate.

- Full-screen image preview verified on localhost:8010: thumbnail opens the native modal; Escape closes it and restores thumbnail focus. Production build and Blade compilation passed.

- [x] Show image-model prices directly in the generation dropdown with billing units/ranges. Remove rendering-provider selection and allow automatic OpenRouter routing; validate common capabilities and reserve across endpoint prices. Verified PHP regressions, automatic-routing pricing tests, 11 JS tests, production build, and Blade compilation.

- [x] Add selectable reference thumbnails and direct uploads in the image generation panel. Uploads persist in the shared References library, auto-select for generation, and appear immediately in both thumbnail lists; thumbnails open full-screen previews.

- Reference upload verification: 12 studio tests / 64 assertions passed, including JSON uploads, private asset access, persistence, and no generation on upload. Production build and Blade compilation passed; localhost thumbnail selection and inline file control verified.

## Dedicated image generation workspace
- [x] Move generation controls into a separate page with the persistent companion.
- [x] Show original prompts and model names beside generated versions.
- [x] Add Generate scene/object and Edit this image actions; edits use the original as a reference and preserve earlier renders.
- [x] Allow companion suggestions to fill the selected visual prompt with visual revision checks; generation remains explicit.
- [x] Verify workspace ownership, prompt snapshots, companion flow, and build.

Generation workspace verification: 85-test regression passed before two new tests; 21 focused PHP tests / 112 assertions and 12 JS tests passed afterward. Build and Blade compilation passed. Localhost gallery, original prompts, model controls, and companion verified without a paid call or changes to existing story content.

- [x] Remove unsupported require_parameters from image requests, including legacy queued automatic-routing payloads. Preserve sanitized HTTP failure details and log job/status/exception metadata without credentials. Release credit for explicit pre-generation HTTP rejections; retain ambiguous outcomes. Verified 13 image tests / 67 assertions. Historical job #3 has no retained provider response and remains unreconciled; no paid retry performed.

- [x] Expire reserved/uncertain AI requests after 60 seconds, atomically release trial holds once, and mark queued/running/failed image jobs failed. Ignore late billing/results and prevent expired queued jobs from calling providers. Run scheduled cleanup every second, with authenticated-request and reserve/settle fallbacks. Verified 91 tests / 353 assertions. Supersedes indefinite reservation retention and manual-only reconciliation for overdue requests.

- [x] Increase AI provider/worker timeouts and reservation expiry to three minutes (180 seconds), superseding the one-minute policy. Update timeout messages, boundary tests, and documentation.
- [x] Encode image requests with literal JSON slashes so reference data URLs send image/png, image/jpeg, and image/webp without escaped MIME separators. Preserve prompt escaping and JSON content type. Verified 17 focused tests / 81 assertions, including raw outgoing body checks; no paid request retried.

## Compact image generation workspace
- [x] Move all generated versions into a right sidebar with independent scrolling; remove the 30-request history cap.
- [x] Collapse failed requests by default while keeping their details expandable.
- [x] Select generated images as references using checkboxes; replace Edit this image with Copy prompt without changing the current draft.
- [x] Move reference thumbnails, selection, and uploads into a native dialog; keep uploads saved in References and selection synchronized with generated cards.
- [x] Remove explanatory billing/provider prose and duplicate pricing detail; retain model dropdown prices and compact settings.
- [x] Fit the desktop workspace to viewport height alongside the companion, with internal overflow for long expanding prompts and a stacked mobile layout.
- Verification: 12 JavaScript tests, 13 scene feature tests / 69 assertions, production build, and Blade compilation passed. Localhost:8010 checked collapsed failures, rendered image cards, reference dialog and synchronized checkboxes. No paid generation or story edits performed.

## Compact companion and shared notifications
- [x] Reduce companion header to its title, remove the visible Message label, tighten chat spacing, and align the model picker left of Send at equal 40px heights.
- [x] Move model catalog reload to AI settings; refresh the companion catalog after reload.
- [x] Add shared bottom-left success/error toasts with four-second display and fade-out, dismiss controls, deduplication, and accessible announcements. Support top-layer display over dialogs.
- [x] Route companion notices, autosave outcomes, image job completion/failure, upload results, copy feedback, model catalog failures, and server session/validation summaries through toasts. Keep field validation hints and historical job errors available in context.
- [x] Remove redundant inline status banners from the studio, generation, companion, authentication, and profile screens.
- Verification: 92 PHP tests / 363 assertions, 13 JavaScript tests (including notification expiry/deduplication/cleanup), production build and Blade compilation passed. Verified compact composer, AI-settings reload, bottom-left toast and automatic disappearance on localhost:8010. No paid calls.

## Image workspace column order and generation progress
- [x] Position generated history on the left, image controls in the center, and the companion on the right.
- [x] Combine back navigation, workspace title, and story/page context into a slim top band.
- [x] Remove separate New scene/New object buttons; expose creation in the visual dropdown, including on empty pages.
- [x] Prepend a submitting placeholder when generation is requested, scroll history to the top, and show a centered spinner during submitting/queued/running states.
- [x] Replace temporary cards with accepted job IDs, preserve accepted cards if a status refresh fails, prevent double submissions, and sort server history by newest ID.
- Verification: 14 JS tests, 13 scene feature tests / 69 assertions, production build and Blade compilation passed. Three-column layout checked on localhost:8010. Queue placeholder lifecycle tested with mocked requests; no paid generation performed.

## Isolate AI request rate limits
- [x] Fix shared unnamed throttle counters: image generation, chat, model catalogs, image endpoint discovery, and personal-key settings now use separate per-user prefixes. Keep existing limits, with chat routes sharing only their intended chat allowance.
- [x] Show Retry-After seconds in image workspace rate-limit toasts; never retry a paid request automatically.
- [x] Regression verifies repeated catalog loads do not block generation, the seventh image request is still limited, and generation resumes after the minute window. Verified 14 feature tests / 96 assertions, 14 JavaScript tests and production build. Route cache cleared; no paid calls.

## Bulk image endpoint discovery
- [x] GET /ai/image-endpoints without a model now returns all allowed image models and endpoint pricing/capabilities keyed by model ID, plus unavailable model IDs. Retain optional single-model lookup for compatibility.
- [x] Reuse per-model server caches and fetch uncached endpoint data concurrently; preserve successful results when another model fails.
- [x] Share one bulk request between frontend catalog consumers and use local endpoint data on model changes. AI settings reload refreshes the bulk client cache.
- Verification: 19 focused PHP tests / 119 assertions, 15 JavaScript tests and production build passed. Includes authorization, allowlist filtering, cache reuse, partial upstream failures, and client request deduplication. No paid generation requests.

- [x] Replace the Interactions tab selected-image dropdown with a thumbnail button and native image-picker dialog. Show all project images, highlight the current selection, apply selections through existing autosave, and support clearing the selection, Escape/Close, and an empty-state generation link. Verified dialog on localhost:8010 without changing story content; 14 scene tests / 96 assertions, build, and Blade compilation passed.

## Direct clickable-area editing
- [x] Select areas on the scene to edit their descriptions/actions below; remove the area dropdown, separate name field, and numeric coordinate controls.
- [x] Drag areas and resize using four corner handles within image bounds. Support pointer cancellation and keyboard move/resize shortcuts.
- [x] Double-click an on-image label to rename it; Enter/blur commits and Escape cancels.
- [x] Show a bottom-right trash icon on the selected area. Require confirmation with Cancel focused before deletion (including keyboard deletion).
- [x] Replace object selection with an image thumbnail dialog, creating/reusing object visuals for selected assets.
- [x] Replace ambiguous next-scene dropdown options with thumbnail cards showing scene numbers, names, and image names; exclude the current scene and number newly created visuals.
- Verification: 17 JavaScript tests and 14 scene feature tests / 96 assertions passed, along with build and Blade compilation. On localhost:8010 verified drag, resize, rename, deletion confirmation/cancel/confirm, object/scene dialogs, and persisted geometry after reload using a temporary fixture. Existing story content untouched; fixture removed.

- [x] Compact story header into a single-line amber-accented band with truncated long titles and revision indicator. On desktop, fit the editor to the window below navigation, keep the storyboard full-height, and scroll storyboard/content independently. Retain the stacked small-screen layout. Build and Blade compilation passed; localhost:8010 confirmed storyboard bounds remain unchanged while scrolling editor content.

- [x] Require names in native HTML dialogs before creating scene/object visuals or duplicates. Replace visual button rows with a numbered type/name dropdown and a Delete visual action. Confirm deletion, disclose dependent areas, clear dangling references with valid fallback actions, and retain image assets. Verified naming/cancel/duplicate/delete confirmation on localhost without altering the user story; 19 JavaScript tests, 14 scene feature tests / 96 assertions, build and Blade compilation passed.

## Context-aware companion and selective review
- [x] Persist the active editor tab in the URL and restore all supported tabs on refresh.
- [x] Send the active screen, selected page/visual/area, and full saved game structure (story fields, every page, visual prompts, areas, reference metadata, and current generation settings) to the companion; exclude generation-job history and credentials.
- [x] Allow incomplete draft interaction links to save so creators can reach image generation before an object image exists; retain ownership, geometry, and supplied-link validation.
- [x] Support compound area-description and linked-object prompt suggestions, with object-specific generation navigation and no automatic paid rendering.
- [x] Replace Fill buttons with expandable bulleted proposals, current/proposed values, per-item checkboxes, and explicit confirmation of selected changes. Support legacy replies and dashboard drafts.
- [x] Apply new story/page/visual/area proposals across the game using stored server-validated changes, revision checks, ownership validation, atomic saves, and revision snapshots. Unchecked fields remain unchanged; dependent navigation is disabled when object preparation is skipped.
- Verification: full PHP suite 99 tests / 428 assertions; 22 JavaScript tests; build and Blade compilation passed. Localhost QA verified URL tab restoration, compound proposal handoff, expandable checklist, skipped premise, and selected object prompt opening. Used disposable fixtures and mocked model responses; no paid calls. Fixtures removed afterward.

## Fixed visual context in image generation
- [x] Carry the selected visual into Open image generation links and display its name/type as read-only; remove the generation-page visual dropdown and name editor.
- [x] Add Change scene to completed generation cards with a destination dialog for visuals on the same page. Validate ownership, target, completion status, and revision before moving the generation; preserve original prompt/settings, assets, and existing scene image assignments.
- [x] Keep the current generation visual selected when using a history image in its associated scene.
- Verification: 16 scene feature tests and 23 JavaScript tests passed, plus production build and Blade compilation. Localhost:8010 verified a non-default selected visual opens with its name as read-only and Change scene lists the correct destinations. No paid requests or changes to the user's story content.

## Named areas and compact scene toolbar
- [x] Start inline naming after drawing/adding an area. Committing a name creates a linked object visual in Current visual without switching away from the scene; repeat edits reuse the object and image selection fills its placeholder. Preserve existing independently named/shared object visuals and enforce the visual limit.
- [x] Move scene/object creation buttons immediately after Delete visual beside Current visual. Move Duplicate beside Test interactions, remove introductory copy, and reduce toolbar typography, padding, and vertical gaps.
- Verification: 25 JavaScript tests, 16 scene feature tests / 109 assertions, production build and Blade compilation passed. Inspected the toolbar on localhost:8010 without modifying existing story content.

## Consistent names after moving generated images
- [x] Update generated asset names/roles along with their generation when Change scene is confirmed; refresh client assets immediately.
- [x] Resolve picker and References names from generation metadata so images moved before this fix also show the correct destination name, retaining render identifiers and uploaded reference names.
- Verification: 17 scene feature tests / 118 assertions, 25 JavaScript tests, and production build passed. Regression covers previously moved images, References rendering, and new moves.

## Live clickable-area drawing
- [x] Disable the image zoom target and show a crosshair throughout drawing mode.
- [x] Track pointer movement and show a dashed, translucent rectangle preview while dragging, clamped to image bounds; save/name the area only on release.
- [x] Clear unfinished previews on Escape, cancel, lost pointer capture, and visual switches; suppress the click generated by drawing so it cannot open zoom.
- Verification: 26 JavaScript tests, production build, and Blade compilation passed. Regression checks live preview before saving, reverse drags, bounds, release, cancellation, and zoom suppression.

## Object-aware image generation handoff
- [x] Resolve Open image generation and the top generation link to the selected area's object for Inspect/Pickup actions, rather than its background scene. Prepare a linked object for older areas that lack one.
- [x] Flush page and visual autosaves before navigating; stop when saving fails. Keep the explicit current-visual image picker generation link scoped to that visual.
- Verification: 27 JavaScript tests, production build, and Blade compilation passed. Regression verifies object handoff, save ordering/failure, missing-object preparation, and assigning the output without changing the background image.

- [x] Persist Current visual as the URL visual parameter, preserving page/tab and restoring selection on refresh. Invalid/deleted editor selections fall back to the first available visual and normalize the URL; empty pages remove the parameter. Verified 28 JavaScript tests and production build, including selection restore and stale-ID fallback.

- [x] Clarify page/scene hierarchy: storyboard entries show Page N with custom titles underneath; old generated Scene N labels are suppressed. New page defaults, page title/final-page labels, single-page story copy, companion instructions, and design document now distinguish pages from the multiple scenes within them. Existing authored content preserved. Verified 12 Studio tests / 64 assertions, build, and Blade compilation.

## Unified editor tabs and dashboard history
- [x] Share tabs across the editor and separate image-generation workspace: Page plan, Interactions, Image generation, References, Story bible. Highlight the generation tab and retain page/visual context in links back to editing.
- [x] Move revision History out of the editor to a History link on each dashboard story card, with owner-scoped revision listing and existing restore controls.
- [x] Move page order/removal controls and recovery guidance below the page plan form; remove its redundant page-number/progression band.
- [x] Avoid competing autosave navigation when a link already handles its own save-before-navigation flow.
- Verification: 13 Studio feature tests and 28 JavaScript tests passed, along with production build and Blade compilation. Regression covers tab order, selected generation tab, bottom controls, and dashboard history ownership.

## Lazy AI call history in settings
- [x] List the signed-in user's writing/image call summaries below Model catalogs, newest first, paginated at 50 calls per page. Compact collapsed cards show model, call type/visual, recorded cost, status, and time.
- [x] Fetch request/response/accounting contents only on expansion through an owner-scoped JSON endpoint; cache loaded details per card, prevent duplicate loads, and offer retry after errors. Initial database queries exclude request/response bodies and image payloads.
- [x] Record new writing request/response bodies (including full submitted context) in nullable ledger columns, redact credentials, and render returned content as text. Existing image details use stored prompts/settings/references, usage/errors, and private output thumbnails, omitting file paths and binary data. Older writing calls explicitly indicate contents were not recorded.
- [x] Apply the additive migration. Preserve existing cost/budget accounting and show unknown costs as pending.
- Verification: 26 focused PHP tests passed after fixture correction (history, companion, studio); 29 JavaScript tests; production build and Blade compilation passed. Localhost:8010 verified collapsed history and AJAX expansion of an existing image call. No paid requests.

## Fixed editor navigation and matching generation header
- [x] Share the compact accented story header between editing and image generation, including All stories, title, and revision.
- [x] Place tabs in a non-scrolling row outside story-editor-content and image-controls; keep storyboard, editor content, generated versions, and generation settings independently scrollable within the viewport.
- Verification: 13 Studio tests / 74 assertions, production build, and Blade compilation passed. On localhost:8010, tab top stayed at 125.28px while page content scrolled 1701.76px and generation controls scrolled 161.92px. Existing story content untouched.

## BYOK image upstream cost accounting
- [x] For zero-priced image usage, use cost_details.upstream_inference_cost; if absent/zero, sum valid prompt/completion components. Never add total and components together. Keep catalog accounting as fallback and validate finite nonnegative amounts.
- [x] Add idempotent ai:reconcile-image-costs for completed image calls with zero/unknown ledger cost, including previously expired reservations. Update platform-funded spend and release only still-active reservations; personal-key costs remain separate from trial spending.
- [x] Reconciled 4 existing image calls, adding $0.203182 to trial spend. Restarted image processing to pick up the pricing fix.
- Verification: 23 PHP tests / 147 assertions passed, including the supplied $0.054928 example, missing breakdown data, no double charging, reservation handling, and personal-key separation.

- [x] Move image-studio tabs into the center controls column above its scrollable form, instead of spanning the whole section. Generated versions now start directly below the story header; tabs stay fixed while settings scroll. Production build and Blade compilation passed.

- [x] Hide Creative companion on dashboard, AI settings, and profile/account layouts, removing the reserved sidebar column so content uses the available width. Keep the companion in story editing and image generation. Updated layout visibility regression; focused companion/profile/studio tests and Blade compilation passed.

- [x] Add Visual type beside Visual name with Scene and Object / close-up options, saved through visual autosave. Preserve visual ID, image, name, and prompt. Require an explanatory confirmation when conversion removes scene areas or clears incompatible object/scene links; reset affected actions safely. Verified 30 JavaScript tests, production build, and Blade compilation, including cancellation and both conversion directions.

## Paper theme
- [x] Add Light / Dark / Paper selection shared across authenticated and guest pages. Paper uses medium warm taupe/parchment surfaces and dark ink text, covering forms, panels, dialogs, buttons, borders, and navigation without tinting artwork.
- [x] Persist the three-way preference before paint, synchronize tabs, retain system light/dark default until explicitly selected, and keep paper/dark classes mutually exclusive.
- Verification: theme persistence/synchronization regression, production build, and Blade compilation passed.

- [x] Replace whole-image zoom targets with small translucent top-left zoom icon buttons in scene editing, generated versions, reference libraries, object previews, and AI call output images. Keep image selection controls unchanged; hide scene zoom during drawing/interaction preview and stop its pointer event from starting a rectangle. Shared accessible icon/button styling supports keyboard focus. Production build and Blade compilation passed.

- [x] Remove Add area, retaining Draw area. Confirmed area deletion now removes its linked object visual from Current visual in the same autosave when no other area references it. Confirmation identifies the object being removed; shared objects and image assets remain intact. Match links by ID, never by label. Verified 17 scene-editor tests, production build, and Blade compilation.

## Image submission rejection feedback
- [x] Diagnose envelope rejection without submitting a paid request: $0.615497 trial credit remained versus a $0.659324 conservative reservation estimate, so no generation job was created.
- [x] Show required reservation and available credit in budget errors, with lower-cost model/settings or personal-key guidance.
- [x] Keep rejected submission cards expanded with their prompt/error and a Dismiss action rather than removing the placeholder; preserve them through background job refreshes for the current page session. No automatic retry.
- Verification: 18 scene-editor JavaScript tests, 31 focused PHP tests / 159 assertions, build, and Blade compilation passed.

- [x] Hard-code image reservation estimates to 2,048 output tokens and 2,048 tokens per reference image. Retain prompt allowance, catalog rates, 5% margin, and actual-usage settlement; pixel-priced models still use image dimensions. ImagePricing tests passed, including fixed token estimates across resolutions.

- [x] Move Test interactions to the right end of the shared tab navigation, including image studio. Open a native modal preview with scene hotspots, transitions, object inspection, inventory, Reset, and Close. Teleport the dialog outside hidden editor tabs so it works from any tab; closing clears runtime preview state without changing authored scene images. Removed inline preview inventory. Verified 18 scene-editor tests, production build, and Blade compilation.

## Inventory object actions
- [x] Add an Inventory actions editor for object visuals: multiple named actions, each selecting a replacement object on the same page, with autosave and removal controls. Allow incomplete targets while drafting; reject non-object/foreign targets and duplicate action IDs on save.
- [x] Support cycles (Open → opened envelope; Close → closed envelope) and multiple transformations such as Crumple/Uncrumple. In the test dialog, show actions when inspecting inventory items and replace that inventory slot's object/image without mutating authored visuals; Reset clears runtime changes.
- [x] Preserve objects referenced by actions when deleting areas. Clear action targets when their visual is deleted or converted into a scene, warn before conversion removes actions, and regenerate action IDs when duplicating visuals.
- Verification: 20 scene-editor tests, 18 scene feature tests / 124 assertions, production build, and Blade compilation passed. Includes repeated cycles, multiple actions, inventory image replacement, missing/invalid targets, and dependency cleanup.

## Object inspection dialog and visual descriptions
- [x] Add autosaved Description textareas to every scene/object visual with server validation and companion visual-field support. Show scene descriptions in the preview and object descriptions for inspection, pickup, and inventory transformations.
- [x] Move object inspection into a centered native dialog over the test scene, with its image/text inside and action buttons in a bottom-left footer. Preserve Back to scene, keyboard dismissal, and zoom.
- [x] Show clickable-area Description only for Examine; other actions expose Object image and Scene after click. Remove the overlay toggle from this form while retaining existing saved overlay data.
- Verification: scene-editor behavior tests and description persistence checks passed; 21 focused PHP tests, production build, and Blade compilation passed before added description assertions.

## Image-focused preview and themed scrolling
- [x] Use thin theme-colored scrollbars throughout documents and overflow panels in Light, Dark, and Paper.
- [x] Keep Choose an image header/footer fixed with only the image grid scrolling; remove the outer dialog scrollbar.
- [x] Fit Test interactions to the scene image within the viewport, remove its header/Reset/Close controls, and dismiss by clicking outside or pressing Escape.
- [x] Overlay a bottom-left inventory icon that toggles a horizontal inventory tray. Selecting an item collapses the tray and opens object inspection with its actions.
- [x] Overlay scene/object descriptions in translucent dark boxes with white text and an 80px maximum scrollable height. Hide scene descriptions while inventory is expanded.
- Verification: 21 scene-editor tests, production build, and Blade compilation passed. Checked on localhost:8010: image-sized preview, inventory inspection/collapse, outside dismissal, and exactly one scrollable region in the image picker. Authored story data unchanged.

## Preview transitions
- [x] Remove hotspot rectangles/highlights in test mode; clickable areas use a pointer cursor only.
- [x] Crossfade outgoing and incoming scene images simultaneously over 200ms, retaining the outgoing image during the transition.
- [x] Queue object/inventory inspection until the scene transition finishes, then fade the dialog and backdrop in over 100ms. Cancel pending inspection when preview closes/restarts.
- Verification: 22 scene-editor tests passed, including delayed inspection and cancellation; production build and Blade compilation passed.

- [x] Refine scene transition layering: incoming image on top fades in for 200ms; outgoing image holds for 100ms then fades out quickly over 50ms. Keep hotspots above both image layers and inspection timing unchanged. Production build passed.

- [x] Wait 75ms after the 200ms scene transition before opening inspection, then fade the dialog/backdrop in over 200ms. Cancel the pending delay on preview close/restart. Verified exact timing and cancellation with mocked timers; all 22 scene-editor tests and production build passed.

- [x] Keep the outgoing scene fully opaque beneath the new scene throughout its 200ms fade-in; remove the old layer only after completion. Preserve the subsequent 75ms inspection delay and 200ms dialog fade. Production build and Blade compilation passed.

- [x] Increase the post-transition inspection delay to 250ms, retaining the 200ms dialog fade-in. Exact-delay/cancellation regression and all 22 scene-editor tests passed; production build passed.

## Image studio library organization
- [x] Add persistent editable names for generated and reference images, keeping creator names independent of generation/visual labels.
- [x] Replace generation-origin labels, Change scene, and Use in scene with links to every actual scene/object assignment across the story. Links open Interactions with page and visual selected; shared asset assignments remain supported.
- [x] Show the first 20 prompt words, with Show all/Show less and Copy prompt.
- [x] Add per-image Archive/Restore and a Show archived images control at the list bottom. Archiving retains the asset and all scene/object assignments.
- [x] Remember expanded/collapsed generation cards in local storage per page.
- [x] Apply asset metadata migration with owner-scoped update API and validation.
- Verification: 19 SceneAuthoring tests (139 assertions), 23 scene-editor tests, production build, and Blade compilation passed. Localhost:8010 verified current usage links, prompt truncation, and collapsed cards surviving reload without changing story content.

- [x] Compact Interactions: remove the heading row and bottom Open image generation link; move Duplicate visual beside Edit/Delete in the visual toolbar. Put New scene and New object first in Current visual instead of separate buttons.
- [x] Keep Visual name, Visual type, Selected image, and Description visible until an image is assigned; hide them after selection and reveal with Edit. Switching visuals resets edit mode; incomplete visuals always retain the fields.
- Verification: localhost:8010 confirmed the compact toolbar and Edit revealing initially hidden fields. Production build, Blade compilation, and 23 scene-editor tests passed.

- [x] Hide failed image generation cards by default, including rejected submissions. Add Show/Hide failed generations with a count at the list bottom, independent of archived images. Sidebar count reflects visible cards; failure toast feedback remains. Existing 23 scene-editor tests, production build, and Blade compilation passed.

- [x] Remove Current visual/name/type heading from image studio, leaving generation controls directly beneath the tabs. Images remain assigned through Interactions. Blade compilation passed.

## Inventory-to-area interactions and named choices
- [x] Add Use inventory on area with introductory text, a default unmatched-item response, and per-object rules. Each rule can show text, change to another scene on the same page, and optionally consume the item.
- [x] Add all objects to quickly prepare response rules for every object state; preserve default Nothing happens behavior for unmatched carried items.
- [x] Add Choose an action with creator-defined button labels, response text, and optional same-page scene destinations (e.g. Walk into the house).
- [x] Run both action types in the game test dialog. Inventory chooser lists only carried items; attempts use current object state and never modify authored visuals. Keep scene/dialog transition timing.
- [x] Extend companion hotspot proposals and prompt instructions for interaction text, fallback responses, inventory rules, and named choices; retain explicit review before applying.
- [x] Validate object/scene targets and duplicate rules; clean references on visual deletion/type changes and preserve objects used by inventory rules.
- Verification: 25 JavaScript tests and 24 PHP tests / 168 assertions passed; production build and Blade compilation passed. Localhost temporary fixture verified envelope failure, key consumption/unlock, delayed result dialog, and Walk into the house choice. Temporary fixture removed; user's story unchanged. Cross-page travel remains outside this page-local preview.

- [x] Fix tiered image estimates (including qwen/qwen-image-3-pro) when resolution/settings are Automatic: reserve the highest applicable catalog variant rather than rejecting a valid price range. Explicit tiers still select their matching price; unknown actual costs are not replaced by the reservation estimate.
- Verification: 7 ImagePricing tests / 25 assertions passed. Live cached Qwen catalog now quotes $0.081900 with one reference and Automatic resolution, including the existing 5% reserve margin. No paid generation submitted. Restarted local image worker to load the update.

## Optional image name and AI naming
- [x] Add a blank Image name input with a hint that AI creates a short name from the description when omitted.
- [x] On Generate, call the selected writing model only for blank names; typed names bypass naming. Keep a generated name if image submission fails so it can be reused, and reset the field after successful queueing.
- [x] Add owner-scoped naming endpoint using shared trial/personal-key accounting, catalog token costs, capped output allowance, recorded request/response, and no automatic retries. Naming failures do not submit image generation.
- [x] Preserve supplied names on generation records and saved assets without sending internal naming metadata to the image provider.
- Verification: 30 PHP tests / 192 assertions and 26 JavaScript tests passed; production build and Blade compilation passed. Localhost verified empty field/hint. No live paid naming/render requests submitted. Image worker restarted with new asset-name handling.

- [x] Remove the saved-success status assignment during visual loading. Refresh only reads visual data and no longer announces a save; actual successful saves still show feedback. Initialization regression verifies GET-only requests, clean state, and no notification. All 27 scene-editor tests and production build passed.

## Manual image retry and four-minute deadline
- [x] Add Try again to failed generation cards (available through Show failed generations). Retry snapshots the original name, prompt, model, settings and reference IDs into a new job, checks current pricing/budget, and leaves current authoring fields untouched.
- [x] Require explicit retry; block nonfailed/foreign jobs and duplicate request IDs. Retain original failed history and use no automatic provider retry.
- [x] Increase provider HTTP calls, image worker timeout, reservation expiration, UI messaging and worker documentation to four minutes. Database retry_after remains 420 seconds.
- Verification: focused reservation/naming/scene PHP tests, 28 JavaScript tests, production build and Blade compilation passed. No paid retry submitted during testing.

## AI credit ledger
- [x] Compact companion proposals behind a Review proposed changes button. Clicking reveals the bullet checklist, expandable before/after details, and explicit confirmation; checkbox selections are retained when collapsed.
- [x] Add token_credits with integer microUSD amounts, grant source, description, and optional unique reference. Grant $1 on signup and backfill existing users without resetting spending or reservations.
- [x] Use cumulative granted credits for writing, image naming, and image-generation budgets; retain personal-key billing behavior.
- [x] Add server-admin ai:grant-credit command for additional USD credits, with amount validation and optional duplicate protection.
- [x] List the signed-in user's credit grants and total on AI settings, with pagination.
- Verification: migration applied; 120 PHP tests / 597 assertions, production build and Blade compilation passed. Localhost AI settings shows the demo user's $1 welcome grant and existing spend. No extra $5 granted; ready for the user's test.

## Next-page click action
- [x] Add Move to next page to clickable areas and companion validation/instructions. Preview follows active storyboard order, skips deleted pages, preserves inventory, and crossfades to the next page's first scene without changing the editor document.
- [x] Show completion on the final page and a helpful message when the next page has no scene.
- Verification: 30 JavaScript tests and 22 scene-authoring PHP tests (165 assertions), production build and Blade compilation passed.

- [x] Image studio: put archived/failed toggles side by side, followed by a Show/Hide images link for every other active storyboard page. Load completed cards on demand without navigating or altering the generation form; allow reference selection, naming, archiving, and prompt copying on loaded images. Keep loaded cards during current-page polling.
- Verification: 31 scene-editor JavaScript tests and 22 PHP scene-authoring tests passed; production build and Blade compilation passed.

- [x] Repair Windows-1252 separator/ellipsis bytes introduced in image-history page labels; save as valid UTF-8. Checked modified files and rebuilt assets/Blade views.

- [x] Simplify other-page image cards to name, image, prompt, scene/object usage links and reference selection. Hide rename/archive/model/status controls for these cards; avoid duplicated default page titles. Production build and Blade compilation passed.

- [x] Default visual/object image pickers to the current page, with an Images from dropdown for other pages and unassigned uploaded references. Page membership includes generation origin and current visual assignments. Reset filter each time the picker opens. PHP scene tests, JavaScript tests, build and Blade compilation passed.

- [x] Test interactions now opens a starting-inventory dialog with object checkboxes, thumbnails, descriptions and source pages across the story. Start test seeds only selected items; cancel leaves the preview closed and empty selection starts with no items. Test inventory uses copies and never changes authored objects. Verified 33 JavaScript tests, production build and Blade compilation.

## Animated preview descriptions and pickup feedback
- [x] Add top/bottom description placement (default top) for scenes, objects, examine areas, area interaction text, inventory rule responses and choice responses; validate and support companion proposals.
- [x] Animate expanding white-text descriptions with word-by-word highlights, translucent dark fill, rounded 2px white border, light shadow and 150px scrolling cap. Click/keyboard reveals all; outside click dismisses; auto-fade runs five seconds after reveal completes. Respect reduced motion and clean up timers.
- [x] Route examine and text-only transition outcomes to scene overlays. Pickups animate toward inventory and pulse the inventory button/count without automatically opening inspection; show the object's description when provided. Manual inventory inspection retains object actions and animated descriptions.
- Verification: 33 scene-editor and 2 animated-description JavaScript tests, 27 PHP authoring/companion tests (190 assertions), build and Blade compilation passed. Localhost verified progressive scene text, click-to-reveal styling and pickup inventory count without item dialog; authored story unchanged.

## Document-style page planning and resizable chat
- [x] Remove storyboard aside; show every active page as a two-column Quill document (Narrative overlay left, Interaction plan right). Add formatting/highlighting, expanding matched-height columns, 300px minimum content height, active-row accent and cursor-driven page context/URL.
- [x] Place page title, move up/down, remove and insert blank page after controls together. Preserve autosave revision checks and flush before structural actions/navigation.
- [x] Store validated Quill deltas alongside plain text, clear obsolete formatting on companion prose replacement, and preserve formatting in revision snapshots. Reject embedded content/unsafe formats.
- [x] Remove Completion condition from page editing. Move existing page image/video prompts into visual documents, drop page prompt columns, and expose video prompt in image studio. Keep prompt edits out of new page proposals.
- [x] Increase default chat width from 360px to 432px, add mouse/keyboard splitter and remember width. Remove You/Companion bubble labels.
- [x] Open proposal review in a 65vw by 70dvh dialog with initially expanded details, checkboxes, scrolling body and fixed confirmation footer.
- Verification: 125 PHP tests / 622 assertions, 50 JavaScript tests, asset build and Blade compilation passed. Localhost verified active page URL, review dialog sizing, mouse resizing, bold formatting save/reload, title autosave and inserting a blank page in a disposable QA story. Migration applied; existing prompt text preserved in visual records.

- [x] Add OPENROUTER_HTTP_REFERER to environment configuration and send HTTP-Referer plus X-OpenRouter-Title from APP_NAME on all authenticated chat/image requests, including personal keys and image naming. Preserve public unauthenticated discovery. Added HTTP-fake regression coverage.

- [x] Replace Current visual native select with an HTML dropdown: select rows, drag handles, keyboard/up/down ordering controls, scrollable list and compact New scene/New object footer buttons. Reordering autosaves the visual array while preserving selected visual and ID-based interaction links.

- [x] Scroll Page plan to the active page when returning from another tab or image studio, keeping the editor cursor and other panels unchanged.

- [x] Compact Interactions top padding to 8px, remove the visible Current visual label, and layer the visual dropdown above image zoom controls.

- [x] Companion can propose creating a page after an existing page, including narrative, scene artwork and clickable areas, as one selectable review operation.
- [x] Companion can prepare independent image draft cards without paid generation or changing scene assignments.
- [x] Rename Generated versions to Images; select and highlight cards to load names, prompts, models, references and settings; generate drafts in place and preserve completed originals when generating a new image.
- [x] Add Create new image to clear the generation form and selection; persist draft prompts and restore selected images through the URL.

- [x] Add Help beside companion credits with a themed capability dialog, review workflow and limitations, plus collapsed technical context/schema documentation. Keep this guide updated with companion capabilities; edit-field lists are generated from server validation rules.

- [x] Companion returns Quill-compatible HTML for Narrative overlay and Interaction plan only, sanitized and converted to validated deltas with plain-text game output; preserve formatting in new pages and show safe formatted review previews. Other fields remain plain text; Help updated.

- [x] Stream companion prose through SSE; hide partial JSON fences behind animated Preparing changes… dots, expose proposals only after validation/persistence, settle final token usage, and handle interrupted streams without automatic retries. Help and streaming regression tests updated.

- [x] Reference images dialog: remove inline renaming, keep one scrolling content area, add named-page filter defaulting to All images on opening, and preserve reference selections across filters.

- [x] Replace next-page action with Move to page and named destination dropdown; validate same-story targets and preserve inventory during previews. Migrate existing next-page links to explicit destinations (last-page endings to Finish story), update companion schemas/Help, and move toasts to the top center.

- [x] Decouple areas from automatic object creation/renaming/deletion; drawing only adds an area and opening image studio does not create missing objects. Keep explicit image selection and reviewed companion object creation. Show animated Thinking… immediately on chat submission and until response text starts.

- [x] Move to page now selects a page/scene pair, lists every scene with page title, validates destination ownership/type, and previews the exact scene. Legacy page-only links default to the first scene. Companion instructions and Help updated.

- [x] Scene inventory skip rules: click background, choose story-wide items with thumbnails/page names, select page/scene destination; all items required, three automatic jumps maximum. Companion can propose rules; validation and preview regression coverage added.

- [x] Restore saved page/scene selections after dynamic dropdown options render, for scene skip rules and page actions.

- [x] Clickable area image overlays with independent image selection, fit-to-area sizing, top/middle/bottom alignment, autosave and preview rendering.

- [x] Fix cross-page companion document overwrite (release PHP reference); recover Rose Bushes from saved revision with key description. Test setup defaults to first scene of edited page and offers every page/scene as a starting point. Navigation explicitly carries active page ID.

- [x] Preview area hover descriptions: hold while hovered, hide on exit, finish current typing before executing clicked interaction, and reveal previously shown text instantly during the test. Hover descriptions editable for every action.

- [x] Add branded V/play favicon (SVG and ICO fallback) to all app layouts.

- [x] Per-scene dialog/background/border/footer palette with average-scene Auto colors, static inventory descriptions and separate action footer. Area hover description defaults to linked item; manual/AI custom descriptions opt out.

- [x] Fix checkbox stretching caused by generic field styles; compact hover description header with inline item-description checkbox and position selector, and display inherited item text in the textarea.

- [x] Remove automatic item-description narration after pickup; retain inventory inspection description and pickup animation.

- [x] Add page navigation before Current visual in Interactions; flush pending edits and open the destination page’s first scene.

- [x] Per-area Show hover text checkbox, enabled by default, persists through autosave and can be changed by the companion. Disabling preserves click actions and descriptions.

- [x] Add Show always beneath Show image in area; defaults on, unchecked images appear only on area hover/keyboard focus in editor and preview.

- [x] Area-relative hover text above/below selection, area width with 150px minimum; clamp horizontal and vertical bounds to scene image, overlapping area when necessary.

- [x] Add Animate text to area hover controls, unchecked by default. Full text fades in over 150ms; checked enables first-hover word animation.

- [x] Trigger hover descriptions on mouse movement inside preview areas after scene transitions, without restarting visible text.

- [x] Apply area animation and placement settings to click narration as well as hover, with scene-bound rectangle positioning and 150ms fade.

- [x] Separate scene narration from item hover/click narration; interactions no longer dismiss or restart scene text. Item descriptions replace each other, with same-edge boxes stacked.

- [x] Reserve full description text layout before revealing words, keeping height, wraps and upward anchor stable; scroll follows revealed words rather than hidden trailing content.

- [x] Increase in-game description minimum width to 200px and maximum height to 250px, retaining image-edge clamping and scrolling.

- [x] Hide editor during game testing behind an opaque backdrop matching the active scene’s dialog color.

- [x] Exclude archived images from scene/object/area image selectors and reference-image dialogs, regardless of history archive visibility.

- [x] Use inventory on area accepts objects from every game page, with page labels in the picker and story-wide Add all objects; validation and companion guidance updated.

- [x] Remeasure game text after dialogs open to prevent collapsed initial descriptions; remove area-interaction close icon, retaining outside click and Escape dismissal.

- [x] Keep area-interaction dialog text visible without timeout or outside-pointer dismissal; replace it only on the next interaction response or dialog close.

- [x] Pickup areas with hover disabled and description text use first-click inspection, second-click pickup; armed area shows grab cursor and Click to pick up hint. Reset on scene change/test restart.

- [x] Remove companion selection prerequisite for named/bulk area edits; add copy_areas proposals between scenes on a page with fresh IDs, preserved settings/geometry and per-area review.

- [x] Compact chat review link; automatically open new proposal review; always-expanded checkboxes and recursively formatted structured data instead of raw JSON.

- [x] Place the review proposed changes link inline as the final sentence of its chat message paragraph.

- [x] Allow pickup/inspect areas to reuse objects from any page of the same game; resolve their images/descriptions in preview and retain foreign-story validation.

- [x] Add Inventory after Image generation with shared item cards, images, descriptions, replacement actions, autosave, deletion confirmation and page/scene usage links.
- [x] Migrate existing page objects to a game-wide inventory document preserving IDs and links; pages store scenes only. Legacy proposals/restore operations extract object definitions into the library.
- [x] Remove New object and scene-to-object conversion from Interactions; area item selection links existing inventory items without creating copies.
- [x] Update game preview, inventory rules, skip rules, image usage links and companion context/proposals for shared item IDs. Protect referenced items from deletion and reject stale inventory writes.

- [x] Verify shared Inventory on localhost:8010; full suite passes (145 PHP tests, 85 JavaScript tests), frontend build and Blade compilation successful.

- [x] Rename Interactions tab to Scenes, including preview labels, navigation hints, companion help and UI copy.

- [x] Add Up/Down layer controls to overlapping areas, autosave stacking order, provide front-to-back area selection for covered areas, and use the same z-index in game preview without covering narration/inventory.

- [x] Examine never executes hidden scene transitions or pickup behavior; clear incompatible scene targets on action changes and server saves. Repair the stale target on Take the brass key.

- [x] Add named boolean story flags with initial values, validation, stale-write protection and Story bible management; include flags in companion context/review and revision history.
- [x] Support flag/item conditions for scene jumps and area hide/disable behavior; set/clear flags on area, pickup, inventory-rule and choice outcomes. Preserve flags between pages, reset for a new playthrough and expose test starting flags.
- [x] Wire The Silent House stone/door state, populate page one's new open-stone overview, preserve correct returns and optional lid closing, prevent key respawn and remove porch back navigation. Previous authored state retained in History.
- [x] Show all pages' image history together, remove page-loading links and save author-only up/down sidebar ordering.
- [x] Verify the live story's open/pickup/back/close/reopen/door/key-consumption sequence without modifying gameplay state; 148 PHP tests and 90 JavaScript tests pass.

- [x] Remove the standalone References tab and library section; retain reference selection/uploads in image generation and fall back to Page plan for old References-tab URLs.

- [x] Combine page/scene navigation into one grouped dropdown; scene ordering controls remain scoped to the active page and cannot move scenes between pages.

- [x] Make area availability conditions opt-in, preserve existing configured rules and retain settings when disabled; collapse optional flag effects. Stabilize scene editor width and scrollbar space when switching areas.

- [x] Move story title/back link/revision beside the brand, put editor tabs in the second header, and pin the Scenes toolbar above scrolling content. Show scene number in the selected navigation label and account for responsive header height.

- [x] Constrain the story editor shell to the viewport using explicit grid row/child minimum heights; prevent document scrolling in every scene/area state while retaining independent editor and chat scrolling.

- [x] Align every scene dropdown row with a drag handle (disabled outside the active page), keep other-page reorder arrows hidden, and scroll to the active page/scene whenever opening the menu.

- [x] Automatically open inventory on pickup, fly the item into its visible slot, and reveal it on arrival. Use the same tray for inventory-area actions with persistent response text instead of duplicate dialog item lists.

- [x] Toggle examine narration on repeated clicks of the same area; clear expired messages and suppress immediate hover redisplay until leaving the area.

- [x] Wait for a newly opened inventory tray to have visible item geometry before measuring pickup flight destinations; use a bottom-of-scene fallback rather than zero-sized top-left coordinates.

- [x] Add validated per-area mouse cursors and companion editing support.
- [x] Keep inventory clicks for inspection; support pointer dragging and layered scene drop targets, returning unused items to their slots. Use standard positioned narration for inventory interactions, keep areas usable with the tray open, and defer consumption/flags/scene changes until response text completes plus a reading pause.

- [x] Remove the drawing-mode hint above the scene image to prevent layout shifts when toggling Draw area.

- [x] Make the selected clickable area name editable and autosaved directly in the form, aligned beside its Up/Down layer buttons.

- [x] Simplify area controls: hide redundant cursor/action labels, clarify the automatic cursor option, keep action flags open with an adjacent + Flag link, and remove the area-selection chip list.

- [x] Add Scenes-only Include scene image chat checkbox; attach the selected scene image privately, validate model vision support/ownership, redact image bytes from history, and budget image input separately from base64 payload size.
- [x] Persist unsent chat drafts per user/story/editor tab in localStorage, restore across tab navigation/reloads, and clear only an accepted message’s draft while preserving failed sends or newer drafts.

- [x] Reduce studio header to 40px with compact logo, theme selector, profile button, and single-line story/navigation layout.

- [x] Support reviewed creation of clickable areas on existing scenes, identify invalid suggestions by number/page/scene/area/field, open error reviews automatically, and repair the two affected stored proposals without applying them.

- [x] Compact story tabs, Creative companion header and scene toolbar to 40px; remove vertical padding/margins, bring tab underline closer to text, and fit dropdown/action buttons within the bar.

- [x] Treat image studio as a shared story library: remove source-page edit/archive restrictions, page-filtered loading and image pickers; allow drafts to be updated/generated from any page with story ownership checks.

- [x] Give image ordering arrows 36px bordered button targets that do not toggle the card; pulse the moved card after a successful reorder, with a static reduced-motion highlight.

- [x] Add confirmed permanent deletion for ungenerated image drafts; reject active/completed/failed requests, enforce story ownership, remove saved ordering entries, and clear the selected draft form.

- [x] Add Scenes > Extract collectible items: free plans from selected pickup areas, adjustable extraction rectangles and original-image selection, cropped references, clean background/layer/inventory outputs, shared image jobs and credit accounting, batch/per-output cost estimates and generation, independent versions/retries, alpha validation, accepted-composite preview, layer alignment, reviewed activation, persistent collected flags, and chat-history activity.
- [x] Test extraction ownership, free preparation, reference cropping, per-output job fencing/retries, opaque-layer rejection, stale activation and layer visibility after item consumption/return. Apply extraction migration and verify the localhost planner without paid generation.
- [ ] Optional follow-up: local empty-background patches for complex reflections/shadows that cannot be represented satisfactorily by transparent layers.

- [x] Show pickup areas with missing Inventory links in the extraction planner as disabled rows with a specific explanation instead of silently hiding them.

- [x] Extraction preparation creates and links missing shared Inventory items for selected pickup areas using their labels/descriptions; existing links are reused, changes are atomic, repeat plans do not duplicate items, and the editor receives updated revisions.

- [x] Replace extraction coordinate controls with cropped, zoomable area previews and selection cards; direct crop adjustments back to the scene areas. Remove numeric layer placement controls from the extraction dialog.

- [x] Scope extraction to the current scene image; remove source/plan settings from the main dialog and offer an initial saved-plan chooser only when that scene already has plans.

- [x] Remove the redundant Back to scene link from extraction; use the dialog Close button.

- [x] Fix extraction preparation for existing Inventory items without a description; fall back to area text or an empty string, with regression coverage.

- [x] Add Pick up object v2 and Change state v2 actions; restrict collectible extraction to pickup_v2 and Create switches to state_v2. Preserve legacy pickup/transition behavior and existing authored actions.
- [x] Add switch extraction plans with preserved original crops, independently generated/reviewed alternate patches, persistent toggle flags, no inventory creation, and no full-scene replacement. Support alternate-state prompts and reuse per-output estimates/retries.
- [x] Apply extraction-mode migration and verify v2/legacy behavior with 161 PHP tests and 105 JavaScript tests.

- [x] Keep extraction preparation disabled through save, server preparation and preview loading; show a spinner, live stage messages, completion feedback, and actionable errors. Block duplicate clicks and selection changes while preparing.

- [x] Fix extraction plan display by preserving reactive getters when composing the scene editor; poll only queued/running plan jobs, prevent overlapping refreshes, and remove redundant preparation instructions. Added integrated editor and polling regression tests.

- [x] Collectible extraction background removal uses a numbered annotated copy of the scene. Before preparation, choose original + annotated reference (default) or annotated only. Prompts list numbered items without decimal coordinates and require removal of annotations. Item crops and original assets remain untouched; existing saved plans retain their references.

- [x] Start image request expiry from ImageGeneration.started_at, excluding queue wait. Preserve queued reservations; retain four-minute expiry for running jobs and text requests. Added queue-wait boundary regression coverage (34 related tests passed).

- [x] Move and resize extracted image layers with their click-area frames, including keyboard movement and cancel restoration. Hide internal extraction crops/numbered references from scene, inventory and reference pickers while preserving AI access and uploaded references.

- [x] Delete individual user and companion chat messages with confirmation, owner-scoped endpoints and immediate UI removal; return persisted user-message IDs after replies so new messages can be deleted without reload. Applied game edits and billing records remain intact.

- [x] Hide a click area's on-image label, layer arrows and delete control during dragging/resizing; restore them on pointer release or cancellation.

- [x] Replace side-by-side extraction/switch previews with an Original / Accepted composition toggle and full-dialog-content-width image; keep composition layer visibility controls.

- [x] Extraction and switch workflows post one detailed chat summary only after successful scene activation; preparation/generation steps no longer post messages. Show the summary immediately in the companion.

- [x] Running image cards display local start time and live elapsed minutes/seconds based on worker started_at, including collapsed cards. Timer cleans up when leaving the studio.

- [x] Extraction/switch completed renders auto-accept on plan refresh, including opaque results for creator visual review. Unaccept preserves versions and does not modify the active scene; it remains unaccepted until a new render completes or the creator accepts a previous version. Re-activation applies replacements. Original switch crops remain preserved.

- [x] Click areas use their own Description only; removed the item-description checkbox and implicit inventory fallback. Local switches show their changed-state artwork in the editor, have a separate state_description, and require description then action clicks for each state when hover is disabled and text is present.
