import test from 'node:test';
import assert from 'node:assert/strict';
import fs from 'node:fs';
import vm from 'node:vm';
const source=fs.readFileSync(new URL('../resources/views/components/theme-init.blade.php',import.meta.url),'utf8').replace(/<\/?script>/g,'');
test('paper theme persists, syncs across tabs, and excludes the dark class',()=>{
 const classes=new Set(),listeners={};let stored='paper',systemListener;
 const root={dataset:{},style:{},classList:{toggle(name,on){on?classes.add(name):classes.delete(name);}}};
 const window={matchMedia:()=>({matches:true,addEventListener(name,fn){systemListener=fn;}}),dispatchEvent(){},addEventListener(name,fn){listeners[name]=fn;}};
 vm.runInNewContext(source,{window,document:{documentElement:root},localStorage:{getItem:()=>stored,setItem:(key,value)=>{stored=value;}},CustomEvent:class{constructor(type,options){this.type=type;this.detail=options.detail;}}});
 assert.equal(root.dataset.theme,'paper');assert.equal(classes.has('paper'),true);assert.equal(classes.has('dark'),false);
 systemListener();assert.equal(root.dataset.theme,'paper');
 window.setVsgTheme('dark');assert.equal(stored,'dark');assert.equal(classes.has('paper'),false);assert.equal(classes.has('dark'),true);
 listeners.storage({key:'vsg-theme',newValue:'paper'});assert.equal(root.dataset.theme,'paper');assert.equal(root.style.colorScheme,'light');
 window.setVsgTheme('invalid');assert.equal(root.dataset.theme,'paper');
 window.setVsgTheme('light');assert.equal(classes.size,0);
});
