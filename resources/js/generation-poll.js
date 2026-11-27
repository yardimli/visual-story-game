// Poll only known active work, never resubmit generation or resend draft fields.
export async function generationStatuses(jobs, request, base = '') {
    const active = jobs.filter(job => ['queued', 'submitting', 'running'].includes(job.status));
    const updates = new Map(), missing = new Set();
    for (let i = 0; i < active.length; i += 50) {
        const query = new URLSearchParams();
        active.slice(i, i + 50).forEach(job => query.append('status_ids[]', job.id));
        const data = await request(base + (base.includes('?') ? '&' : '?') + query);
        data.jobs.forEach(job => updates.set(job.id, job));
        (data.missing_ids || []).forEach(id => missing.add(id));
    }
    return {
        finished: [...updates.values()].some(job => ['completed', 'failed'].includes(job.status)),
        jobs: jobs.filter(job => !missing.has(job.id)).map(job => ({ ...job, ...updates.get(job.id) })),
    };
}
