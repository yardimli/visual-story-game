# Companion authoring commands

The companion proposes saved application changes; it does not manipulate browser
controls. Approval applies either field edits or an explicit editor command.

## Chat context and scope

Chat includes all page prose, but full visual documents and extraction/container
plans only for the current page. A compact scene directory supplies cross-page
navigation IDs. Selection is included as IDs, not repeated scene documents.
Image history is page-scoped. Assets, sounds and videos follow references from
that page, its generation/planning records, shared inventory and characters,
global audio defaults, and explicit attachments. Narration is matched by text.
Bible, inventory, flags, characters, settings and recent chat history remain shared.

Every request also applies deterministic filtering; no summarization model is
called. The last ten conversation messages retain their exact roles and content.
Canonical page HTML is retained while duplicate Quill deltas are omitted.
Extraction version lists and previous backgrounds are omitted unless history or
retry context is relevant. Ordinary scene requests omit unused completed/failed
image history; assigned media, working jobs, named renders, and page image tasks
retain their relevant records. Media references are recalculated after filtering.
Redundant internal character/sound URLs are omitted, keeping their IDs and labels.

`ChatInstructions` consolidates current rules and selects detailed command families
from the active tab, request and latest exchange. Core authoring rules remain
available. Ambiguous requests retain all families; history/retry requests preserve
the broader schemas and historical metadata. This routing changes neither saved
game data nor conversation history. The manual Compact context button remains a
placeholder; the deterministic filtering already runs on every send.

Existing scene edits are limited to the scene selected when sending the request.
The server stores this scope on proposals and checks direct scene/area edits and
whole-document edits before review. Other scene IDs may be navigation destinations.

Above 250,000 JSON-encoded context bytes, chat returns a confirmation response
before saving a message, reserving credit or calling the provider. The panel offers
Cancel, Compact context (currently an alert only), and Continue sending. Continue
resubmits the captured request once with `allow_large_context=true`; model context
and credit checks still apply. No automatic retry follows a provider failure.

History refreshes use conditional GETs with a private ETag covering the snapshot.
Unchanged history returns 304 without a body. The initial page seeds this tag and
the pending-response state. Visible idle tabs check every 30 seconds; pending
reply recovery checks every 3 seconds. Hidden tabs and live local sends do not poll,
and focus/visibility return checks immediately. Changed snapshots still include
the latest 40 messages so deletions and saved review state reconcile correctly.

## Core proposals

- `create_character` / `character`: real character profiles with canonical text
  and labeled asset or image-draft views. Edits preserve omitted fields and check
  the profile revision independently of the story revision.
- `create_visual`, `create_hotspot`, `visual`, `hotspot`: scenes, clickable areas,
  explanations, single/dual action labels, click behavior, state images, geometry,
  music, sound, intro video, conditions and effects.
- `scene_document`: atomic updates to a page's complete scene document. Preserve
  unrelated fields and IDs. Scene deletion uses `scene.save` below for link cleanup.
- `story`: flags, theme, cover, gameplay messages and their display times, plus
  the existing story fields. New flags can be reviewed with dependent scene edits.
- `inventory`, `create_inventory`, `page`, `create_page`, `copy_areas`, `image`,
  `video`, `audio`, `missing_audio`, `create_container_plan` remain supported.
- Reference video drafts use `minimax_h3_reference`, ordered
  `reference_asset_ids`, first frame and optional last frame. Saving is not rendering.

## Manual editor bridge

`CompanionEditorActions::catalog()` is included in the model instructions. It is
an allowlist of controller/method pairs; model output cannot choose a PHP class,
method, route, file path, or another story. Each command goes through the same
controller validation, ownership checks, service logic and billing as the form.

```json
{
  "changes": [{
    "scope": "editor_action",
    "fields": {
      "operation": "character.view",
      "targets": {"character": "EXISTING_CHARACTER_UUID"},
      "input": {"page_id": 4, "label": "Rear full body"}
    }
  }]
}
```

Send an editor action alone so the full operation is one review item. The server
captures a fingerprint of the story, pages and selected records, supplies revision
tokens and generation request keys, and rejects stale approvals. The operation's
result is returned with the approval response. Reapplying the proposal is blocked
by the changed story revision.

The catalog covers full scene/inventory forms, page structure, flag rename/delete,
character reference drafts/deletion, image/video/audio draft editing and explicit
generation, media categories/order, image archive/rename, extraction/container
preparation and activation, combined-scene capture, publishing, and history restore.
Generation, deletion and publishing require an explicit user request followed by
the proposal review; generation rows are labeled as using credits. File selection
and uploads remain user controls; the model can select already-stored media.
Account credentials and credit settings are deliberately not exposed.

Adding a new editor form requires a catalog entry with input/target documentation
or a core proposal scope, plus tests. The allowlist is not automatic endpoint access.
Click-linked events use the current action/flag/condition schema; this change does
not introduce timers or click counters.
