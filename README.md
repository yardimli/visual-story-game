# Visual Story Game (VSG)

Laravel 10 + Breeze (Blade/Alpine) + Tailwind visual-story creator.

## Current milestone

Working: registration/login/password reset/profile, private studio, 1–100-page story creation, page planning, story bible, private reference uploads, persistent OpenRouter chat, review/apply assistant story proposals, draft revisions/restore, encrypted personal keys, and a lifetime $1 platform budget shared by writing and images.

The complete product backlog is in [todo.md](todo.md); [design-document.md](design-document.md) is the exported design. Queued image rendering, scene states, object images, clickable-area authoring, and a local interaction preview are implemented. ComfyUI workers, publishing/discovery, and the full player remain subsequent milestones.

## Run locally

Requirements: PHP 8.1+, Composer, Node 22.12+ (tested Node 24), a Laravel-supported database, and PHP database/fileinfo/mbstring/openssl extensions.

1. `composer install`
2. On a new checkout, copy `.env.example` to `.env` and configure the database. **Do not overwrite an existing .env.**
3. `php scripts/prepare-env.php` appends missing defaults and preserves all existing DB_* entries, DATABASE_URL, and OPEN_ROUTER_KEY. It creates an application encryption key only if missing/blank. Keep that key stable: personal provider keys are encrypted with it.
4. `php artisan migrate` creates application tables. Never run migrate:fresh on a shared database.
5. `npm ci && npm run build`
6. `php artisan serve --host=127.0.0.1 --port=8010`
7. Visit http://localhost:8010 and register. The host must match SESSION_DOMAIN if set (the existing environment uses localhost). The explicitly requested demo account is demo@demo.com with password demo. Recreate it with php artisan db:seed --class=DemoUserSeeder; it is never seeded automatically.

Use `npm run dev -- --host 127.0.0.1` for frontend development. The existing database and platform OpenRouter key were preserved during setup. Uploaded references use private local storage and authenticated routes; no storage symlink is needed for them.

## AI billing

Set `OPEN_ROUTER_KEY` to the platform key and `LLM_ALLOWANCE_MICRO_USD=1000000` for $1. No automatic resets. Users can save a personal OpenRouter key in AI settings after confirming their password. Personal keys are encrypted, hidden from model serialization, excluded from flashed validation input, and never sent to JavaScript.

All monetary amounts use integer millionths of USD. Before a platform request, the app locks the account, checks spent plus reserved balance, and reserves a conservative text-token maximum plus a margin. Provider routing has per-token/per-request price ceilings, bounded completion tokens, and no automatic fallback. Unknown prices block platform-funded requests. Returned token counts and captured catalog rates settle the ledger exactly once (see the updated token-accounting policy below). Duplicate request UUIDs cannot call the provider twice. Browser rate limits also apply.

Timeouts, missing token counts, and ambiguous provider failures retain their reservation. Do not blindly refund these. Inspect the ledger/provider usage and reconcile a confirmed cost with:
`php artisan llm:reconcile REQUEST_ID COST_IN_MICRO_USD`
This command requires operator confirmation. If a provider reports an amount above the reservation, it is recorded rather than hidden and further platform spending is blocked as appropriate. Provider billing guarantees still require live integration testing; the tests use mocked provider responses.

Personal-key requests do not spend platform credit and never fall back to the platform key. Images and chat share the lifetime $1 trial balance; a saved personal key bypasses that balance. Chat currently runs synchronously with a 120-second provider timeout; queued/streaming chat remains a scaling improvement. Up to ten recent messages plus the complete saved story form context; oversized context is rejected rather than silently discarding story pages. Automatic summarization remains pending.

API references used:
- [Model catalog](https://openrouter.ai/docs/guides/overview/models)
- [Usage accounting](https://openrouter.ai/docs/cookbook/administration/usage-accounting)
- [Provider price ceilings](https://openrouter.ai/docs/guides/routing/provider-selection)

## Tests and checks

`php artisan test` uses SQLite in memory, a test APP_KEY, and a fake platform key through phpunit.xml. It never migrates the configured development database. Tests cover Breeze authentication, project isolation, upload access, revision conflicts, full-encrypted secrets, credit exhaustion, duplicate requests, reservations, uncertain costs, and personal-key routing.

`npm run build` builds production CSS and JS. `php artisan view:cache` verifies template compilation.

## Deployment notes

The intended production host is Ubuntu. Set APP_ENV=production, APP_DEBUG=false, APP_URL to the HTTPS origin, and SESSION_SECURE_COOKIE=true; serve only public/. Configure mail transport before relying on password reset delivery. Back up the database, private storage, and APP_KEY. The default MAIL_MAILER=log is for development; reset links are written to local logs when no mail transport is configured.

The user requested Laravel 10. Composer audit currently reports known framework advisories, including TokenGuard, debug-page XSS, signed URL path confusion, and email validation. This app uses Breeze session auth and private ownership checks, avoids TokenGuard and temporary signed media URLs, disables debug, and adds strict email input rejection. These mitigations do not make Laravel 10 a fully patched framework. Resolve the framework upgrade/backport decision before public deployment. The JavaScript dependencies were updated to remove the audit findings from the original Breeze scaffold.

## Next milestones

Follow todo.md for remaining work: the Windows video worker protocol (outbound pull, leased claims, heartbeat, uploads, idempotency), inventory-use rules, immutable publishing, and the linear player. No ComfyUI connection or paid render has been attempted by the setup process.

## Interface and theme

The home page presents the full product feature set and labels unfinished features as in development. Authentication, account settings, and creator studio share the VSG design. The theme toggle stores a browser-local choice under vsg-theme, follows system appearance until a choice is made, and applies before rendering to avoid a light-theme flash.

### Creative companion
The shared studio panel is available on the dashboard, story editor, profile, and AI settings. Choose a writing model and ask for a proposal. **Fill form draft** applies suggestions to existing story/page fields, which autosave; creating a new story remains explicit. Story proposals affect only the current page and bible. Account/security fields are excluded. Desktop uses a sticky right panel; mobile stacks the expanded companion below the content.

Run `php artisan migrate` after updating: account conversations have their own private message table and LLM ledger records can now exist without a story. Existing balances and story histories are preserved. Story settings include a separate image-model selector; use Images & interactions on a page to render artwork.

### Model policy and token accounting (updated)
`OPENROUTER_PROVIDERS=deepseek,anthropic,openai,moonshotai,google,qwen` controls the visible editorial catalog by model ID author prefix, not inference hosting provider. Changes apply after `php artisan config:clear` if configuration is cached. Text-output models are filtered by the editorial policy (legacy/coding/lite and small Qwen exclusions), with latest version/snapshot per product family; batch/free variants are omitted. Image-only models are excluded from this editorial catalog; the dedicated image catalog is used for generation.

Dropdown prices are catalog USD per million input/output tokens. The app allowance now charges `(prompt_tokens × prompt_rate + completion_tokens × completion_rate)` in USD, rounded up to integer microUSD. Catalog API rates are per token. OpenRouter `usage.cost` is ignored, including for BYOK zero-cost responses. Applicable long-context and UTC schedule overrides are applied from a request-time pricing snapshot. Cache discounts and separate add-on charges do not alter this app token-rate formula; completion totals already include reasoning. Personal keys continue to bypass the platform allowance. Missing/invalid token counts retain the reservation for reconciliation. Historical settled records are not rewritten.

Image discovery update: the image selector now uses GET /api/v1/images/models and reads provider pricing/capabilities from /images/models/{author}/{slug}/endpoints. Text editorial exclusions do not apply; OPENROUTER_PROVIDERS and batch/free exclusions still apply. Display price lines by their image/megapixel/token units and variants. The image transport uses POST /api/v1/images with prompt/reference inputs, not chat completions. Generation now uses a database queue with capability validation and private asset storage. Images use shared trial credit or the saved personal key; selecting a model makes no paid request.

Autosave update: existing story and page fields save automatically after a 400ms typing pause. Applying a companion proposal also autosaves. Writes are serialized across forms with revision checks; validation failures retain text, and ambiguous failures/conflicts stop automatic retries. The editor shows save status and waits for pending changes before navigation, chat or restore. Creation, uploads, and account/security actions remain explicit. This supersedes the earlier manual Save-button behavior for existing story/page forms.

### Page management
Use **Add page** in the storyboard and **Move earlier**, **Move later**, or **Remove page** above the page form. Stories retain 1–100 active pages. Actions wait for autosave and preserve revision history. Removed pages are recoverable through **History → Restore**, which now restores page membership, order, and content as well as the story details. Review free-text item dependencies after reordering; structured dependency validation remains on the roadmap. Run `php artisan migrate` when updating an existing installation.

Planning and creation are freely interchangeable. There is no full-plan approval action or gate; readiness counts are informational. Individual generation jobs will validate their own required inputs.


### Scene images and clickable areas
Open a page's **Images & interactions** tab. Add named **Scene states** for backgrounds and **Object / close-up** visuals for notes, inventory items, or inspection images. Each visual has its own prompt and chosen image. Generate multiple versions, select one explicitly, or select an uploaded image from References. Choose earlier renders as references for consistent scene edits. Settings and prices come from the chosen model's endpoint capabilities. The app accepts PNG, JPEG, and WebP outputs.

Run the image queue in a **separate terminal**, keeping your existing web server running:

```sh
php artisan queue:work database --queue=images --timeout=240 --tries=1
```

Image jobs explicitly use the database connection even if the default queue connection is sync. Database `retry_after` is 420 seconds, longer than the 240-second worker timeout. On Windows PHP, process timeout enforcement may be unavailable; production workers should run on Ubuntu with PCNTL and a process supervisor. Restart workers after code/config updates. Jobs remain queued until a worker runs. Generation uses available trial credit or a saved personal OpenRouter key. Prompt, reference paths, settings, and endpoint prices are snapshotted; credentials never enter the queue payload. No automatic retry of paid calls. If a worker is killed during a running request, inspect provider activity before resubmitting; a running job may require operator reconciliation.

For the Silent House example:
1. Create a porch background without the note and an isolated note object.
2. Draw a rectangle on the porch, choose **Inspect**, select the note object, and enable its overlay if the note should appear on the background.
3. Create an **Opened stone** scene state (a close-up can also be a scene state when it needs clickable contents). Draw an area over the closed stone and choose **Change scene state** with this target.
4. Create the key object. On the opened-stone state, add **Pick up object** targeting the key; optionally select another background state with the key removed. Add a return-to-porch area if needed.
5. Use **Test interactions** to inspect objects, change states, and collect items. Reset clears local preview inventory. This preview does not alter saved story data.

Area labels, descriptions, normalized rectangle coordinates, overlay settings, and scene targets autosave with their own page visual revision. **Add area** plus numeric coordinates is the keyboard alternative to drawing. Cross-project assets and invalid targets are rejected. Visual document history restoration, conditions/key-use rules, crafting, and published gameplay remain backlog items. The companion still edits prose planning fields, not the visual document.


### Shared image trial billing
Image generation reserves against the same `llm_spent` / `llm_reserved` balance as writing. The legacy column and ledger names are retained to preserve balances. Funding is fixed when a job is queued. A saved personal key takes priority; removing it cannot make an already queued personal job spend platform credit. Existing jobs created before this update retain their personal-key behavior.

Image settlement uses captured endpoint catalog rates, never OpenRouter `usage.cost`: flat image/reference prices use actual counts, megapixel prices use image dimensions, and token prices use returned token counts. Differing input text/image rates require a token breakdown; missing accounting retains the reservation for reconciliation instead of assuming the render was free. Variant-specific pricing requires matching explicit settings. Token reservations use conservative estimates (16,384 output tokens minimum, scaled for resolution, plus prompt bytes and 16,384 input tokens per reference); these are not provider-enforced limits. As with chat, an actual cost above the estimate is recorded and blocks further trial spending when exhausted. Unknown pricing or insufficient unreserved balance blocks the request before queueing. Failure before any provider call releases credit; ambiguous provider outcomes retain it. The existing `llm:reconcile` command can reconcile image ledger entries too.

Run `php artisan migrate` and restart image workers after this update. No changes to existing credit balances or provider keys are needed.


Image routing update: the generation model dropdown displays endpoint catalog prices with image, megapixel, or million-token units and ranges across providers/settings. OpenRouter chooses the provider automatically; no `provider.only` constraint is sent. Optional settings are limited to common endpoint capabilities. Trial reservation uses the highest endpoint quote. Settlement uses the returned provider identity when available, or identical computed costs across endpoints; ambiguous provider-dependent costs retain their reservation for reconciliation rather than guessing. Existing queued jobs keep their original snapshots.


Image generation workspace: use the Image generation link for the current page. The dedicated page keeps the creative companion visible, shows generated images with their immutable request prompts/model names, and provides Generate scene, Generate object, and Edit this image. Editing selects the source render as a reference and produces a new version. The companion can propose the selected visual’s image prompt; applying it checks the visual ID/revision and autosaves only that prompt. Rendering remains an explicit action. The Interactions tab focuses on content, selected assets, clickable areas, and preview.


### Three-minute AI timeout (current policy)
Pending or uncertain AI ledger requests expire 240 seconds after creation, including queue wait time. Cleanup marks them failed and releases platform holds exactly once. Completed charges are untouched. Late results cannot re-charge an expired request; expired queued images never call the provider. HTTP provider calls time out after 240 seconds. This policy supersedes older instructions to retain ambiguous holds indefinitely. Upstream providers can still bill work after the application deadline; those costs are not re-debited from released trial credit.

Run `php artisan schedule:work` alongside the web server and image worker locally. The scheduled `ai:expire-reservations` command runs every second; production should run Laravel's scheduler through the standard minute cron (`php artisan schedule:run`), which keeps the sub-minute schedule active. Authenticated page requests, image status polling, and new reservations also clear overdue holds, so credit recovers even if the scheduler is unavailable. Run `php artisan ai:expire-reservations` for immediate cleanup. Restart long-running image/scheduler workers after code updates.


### Grant AI credits (server administrator)
`token_credits` records grants in integer micro-USD (`amount`: 1,000,000 = $1). New accounts receive one $1 signup grant. Existing accounts are backfilled without resetting their spend or reservations. Available credit is total grants minus platform spend and active reservations; personal-key calls remain separate.

Grant an additional $5:
```powershell
php artisan ai:grant-credit demo@demo.com 5 --description="Testing credit"
```
Optional `--reference=unique-grant-id` prevents duplicate grants when repeating an admin operation. Only trusted server administrators should run this command. AI settings lists the account's grants; there is no user-accessible credit-grant endpoint.
