import Quill from 'quill';
import 'quill/dist/quill.snow.css';
export function pageDocument(options){
 let editors=[];
 return {
  init(){
   for(const field of ['narrative','interaction_plan']){
    const host=this.$el.querySelector('[data-editor="'+field+'"]'),input=this.$el.querySelector('[name="'+field+'"]'),delta=this.$el.querySelector('[name="'+field+'_delta"]');
    const editor=new Quill(host,{theme:'snow',formats:['bold','italic','underline','strike','background','header','list','blockquote'],modules:{toolbar:[['bold','italic','underline','strike'],[{background:['#fef08a','#bbf7d0','#bfdbfe',false]}],[{header:[1,2,3,false]},{list:'bullet'},{list:'ordered'}],['blockquote','clean']]}});
    const toolbar=editor.getModule('toolbar').container;toolbar.querySelector('.ql-background .ql-picker-label')?.setAttribute('aria-label','Highlight color');toolbar.querySelector('.ql-header .ql-picker-label')?.setAttribute('aria-label','Heading level');
    editor.root.setAttribute('aria-label',field==='narrative'?'Narrative overlay — '+options.title:'Interaction plan — '+options.title);
    try{if(delta.value && delta.value!=='null')editor.setContents(JSON.parse(delta.value),'silent');else editor.setText(input.value,'silent');}catch(_){editor.setText(input.value,'silent');}
    const sync=()=>{input.value=editor.getText().replace(/\n$/,'');delta.value=JSON.stringify(editor.getContents());input.dispatchEvent(new Event('input',{bubbles:true}));};
    editor.on('text-change',(_d,_o,source)=>{if(source==='user')sync();});
    editor.on('selection-change',range=>{if(range)window.vsgAutosave?.selectPage(options.id,options.position);});
    input.addEventListener('change',()=>{if(input.value!==editor.getText().replace(/\n$/,'')){editor.setText(input.value,'silent');delta.value=JSON.stringify(editor.getContents());}});
    editors.push(editor);
   }
  },
  destroy(){editors.forEach(e=>{e.off('text-change');e.off('selection-change');});editors=[];}
 };
}
export function companionResize(){return {
 width:432, dragging:false,
 init(){try{this.width=Number(localStorage.getItem('vsg-chat-width'))||432;}catch(_){}this.width=Math.max(300,Math.min(this.width,window.innerWidth*.65));},
 resize(event){if(!this.dragging)return;this.width=Math.round(Math.max(300,Math.min(window.innerWidth-event.clientX,window.innerWidth*.65)));},
 finish(){this.dragging=false;try{localStorage.setItem('vsg-chat-width',String(this.width));}catch(_){}},
 keyboard(delta){this.width=Math.max(300,Math.min(this.width+delta,window.innerWidth*.65));this.finish();}
};}
