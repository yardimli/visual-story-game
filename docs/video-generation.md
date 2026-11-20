# Video generation with a Windows ComfyUI worker

Videos are separate from gameplay for now. In **Video**, select an engine, first
frame and optional last frame, write the prompt, and save a draft or queue it.
Completed MP4s can be played and downloaded there. Chat can propose video drafts;
accepting a proposal does not start generation. Start it from Video.

## 1. Online server

Deploy this code, including `comfyui/engines.json` and the API workflow files.
Run from the repository directory:

```bash
composer install --no-dev --optimize-autoloader
php artisan migrate --force
npm ci
npm run build
```

Set the application's `.env`:

```dotenv
VIDEO_MODE=hub
VIDEO_HUB_URL=https://vgs.playground.computer
VIDEO_RELAY_TOKEN=
```

Then run `php artisan config:cache`. A hub never forwards video jobs. The Windows
worker polls its API, so a Laravel `queue:work` process is not needed for video
rendering. Existing image/audio workers remain unchanged.

Create two different credentials on the hub, replacing the example email with
an existing application user's email. Each command prints its secret once:

```bash
php artisan videos:token worker owner@example.com windows-gpu
php artisan videos:token relay owner@example.com local-development
```

The worker credential is trusted to process all queued videos on this server.
The relay credential can only read jobs imported using that credential. Imported
jobs do not attach to a hub story by a potentially conflicting local numeric ID.
They appear in the originating localhost story, while hub-created videos appear
in their hub story. Keep one relay credential per independent local database.

Reissuing the same role/email/name rotates a credential without losing its jobs.
Add `--revoke` to disable it. Do not put credentials in Git or browser JavaScript.

Allow MP4 uploads in both PHP and your reverse proxy. For example:

```ini
upload_max_filesize = 512M
post_max_size = 520M
max_execution_time = 600
```

For nginx, use `client_max_body_size 520m;` and an appropriate
`fastcgi_read_timeout 600s;` in the site configuration. Reload the affected services.
The application caps MP4 uploads/downloads at 512 MiB. Frames are capped at 20 MiB
on import; normal editor image uploads retain their existing 10 MiB limit.

## 2. Windows GPU PC

Install Python 3.10+ and start ComfyUI normally. Install all models/custom nodes
needed by the supplied workflows and verify the examples run directly in ComfyUI.
ComfyUI can stay bound to `127.0.0.1:8188`; no inbound Internet port is needed.

In Command Prompt, from this repository clone:

```bat
py -m venv worker\.venv
worker\.venv\Scripts\python.exe -m pip install -r worker\requirements.txt
copy worker\.env.example worker\.env
```

Edit **worker/.env** (separate from Laravel's `.env`):

```dotenv
VIDEO_SERVER_URL=https://vgs.playground.computer
VIDEO_WORKER_TOKEN=the-worker-credential-printed-on-the-server
VIDEO_WORKER_ID=windows-gpu-1
COMFYUI_URL=http://127.0.0.1:8188
VIDEO_POLL_SECONDS=10
```

Start the worker yourself:

```bat
worker\.venv\Scripts\python.exe worker\video_worker.py
```

Stop with Ctrl+C. Restart the same command to resume. Do not delete
`worker/state/active.json` while a job is active. The local journal and a process
lock prevent duplicate submissions. Use a distinct worker ID and clone/state
directory for each additional GPU worker. Keep the ID stable across restarts.

The worker snapshots its submission intent before calling ComfyUI and remembers
the returned prompt ID. After a lost response, it searches queue/history by the
job ID instead of sending the prompt again. If ComfyUI has lost the queue/history,
the job fails with an explicit uncertain-submission message. Check ComfyUI before
choosing **Create another version**. No generation is retried automatically.

The app shows the current stage, sampling progress when ComfyUI sends it, start
time and elapsed time. Start time is recorded when ComfyUI actually begins
executing, not when the app queues the job. A missed heartbeat shows a disconnected
worker notice; it does not hand the job to another worker and duplicate the render.
There is no inherited four-minute image timeout for these video jobs.

## 3. Local development installation

Run `php artisan migrate` and rebuild assets. Set the Laravel `.env`:

```dotenv
VIDEO_MODE=relay
VIDEO_HUB_URL=https://vgs.playground.computer
VIDEO_RELAY_TOKEN=the-relay-credential-printed-on-the-server
```

Run `php artisan config:clear`. Run the scheduler in another terminal:

```bat
php artisan schedule:work
```

The scheduler runs `videos:sync` every minute. It uploads queued frame snapshots,
imports jobs idempotently, polls hub status, and downloads completed MP4s with a
SHA-256 check. Closing the browser does not stop the transfer. For an immediate
single sync, use `php artisan videos:sync`. This command is a no-op in hub mode.
The local UI refreshes every five seconds; remote status can lag by the one-minute
sync interval. Keep the scheduler running on the local machine while testing.

If you use a Linux relay installation instead, the standard scheduler cron is:

```cron
* * * * * cd /absolute/path/to/visual-story-game && php artisan schedule:run >> /dev/null 2>&1
```

Both images are transferred as files, never localhost URLs. Returned videos are
stored privately on the local installation, so local playback does not rely on
the hub session. The hub retains its relay result as well; deleting a local video
does not delete the hub's transfer record.

## Adding another video engine

`comfyui/engines.json` is the shared engine registry used by PHP, the UI, chat,
and the worker. Add a new entry with:

- A stable engine ID, friendly `label`, and integer `version`.
- API-format `workflow` and optional `last_frame_workflow` filenames in `comfyui/`.
- `supports_last_frame`.
- `parameters` with type `select`, `integer`, or `number`, labels/defaults,
  ranges or options, and the target node/input. Select options map the UI value
  to the workflow's actual input value.
- `bindings` for prompt, first frame, optional last frame, seed, and output prefix.
- Optional `overrides` mapping node IDs to fixed input values, such as MP4 codec.

The current engine uses the supplied separate first/last-frame workflow. Its
duration node preserves the model's 17k+5 frame rounding, so actual MP4 duration
may be slightly longer than the requested 5–15 seconds. First-frame aspect ratios
should match the selected output ratio to avoid stretching.

New workflow engines must accept uploaded `LoadImage` filenames and produce an
MP4 file in the configured output node's history. Keep node-specific adaptations
in the registry/workflow, rather than the HTTP queue transport. Bump the engine
version when changing incompatible inputs and update the hub, relay and worker
together; workers only claim matching engine versions. Already queued jobs use
their recorded parameters and seed. Do not replace a version while it has active
jobs. Gameplay integration is intentionally deferred.

## Verification

```bash
php artisan test --filter=VideoGenerationTest
node --test tests/story-video.test.mjs
```

With the worker's Python environment:

```bat
worker\.venv\Scripts\python.exe -m unittest discover -s worker -p "test_*.py"
```

These tests do not submit paid requests or run GPU rendering. A real render
requires the server credentials, running ComfyUI, installed models, and worker.
