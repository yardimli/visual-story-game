import './bootstrap';
import './textarea-autosize';

import Alpine from 'alpinejs';
import {studioAutosave} from './autosave';
import {proposalTargets, displayMessage} from './companion-proposals';
import {selectableModels, rememberedModel, rememberModel, modelLabel, imagePriceLabel} from './model-preferences';

window.Alpine = Alpine;
Alpine.data('studioAutosave', studioAutosave);

Alpine.data('modelPicker', (initial, modality = 'text') => ({
    modelLabel,
    imagePriceLabel, endpoints: [], pricingError: '', pricingLoading: false, pricingRequest: 0,
    models: [], selected: initial, error: '', sending: false,
    init() { this.selected = rememberedModel(this.storageKey(), initial); this.$watch('selected', () => this.loadPricing()); this.load(); },
    storageKey() { return modality === 'text' ? 'vsg-writing-model' : 'vsg-image-model'; },
    remember() { rememberModel(this.storageKey(), this.selected); },
    async loadPricing() {
        if (modality !== 'image') return;
        const request = ++this.pricingRequest;
        this.endpoints = []; this.pricingError = ''; this.pricingLoading = false;
        if (!this.selected) return;
        this.pricingLoading = true;
        try {
            const response = await fetch('/ai/image-endpoints?model='+encodeURIComponent(this.selected), {headers:{Accept:'application/json'}});
            if (!response.ok) throw new Error();
            const data = await response.json();
            if (request === this.pricingRequest) this.endpoints = data.endpoints;
        } catch (_) { if (request === this.pricingRequest) this.pricingError = 'Could not load image pricing. Please retry.'; }
        finally { if (request === this.pricingRequest) this.pricingLoading = false; }
    },
    async load() {
        this.error = '';
        try {
            const response = await fetch('/ai/models?modality='+modality, {headers: {'Accept':'application/json'}});
            if (!response.ok) throw new Error('Catalog unavailable');
            const data = await response.json();
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
    modelLabel, displayMessage,
    messages: options.messages, models: [], model: options.model, prompt: '', error: '', notice: '',
    sending: false, catalogError: false, remaining: options.remaining,
    async init() {
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
        } catch (_) { this.catalogError = true; }
    },
    draft() {
        const form = document.querySelector('[data-companion-form="create"]');
        return form ? Object.fromEntries(['title','premise','genre','page_count'].map(key => [key,form.elements.namedItem(key).value])) : {};
    },
    async send() {
        if (this.sending || !this.prompt.trim() || !this.model) return;
        this.sending = true; this.error = ''; this.notice = '';
        const message = this.prompt;
        try {
            if (window.vsgAutosave && !(await window.vsgAutosave.flush())) throw new Error('Resolve unsaved changes before sending a message.');
            const response = await fetch(options.endpoint, {
                method:'POST', headers:{Accept:'application/json','Content-Type':'application/json','X-CSRF-TOKEN':document.querySelector('meta[name="csrf-token"]').content},
                body:JSON.stringify({message,model:this.model,request_key:crypto.randomUUID(),page_id:options.pageId,form:this.draft()})
            });
            const data = await response.json();
            if (!response.ok) throw new Error(Object.values(data.errors ?? {}).flat().join(' ') || data.message || 'Request failed.');
            this.messages.push({role:'user',content:message},data.message);
            this.remaining = data.remaining;
            this.prompt = '';
            this.$nextTick(() => window.dispatchEvent(new Event('textarea-refresh')));
            this.remember();
            this.scroll();
        } catch (e) { this.error = e.message || 'Connection interrupted. Check your allowance before trying again.'; }
        finally { this.sending = false; }
    },
    fill(item) {
        this.notice = ''; this.error = '';
        if (options.revision && item.revision !== (window.vsgAutosave?.revision ?? options.revision)) {
            this.error = 'This suggestion belongs to an older revision. Ask for an updated suggestion.'; return;
        }
        const targets = proposalTargets(item.proposal, options.pageId, options.position);
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

Alpine.start();
