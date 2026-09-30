import test from 'node:test';
import assert from 'node:assert/strict';
import vm from 'node:vm';
import {readFileSync} from 'node:fs';

const source = readFileSync(new URL('../../public/js/enforcer-camera.js', import.meta.url), 'utf8');
function setup({secure = true, denied = false} = {}) {
    class Element {
        listeners = {}; children = []; style = {}; disabled = false; hidden = false;
        videoWidth = 1920; videoHeight = 1080;
        addEventListener(name, callback) { this.listeners[name] = callback; }
        append(...children) { this.children.push(...children); }
        replaceChildren() { this.children = []; }
        play() { return Promise.resolve(); }
        getContext() { return {drawImage() {}}; }
        toBlob(callback) { callback(new Blob(['photo'], {type: 'image/jpeg'})); }
    }
    const elements = Object.fromEntries(['minor_photos', 'open-camera', 'camera-panel', 'camera-preview', 'capture-photo', 'camera-status', 'captured-photos', 'close-camera'].map(id => [id, new Element()]));
    elements.minor_photos.form = new Element();
    let stopped = 0, requested = 0;
    const document = new Element();
    document.getElementById = id => elements[id];
    document.createElement = () => new Element();
    const window = new Element();
    window.isSecureContext = secure;
    vm.runInNewContext(source, {
        document, window, Blob, File,
        URL: {createObjectURL: () => 'blob:photo', revokeObjectURL() {}},
        DataTransfer: class {
            files = [];
            items = {add: file => this.files.push(file)};
        },
        navigator: {mediaDevices: {getUserMedia: async options => {
            requested++;
            assert.equal(options.audio, false);
            assert.equal(options.video.facingMode.ideal, 'environment');
            if (denied) throw {name: 'NotAllowedError'};
            return {getTracks: () => [{stop: () => stopped++}]};
        }}},
    });
    return {elements, window, document, requested: () => requested, stopped: () => stopped};
}

test('captures up to five images, removes a capture, and releases the camera on submit', async () => {
    const {elements: e, stopped} = setup();
    await e['open-camera'].listeners.click();
    for (let i = 0; i < 6; i++) e['capture-photo'].listeners.click();
    assert.equal(e.minor_photos.files.length, 5);
    assert.equal(e['capture-photo'].disabled, true);
    assert.equal(e.minor_photos.files[0].type, 'image/jpeg');
    e['captured-photos'].children[0].children[1].onclick();
    assert.equal(e.minor_photos.files.length, 4);
    assert.equal(e['capture-photo'].disabled, false);
    e.minor_photos.form.listeners.submit({preventDefault() { assert.fail('Unexpected blocked submit'); }});
    assert.equal(stopped(), 1);
    assert.equal(e['camera-panel'].hidden, true);
});

test('camera permission denial gives a retryable error', async () => {
    const {elements: e} = setup({denied:true});
    await e['open-camera'].listeners.click();
    assert.match(e['camera-status'].textContent, /permission was denied/);
    assert.equal(e['open-camera'].disabled, false);
});

test('insecure pages explain why camera access is unavailable', async () => {
    const {elements: e, requested} = setup({secure:false});
    await e['open-camera'].listeners.click();
    assert.match(e['camera-status'].textContent, /HTTPS/);
    assert.equal(requested(), 0);
});

test('leaving the page releases camera tracks', async () => {
    const {elements: e, window, stopped} = setup();
    await e['open-camera'].listeners.click();
    window.listeners.pagehide();
    assert.equal(stopped(), 1);
});
