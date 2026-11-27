<?php
namespace App\Services;

use App\Models\Story;
use Illuminate\Validation\Rule;

class CompanionAuthoring
{
    public function validateStory(Story $story, array $fields): void
    {
        validator($fields, [
            'ui_theme' => ['sometimes', Rule::in(array_column(app(GuiThemes::class)->options(), 'id'))],
            'cover_asset_id' => ['sometimes', 'nullable', 'integer', Rule::exists('assets', 'id')->where('story_id', $story->id)->where(fn ($query) => $query->where('mime', 'like', 'image/%'))],
            'gameplay_messages' => 'sometimes|array:placed,occupied,placement_denied,nothing,complete,scene_load_error,no_scenes,missing_pages,next_scene_missing,destination_page_missing,destination_scene_missing',
            'gameplay_messages.*' => 'array:text,timeout_ms',
            'gameplay_messages.*.text' => 'nullable|string|max:3000',
            'gameplay_messages.*.timeout_ms' => 'required|integer|min:0|max:60000',
        ])->validate();
    }

    public function instructions(): string
    {
        return <<<'TEXT'
AUTHORING COMMANDS: Propose concrete saved edits, not instructions for the creator to fill forms. Every proposal is reviewed before application; say "proposed" or "drafted", never "added" or "applied" before it is accepted.
Create a reusable character with {"scope":"create_character","fields":{"name":"The Visitor","description":"Canonical appearance and clothing","views":[{"label":"Rear full body","asset_id":EXISTING_IMAGE_ID}]}}. Edit with scope character and character_id from characters context; fields may include name, description, views. Omitted fields are preserved. When changing views, include all retained views. A view can link an existing image draft with image_id instead of asset_id. Store profiles in Characters, never substitute Bible prose for a requested character. Use image scope for a new unrendered reference-image draft.
Create a scene on an existing page with {"scope":"create_visual","page_id":ID,"fields":{"name":"Room","kind":"scene","asset_id":EXISTING_IMAGE_ID_OR_NULL,"description":"Scene introduction","hotspots":[]}}. The server assigns IDs to the new visual and its hotspots. Use create_hotspot to add click areas to an existing scene. For coupled edits to multiple scene fields/areas, including reordering scenes or adding/removing areas, use {"scope":"scene_document","page_id":ID,"fields":{"visuals":[COMPLETE_DESIRED_PAGE_VISUALS]}}. Preserve existing IDs and all unrelated settings; the entire document is one review item and uses the manual editor validator. Do not combine scene_document with other scene edits on that page in one proposal. Use editor_action scene.save to remove scenes, so the manual editor cleans destination links. Prefer narrow visual/hotspot commands for independent fields. Do not invent IDs for existing targets.
Click events use hotspot.action, not a generic events array. Supported actions are examine, inspect, transition, pickup_v2, state_v2, inventory, choices, page, next_page, complete. Use click_behavior=text to show text then action choices, or direct to execute immediately. Action presets: take,open,close,look,enter,search,read,talk,continue,back,press,open_close,pull_push,turn_on_off,insert_remove,custom_single,custom_dual. Custom single uses action_label; custom dual also uses changed_action_label. description is the original explanation, state_description the changed explanation; unavailable_text and changed_unavailable_text guide blocked actions. Keep labels stable when requirements change. No cursor tooltips.
Conditions and effects are arrays of {key:EXISTING_FLAG_KEY,value:BOOLEAN}: flag_conditions gates an action, flag_effects applies after it, visible_when shows an area, disabled_when disables it. availability_enabled enables required_items (inventory UUIDs) and flag_conditions. To add flags use story fields.game_flags with all existing flags retained plus new {key,label,initial} entries. Include the flag proposal and its dependent interactions together and explain both must be accepted. state_v2 uses state_flag and state_asset_id; pickup_v2 uses object_id, collectible_flag, initial_empty, placement_enabled. auto_state is {enabled,sticky,flag_conditions,placements:[{area_id,object_id}],original_target,changed_target}; placements reference collectible locations in the same scene. Automatic conditions must not be circular. Targets are existing scene UUIDs; cross-page navigation uses action=page,target_page_id,target_scene_id. restart_game can reset progress on a navigation action.
Inventory drop rules: inventory_rules:[{object_id,response,target_id,consume,allowed,description_position,flag_conditions,flag_effects}]. Choice buttons: choices:[{label,response,target_id,description_position,flag_effects}]. Inventory action buttons: actions:[{id:UUID,label,target_id,flag_effects}]. Scene skip rules: skip_rules:[{id:UUID,object_ids:[],flag_conditions:[],target_page_id,target_scene_id}]. Use only actual inventory IDs, flags and destinations. There are no configurable timers or click counters.
Scene visual fields also include intro_video_id (completed video only), intro_video_repeat,intro_video_pause_music,music_mode (keep/replace/stop),music_id,music_loop,sound_id,action_sound_id,sound_volume,sound_loop,dialog_background,dialog_border,dialog_footer (hex colors),skip_rules. Area geometry uses x,y,width,height in 0..1; image_bounds,overlay_bounds,default_bounds,default_overlay_bounds each use the same four keys. Preserve geometry unless asked to change it.
Story settings include ui_theme (from theme catalog), cover_asset_id, unavailable_text,unavailable_timeout_ms,gameplay_messages. Gameplay message keys are placed,occupied,placement_denied,nothing,complete,scene_load_error,no_scenes,missing_pages,next_scene_missing,destination_page_missing,destination_scene_missing; values are {text,timeout_ms}. Merge with existing entries.
Video drafts support first_asset_id,optional last_asset_id,reference_asset_ids in order. Default fastvideo_h3 for first/last frames; minimax_h3_reference for up to nine additional references with prompt tags <Picture 1> through <Picture 9>. Opening/ending frames are timeline guides and do not consume reference numbers. A draft is not a running render.
TEXT;
    }
}
