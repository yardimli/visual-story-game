import test from 'node:test';
import assert from 'node:assert/strict';
import {reviewItems,selectedLegacyProposal,proposalReview} from '../resources/js/proposal-review.js';
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
