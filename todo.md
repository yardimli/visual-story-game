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
