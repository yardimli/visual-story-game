export function notify(message, type = 'success') {
 if (message && typeof window !== 'undefined') window.dispatchEvent(new CustomEvent('vsg-toast', {detail:{message, type}}));
}
export function watchNotices(component, fields) {
 for (const [field, type] of Object.entries(fields)) component.$watch?.(field, value => {
  if (value && !/^(Loading|Saving|Unsaved|Not saved|Uploading)/.test(value)) notify(value, type);
 });
}
export function toastCenter(initial = []) {
 return {
  items:[], timers:new Set(),
  init(){this.$nextTick(()=>initial.forEach(item=>this.add(item)));},
  later(fn, delay){const timer=setTimeout(()=>{this.timers.delete(timer);fn();},delay);this.timers.add(timer);},
  add({message, type='success'}) {
   if(!message)return;
   const existing=this.items.find(item=>item.message===message && item.visible);if(existing)return;
   const item={id:crypto.randomUUID(),message,type,visible:true};this.items.push(item);
   if(this.items.length>4)this.items.shift();
   this.$nextTick(()=>{if(this.$el.showPopover && !this.$el.matches(':popover-open'))this.$el.showPopover();});
   this.later(()=>this.dismiss(item.id),4000);
  },
  dismiss(id){const item=this.items.find(item=>item.id===id);if(!item)return;item.visible=false;
   this.later(()=>{this.items=this.items.filter(item=>item.id!==id);if(!this.items.length)this.$el.hidePopover?.();},300);
  },
  destroy(){this.timers.forEach(clearTimeout);}
 };
}
