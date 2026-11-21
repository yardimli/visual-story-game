<div class="flex flex-wrap items-center gap-2 pt-1"><button
        type="button"
        class="btn-secondary"
        @click="openReferences()"
        x-text="'Reference images ('+references.filter(id=>!characterReferenceIds.includes(String(id))).length+')'"
    ></button>
    <button
        type="button"
        class="btn-secondary"
        @click="openCharacters()"
        x-text="'Characters ('+selectedCharacterViews+' views)'"
    ></button>
    <button
        type="button"
        class="btn-primary"
        @click="generate()"
        :disabled="uploading || generating || !model || !endpoints.length || !visual?.prompt || halted"
        x-text="generating?(namingImage?'Naming image…':'Queueing…'):'Generate image'"
    ></button><button
        type="button"
        class="btn-secondary"
        @click="createNewImage()"
        :disabled="generating"
    >Create new image</button>
</div>
