const pending = new WeakSet();

// Keep existing labels and Alpine disabled bindings intact. A capture listener
// blocks repeated clicks even when a reactive binding changes during the request.
export async function runButtonAction(button, action) {
    if (!button) return await action();
    if (pending.has(button)) return;
    pending.add(button);
    const busy = button.getAttribute('aria-busy');
    const disabled = button.getAttribute('aria-disabled');
    const block = (event) => { event.preventDefault(); event.stopImmediatePropagation(); };
    button.addEventListener('click', block, { capture: true });
    button.classList.add('button-loading');
    button.setAttribute('aria-busy', 'true');
    button.setAttribute('aria-disabled', 'true');
    try {
        return await action();
    } finally {
        pending.delete(button);
        button.removeEventListener('click', block, { capture: true });
        button.classList.remove('button-loading');
        for (const [name, value] of [['aria-busy', busy], ['aria-disabled', disabled]]) {
            if (value === null) button.removeAttribute(name);
            else button.setAttribute(name, value);
        }
    }
}
