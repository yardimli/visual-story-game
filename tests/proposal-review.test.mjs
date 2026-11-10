import test from 'node:test';
import assert from 'node:assert/strict';
import {reviewItems,selectedLegacyProposal,proposalReview,structuredPreview} from '../resources/js/proposal-review.js';
test('legacy review applies only checked fields and retains page identity',()=>{
 const proposal={story:{title:'Title',premise:'Premise'},pages:[{position:2,title:'Page',narrative:'Narrative'}]};
 const items=reviewItems(proposal);assert.equal(items.length,4);
 assert.deepEqual(selectedLegacyProposal(proposal,['1','3']),{story:{premise:'Premise'},pages:[{position:2,narrative:'Narrative'}]});
 assert.equal(proposal.story.title,'Title');
});
test('skipping object preparation removes the dependent navigation selection',()=>{
 const review=proposalReview({proposal:{_review:{items:[{id:0,title:'Description'},{id:1,title:'Object'},{id:2,title:'Open',depends_on:1}]}}});
 review.init();review.chosen=['0','2'];review.toggle();assert.deepEqual(review.chosen,['0']);assert.equal(review.unavailable(review.entries[2]),true);
});

test('structured review renders nested objects and JSON strings as escaped labeled fields',()=>{
 const html=structuredPreview({name:'Door',prompt:'First line\nSecond line',references:[41,27],settings:{quality:'high'},nested:'{"enabled":true}',unsafe:'<script>alert(1)</script>'});
 assert.ok(html.includes('<dt>Prompt</dt>'));assert.ok(html.includes('First line\nSecond line'));assert.ok(html.includes('<dt>Enabled</dt>'));assert.ok(html.includes('Yes'));assert.ok(!html.includes('<script>'));assert.ok(html.includes('&lt;script&gt;'));
});
test('new valid proposals open review once; loaded history does not open automatically',()=>{
 let opened=0;const item={autoReview:true,proposal:{_review:{items:[{id:1}]}}};const r=proposalReview(item);r.$nextTick=fn=>fn();r.$refs={reviewDialog:{showModal(){opened++;}}};r.init();r.init();assert.equal(opened,1);
 const old=proposalReview({proposal:item.proposal});old.$nextTick=fn=>fn();old.$refs=r.$refs;old.init();assert.equal(opened,1);
});
