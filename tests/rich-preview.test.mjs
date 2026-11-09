import test from 'node:test';
import assert from 'node:assert/strict';
import {richPreview} from '../resources/js/proposal-review.js';

test('review renders headings and grouped lists without allowing HTML or unsafe styles',()=>{
 const html=richPreview({ops:[{insert:'Heading'},{insert:'\n',attributes:{header:2}},{insert:'First',attributes:{bold:true}},{insert:'\n',attributes:{list:'ordered'}},{insert:'<img src=x onerror=alert(1)>',attributes:{background:'red;position:fixed'}},{insert:'\n',attributes:{list:'ordered'}}]});
 assert.equal(html,'<h2>Heading</h2><ol><li><strong>First</strong></li><li>&lt;img src=x onerror=alert(1)&gt;</li></ol>');
 assert.equal(richPreview({ops:[{insert:{image:'javascript:alert(1)'}}]}),'');
});
