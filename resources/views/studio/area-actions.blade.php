<div x-show="['inventory','choices','pickup_v2'].includes(hotspot.action)" class="space-y-3">
<div x-show="hotspot.action==='inventory' || (hotspot.action==='pickup_v2' && hotspot.placement_enabled!==false)" class="space-y-3">
<label class="field">Response for other items<textarea x-model="hotspot.fallback_text" @input="changed()" maxlength="3000" placeholder="Nothing happens."></textarea></label>
<div class="flex flex-wrap items-center gap-2"><h4 class="font-semibold" x-text="hotspot.action==='pickup_v2'?'Placement rules (empty location only)':'Inventory actions'"></h4><button type="button" class="btn-secondary" @click="addInventoryRule()" :disabled="(hotspot.inventory_rules||[]).length>=100">+ Item rule</button><button type="button" class="btn-secondary" @click="addAllInventoryRules()">Add all objects</button></div>
<button type="button" x-show="hotspot.action==='pickup_v2' && hotspot.object_id && !(hotspot.inventory_rules||[]).some(r=>r.object_id===hotspot.object_id)" class="text-sm underline" @click="configurePlacement()">Configure default return-item rule</button>
<template x-for="(rule,index) in hotspot.inventory_rules||[]" :key="index"><div class="space-y-3 rounded-lg border p-3 dark:border-stone-700">
<div class="flex items-end gap-2"><label class="field min-w-0 flex-1">Inventory object<select x-model="rule.object_id" @change="changed()"><option :value="null">Choose object</option><template x-for="object in previewInventoryItems" :key="object.id"><option :value="object.id" :disabled="(hotspot.inventory_rules||[]).some(r=>r!==rule && r.object_id===object.id)" x-text="object.name"></option></template></select></label><button type="button" class="btn-secondary" @click="hotspot.inventory_rules.splice(index,1);changed()">Remove rule</button></div>
<label class="field">Response<textarea x-model="rule.response" @input="changed()" maxlength="3000" placeholder="You try to push the envelope into the keyhole, but lack the lock-picking skill."></textarea></label>
<div x-show="hotspot.action==='pickup_v2'" class="space-y-3"><label class="area-checkbox"><input type="checkbox" :checked="rule.allowed!==false" @change="rule.allowed=$event.target.checked;changed()"><span>Allow this item to be placed here</span></label>
@include('studio.flag-rules',['target'=>'rule','field'=>'flag_conditions','heading'=>'Required flag states for placement'])
</div>
<label x-show="hotspot.action!=='pickup_v2'" class="field">Scene after using this item<select x-model="rule.target_id" @change="changed()"><option :value="null">Keep current scene</option><template x-for="destination in pageSceneDestinations" :key="destination.key"><option :value="destination.sceneId" x-text="destination.label"></option></template></select></label>
<div x-show="hotspot.action==='inventory'" class="space-y-2"><h5 class="text-sm font-semibold">Change local states after using this item</h5>
<p x-show="!inventoryStateTargets.length" class="text-xs">Create and activate a local switch on this scene first. Its changed image can then be triggered by this item.</p>
<template x-for="effect in inventoryStateEffects(rule)" :key="effect.key"><div class="flex items-end gap-2">
<label class="field min-w-0 flex-1">Affected area<select :value="effect.key" @change="changeInventoryStateTarget(rule,effect,$event.target.value)"><template x-for="target in inventoryStateTargets" :key="target.id"><option :value="target.flag" :disabled="target.flag!==effect.key && inventoryStateEffects(rule).some(e=>e.key===target.flag)" x-text="target.label"></option></template></select></label>
<label class="field min-w-0 flex-1">State<select :value="String(effect.value)" @change="effect.value=$event.target.value==='true';changed()"><option value="true">Show changed state</option><option value="false">Restore original state</option></select></label>
<button type="button" class="btn-secondary" @click="rule.flag_effects=rule.flag_effects.filter(e=>e!==effect);changed()">Remove</button>
</div></template>
<label class="field" x-show="inventoryStateTargets.some(target=>!inventoryStateEffects(rule).some(e=>e.key===target.flag))">Affected area<select aria-label="Add affected local state" @change="addInventoryStateTarget(rule,$event.target.value);$event.target.value=''"><option value="">Choose a local state to affect</option><template x-for="target in inventoryStateTargets" :key="target.id"><option :value="target.flag" :disabled="inventoryStateEffects(rule).some(e=>e.key===target.flag)" x-text="target.label"></option></template></select></label>
</div>
@include('studio.flag-rules',['target'=>'rule','field'=>'flag_effects','heading'=>'Set flags for this response'])
<label x-show="hotspot.action!=='pickup_v2'" class="flex items-center gap-2 text-sm"><input type="checkbox" x-model="rule.consume" @change="changed()">Remove item from inventory after use</label>
</div></template>
<p class="text-xs text-stone-500">Only carried items can be used. Unmatched items use the fallback response. Ask Creative companion to write responses for this area's objects.</p>
</div>
<div x-show="hotspot.action==='choices'" class="space-y-3">
<div class="flex items-center gap-2"><h4 class="font-semibold">Available actions</h4><button type="button" class="btn-secondary" @click="addAreaChoice()" :disabled="(hotspot.choices||[]).length>=20">+ Choice</button></div>
<template x-for="(choice,index) in hotspot.choices||[]" :key="index"><div class="space-y-3 rounded-lg border p-3 dark:border-stone-700">
<div class="flex items-end gap-2"><label class="field min-w-0 flex-1">Button text<input x-model="choice.label" @input="changed()" maxlength="160" placeholder="Walk into the house"></label><button type="button" class="btn-secondary" @click="hotspot.choices.splice(index,1);changed()">Remove choice</button></div>
<label class="field">Response<textarea x-model="choice.response" @input="changed()" maxlength="3000"></textarea></label>
<label class="field" >Response position<select :value="choice.description_position || 'top'" @change="choice.description_position=$event.target.value;changed()"><option value="top">Top</option><option value="bottom">Bottom</option><option value="area_top">Top of selection area</option><option value="area_bottom">Bottom of selection area</option></select></label>
<label class="field">Scene after choice<select x-model="choice.target_id" @change="changed()"><option :value="null">Keep current scene</option><template x-for="scene in scenes" :key="scene.id"><option :value="scene.id" x-text="scene.name"></option></template></select></label>
@include('studio.flag-rules',['target'=>'choice','field'=>'flag_effects','heading'=>'Set flags after this choice'])
</div></template>
</div>
</div>
