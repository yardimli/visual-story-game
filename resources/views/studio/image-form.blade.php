<div class="space-y-3">
    <label class="field">Image name<input
            type="text"
            x-model="generationName"
            @input="changed()"
            maxlength="160"
            :disabled="generating"
            placeholder="Leave blank: AI will create a short name from the description."
        ></label>
    <label class="field">Image prompt
        <textarea
            x-model="visual.prompt"
            @input="changed()"
            maxlength="10000"
            placeholder="Describe the scene or changes to your selected references."
        ></textarea>
    </label>
    <label class="field">Image model<select
            x-model="model"
            @change="loadEndpoints();changed()"
        >
            <option value="">Select an image model</option><template
                x-for="m in models"
                :key="m.id"
            >
                <option
                    :value="m.id"
                    x-text="imageModelLabel(m)"
                ></option>
            </template>
        </select></label>
    <template x-if="endpoint">
        <div class="flex flex-wrap gap-3"><template
                x-for="key in ['quality','resolution','aspect_ratio','background'].filter(k=>endpoint.supported_parameters?.[k]?.type==='enum')"
                :key="key"
            ><label class="field min-w-0 flex-1 basis-36"><span
                        class="capitalize"
                        x-text="key.replaceAll('_',' ')"
                    ></span><select
                        x-model="settings[key]"
                        @change="changed()"
                    >
                        <option value="">Automatic</option><template
                            x-for="value in endpoint.supported_parameters[key].values"
                            :key="value"
                        >
                            <option
                                :value="value"
                                x-text="value"
                            ></option>
                        </template>
                    </select></label></template></div>
    </template>
    <p class="text-xs text-stone-500 dark:text-stone-400">For scene images, use 16:9 or match your video's aspect ratio to reduce cropping and framing changes between images and videos.</p>
</div>
