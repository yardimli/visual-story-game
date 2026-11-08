import {watchNotices} from './toasts.js';
export function studioAutosave(initialRevision, options = {}) {
    return {
        tab: 'page', revision: initialRevision, status: 'All changes saved', error: '', busy: false,
        pending: new Set(), timer: null, active: null, stopped: false,
        init() {
            watchNotices(this,{status:'success',error:'error'});
            window.vsgAutosave = this;
            if(options.tabs){
                const allowed=['page','story','visuals','assets'];
                const current=new URL(window.location.href).searchParams.get('tab');
                this.tab=allowed.includes(current)?current:'page';
                const sync=tab=>{if(!allowed.includes(tab))return;const url=new URL(window.location.href);url.searchParams.set('tab',tab);window.history.replaceState(null,'',url);};
                sync(this.tab);this.$watch('tab',sync);
            }
            this.unload = event => { if (this.busy || this.pending.size) { event.preventDefault(); event.returnValue = ''; } };
            window.addEventListener('beforeunload', this.unload);
            this.navigation = async event => {
                const link = event.target.closest('a[href]');
                if (event.defaultPrevented || !link || event.ctrlKey || event.metaKey || event.shiftKey || link.target === '_blank' || !(this.pending.size || this.busy || window.vsgSceneEditor?.dirty || window.vsgSceneEditor?.saving)) return;
                event.preventDefault();
                if (await this.flush()) window.location.assign(link.href);
            };
            document.addEventListener('click', this.navigation);
        },
        destroy() { clearTimeout(this.timer); window.removeEventListener('beforeunload',this.unload); document.removeEventListener('click',this.navigation); delete window.vsgAutosave; },
        changed(event) {
            const form = event.target.closest('form[data-autosave]');
            if (!form || !event.target.name || event.target.name === 'revision') return;
            this.pending.add(form); this.status = 'Unsaved changes';
            if (this.stopped) return;
            this.error = ''; clearTimeout(this.timer);
            this.timer = setTimeout(() => this.flush(), 400);
        },
        async flush() {
            const saved = await this.flushForms();
            return saved && (typeof window !== 'undefined' && window.vsgSceneEditor ? await window.vsgSceneEditor.flush() : true);
        },
        async flushForms() {
            clearTimeout(this.timer);
            if (this.active) { await this.active; return this.pending.size && !this.stopped ? this.flushForms() : !this.error; }
            if (this.stopped) return false;
            this.active = this.drain();
            try { return await this.active; } finally { this.active = null; }
        },
        async drain() {
            while (this.pending.size) {
                const form = this.pending.values().next().value;
                if (!form.checkValidity()) { this.error = 'Complete the required fields before changes can be saved.'; this.status = 'Not saved'; return false; }
                this.pending.delete(form); this.busy = true; this.status = 'Saving…';
                const body = new FormData(form); body.set('revision',this.revision);
                try {
                    const response = await fetch(form.action,{method:'POST',body,headers:{Accept:'application/json'}});
                    const result = await response.json();
                    if (!response.ok) {
                        if (result.errors?.revision || response.status !== 422) this.stopped = true;
                        throw new Error(Object.values(result.errors ?? {}).flat().join(' ') || result.message || 'Save failed.');
                    }
                    this.revision = result.revision;
                    document.querySelectorAll('input[name="revision"]').forEach(field => { field.value = this.revision; });
                    this.error = ''; this.status = 'All changes saved';
                } catch (error) {
                    this.pending.add(form);
                    if (!(error instanceof Error)) this.stopped = true;
                    // A lost response may have committed. Never retry with an uncertain revision.
                    if (error instanceof TypeError || error instanceof SyntaxError) this.stopped = true;
                    this.error = error.message || 'Save failed. Reload to check the saved version.';
                    this.status = 'Not saved'; return false;
                } finally { this.busy = false; }
            }
            return true;
        },
        async submitAction(event) {
            event.preventDefault();
            if (await this.flush()) event.target.submit();
        }
    };
}
