// Native content sizing also handles programmatic Alpine updates and hidden tabs.
// The fallback supports browsers without field-sizing.
if (!CSS.supports('field-sizing', 'content')) {
    const widths = new WeakMap();
    const observed = new WeakSet();
    function fit(field) {
        if (!(field instanceof HTMLTextAreaElement) || !field.getClientRects().length) return;
        field.style.height = 'auto';
        const style = getComputedStyle(field);
        const borders = parseFloat(style.borderTopWidth) + parseFloat(style.borderBottomWidth);
        field.style.height = `${field.scrollHeight + borders}px`;
    }
    const resize = new ResizeObserver(entries => {
        for (const {target, contentRect} of entries) {
            if (widths.get(target) !== contentRect.width) {
                widths.set(target, contentRect.width);
                fit(target);
            }
        }
    });
    let frame;
    function refresh() {
        cancelAnimationFrame(frame);
        frame = requestAnimationFrame(() => {
            document.querySelectorAll('textarea').forEach(field => {
                if (!observed.has(field)) { observed.add(field); resize.observe(field); }
                fit(field);
            });
        });
    }
    document.addEventListener('input', event => fit(event.target));
    document.addEventListener('change', event => fit(event.target));
    document.addEventListener('reset', refresh);
    window.addEventListener('textarea-refresh', refresh);
    new MutationObserver(records => {
        if (records.some(record => !(record.target instanceof HTMLTextAreaElement))) refresh();
    }).observe(document.body, {subtree:true, childList:true, attributes:true, attributeFilter:['style','class','hidden']});
    document.fonts?.ready.then(refresh);
    refresh();
}
