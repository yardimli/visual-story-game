export function chatDrafts(options) {
 return {
  activeTab:'page',includeSceneImage:false,draftTabListener:null,
  draftKey(tab=this.activeTab){return 'vsg-chat-draft:'+options.userId+':'+(options.storyId||'new')+':'+tab;},
  initDrafts(){
   const url=new URL(window.location.href);this.activeTab=url.pathname.endsWith('/image-studio')?'images':(['page','visuals','inventory','story'].includes(url.searchParams.get('tab'))?url.searchParams.get('tab'):'page');
   this.loadPrompt();this.draftTabListener=event=>this.switchDraftTab(event.detail.tab);window.addEventListener('vsg-editor-tab',this.draftTabListener);
  },
  loadPrompt(){try{this.prompt=localStorage.getItem(this.draftKey())||'';}catch{this.prompt='';}this.$nextTick?.(()=>window.dispatchEvent(new Event('textarea-refresh')));},
  savePrompt(value=this.prompt){try{if(value)localStorage.setItem(this.draftKey(),value);else localStorage.removeItem(this.draftKey());}catch{}},
  switchDraftTab(tab){if(tab===this.activeTab)return;this.savePrompt();this.activeTab=tab;this.includeSceneImage=false;this.loadPrompt();},
  clearSentDraft(key,message){try{if(localStorage.getItem(key)===message)localStorage.removeItem(key);}catch{}if(this.draftKey()===key && this.prompt===message)this.prompt='';},
  destroyDrafts(){window.removeEventListener('vsg-editor-tab',this.draftTabListener);}
 };
}
