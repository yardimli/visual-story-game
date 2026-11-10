export function animatedDescription(options={}) {
 return {
  independentScene:!!options.independentScene, persistent:!!options.persistent,
  sizeObserver:null,
  init(){this.$nextTick?.(()=>{if(typeof ResizeObserver==='undefined'||!this.$refs?.words)return;this.sizeObserver=new ResizeObserver(()=>{if(this.visible)this.measure();});this.sizeObserver.observe(this.$refs.words);});},
  syncSceneSpace(){if(!this.independentScene)return;const stage=this.$refs?.box?.closest?.('.preview-stage');if(!stage)return;for(const side of ['top','bottom'])stage.style.setProperty('--scene-'+side+'-space',this.visible && this.placement===side?(this.height+12)+'px':'0px');},
  signature:null, measured:false, words:[], count:0, visible:false, placement:'top', height:0, wordTimer:null, hideTimer:null,
  anchor:null, boundsWidth:0, boundsHeight:0,
  updateBounds(){const stage=this.$refs?.box?.closest?.('.preview-stage');if(stage){this.boundsWidth=stage.clientWidth;this.boundsHeight=stage.clientHeight;}},
  get boxStyle(){if(!this.anchor || !['area_top','area_bottom'].includes(this.placement))return {height:this.height+'px',visibility:this.measured?'visible':'hidden'};const b=areaDescriptionBounds(this.anchor,this.boundsWidth,this.boundsHeight,this.height,this.placement);return {visibility:this.measured?'visible':'hidden',left:b.left+'px',right:'auto',top:b.top+'px',bottom:'auto',width:b.width+'px',height:b.height+'px',maxHeight:this.boundsHeight+'px'};},
  play(text,position='top',key='',animate=true,area=null) {
   const signature=JSON.stringify([text,position,key,animate,area]);if(signature===this.signature)return;
   this.signature=signature;this.stop();this.placement=['bottom','area_top','area_bottom'].includes(position)?position:'top';this.anchor=area;this.updateBounds();
   this.words=String(text||'').match(/\S+\s*/g)||[];this.count=0;this.height=0;this.measured=false;this.visible=!!this.words.length;
   this.syncSceneSpace();if(!this.visible)return;
   if(!animate){this.reveal();return;}
   if(globalThis.matchMedia?.('(prefers-reduced-motion: reduce)').matches){this.reveal();return;}
   this.step();
  },
  step(){this.count=Math.min(this.count+1,this.words.length);this.measure();if(this.count<this.words.length)this.wordTimer=setTimeout(()=>this.step(),110);else this.scheduleHide();},
  measure(){this.$nextTick?.(()=>{this.updateBounds();const content=this.$refs?.words;if(content){this.height=Math.min(250,content.scrollHeight+24);this.measured=true;this.syncSceneSpace();
    const box=this.$refs.box,word=content.querySelectorAll?.('span')[this.count-1];
    if(box && word){const bottom=word.offsetTop+word.offsetHeight;if(bottom>box.scrollTop+box.clientHeight-12)box.scrollTop=bottom-box.clientHeight+12;}
    else if(box && this.height<250)box.scrollTop=0;}});},
  scheduleHide(){clearTimeout(this.hideTimer);if(!this.persistent)this.hideTimer=setTimeout(()=>this.dismiss(),5000);},
  reveal(){clearTimeout(this.wordTimer);this.count=this.words.length;this.measure();this.scheduleHide();},
  dismiss(){this.visible=false;this.stop();this.syncSceneSpace();},
  stop(){clearTimeout(this.wordTimer);clearTimeout(this.hideTimer);},
  destroy(){this.sizeObserver?.disconnect();this.stop();}
 };
}

// Preview-only hover text. Delays a clicked action until its current text finishes.
export function hoverDescription() {
 return {
  ...animatedDescription(), hoveredArea:null, areaPlacement:'top', stageWidth:0, stageHeight:0,
  updateHoverBounds(){const stage=this.$refs?.box?.parentElement;if(stage){this.stageWidth=stage.clientWidth;this.stageHeight=stage.clientHeight;}},
  measure(){animatedDescription().measure.call(this);this.$nextTick?.(()=>this.updateHoverBounds());},
  get hoverBoxStyle(){
   if(!this.hoveredArea || !['area_top','area_bottom'].includes(this.areaPlacement))return {height:this.height+'px',visibility:this.measured?'visible':'hidden'};
   const b=areaDescriptionBounds(this.hoveredArea,this.stageWidth,this.stageHeight,this.height,this.areaPlacement);
   return {visibility:this.measured?'visible':'hidden',pointerEvents:'none',left:b.left+'px',right:'auto',top:b.top+'px',bottom:'auto',width:b.width+'px',height:b.height+'px',maxHeight:this.stageHeight+'px'};
  },
  seen:new Set(), hoverKey:null, pendingAction:null, clicked:false,
  hoverArea(area,pageId,sceneId){
   if(this.clicked)return;
   const text=String(area.description||'').trim();
   if(!text){this.leaveArea();return;}
   this.hoveredArea={x:area.x,y:area.y,width:area.width,height:area.height};this.areaPlacement=area.description_position||'top';this.updateHoverBounds();
   const key=JSON.stringify([pageId,sceneId,area.id,text]);
   if(this.hoverKey===key && this.visible)return;
   this.$dispatch?.('area-hover-start');
   const instant=this.seen.has(key);this.hoverKey=key;this.signature=null;
   this.play(text,area.description_position||'top',key);this.seen.add(key);
   if(instant || area.animate_hover_text!==true)this.reveal();
  },
  scheduleHide(){
   clearTimeout(this.hideTimer);
   if(this.clicked){const action=this.pendingAction;this.pendingAction=null;this.clicked=false;this.dismiss();action?.();}
  },
  leaveArea(){if(!this.clicked){this.hoverKey=null;this.dismiss();}},
  clickArea(area,action){
   if(this.clicked)return;
   if(!this.visible){action();return;}
   this.clicked=true;this.pendingAction=area.action==='examine'?null:action;
   if(this.count>=this.words.length)this.scheduleHide();
  },
  outsideClick(event){if(event.target.closest('.preview-hotspot') || event.target.closest('.hover-description') || !this.visible)return;this.clicked=true;this.pendingAction=null;if(this.count>=this.words.length)this.scheduleHide();},
  resetHover(){this.pendingAction=null;this.clicked=false;this.hoverKey=null;this.seen.clear();this.signature=null;this.dismiss();},
 };
}

export function areaDescriptionBounds(area, width, height, textHeight, position) {
 const boxWidth=Math.min(width,Math.max(200,(area.width||0)*width));
 const boxHeight=Math.min(textHeight,height);
 const left=Math.max(0,Math.min((area.x||0)*width,width-boxWidth));
 const desired=position==='area_top'?(area.y||0)*height-boxHeight-8:((area.y||0)+(area.height||0))*height+8;
 return {left,width:boxWidth,top:Math.max(0,Math.min(desired,height-boxHeight)),height:boxHeight};
}
