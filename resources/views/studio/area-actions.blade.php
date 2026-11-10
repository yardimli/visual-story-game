<div x-show="['inventory','choices'].includes(hotspot.action)" class="space-y-3">
<label class="field">Interaction text<textarea x-model="hotspot.interaction_text" @input="changed()" maxlength="3000" placeholder="A small brass keyhole sits beneath the handle."></textarea></label>
<label class="field" >Interaction text position<select :value="hotspot.description_position || 'top'" @change="hotspot.description_position=$event.target.value;changed()"><option value="top">Top</option><option value="bottom">Bottom</option></select></label>
<div x-show="hotspot.action==='inventory'" class="space-y-3">
<label class="field">Response for other items<textarea x-model="hotspot.fallback_text" @input="changed()" maxlength="3000" placeholder="Nothing happens."></textarea></label>
<div class="flex flex-wrap items-center gap-2"><h4 class="font-semibold">Inventory actions</h4><button type="button" class="btn-secondary" @click="addInventoryRule()" :disabled="(hotspot.inventory_rules||[]).length>=100">+ Item rule</button><button type="button" class="btn-secondary" @click="addAllInventoryRules()">Add all objects</button></div>
<template x-for="(rule,index) in hotspot.inventory_rules||[]" :key="index"><div class="space-y-3 rounded-lg border p-3 dark:border-stone-700">
<div class="flex items-end gap-2"><label class="field min-w-0 flex-1">Inventory object<select x-model="rule.object_id" @change="changed()"><option :value="null">Choose object</option><template x-for="object in previewInventoryItems" :key="object.id"><option :value="object.id" :disabled="(hotspot.inventory_rules||[]).some(r=>r!==rule && r.object_id===object.id)" x-text="object.name+' — '+object.sourcePage"></option></template></select></label><button type="button" class="btn-secondary" @click="hotspot.inventory_rules.splice(index,1);changed()">Remove rule</button></div>
<label class="field">Response<textarea x-model="rule.response" @input="changed()" maxlength="3000" placeholder="You try to push the envelope into the keyhole, but lack the lock-picking skill."></textarea></label>
<label class="field" >Response position<select :value="rule.description_position || 'top'" @change="rule.description_position=$event.target.value;changed()"><option value="top">Top</option><option value="bottom">Bottom</option></select></label>
<label class="field">Scene after using this item<select x-model="rule.target_id" @change="changed()"><option :value="null">Keep current scene</option><template x-for="scene in scenes" :key="scene.id"><option :value="scene.id" x-text="scene.name"></option></template></select></label>
@include('studio.flag-rules',['target'=>'rule','field'=>'flag_effects','heading'=>'Set flags for this response'])
<label class="flex items-center gap-2 text-sm"><input type="checkbox" x-model="rule.consume" @change="changed()">Remove item from inventory after use</label>
</div></template>
<p class="text-xs text-stone-500">Only carried items can be used. Unmatched items use the fallback response. Ask Creative companion to write responses for this area's objects.</p>
</div>
<div x-show="hotspot.action==='choices'" class="space-y-3">
<div class="flex items-center gap-2"><h4 class="font-semibold">Available actions</h4><button type="button" class="btn-secondary" @click="addAreaChoice()" :disabled="(hotspot.choices||[]).length>=20">+ Choice</button></div>
<template x-for="(choice,index) in hotspot.choices||[]" :key="index"><div class="space-y-3 rounded-lg border p-3 dark:border-stone-700">
<div class="flex items-end gap-2"><label class="field min-w-0 flex-1">Button text<input x-model="choice.label" @input="changed()" maxlength="160" placeholder="Walk into the house"></label><button type="button" class="btn-secondary" @click="hotspot.choices.splice(index,1);changed()">Remove choice</button></div>
<label class="field">Response<textarea x-model="choice.response" @input="changed()" maxlength="3000"></textarea></label>
<label class="field" >Response position<select :value="choice.description_position || 'top'" @change="choice.description_position=$event.target.value;changed()"><option value="top">Top</option><option value="bottom">Bottom</option></select></label>
<label class="field">Scene after choice<select x-model="choice.target_id" @change="changed()"><option :value="null">Keep current scene</option><template x-for="scene in scenes" :key="scene.id"><option :value="scene.id" x-text="scene.name"></option></template></select></label>
@include('studio.flag-rules',['target'=>'choice','field'=>'flag_effects','heading'=>'Set flags after this choice'])
</div></template>
</div>
</div>
