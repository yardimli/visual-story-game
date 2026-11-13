<template x-teleport="body">
<dialog x-ref="previewInventoryDialog" class="image-picker-dialog reference-dialog rounded-2xl border border-stone-200 bg-white text-stone-900 shadow-xl dark:border-stone-700 dark:bg-stone-900 dark:text-stone-100" aria-labelledby="preview-inventory-title" @click="if($event.target===$el)$el.close()">
<div class="flex max-h-[85dvh] flex-col">
<header class="flex items-center justify-between gap-3 border-b border-stone-200 p-4 dark:border-stone-700"><h2 id="preview-inventory-title" class="font-serif text-xl">Starting inventory</h2><button type="button" class="btn-secondary" @click="$refs.previewInventoryDialog.close()">Cancel</button></header>
<div class="min-h-0 space-y-3 overflow-y-auto p-4">
<label class="field">Start at page &amp; scene<select x-model="previewStartKey"><template x-for="destination in pageSceneDestinations" :key="destination.key"><option :value="destination.key" :selected="destination.key===previewStartKey" x-text="destination.label"></option></template></select></label>
<div x-show="flagDefinitions.length" class="space-y-2"><h3 class="text-sm font-semibold">Starting story flags</h3><div class="flex flex-wrap gap-3"><template x-for="flag in flagDefinitions" :key="flag.key"><label class="area-checkbox"><input type="checkbox" :value="flag.key" x-model="previewFlags"><span x-text="flag.label"></span></label></template></div></div>
<p class="text-sm text-stone-500">Choose items to carry during this test, or start with an empty inventory.</p>
<p x-show="!previewInventoryItems.length" class="text-sm text-stone-500">No object items have been created yet.</p>
<template x-for="item in previewInventoryItems" :key="item.id"><label class="flex cursor-pointer items-start gap-3 rounded-xl border border-stone-200 p-3 dark:border-stone-700">
<input type="checkbox" class="mt-1 shrink-0 rounded" :value="item.id" x-model="previewInventorySelection">
<img x-show="asset(item.asset_id)" :src="asset(item.asset_id)?.url" alt="" class="h-16 w-20 shrink-0 rounded object-contain">
<span class="min-w-0"><span class="block font-semibold" x-text="item.name"></span><span class="block text-xs text-stone-500" x-text="item.sourcePage"></span><span class="mt-1 block whitespace-pre-wrap break-words text-sm" x-text="item.description || 'No description yet.'"></span></span>
</label></template>
</div>
<footer class="flex items-center justify-between gap-3 border-t border-stone-200 p-4 dark:border-stone-700"><span class="text-sm" x-text="previewInventorySelection.length+' selected'"></span><button type="button" class="btn-primary" @click="confirmPreviewInventory()">Start test</button></footer>
</div></dialog>
</template>
<template x-teleport="body">
<dialog x-ref="previewDialog" class="preview-dialog" :style="gameDialogStyle" aria-label="Test scenes" @pointermove.window="moveInventoryDrag($event)" @pointerup.window="endInventoryDrag($event)" @pointercancel.window="endInventoryDrag($event,true)" @click="if($event.target===$el)$el.close()" @close="closePreview()" @cancel="if(modal){$event.preventDefault();modal=null;}">
<button type="button" class="fixed right-4 top-3 z-50 rounded-lg border border-white/50 bg-black/70 px-3 py-2 text-sm text-white" @click.stop="audioMuted=soundPlayer.toggleMute()" :aria-pressed="audioMuted" x-text="audioMuted?'Unmute':'Mute all'"></button>
<div class="contents" x-data="hoverDescription()" @area-hover-start="if(!pendingInventoryUse)narration=null" @pointerdown="outsideClick($event)" x-effect="if(!preview)resetHover();if(inventoryDrag?.active || pendingInventoryUse)dismiss()">
<template x-if="preview && asset(displayed?.asset_id)"><div class="preview-stage" @click="if(modalArea?.action==='inventory' && !$event.target.closest('.inventory-interaction-overlay,.game-inventory,.preview-hotspot'))modal=null">
<img :src="asset(displayed.asset_id).url" :alt="displayed.name" class="preview-scene-image" :class="sceneTransition ? 'scene-fade-in' : ''" draggable="false">
<template x-if="outgoingScene && asset(outgoingScene.asset_id)"><img :src="asset(outgoingScene.asset_id).url" alt="" class="preview-outgoing-image" aria-hidden="true"></template>
<div class="scene-area-layers">
<template x-for="h in currentHotspots" :key="h.id"><button type="button" :style="style(h)+';z-index:'+areaLayer(h)+';cursor:'+areaCursor(h)" :aria-label="pickupReady(h)?'Pick up '+h.label:h.label" :title="pickupReady(h)?'Click to pick up':''" class="preview-hotspot absolute cursor-pointer border-0 bg-transparent focus:outline-none" :disabled="sceneTransition || !!pendingInventoryUse" @mouseenter="if(!sceneTransition && !inventoryDrag?.active && !pendingInventoryUse)hoverArea(hoverDetails(h),previewPageId,previewId)" @mousemove="if(!sceneTransition && !inventoryDrag?.active && !pendingInventoryUse)hoverArea(hoverDetails(h),previewPageId,previewId)" @mouseleave="leaveArea()" @focus="if(!sceneTransition && !inventoryDrag?.active && !pendingInventoryUse)hoverArea(hoverDetails(h),previewPageId,previewId)" @blur="leaveArea()" @click="clickArea(h,()=>activate(h))"><template x-if="areaImage(h)"><img :src="areaImage(h).url" :class="h.overlay_always===false?'area-image-on-hover':''" :style="areaImageStyle(h)" alt="" class="h-full w-full object-contain"></template></button></template>
</div>
<div x-show="visible" class="animated-description hover-description" :class="'description-'+placement" :style="hoverBoxStyle" @resize.window="updateHoverBounds()" x-ref="box" x-transition.opacity.duration.150ms @click.stop="reveal()" role="region" aria-label="Area description">
<div x-ref="words"><template x-for="(word,index) in words" :key="signature+':'+index"><span :class="index<count?'description-word':'description-word-pending'" :aria-hidden="index>=count" x-text="word"></span></template></div></div>
@include('studio.animated-description',['text'=>"preview && !sceneTransition ? displayed?.description : ''",'position'=>"displayed?.description_position || 'top'",'identity'=>"previewPageId+':'+previewId",'extraClass'=>'scene-narration','sceneIndependent'=>true])
<div x-show="!modal && (!visible || !!pendingInventoryUse)" @description-complete="finishInventoryText($event.detail.key)" @description-hidden="if(!pendingInventoryUse && narration && $event.detail.key===previewId+':'+narrationVersion)narration=null">
@include('studio.animated-description',['text'=>"preview && !sceneTransition ? narration?.text : ''",'position'=>"narration?.position || 'top'",'identity'=>"previewId+':'+narrationVersion",'extraClass'=>'item-narration','persistentExpression'=>'!!pendingInventoryUse','animate'=>"narration?.animate!==false",'area'=>"narration?.area || null"])
</div>
<template x-if="pickupFlight?.url"><img :src="pickupFlight.url" alt="" class="pickup-flight" :style="{'--pickup-x':pickupFlight.x+'%','--pickup-y':pickupFlight.y+'%','--pickup-target-x':pickupFlight.targetX+'px','--pickup-target-y':pickupFlight.targetY===null?'calc(100% - 56px)':pickupFlight.targetY+'px'}"></template>
<template x-if="inventoryDrag?.active && inventoryDrag?.url"><img class="inventory-drag-ghost" :src="inventoryDrag.url" alt="" :style="{left:inventoryDrag.x+'px',top:inventoryDrag.y+'px'}"></template>
<div class="game-inventory">
<button type="button" class="inventory-toggle" :class="inventoryPulse?'inventory-pulse':''" aria-label="Inventory" :aria-expanded="inventoryOpen" @click="inventoryOpen=!inventoryOpen"><svg class="h-6 w-6" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" aria-hidden="true"><path d="M5 7h14l2 14H3L5 7ZM8 7V5a4 4 0 0 1 8 0v2M8 12h8"/></svg><span class="text-xs" x-text="inventory.length"></span></button>
<div x-show="inventoryOpen" class="inventory-tray" x-ref="inventoryTray" aria-label="Inventory items"><p x-show="!inventory.length" class="p-3 text-sm">No items collected.</p><template x-for="item in inventory" :key="item.inventoryId||item.id"><button type="button" class="inventory-item" :data-inventory-item="item.id" :class="pickupFlight?.itemId===item.id || (inventoryDrag?.active && inventoryDrag?.itemId===item.id) || pendingInventoryUse?.inventoryId===(item.inventoryId||item.id)?'inventory-item-arriving':''" :disabled="pickupFlight?.itemId===item.id || sceneTransition || !!pendingInventoryUse" @pointerdown="startInventoryDrag($event,item)" @dragstart.prevent @click="selectInventoryItemForPlay(item)" :aria-label="'Inspect '+item.name+'; drag onto a scene area to use'"><img x-show="asset(item.asset_id)" :src="asset(item.asset_id)?.url" :alt="item.name" class="h-14 w-16 object-contain" draggable="false"><span class="block max-w-24 truncate text-xs" x-text="item.name"></span></button></template></div>
</div>
</div></template>
<div x-show="!asset(displayed?.asset_id)" class="p-8">Assign an image to this scene to preview it. Click outside to close.</div>
<dialog x-ref="objectPreviewDialog" class="object-preview-dialog" :style="gameDialogStyle" aria-label="Object inspection"
 x-effect="if(modal && preview && modalArea?.action!=='inventory'){$nextTick(()=>{if(modal && preview && modalArea?.action!=='inventory' && !$el.open)$el.showModal();});}else if($el.open){$el.close();}" @click="if($event.target===$el)modal=null" @cancel="modal=null" @close.stop="if(modalArea?.action!=='inventory')modal=null">
<template x-if="modal && !modal.areaId"><div class="object-preview-stage" :class="!modal.url?'object-text-only':''">
<img x-show="modal.url" :src="modal.url" :alt="modal.label" class="preview-object-image">
<button type="button" class="game-object-close" aria-label="Back to scene" @click="modal=null">×</button>
<div x-show="modal?.text" class="game-item-description" x-text="modal?.text"></div>
<footer x-show="inventoryActions.length" class="game-object-actions game-dialog-footer"><template x-for="action in inventoryActions" :key="action.id"><button type="button" class="game-action" @click="performObjectAction(action.id)" :disabled="!objects.some(v=>v.id===action.target_id)" x-text="action.label"></button></template></footer>
</div></template>
<template x-if="modal?.areaId"><div class="area-interaction-panel relative flex flex-col gap-4 p-5">
<h3 class="font-serif text-xl" x-text="modal.label"></h3>
@include('studio.animated-description',['text'=>"modal?.text",'position'=>"modal?.position || 'top'",'identity'=>"modal?.areaId+':'+(modal?.version||0)",'extraClass'=>'area-narration','persistent'=>true,'animate'=>"modal?.animate!==false"])
<div x-show="modalArea?.action==='choices'" class="flex flex-wrap gap-2"><template x-for="(choice,index) in modalArea?.choices||[]" :key="index"><button type="button" class="game-action" @click="chooseAreaAction(index)" x-text="choice.label"></button></template><p x-show="!modalArea?.choices?.length" class="text-sm">No actions available.</p></div>
</div></template>
</dialog>
</div>
</dialog>
</template>
