# Visual Story Game (VSG)

Laravel 10 + Breeze (Blade/Alpine) + Tailwind visual-story creator.

## Current milestone

Working: registration/login/password reset/profile, private studio, 1–100-page story creation, page planning, story bible, plan approval, private reference uploads, persistent OpenRouter chat, review/apply assistant story proposals, draft revisions/restore, encrypted personal keys, and a lifetime $1 platform LLM budget.

The complete product backlog is in [todo.md](todo.md); [design-document.md](design-document.md) is the exported design. Image rendering, ComfyUI workers, scene interaction authoring, publishing/discovery, and the player are subsequent milestones and are not represented as finished controls.

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

All monetary amounts use integer millionths of USD. Before a platform request, the app locks the account, checks spent plus reserved balance, and reserves a conservative text-token maximum plus a margin. Provider routing has per-token/per-request price ceilings, bounded completion tokens, and no automatic fallback. Unknown prices and additional fee types require a personal key. Actual provider usage.cost settles the ledger exactly once. Duplicate request UUIDs cannot call the provider twice. Browser rate limits also apply.

Timeouts, missing cost, and ambiguous provider failures retain their reservation. Do not blindly refund these. Inspect the ledger/provider usage and reconcile a confirmed cost with:
`php artisan llm:reconcile REQUEST_ID COST_IN_MICRO_USD`
This command requires operator confirmation. If a provider reports an amount above the reservation, it is recorded rather than hidden and further platform spending is blocked as appropriate. Provider billing guarantees still require live integration testing; the tests use mocked provider responses.

Personal-key requests do not spend platform credit and never fall back to the platform key. Images will use personal keys until a separate image funding policy is implemented. Chat currently runs synchronously with a 120-second provider timeout; queued/streaming chat remains a scaling improvement. Up to ten recent messages plus the complete saved story form context; oversized context is rejected rather than silently discarding story pages. Automatic summarization remains pending.

API references used:
- [Model catalog](https://openrouter.ai/docs/guides/overview/models)
- [Usage accounting](https://openrouter.ai/docs/cookbook/administration/usage-accounting)
- [Provider price ceilings](https://openrouter.ai/docs/guides/routing/provider-selection)

## Tests and checks

`php artisan test` uses SQLite in memory, a test APP_KEY, and a fake platform key through phpunit.xml. It never migrates the configured development database. Tests cover Breeze authentication, project isolation, upload access, revision conflicts, full-plan approval, encrypted secrets, credit exhaustion, duplicate requests, reservations, uncertain costs, and personal-key routing.

`npm run build` builds production CSS and JS. `php artisan view:cache` verifies template compilation.

## Deployment notes

The intended production host is Ubuntu. Set APP_ENV=production, APP_DEBUG=false, APP_URL to the HTTPS origin, and SESSION_SECURE_COOKIE=true; serve only public/. Configure mail transport before relying on password reset delivery. Back up the database, private storage, and APP_KEY. The default MAIL_MAILER=log is for development; reset links are written to local logs when no mail transport is configured.

The user requested Laravel 10. Composer audit currently reports known framework advisories, including TokenGuard, debug-page XSS, signed URL path confusion, and email validation. This app uses Breeze session auth and private ownership checks, avoids TokenGuard and temporary signed media URLs, disables debug, and adds strict email input rejection. These mitigations do not make Laravel 10 a fully patched framework. Resolve the framework upgrade/backport decision before public deployment. The JavaScript dependencies were updated to remove the audit findings from the original Breeze scaffold.

## Next milestones

Follow todo.md in order. Add image generation and capability validation; then the Windows worker protocol (outbound pull, leased claims, heartbeat, uploads, idempotency), scene state authoring, immutable publishing, and the linear player. No ComfyUI connection or paid render has been attempted by the setup process.

## Interface and theme

The home page presents the full product feature set and labels unfinished features as in development. Authentication, account settings, and creator studio share the VSG design. The theme toggle stores a browser-local choice under vsg-theme, follows system appearance until a choice is made, and applies before rendering to avoid a light-theme flash.

### Creative companion
The shared studio panel is available on the dashboard, story editor, profile, and AI settings. Choose a writing model and ask for a proposal. **Fill form draft** changes only the visible draft fields; submit the ordinary form to save. Story proposals affect only the current page and bible. Account/security fields are excluded. Desktop uses a sticky right panel; mobile stacks the expanded companion below the content.

Run `php artisan migrate` after updating: account conversations have their own private message table and LLM ledger records can now exist without a story. Existing balances and story histories are preserved. Story settings also include a separate image-model selector for planning; image generation remains on the roadmap.

### Model policy and token accounting (updated)
`OPENROUTER_PROVIDERS=deepseek,anthropic,openai,moonshotai,google,qwen` controls the visible editorial catalog by model ID author prefix, not inference hosting provider. Changes apply after `php artisan config:clear` if configuration is cached. Text-output models are filtered by the editorial policy (legacy/coding/lite and small Qwen exclusions), with latest version/snapshot per product family; batch/free variants are omitted. Image-only models are excluded from this editorial catalog; a dedicated generation catalog remains future work.

Dropdown prices are catalog USD per million input/output tokens. The app allowance now charges `(prompt_tokens × prompt_rate + completion_tokens × completion_rate)` in USD, rounded up to integer microUSD. Catalog API rates are per token. OpenRouter `usage.cost` is ignored, including for BYOK zero-cost responses. Applicable long-context and UTC schedule overrides are applied from a request-time pricing snapshot. Cache discounts and separate add-on charges do not alter this app token-rate formula; completion totals already include reasoning. Personal keys continue to bypass the platform allowance. Missing/invalid token counts retain the reservation for reconciliation. Historical settled records are not rewritten.

Image discovery update: the image selector now uses GET /api/v1/images/models and reads provider pricing/capabilities from /images/models/{author}/{slug}/endpoints. Text editorial exclusions do not apply; OPENROUTER_PROVIDERS and batch/free exclusions still apply. Display price lines by their image/megapixel/token units and variants. The image transport uses POST /api/v1/images with prompt/reference inputs, not chat completions. The creation queue, validation, asset storage, and image-specific accounting remain pending; selecting a model makes no paid request.

Autosave update: existing story and page fields save automatically after a 400ms typing pause. Applying a companion proposal also autosaves. Writes are serialized across forms with revision checks; validation failures retain text, and ambiguous failures/conflicts stop automatic retries. The editor shows save status and waits for pending changes before navigation, chat, approval, or restore. Creation, uploads, and account/security actions remain explicit. This supersedes the earlier manual Save-button behavior for existing story/page forms.
