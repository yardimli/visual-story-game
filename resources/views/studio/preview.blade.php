<template x-teleport="body">
<dialog x-ref="previewDialog" class="preview-dialog" aria-label="Test interactions" @click="if($event.target===$el)$el.close()" @close="closePreview()" @cancel="if(modal){$event.preventDefault();modal=null;}">
<template x-if="preview && asset(displayed?.asset_id)"><div class="preview-stage">
<img :src="asset(displayed.asset_id).url" :alt="displayed.name" class="preview-scene-image" :class="sceneTransition ? 'scene-fade-in' : ''" draggable="false">
<template x-if="outgoingScene && asset(outgoingScene.asset_id)"><img :src="asset(outgoingScene.asset_id).url" alt="" class="preview-outgoing-image" aria-hidden="true"></template>
<template x-for="h in currentHotspots" :key="h.id"><button type="button" :style="style(h)" :aria-label="h.label" class="preview-hotspot absolute cursor-pointer border-0 bg-transparent focus:outline-none" :disabled="sceneTransition" @click="activate(h)"><template x-if="h.overlay && visualAsset(h.object_id)"><img :src="visualAsset(h.object_id).url" alt="" class="h-full w-full object-contain"></template></button></template>
<div x-show="displayed?.description && !inventoryOpen" class="game-description scene-description" tabindex="0" role="region" aria-label="Scene description" x-text="displayed?.description"></div>
<div class="game-inventory">
<button type="button" class="inventory-toggle" aria-label="Inventory" :aria-expanded="inventoryOpen" @click="inventoryOpen=!inventoryOpen"><svg class="h-6 w-6" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" aria-hidden="true"><path d="M5 7h14l2 14H3L5 7ZM8 7V5a4 4 0 0 1 8 0v2M8 12h8"/></svg><span class="text-xs" x-text="inventory.length"></span></button>
<div x-show="inventoryOpen" class="inventory-tray" aria-label="Inventory items"><p x-show="!inventory.length" class="p-3 text-sm">No items collected.</p><template x-for="item in inventory" :key="item.inventoryId||item.id"><button type="button" class="inventory-item" @click="openInventory(item)"><img x-show="asset(item.asset_id)" :src="asset(item.asset_id)?.url" :alt="item.name" class="h-14 w-16 object-contain"><span class="block max-w-24 truncate text-xs" x-text="item.name"></span></button></template></div>
</div>
</div></template>
<div x-show="!asset(displayed?.asset_id)" class="p-8">Assign an image to this scene to preview it. Click outside to close.</div>
<dialog x-ref="objectPreviewDialog" class="object-preview-dialog" aria-label="Object inspection"
 x-effect="if(modal && preview){$nextTick(()=>{if(modal && preview && !$el.open)$el.showModal();});}else if($el.open){$el.close();}" @click="if($event.target===$el)modal=null" @cancel="modal=null" @close.stop="modal=null">
<template x-if="modal"><div class="object-preview-stage" :class="!modal.url?'object-text-only':''">
<img x-show="modal.url" :src="modal.url" :alt="modal.label" class="preview-object-image">
<button type="button" class="game-object-close" aria-label="Back to scene" @click="modal=null">×</button>
<div x-show="modal.text" class="game-description object-description" :class="inventoryActions.length?'has-actions':''" tabindex="0" role="region" aria-label="Object description" x-text="modal.text"></div>
<div x-show="inventoryActions.length" class="game-object-actions"><template x-for="action in inventoryActions" :key="action.id"><button type="button" class="game-action" @click="performObjectAction(action.id)" :disabled="!objects.some(v=>v.id===action.target_id)" x-text="action.label"></button></template></div>
</div></template>
</dialog>
</dialog>
</template>
