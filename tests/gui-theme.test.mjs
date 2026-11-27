import test from 'node:test';
import assert from 'node:assert/strict';
import {guiThemeStyle,layerBackground} from '../resources/js/gui-theme.js';

test('text theme leaves existing palette untouched',()=>assert.deepEqual(guiThemeStyle({renderer:'existing_css'}),{}));
test('layer order, inset overlays and source cropping map to CSS backgrounds',()=>{
    const base={url:'/base',width:100,height:100,rect:[0,0,1,1]};
    const overlay={url:'/hover',width:80,height:80,rect:[.1,.1,.8,.8]};
    const style=layerBackground([base,overlay]);
    assert.equal(style.image,'url("/hover"),url("/base")');
    assert.equal(style.size,'80% 80%,100% 100%');
    assert.ok(Math.abs(parseFloat(style.position)-50)<.0001);
    const cropped=layerBackground([{...base,width:128,height:128,source_crop:[15,15,113,113]}]);
    assert.ok(Math.abs(parseFloat(cropped.position)-50)<.0001);
    assert.ok(parseFloat(cropped.size)>130);
});
test('component and state aliases preserve disabled styling and inventory art',()=>{
    const layer={url:'/panel',width:10,height:10};
    const style=guiThemeStyle({renderer:'png',components:{dialogue:{layers:[layer]},scene_introduction:{same_as:'dialogue'},inventory_dialogue:{same_as:'dialogue'},choice_button:{states:{normal:{layers:[layer]},hover:{layers:[layer],css:{filter:'brightness(1.15)'}},focus:{same_as:'hover'},disabled:{same_as:'normal',css:{opacity:.45}}}},close_button:{same_as:'choice_button'}}});
    assert.equal(style['--ui-inventory-image'],style['--ui-dialog-image']);
    assert.equal(style['--ui-close-focus-filter'],'brightness(1.15)');
    assert.equal(style['--ui-choice-disabled-opacity'],.45);
});
