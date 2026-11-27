import test from 'node:test';
import assert from 'node:assert/strict';
import { messageTime } from '../resources/js/chat-time.js';

test('chat timestamps transition from just now to minutes, hours and an exact local date', () => {
    const value='2026-11-27T01:00:00Z', start=Date.parse(value);
    for(const [age,label] of [[0,'Just now'],[59999,'Just now'],[60000,'1 minute ago'],[120000,'2 minutes ago'],[3600000,'1 hour ago'],[86399999,'23 hours ago']]) {
        const result=messageTime(value,start+age);
        assert.equal(result.label,label);
        assert.equal(result.exact,new Date(value).toLocaleString());
        assert.equal(result.iso,new Date(value).toISOString());
    }
    assert.equal(messageTime(value,start+86400000).label,new Date(value).toLocaleString());
    assert.equal(messageTime(value,start-1000).label,'Just now');
    assert.equal(messageTime(null).label,'');
    assert.equal(messageTime('invalid').label,'');
});
