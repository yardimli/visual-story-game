export function animatedDescription() {
 return {
  signature:null, words:[], count:0, visible:false, placement:'top', height:0, wordTimer:null, hideTimer:null,
  play(text,position='top',key='') {
   const signature=JSON.stringify([text,position,key]);if(signature===this.signature)return;
   this.signature=signature;this.stop();this.placement=position==='bottom'?'bottom':'top';
   this.words=String(text||'').match(/\S+\s*/g)||[];this.count=0;this.height=0;this.visible=!!this.words.length;
   if(!this.visible)return;
   if(globalThis.matchMedia?.('(prefers-reduced-motion: reduce)').matches){this.reveal();return;}
   this.step();
  },
  step(){this.count=Math.min(this.count+1,this.words.length);this.measure();if(this.count<this.words.length)this.wordTimer=setTimeout(()=>this.step(),110);else this.scheduleHide();},
  measure(){this.$nextTick?.(()=>{const content=this.$refs?.words;if(content){this.height=Math.min(150,content.scrollHeight+24);if(this.$refs.box)this.$refs.box.scrollTop=this.height>=150?this.$refs.box.scrollHeight:0;}});},
  scheduleHide(){clearTimeout(this.hideTimer);this.hideTimer=setTimeout(()=>this.dismiss(),5000);},
  reveal(){clearTimeout(this.wordTimer);this.count=this.words.length;this.measure();this.scheduleHide();},
  dismiss(){this.visible=false;this.stop();},
  stop(){clearTimeout(this.wordTimer);clearTimeout(this.hideTimer);},
  destroy(){this.stop();}
 };
}
