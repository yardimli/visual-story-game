// Only render our supported Delta formats; never inject HTML supplied by the LLM.
export function richPreview(delta) {
 const escape=value=>String(value).replace(/[&<>"']/g,c=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));
 let html='',line='',list='';
 const endLine=attributes=>{
  const kind=attributes.list==='bullet'?'ul':attributes.list==='ordered'?'ol':'';
  if(list!==kind){if(list)html+=`</${list}>`;if(kind)html+=`<${kind}>`;list=kind;}
  const tag=kind?'li':[1,2,3].includes(attributes.header)?'h'+attributes.header:attributes.blockquote?'blockquote':'p';
  html+=`<${tag}>${line||'<br>'}</${tag}>`;line='';
 };
 for(const op of delta?.ops||[]){
  if(typeof op.insert!=='string')continue;const attrs=op.attributes||{};
  const parts=op.insert.split('\n');parts.forEach((part,index)=>{
   if(!part){if(index<parts.length-1)endLine(attrs);return;}
   let text=escape(part);
   for(const [key,tag] of [['bold','strong'],['italic','em'],['underline','u'],['strike','s']])if(attrs[key])text=`<${tag}>${text}</${tag}>`;
   if(/^#[0-9a-f]{6}$/i.test(attrs.background||''))text=`<mark style="background-color:${attrs.background}">${text}</mark>`;
   line+=text;if(index<parts.length-1)endLine(attrs);
  });
 }
 if(line)endLine({});if(list)html+=`</${list}>`;return html;
}
export function reviewItems(proposal) {
 if(proposal?._review)return proposal._review.items;
 const items=[];
 const add=(path,value,title)=>items.push({id:items.length,path,after:value,title});
 const fields=(object,path,title)=>{if(object&&typeof object==='object'&&!Array.isArray(object))for(const [key,value] of Object.entries(object)){if(['string','number','boolean'].includes(typeof value))add([...path,key],value,title+' · '+key.replaceAll('_',' '));}};
 if(proposal?.form)fields(proposal.form,['form'],'New story');
 if(proposal?.story)fields(proposal.story,['story'],'Story');
 if(typeof proposal?.bible==='string')add(['bible'],proposal.bible,'Story · bible');
 else fields(proposal?.bible,['bible'],'Story');
 for(const key of ['title','premise','genre','language','style'])if(typeof proposal?.[key]==='string')add([key],proposal[key],'Story · '+key);
 for(const [index,page] of (proposal?.pages||[]).entries())for(const [key,value] of Object.entries(page))if(key!=='position'&&typeof value==='string')add(['pages',index,key],value,'Page '+page.position+' · '+key.replaceAll('_',' '));
 if(proposal?.interaction){
  for(const key of ['description','action'])if(proposal.interaction[key]!==undefined)add(['interaction',key],proposal.interaction[key],'Selected area · '+key);
  if(proposal.interaction.object)add(['interaction','object'],proposal.interaction.object,'Prepare object image and open generation');
 }
 return items;
}
export function selectedLegacyProposal(proposal,selected) {
 const result={};
 for(const item of reviewItems(proposal).filter(item=>selected.includes(String(item.id)))){
  let target=result;
  item.path.forEach((key,index)=>{
   if(index===item.path.length-1)target[key]=item.after;
   else {target[key]??=typeof item.path[index+1]==='number'?[]:{};target=target[key];}
  });
 }
 if(result.pages)result.pages=result.pages.map((page,index)=>page?{...page,position:proposal.pages[index].position}:null).filter(Boolean);
 for(const key of ['_image','_interaction'])if(proposal[key])result[key]=proposal[key];
 return result;
}
export function proposalReview(item) {
 return {entries:reviewItems(item.proposal),chosen:[],reviewOpen:false,richPreview,
  init(){this.chosen=this.entries.map(entry=>String(entry.id));},
  toggle(){this.chosen=this.chosen.filter(id=>{const entry=this.entries.find(e=>String(e.id)===id);return entry?.depends_on==null||this.chosen.includes(String(entry.depends_on));});},
  unavailable(entry){return entry.depends_on!=null&&!this.chosen.includes(String(entry.depends_on));},
  result(value){return value==null?'Not set':typeof value==='object'?JSON.stringify(value,null,2):String(value);}
 };
}
