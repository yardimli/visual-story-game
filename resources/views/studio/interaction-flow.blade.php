<div class="space-y-3 rounded-lg border p-3">
    <label class="field">On click
        <select
            :value="hotspot.click_behavior || (hotspot.description ? 'text' : 'direct')"
            @change="hotspot.click_behavior=$event.target.value;changed()"
        >
            <option value="text">Show text and action</option>
            <option value="direct">Execute action immediately</option>
        </select>
    </label>
    <label class="field">Action button text
        <select
            :value="actionPreset(hotspot)"
            @change="hotspot.action_preset=$event.target.value;changed()"
        >
            <option value="take">Take</option>
            <option value="open">Open</option>
            <option value="close">Close</option>
            <option value="look">Look closer</option>
            <option value="enter">Enter</option>
            <option value="search">Search</option>
            <option value="read">Read</option>
            <option value="talk">Talk</option>
            <option value="continue">Continue</option>
            <option value="back">Back</option>
            <option value="press">Press</option>
            <option value="open_close">Open / Close</option>
            <option value="pull_push">Pull / Push</option>
            <option value="turn_on_off">Turn on / Turn off</option>
            <option value="insert_remove">Insert / Remove</option>
            <option value="custom_single">Custom single</option>
            <option value="custom_dual">Custom dual</option>
        </select>
    </label>
    <label
        class="field"
        x-show="['custom_single','custom_dual'].includes(actionPreset(hotspot))"
    >Action text
        <input
            x-model="hotspot.action_label"
            @input="changed()"
            maxlength="160"
            placeholder="e.g. Lift the lid"
        >
    </label>
    <label
        class="field"
        x-show="actionPreset(hotspot)==='custom_dual'"
    >Changed-state action text
        <input
            x-model="hotspot.changed_action_label"
            @input="changed()"
            maxlength="160"
            placeholder="e.g. Lower the lid"
        >
    </label>
    <p class="text-xs">Labels do not change the effect. Dual labels use the object's original and changed states.
        Descriptions are optional; blank text performs the action immediately.</p>
    <label
        class="area-checkbox"
        x-show="hotspot.move_after_examine"
    >
        <input
            type="checkbox"
            :checked="!!hotspot.restart_game"
            @change="hotspot.restart_game=$event.target.checked;changed()"
        >
        <span>Restart progress when moving to this scene</span>
    </label>
</div>
