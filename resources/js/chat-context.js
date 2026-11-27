// A size warning is a preflight response: no generation or billing has started.
export async function sendChatWithContextConfirmation(url, options, confirm) {
    const response = await fetch(url, options);
    if (response.status !== 409) return response;
    const warning = await response.clone().json();
    if (warning.code !== 'context_confirmation_required') return response;
    if (!(await confirm(warning.context_bytes))) return null;
    const payload = JSON.parse(options.body);
    return fetch(url, { ...options, body: JSON.stringify({ ...payload, allow_large_context: true }) });
}
