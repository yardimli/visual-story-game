<div x-data="{ image: null, previousOverflow: '',
    open(image) { if (!image?.url) return; this.image=image; if (!this.$refs.viewer.open) { this.previousOverflow=document.body.style.overflow; document.body.style.overflow='hidden'; this.$refs.viewer.showModal(); } },
    close() { this.$refs.viewer.close(); },
    closed() { document.body.style.overflow=this.previousOverflow; this.image=null; }
}" @vsg-image.window="open($event.detail)">
    <dialog x-ref="viewer" class="image-viewer" aria-label="Full-screen image preview" @close="closed()">
        <img :src="image?.url" :alt="image?.name || 'Image preview'" class="h-full w-full object-contain">
        <div class="absolute inset-x-0 top-0 flex items-start justify-between gap-4 bg-gradient-to-b from-black/80 to-transparent p-4 text-white">
            <p class="min-w-0 break-words text-sm" x-text="image?.name"></p>
            <button type="button" autofocus @click="close()" class="shrink-0 rounded-lg border border-white/40 bg-black/60 px-4 py-2 text-sm focus:ring-2 focus:ring-amber-400" aria-label="Close image preview">Close ✕</button>
        </div>
    </dialog>
</div>
