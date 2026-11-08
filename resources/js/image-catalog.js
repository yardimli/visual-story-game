let pending;
export function imageCatalog(reload = false) {
 if(reload)pending=null;
 if(!pending)pending=fetch('/ai/image-endpoints',{headers:{Accept:'application/json'}}).then(async response=>{
  if(!response.ok)throw new Error('Image catalog unavailable. Reload models from AI settings.');
  const data=await response.json();
  return {...data,models:data.models.map(model=>({...model,imageEndpoints:data.endpoints[model.id]||[]}))};
 }).catch(error=>{pending=null;throw error;});
 return pending;
}
