// A changed src can leave the previous bitmap visible while the new one downloads.
// Track each source independently, including its decode, so an older load cannot
// dismiss the indicator for a newer selection.
export function trackImageLoading(image, onState) {
    let version = 0;
    let disposed = false;
    const source = () => image.getAttribute('src') || image.getAttribute('srcset');
    const settle = async () => {
        if (disposed || !source() || !image.complete) return;
        const current = version;
        if (!image.naturalWidth) { onState('error'); return; }
        try {
            if (image.decode) await image.decode();
            if (!disposed && current === version) onState(null);
        } catch (_) {
            if (!disposed && current === version) onState('error');
        }
    };
    const refresh = () => {
        version++;
        onState(source() ? 'loading' : null);
        void settle();
    };
    const error = () => { if (!disposed && source() && image.complete && !image.naturalWidth) onState('error'); };
    image.addEventListener('load', settle);
    image.addEventListener('error', error);
    refresh();
    return {
        refresh,
        dispose() {
            disposed = true;
            version++;
            image.removeEventListener('load', settle);
            image.removeEventListener('error', error);
            onState(null);
        },
    };
}

export function installEditorImageLoading(root = document) {
    if (!root.documentElement.classList.contains('editor-document')) return;
    const tracked = new Map();
    const indicators = new Map();
    let frame = null;
    const position = () => {
        frame = null;
        for (const [image, indicator] of indicators) {
            // Closed pickers and hidden tabs must not display a floating spinner.
            const dialog = image.closest('dialog');
            const style = getComputedStyle(image);
            const visible = image.isConnected && image.getClientRects().length &&
                style.visibility !== 'hidden' && (!dialog || dialog.open);
            indicator.hidden = !visible;
            if (!visible) continue;
            const rect = image.getBoundingClientRect();
            const parent = image.parentElement.getBoundingClientRect();
            const width = rect.width || Math.min(parent.width, 80);
            const height = rect.height || Math.min(parent.height || 48, 80);
            indicator.style.left = `${rect.left + width / 2}px`;
            indicator.style.top = `${rect.top + height / 2}px`;
            indicator.classList.toggle('editor-image-status-small', width < 100 || height < 70);
        }
        if (indicators.size) frame = requestAnimationFrame(position);
    };
    const state = (image, value) => {
        if (!value) {
            indicators.get(image)?.remove();
            indicators.delete(image);
            image.removeAttribute('aria-busy');
            return;
        }
        let indicator = indicators.get(image);
        if (!indicator) {
            indicator = root.createElement('span');
            indicator.className = 'editor-image-status';
            indicator.setAttribute('role', 'status');
            indicator.innerHTML = '<span class="editor-image-spinner" aria-hidden="true"></span><span class="editor-image-status-text"></span>';
            // Dialogs live in the browser's top layer, above body overlays.
            (image.closest('dialog') || root.body).append(indicator);
            indicators.set(image, indicator);
        }
        image.setAttribute('aria-busy', value === 'loading' ? 'true' : 'false');
        indicator.classList.toggle('editor-image-error', value === 'error');
        indicator.querySelector('.editor-image-status-text').textContent = value === 'error' ? 'Image unavailable' : 'Loading image…';
        if (frame === null) frame = requestAnimationFrame(position);
    };
    const add = (image) => {
        if (image.tagName !== 'IMG' || tracked.has(image) || image.closest('.preview-dialog')) return;
        tracked.set(image, trackImageLoading(image, (value) => state(image, value)));
    };
    const scan = (node) => {
        if (node.nodeType !== 1) return;
        if (node.tagName === 'IMG') add(node);
        node.querySelectorAll('img').forEach(add);
    };
    root.querySelectorAll('img').forEach(add);
    const observer = new MutationObserver((mutations) => {
        for (const mutation of mutations) {
            if (mutation.type === 'attributes') {
                add(mutation.target);
                tracked.get(mutation.target)?.refresh();
            } else mutation.addedNodes.forEach(scan);
        }
        for (const [image, tracking] of tracked) {
            if (!image.isConnected) { tracking.dispose(); tracked.delete(image); }
        }
    });
    observer.observe(root.body, { subtree: true, childList: true, attributes: true, attributeFilter: ['src', 'srcset'] });
    return () => {
        observer.disconnect();
        for (const tracking of tracked.values()) tracking.dispose();
        tracked.clear();
        if (frame !== null) cancelAnimationFrame(frame);
    };
}
