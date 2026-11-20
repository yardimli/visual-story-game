// Rebuild from saved messages, retaining local review UI state only for saved IDs.
export function recoverChatMessages(current, snapshot) {
    const existing = new Map(current.filter((item) => Number.isInteger(item.id)).map((item) => [item.id, item]));
    const pending = new Map(snapshot.pending.map((item) => [item.id, item]));
    return snapshot.messages.flatMap((message) => {
        const saved = { ...existing.get(message.id), ...message };
        const running = pending.get(message.id);
        return running
            ? [saved, { id: 'pending:' + message.id, role: 'assistant', content: running.content, streaming: true }]
            : [saved];
    });
}
