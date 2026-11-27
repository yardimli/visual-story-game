export function historyPollDelay(pending, failed = false) {
    return failed ? 10000 : pending ? 3000 : 30000;
}

export async function fetchChatHistory(url, etag) {
    const response = await fetch(url, {
        headers: { Accept: 'application/json', ...(etag ? { 'If-None-Match': etag } : {}) },
        cache: 'no-store',
    });
    if (response.status === 304) return { unchanged: true, etag };
    if (!response.ok) throw new Error('History could not load.');
    return { data: await response.json(), etag: response.headers.get('ETag') };
}

// Rebuild from saved messages, retaining local review UI state only for saved IDs.
export function recoverChatMessages(current, snapshot) {
    const existing = new Map(current.filter((item) => Number.isInteger(item.id)).map((item) => [item.id, item]));
    const pending = new Map(snapshot.pending.map((item) => [item.id, item]));
    return snapshot.messages.flatMap((message) => {
        const saved = { ...existing.get(message.id), ...message };
        const running = pending.get(message.id);
        return running
            ? [saved, { id: 'pending:' + message.id, role: 'assistant', content: running.content, streaming: true, created_at: message.created_at }]
            : [saved];
    });
}
