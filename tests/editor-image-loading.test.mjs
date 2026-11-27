import test from 'node:test';
import assert from 'node:assert/strict';
import { trackImageLoading } from '../resources/js/editor-image-loading.js';

class ImageStub extends EventTarget {
    src = '/first.png';
    complete = false;
    naturalWidth = 0;
    getAttribute(name) { return name === 'src' ? this.src : null; }
}

test('replacement images show loading immediately and wait for decode', async () => {
    const image = new ImageStub();
    const states = [];
    let decode;
    image.decode = () => new Promise(resolve => { decode = resolve; });
    const tracking = trackImageLoading(image, s => states.push(s));
    assert.equal(states.at(-1), 'loading');
    image.complete = true;
    image.naturalWidth = 1200;
    image.dispatchEvent(new Event('load'));
    assert.equal(states.at(-1), 'loading');
    decode(); await Promise.resolve();
    assert.equal(states.at(-1), null);
    image.src = '/second.png'; image.complete = false;
    tracking.refresh();
    assert.equal(states.at(-1), 'loading');
    tracking.dispose();
});

test('an old decode cannot dismiss a newer image spinner', async () => {
    const image = new ImageStub();
    const states = [];
    let decode;
    image.decode = () => new Promise(resolve => { decode = resolve; });
    const tracking = trackImageLoading(image, s => states.push(s));
    image.complete = true; image.naturalWidth = 1200;
    image.dispatchEvent(new Event('load'));
    image.src = '/new.png'; image.complete = false;
    tracking.refresh();
    decode(); await Promise.resolve();
    assert.equal(states.at(-1), 'loading');
    tracking.dispose();
});

test('empty sources, cached images, failures and removal stop spinning', async () => {
    const image = new ImageStub(); image.src = null;
    const states = [];
    const tracking = trackImageLoading(image, s => states.push(s));
    assert.equal(states.at(-1), null);
    image.src = '/cached.png'; image.complete = true; image.naturalWidth = 200;
    image.decode = async () => {};
    tracking.refresh(); await Promise.resolve();
    assert.equal(states.at(-1), null);
    image.src = '/missing.png'; image.naturalWidth = 0;
    tracking.refresh(); image.dispatchEvent(new Event('error'));
    assert.equal(states.at(-1), 'error');
    tracking.dispose();
    assert.equal(states.at(-1), null);
});
