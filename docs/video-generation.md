# Video generation with a Windows ComfyUI worker

Videos are separate from gameplay for now. In **Video**, select an engine, first
frame and optional last frame, write the prompt, and save a draft or queue it.
Completed MP4s can be played and downloaded there. Chat can propose video drafts;
accepting a proposal does not start generation. Start it from Video.

## Video engines and image roles

**FastVideo MiniMax H3** remains the default for first-frame and optional
last-frame clips. **MiniMax H3 Reference** uses the supplied
`comfyui/video_minimax_h3_r2v_api.json` with the Ref2VA checkpoint. Normal mode
uses 20 steps. **Fast mode (Lightning LoRA)** enables the workflow's model/steps
switch and uses 4 steps; there is no editable step count. It requires
`minimax_h3_ref2v_turbo_4step_v0.1_comfyui_bf16.safetensors` in ComfyUI's LoRA models.
Reference engine version 2 requires updating the app/hub and worker repository
and restarting the worker. Older saved drafts normalize on save/queue to normal
mode unless Lightning is selected; already queued jobs are not rewritten.
The visual `video_minimax_h3_r2v.json` is the editable ComfyUI example;
the worker submits the API export.

For Reference jobs, choose the first frame and optional last frame as usual,
then add up to nine reference images. These guide identity, objects, or style;
they are not additional timeline frames. The worker replaces the example
images and dynamically wires `ref_images.ref_image_0` through `_8`. Their order
matches `<Picture 1>` through `<Picture 9>` in the editor and prompt. Say explicitly
when two pictures depict different views of the same person. Removing a reference
renumbers the following images, so check the prompt afterward.

The worker adds native `MiniMaxH3AddGuide` nodes at frame 0 and, when supplied,
frame -1, chaining their conditioning into the guider. These frame images do not
consume the nine reference slots or Picture numbers. They condition the timeline;
they are not pasted onto the output. Audio/video reference inputs are not exposed.

Example with an empty room as the first frame, a chest close-up as the last,
and a portrait as Picture 1:

> The person in <Picture 1> enters the empty room from the left, walks to the
> chest, and bends down beside it. The camera moves forward past his right
> shoulder and finishes on the chest. Preserve his face, coat, and proportions.

Use a current ComfyUI installation containing `MiniMaxH3ReferenceToVideo` and
`MiniMaxH3AddGuide`. Missing nodes cause a clear error before submission. The
Ref2VA checkpoint and shared encoder/VAEs must match the API workflow filenames.
The supplied workflow also contains its optional turbo LoRA loader; install that
LoRA as documented by ComfyUI, although turbo is disabled for this engine.

Deploy the migration, PHP changes, and rebuilt frontend to both local/relay and
hub installations. Copy the updated `worker/video_worker.py`, engine catalog,
and R2V API workflow to the GPU machine, then restart the worker. Queued reference
images are snapshotted and transferred in order, like first/last frames.

Sources: [native H3 workflows](https://docs.comfy.org/tutorials/video/minimax/minimax-h3-native)
and [native node implementation](https://github.com/Comfy-Org/ComfyUI/blob/master/comfy_extras/nodes_minimax_h3.py).

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
VIDEO_HUB_URL=https://vsg.playground.computer
VIDEO_RELAY_TOKEN=
```

Then run `php artisan config:cache`. A hub never forwards video jobs. The Windows
worker polls its API, so a Laravel `queue:work` process is not needed for video
rendering. Existing image/audio workers remain unchanged.

Create two installation credentials on the hub. No user account or email is
required. Each command prints its secret once:

```bash
php artisan videos:token worker windows-gpu
php artisan videos:token relay local-development
```

Any signed-in user can create videos for their own stories. Online jobs queue
directly on the hub; local jobs relay to the hub and download back to localhost.
Credentials identify installations, not the users creating videos. Existing
credentials remain valid after running the migration.

The worker credential is trusted to process all queued videos on this server.
The relay credentwial can only read jobs imported using that credential. Imported
jobs do not attach to a hub story by a potentially conflicting local numeric ID.
They appear in the originating localhost story, while hub-created videos appear
in their hub story. Keep one relay credential per independent local database.

Reissuing the same role/name rotates a credential without losing its jobs.
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
VIDEO_SERVER_URL=https://vsg.playground.computer
VIDEO_WORKER_TOKEN=the-worker-credential-printed-on-the-server
VIDEO_WORKER_ID=windows-gpu-1
VIDEO_WORKER_USER_AGENT=VisualStoryGame-VideoWorker/1.0
COMFYUI_URL=http://127.0.0.1:8188
VIDEO_POLL_SECONDS=10
```

The worker uses `VisualStoryGame-VideoWorker/1.0` instead of the default
`python-requests` User-Agent for all hub requests, including uploads. Allow this
value through any User-Agent filter on the server, or set
`VIDEO_WORKER_USER_AGENT` to your preferred allowed value. Token authentication
is still required. Restart the worker after changing this setting.

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
VIDEO_HUB_URL=https://vsg.playground.computer
VIDEO_RELAY_TOKEN=the-relay-credential-printed-on-the-server
```

Run `php artisan config:clear`, then use the same worker as images and audio:

```bat
php artisan queue:work database --queue=images,audio,video --timeout=240 --tries=1
```

Local videos automatically enqueue their relay work on `video`. Each attempt uploads
frames, checks hub status, or downloads the completed MP4 with a SHA-256 check.
While the GPU renders, the job waits 15 seconds between checks and releases the worker
for other jobs. Transport failures retry after 60 seconds without creating a second
remote video. Closing the browser does not stop the transfer. No scheduler is required
for videos. A production hub needs no cron change: its GPU worker claims videos
directly through the API. Only relay installations need `video` in their Laravel
worker queue list. Restart existing workers after updating the code.

To resume videos queued before this change, or recover pending transfers, run
`php artisan videos:sync` once. It enqueues pending relay work without duplicating
already queued transfers and is a no-op in hub mode.

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
