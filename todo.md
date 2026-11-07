# Implementation work list

Source: [design document](./design-document.md). Updated as implementation progresses.
Status: [ ] pending, [x] completed. Unchecked acceptance criteria remain outstanding.

## Delivery order and assumptions
Build foundations, authoring, AI billing, assets, worker integration, then publishing and player interactions. Use Laravel 10, Breeze Blade, Tailwind, and Alpine. The $1 allowance is lifetime per user; image jobs require a personal key until separately funded. Provider calls are mocked in automated tests. Never overwrite existing database settings or OPEN_ROUTER_KEY.

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
- [x] Full-plan approval and invalidation when relevant content changes.
- [ ] Ordered page navigation, add/delete/reorder with dependency validation.
- [x] Reviewable draft changes, revision history, undo.

## S05 Creator editor
As a creator I can work with scene, page list, inspector, and persistent chat together.
- [ ] Responsive Tailwind studio shell, page navigation, story/visuals/interactions/states/preview tabs.
- [x] Editable story and page forms with validation and save feedback.
- [x] Docked creator chat with project and selected-page context.
- [ ] Model selectors, generation status, allowance indicator.
- [ ] Keyboard-accessible controls and useful empty/error states.

## S06 OpenRouter catalog and chat
As a creator I choose an available OpenRouter LLM to plan, write, or revise my story.
- [x] Server-side model catalog and separate text/image model selection.
- [x] Persist conversations/messages and model used per answer.
- [ ] Include story bible, page plan, inventory rules, and relevant selected page.
- [x] Validate proposed structured changes; apply and undo without arbitrary execution.
- [ ] Context limits and explicit long-story summarization/retrieval.
- [x] Handle unavailable models, timeouts, and provider failures visibly.

## S07 One-dollar LLM allowance
As a user I can use up to $1 of platform LLM credit then use my own key.
- [x] Integer-denominated ledger and $1 default lifetime limit.
- [x] Lock user balance and reserve conservative maximum before provider request.
- [x] Price-aware token limits; reject unknown/unbounded pricing for platform key.
- [x] Reconcile provider-reported actual cost; no double charging on retries.
- [x] Retain uncertain reservations for reconciliation; no automatic credit release after ambiguous failures.
- [x] Personal-key calls bypass platform spending and never silently fall back to platform key.
- [ ] Test exhausted budget, concurrency/idempotency, uncertain calls, and personal-key routing.

## S08 Reference and asset library
As a creator I can upload and reuse images and video assets.
- [x] Private project-scoped upload/download with type and size validation.
- [ ] Reference roles, ordering, prompts, source asset versions and approval.
- [ ] Preview image/video assets and select playable/start/end images.
- [ ] Image model capability checks, one/multiple input references.
- [ ] Queued OpenRouter image generation and scene editing.
- [ ] Track job failure/retry; preserve originals and mark dependencies outdated.
- [ ] Separate image billing policy and personal-key enforcement.

## S09 Objects and scene states
As a creator I can define a playable scene and its visual changes.
- [ ] Named states and approved scene images.
- [ ] Normalized rectangle/polygon hotspot editor with keyboard alternatives.
- [ ] Object descriptions, highlight masks/regions, visibility conditions.
- [ ] Item definitions, isolated renders, reusable/consumable behavior.
- [ ] Containers, contents, and empty states.
- [ ] Conditions/effects rule builder and recipes.
- [ ] Reachable-state and required-item dependency checks.

## S10 Video production API
As a creator I enqueue local ComfyUI renders from approved plans.
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
- Remaining within early milestones: page add/reorder/delete, image-model catalog UI, automated long-context summarization, dedicated multiprocess concurrency tests, and richer readiness indicators.
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
- [x] Story suggestions populate page/bible forms; creator explicitly saves changes.
- [x] Persist private account conversation, model choice, errors, and allowance handling.
- [x] Test isolation, billing, proposal validation, and draft-only behavior.
- [x] Continue backlog: dashboard planning readiness and production status.
- [ ] Extend contextual form assistance to future asset, interaction, and production screens as implemented.

- Companion milestone: shared panel on dashboard, story editor, AI settings, and profile. Desktop panel stays alongside the form; narrow screens keep an expanded panel below content. Account conversation is user-scoped; story conversation remains project-scoped. Public/guest screens and future production screens remain to be integrated.
- Suggestions now fill allowlisted browser form fields only. Save/Create buttons remain explicit; existing server revision checks still protect submitted changes. Account passwords and keys are never read into companion context.
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
