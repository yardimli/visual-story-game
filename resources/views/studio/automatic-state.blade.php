<section
    x-show="hotspot.action==='state_v2'"
    class="space-y-3 rounded-lg border p-3"
>
    <label class="field">Change state<select
            :value="hotspot.auto_state?.enabled ? 'automatic' : 'click'"
            @change="hotspot.auto_state??={sticky:false,flag_conditions:[],placements:[],original_target:null,changed_target:null};hotspot.auto_state.enabled=$event.target.value==='automatic';changed()"
        >
            <option value="click">On click</option>
            <option value="automatic">When conditions match</option>
        </select></label>
    <template x-if="hotspot.auto_state?.enabled">
        <div class="space-y-3">
            <p class="text-xs">All conditions must match to show the changed image. The area stays clickable.</p>
            @include('studio.flag-rules', [
                'target' => 'hotspot.auto_state',
                'field' => 'flag_conditions',
                'heading' => 'Required flags',
            ])
            <div class="flex justify-between"><span class="text-sm font-semibold">Items placed in locations</span><button
                    type="button"
                    class="text-sm underline"
                    :disabled="!visual.hotspots.some(a => a.action === 'pickup_v2') || !inventoryItems.length"
                    @click="hotspot.auto_state.placements??=[];hotspot.auto_state.placements.push({area_id:visual.hotspots.find(a=>a.action==='pickup_v2')?.id||'',object_id:visual.hotspots.find(a=>a.action==='pickup_v2')?.object_id||inventoryItems[0]?.id||''});changed()"
                >+ Item</button></div>
            <template
                x-for="(condition,index) in hotspot.auto_state.placements"
                :key="index"
            >
                <div class="flex gap-2"><select
                        class="min-w-0 flex-1"
                        aria-label="Placement location"
                        x-model="condition.area_id"
                        @change="changed()"
                    >
                        <option value="">Choose location</option><template
                            x-for="area in visual.hotspots.filter(a=>a.action==='pickup_v2')"
                            :key="area.id"
                        >
                            <option
                                :value="area.id"
                                x-text="area.label"
                            ></option>
                        </template>
                    </select><select
                        class="min-w-0 flex-1"
                        aria-label="Placed item"
                        x-model="condition.object_id"
                        @change="changed()"
                    >
                        <option value="">Choose item</option><template
                            x-for="item in inventoryItems"
                            :key="item.id"
                        >
                            <option
                                :value="item.id"
                                x-text="item.name"
                            ></option>
                        </template>
                    </select><button
                        type="button"
                        class="text-sm underline"
                        @click="hotspot.auto_state.placements.splice(index,1);changed()"
                    >Remove</button></div>
            </template>
            <label class="area-checkbox"><input
                    type="checkbox"
                    x-model="hotspot.auto_state.sticky"
                    @change="changed()"
                ><span>Stay changed once conditions have matched</span></label>
            <template
                x-for="state in [{key:'original_target',label:'Original state click action'},{key:'changed_target',label:'Changed state click action'}]"
                :key="state.key"
            ><label class="field"><span x-text="state.label"></span><select
                        :value="hotspot.auto_state[state.key] || ''"
                        @change="hotspot.auto_state[state.key]=$event.target.value||null;changed()"
                    >
                        <option value="">Examine only</option><template
                            x-for="destination in pageSceneDestinations"
                            :key="destination.key"
                        >
                            <option
                                :value="destination.sceneId"
                                x-text="'Move to '+destination.label"
                            ></option>
                        </template>
                    </select></label></template>
            <p class="text-xs">Uses Description for the original state and Changed-state description for the changed
                state. A blank description moves immediately.</p>
        </div>
    </template>
</section>
