import test from 'node:test';
import assert from 'node:assert/strict';
import {scenePalette,contrastText} from '../resources/js/scene-colors.js';
test('palette averages opaque scene pixels and ignores transparent pixels',()=>{assert.deepEqual(scenePalette([100,80,60,255,255,0,0,0]),scenePalette([100,80,60,255]));const p=scenePalette([100,80,60,255]);assert.notEqual(p.background,p.footer);assert.notEqual(p.border,p.background);assert.equal(contrastText(p.background),'#ffffff');assert.equal(contrastText('#ffffff'),'#171717');});
