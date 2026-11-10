export function scenePalette(pixels) {
 let rgb=[0,0,0],weight=0;
 for(let i=0;i<pixels.length;i+=4){const a=pixels[i+3]/255;weight+=a;for(let c=0;c<3;c++)rgb[c]+=pixels[i+c]*a;}
 rgb=weight?rgb.map(v=>v/weight):[70,60,50];
 const hex=(factor,lift=0)=>'#'+rgb.map(v=>Math.round(Math.min(255,v*factor+lift)).toString(16).padStart(2,'0')).join('');
 return {background:hex(.38),border:hex(.65,85),footer:hex(.6,12)};
}
export function contrastText(hex){const c=hex.replace('#','').match(/../g).map(v=>parseInt(v,16)/255).map(v=>v<=.04045?v/12.92:((v+.055)/1.055)**2.4);return c[0]*.2126+c[1]*.7152+c[2]*.0722>.179?'#171717':'#ffffff';}
