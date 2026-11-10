import {storyFlags} from './story-flags';
import {inventoryLibrary} from './inventory-library';
import {readChatStream, streamingMessage} from './chat-stream';
import {pageDocument,companionResize} from './page-documents';
import {animatedDescription, hoverDescription} from './animated-description';
import {aiCallHistory} from './ai-call-history';
import {proposalReview, selectedLegacyProposal} from './proposal-review';
import {imageCatalog} from './image-catalog';
import {notify, watchNotices, toastCenter} from './toasts';
import './bootstrap';
import './textarea-autosize';

import Alpine from 'alpinejs';
import {sceneEditor} from './scene-editor';
import {studioAutosave} from './autosave';
import {proposalTargets, displayMessage} from './companion-proposals';
import {selectableModels, rememberedModel, rememberModel, modelLabel, imagePriceLabel} from './model-preferences';

window.Alpine = Alpine;
Alpine.data('storyFlags',storyFlags);
Alpine.data('inventoryLibrary',inventoryLibrary);
Alpine.data('aiCallHistory', aiCallHistory);
Alpine.data('toastCenter', toastCenter);
Alpine.data('proposalReview', proposalReview);
Alpine.data('studioAutosave', studioAutosave);
Alpine.data('sceneEditor', sceneEditor);
Alpine.data('pageDocument',pageDocument);
Alpine.data('companionResize',companionResize);
Alpine.data('animatedDescription', animatedDescription);
Alpine.data('hoverDescription', hoverDescription);

Alpine.data('modelPicker', (initial, modality = 'text') => ({
    modelLabel,
    imagePriceLabel, endpoints: [], pricingError: '', pricingLoading: false, pricingRequest: 0,
    models: [], selected: initial, error: '', sending: false,
    init() { watchNotices(this,{error:'error',pricingError:'error'}); this.selected = rememberedModel(this.storageKey(), initial); this.$watch('selected', () => this.loadPricing()); this.load(); },
    storageKey() { return modality === 'text' ? 'vsg-writing-model' : 'vsg-image-model'; },
    remember() { rememberModel(this.storageKey(), this.selected); },
    async loadPricing() {
        if (modality !== 'image') return;
        const request = ++this.pricingRequest;
        this.endpoints = []; this.pricingError = ''; this.pricingLoading = false;
        if (!this.selected) return;
        this.pricingLoading = true;
        try {
            const data = await imageCatalog();
            if (request === this.pricingRequest) this.endpoints = data.endpoints[this.selected] || [];
        } catch (_) { if (request === this.pricingRequest) this.pricingError = 'Could not load image pricing. Please retry.'; }
        finally { if (request === this.pricingRequest) this.pricingLoading = false; }
    },
    async load() {
        this.error = '';
        try {
            const data = modality === 'image' ? await imageCatalog() : await (async()=>{
                const response=await fetch('/ai/models?modality=text',{headers:{Accept:'application/json'}});
                if(!response.ok)throw new Error('Catalog unavailable');return response.json();
            })();
            const choice = this.selected;
            this.models = selectableModels(data.models, modality);
            this.$nextTick(() => {
                this.selected = this.models.some(m => m.id === choice) ? choice : '';
                this.remember();
                this.loadPricing();
            });
        } catch (_) { this.error = 'Model catalog is unavailable. Reload to try again.'; }
    }
}));

Alpine.data('creativeCompanion', (options) => ({
    modelLabel, displayMessage, streamingMessage,
    messages: options.messages, models: [], model: options.model, prompt: '', error: '', notice: '',
    sending: false, waitingForResponse:false, applying: false, catalogError: false, remaining: options.remaining,
    async init() {
        watchNotices(this,{notice:'success',error:'error'});
        this.model = rememberedModel('vsg-writing-model', options.model);
        this.loadModels();
        if (options.history) {
            try {
                const response = await fetch(options.history, {headers:{Accept:'application/json'}});
                if (!response.ok) throw new Error();
                this.messages = (await response.json()).messages;
            } catch (_) { this.error = 'Conversation history could not load. Reload before sending.'; }
        }
        this.scroll();
    },
    remember() { rememberModel('vsg-writing-model', this.model); },
    scroll() { this.$nextTick(() => { this.$refs.history.scrollTop = this.$refs.history.scrollHeight; }); },
    async loadModels() {
        this.catalogError = false;
        try {
            const response = await fetch('/ai/models', {headers:{Accept:'application/json'}});
            if (!response.ok) throw new Error();
            const choice = this.model;
            this.models = selectableModels((await response.json()).models);
            this.$nextTick(() => {
                this.model = this.models.some(m => m.id === choice) ? choice : '';
                this.remember();
            });
        } catch (_) { this.catalogError = true; this.error='Model catalog unavailable. Reload models from AI settings.'; }
    },
    draft() {
        const form = document.querySelector('[data-companion-form="create"]');
        return form ? Object.fromEntries(['title','premise','genre','page_count'].map(key => [key,form.elements.namedItem(key).value])) : {};
    },
    async send() {
        if (this.sending || !this.prompt.trim() || !this.model) return;
        this.sending = true; this.waitingForResponse=true; this.error = ''; this.notice = '';this.scroll();
        const message = this.prompt;let streamingIndex=null;
        try {
            if (window.vsgAutosave && !(await window.vsgAutosave.flush())) throw new Error('Resolve unsaved changes before sending a message.');
            const response = await fetch(options.endpoint, {
                method:'POST', headers:{Accept:'application/json','Content-Type':'application/json','X-CSRF-TOKEN':document.querySelector('meta[name="csrf-token"]').content},
                body:JSON.stringify({stream:true,message,model:this.model,request_key:crypto.randomUUID(),page_id:window.vsgAutosave?.activePageId||options.pageId,image_visual_id:window.vsgSceneEditor?.generationMode?window.vsgSceneEditor.visual?.id:undefined,active_tab:window.vsgSceneEditor?.generationMode?'images':window.vsgAutosave?.tab,visual_id:window.vsgAutosave?.tab==='visuals'?window.vsgSceneEditor?.selected:undefined,hotspot_id:window.vsgAutosave?.tab==='visuals'?window.vsgSceneEditor?.hotspotId||undefined:undefined,image_draft:window.vsgSceneEditor?.generationMode?window.vsgSceneEditor.generationSnapshot():undefined,image_settings:window.vsgSceneEditor?.generationMode?window.vsgSceneEditor.settings:undefined,reference_ids:window.vsgSceneEditor?.generationMode?window.vsgSceneEditor.references.map(Number):undefined,form:this.draft()})
            });
            if(!response.ok){const data=await response.json();throw new Error(Object.values(data.errors??{}).flat().join(' ')||data.message||'Request failed.');}
            if(response.headers.get('Content-Type')?.includes('text/event-stream')){
                this.messages.push({role:'user',content:message},{id:'stream:'+crypto.randomUUID(),role:'assistant',content:'',streaming:true});
                streamingIndex=this.messages.length-1;this.waitingForResponse=false;this.prompt='';this.scroll();
                await readChatStream(response,(event,data)=>{
                    if(event==='delta'){this.messages[streamingIndex].content+=data.text;this.scroll();}
                    if(event==='complete'){data.message.autoReview=true;this.messages.splice(streamingIndex,1,data.message);this.remaining=data.remaining;this.scroll();}
                });
            }else{
                const data=await response.json();data.message.autoReview=true;this.messages.push({role:'user',content:message},data.message);this.remaining=data.remaining;this.prompt='';
            }
            this.$nextTick(() => window.dispatchEvent(new Event('textarea-refresh')));
            this.remember();
            this.scroll();
        } catch (e) {if(streamingIndex!==null && this.messages[streamingIndex]?.streaming){const item=this.messages[streamingIndex];item.content=streamingMessage(item.content).text;item.streaming=false;item.interrupted=true;} this.error = e.message || 'Connection interrupted. Check your allowance before trying again.'; }
        finally { this.sending = false;this.waitingForResponse=false; }
    },
    async applyReviewed(item,chosen){
        if(this.applying||!chosen.length)return;
        if(!item.proposal._review){await this.fill({...item,proposal:selectedLegacyProposal(item.proposal,chosen)});return;}
        this.applying=true;this.error='';
        try {
            if(window.vsgAutosave && !(await window.vsgAutosave.flush()))throw new Error('Resolve unsaved changes before applying this proposal.');
            const response=await fetch(options.changesBase+'/'+item.id+'/changes',{method:'POST',headers:{Accept:'application/json','Content-Type':'application/json','X-CSRF-TOKEN':document.querySelector('meta[name="csrf-token"]').content},body:JSON.stringify({selected:chosen.map(Number)})});
            const data=await response.json();if(!response.ok)throw new Error(Object.values(data.errors??{}).flat().join(' ')||data.message||'Could not apply changes.');
            if(data.navigate)window.location.assign(data.navigate);else window.location.reload();
        }catch(e){this.error=e.message;}finally{this.applying=false;}
    },
    async fill(item) {
        this.notice = ''; this.error = '';
        if (options.revision && item.revision !== (window.vsgAutosave?.revision ?? options.revision)) {
            this.error = 'This suggestion belongs to an older revision. Ask for an updated suggestion.'; return;
        }
        const editor=window.vsgSceneEditor;
        if(item.proposal?._interaction){
            try {
                if(window.vsgAutosave?.tab!=='visuals'||!editor)throw new Error('Open Scenes and select the original area to apply this suggestion.');
                const objectId=editor.applyInteractionProposal(item.proposal,options.pageId);
                if(!(await editor.flush()))return;
                if(objectId){const url=new URL(options.imageStudio,window.location.origin);url.searchParams.set('visual',objectId);window.location.assign(url.href);}
                else this.notice='Area description updated.';
            } catch(e){this.error=e.message;}
            return;
        }
        if(editor?.generationMode) {
            const target=item.proposal?._image;
            const prompt=item.proposal?.pages?.find(p=>Number(p.position)===Number(options.position))?.scene_prompt;
            if(!target || target.id!==editor.visual?.id || target.revision!==editor.revision || editor.dirty || editor.saving){this.error='Select the original visual and ask for a fresh prompt suggestion before applying it.';return;}
            if(typeof prompt!=='string'||prompt.length>10000){this.error='This suggestion has no valid image prompt.';return;}
            editor.visual.prompt=prompt;editor.changed();window.dispatchEvent(new Event('textarea-refresh'));this.notice='Image prompt applied and autosaving. Generate when you are ready.';return;
        }
        if(item.proposal?._image){this.error='Open Image generation to apply this image prompt suggestion.';return;}
        const targets = proposalTargets(item.proposal, window.vsgAutosave?.activePageId||options.pageId, window.vsgAutosave?.activePosition||options.position);
        let count = 0, tab = '';
        for (const [name, values, keys] of targets) {
            const form = document.querySelector(`[data-companion-form="${name}"]`);
            if (!form) continue;
            for (const key of keys) {
                const field = form.elements.namedItem(key), value = values[key];
                if (!field || !['string','number'].includes(typeof value)) continue;
                field.value = value;
                field.dispatchEvent(new Event('input',{bubbles:true}));
                field.dispatchEvent(new Event('change',{bubbles:true}));
                count++; tab = name;
            }
        }
        if (count) {
            window.dispatchEvent(new CustomEvent('companion-filled',{detail:{tab}}));
            this.notice = options.pageId ? 'Changes applied. Autosave is saving your story and page fields.' : 'Form draft filled. Review it and press Create draft to save.';
        } else this.notice = 'Open the relevant story page or the dashboard to fill this suggestion. Nothing was changed.';
    }
}));

Alpine.data('modelCatalogReload', () => ({
 busy:false,
 async reload(){
  if(this.busy)return;this.busy=true;
  try {
   const [response]=await Promise.all([fetch('/ai/models?modality=text',{headers:{Accept:'application/json'}}),imageCatalog(true)]);
   if(!response.ok)throw new Error();
   window.dispatchEvent(new Event('vsg-models-reload'));notify('Model catalogs reloaded.');
  } catch(_){notify('Could not reload model catalogs. Please try again.','error');}
  finally{this.busy=false;}
 }
}));
Alpine.start();
