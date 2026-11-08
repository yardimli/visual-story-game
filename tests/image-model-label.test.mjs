import test from 'node:test';
import assert from 'node:assert/strict';
import {imageModelLabel} from '../resources/js/model-preferences.js';
test('image labels show token units and image price ranges',()=>{
 assert.equal(imageModelLabel({name:'Image',imageEndpoints:[{pricing:[{billable:'output_image',unit:'image',cost_usd:.05},{billable:'output_image',unit:'image',cost_usd:.1}]}]}),'Image (output image $0.05–$0.1/image)');
 assert.equal(imageModelLabel({name:'Image',imageEndpoints:[{pricing:[{billable:'input_text',unit:'token',cost_usd:.000005},{billable:'output_image',unit:'token',cost_usd:.00003}]}]}),'Image (input text $5/1M tokens; output image $30/1M tokens)');
});
