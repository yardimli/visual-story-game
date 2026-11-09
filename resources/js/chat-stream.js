export function streamingMessage(content='') {
 let text='',position=0,preparing=false;
 while(position<content.length){
  const start=content.indexOf('```',position);
  if(start<0){text+=content.slice(position).replace(/`{1,2}$/,'');break;}
  text+=content.slice(position,start);
  const rest=content.slice(start+3),header=rest.match(/^([^\r\n]*)/)[0];
  if(!/[\r\n]/.test(rest) && /^\s*(?:j(?:s(?:o(?:n)?)?)?)?\s*$/i.test(header)){preparing=true;break;}
  const json=/^\s*json\b/i.test(rest)||/^\s*\{/.test(rest);
  const end=content.indexOf('```',start+3);
  if(json){preparing=true;if(end<0)break;position=end+3;}
  else {if(end<0){text+=content.slice(start);break;}text+=content.slice(start,end+3);position=end+3;}
 }
 return {text:text.trim(),preparing};
}

export async function readChatStream(response,onEvent) {
 const reader=response.body?.getReader();if(!reader)throw new Error('Streaming is unavailable in this browser.');
 const decoder=new TextDecoder();let buffer='',complete=false;
 const consume=()=>{
  let end;
  while((end=buffer.indexOf('\n\n'))>=0){
   const frame=buffer.slice(0,end);buffer=buffer.slice(end+2);
   const lines=frame.split('\n'),event=lines.find(line=>line.startsWith('event:'))?.slice(6).trim();
   const payload=lines.filter(line=>line.startsWith('data:')).map(line=>line.slice(5).trimStart()).join('\n');
   if(!payload)continue;const data=JSON.parse(payload);
   if(event==='error')throw new Error(data.message||'The response was interrupted.');
   onEvent(event,data);if(event==='complete')complete=true;
  }
 };
 try {
  while(true){const {done,value}=await reader.read();if(done)break;buffer+=decoder.decode(value,{stream:true}).replace(/\r/g,'');consume();}
  buffer+=decoder.decode();consume();
  if(!complete)throw new Error('The response was interrupted. Check chat history before sending again; no automatic retry was made.');
 }finally{reader.releaseLock();}
}
