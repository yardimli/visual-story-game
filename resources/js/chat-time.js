export function messageTime(value, now = Date.now()) {
    if (!value) return { label: '', exact: '', iso: '' };
    const date = new Date(value);
    if (!Number.isFinite(date.getTime())) return { label: '', exact: '', iso: '' };
    const exact = date.toLocaleString();
    const age = Math.max(0, now - date.getTime());
    let label = exact;
    if (age < 60000) label = 'Just now';
    else if (age < 3600000) {
        const minutes = Math.floor(age / 60000);
        label = `${minutes} minute${minutes === 1 ? '' : 's'} ago`;
    } else if (age < 86400000) {
        const hours = Math.floor(age / 3600000);
        label = `${hours} hour${hours === 1 ? '' : 's'} ago`;
    }
    return { label, exact, iso: date.toISOString() };
}
