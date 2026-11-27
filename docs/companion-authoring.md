# Companion authoring commands

The companion proposes saved application changes; it does not manipulate browser
controls. Approval applies either field edits or an explicit editor command.

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
