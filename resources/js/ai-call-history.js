import {notify} from './toasts.js';
export function aiCallHistory(url) {
 return {busy:false,loaded:false,failed:false,detail:null,
  async load(open=true){
   if(!open||this.busy||this.loaded)return;
   this.busy=true;this.failed=false;
   try{const response=await fetch(url,{headers:{Accept:'application/json'}});if(!response.ok)throw new Error('Could not load this AI call. Please try again.');this.detail=await response.json();this.loaded=true;}
   catch(error){this.failed=true;notify(error.message,'error');}finally{this.busy=false;}
  },
  format(value){return value==null?'Not recorded':JSON.stringify(value,null,2);}
 };
}
