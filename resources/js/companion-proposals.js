const storyFields = ['title', 'premise', 'genre', 'language', 'style', 'bible'];
const pageFields = ['title', 'narrative', 'scene_prompt', 'video_prompt', 'interaction_plan', 'completion'];
const object = value => value && typeof value === 'object' && !Array.isArray(value) ? value : {};

export function proposalTargets(proposal, pageId, position) {
    proposal = object(proposal);
    if (!pageId) return [['create', object(proposal.form), ['title','premise','genre','page_count']]];
    // Older replies sometimes nested story fields under bible rather than story.
    const story = {...object(proposal.bible), ...object(proposal.story)};
    for (const key of storyFields) if (typeof proposal[key] === 'string') story[key] = proposal[key];
    const page = (Array.isArray(proposal.pages) ? proposal.pages : []).find(p => Number(p?.position) === Number(position));
    return [['story', story, storyFields], ['page', object(page), pageFields]];
}

export function displayMessage(item) {
    if (item.role !== 'assistant' || !item.proposal) return item.content;
    return item.content.replace(/```json\s*([\s\S]*?)```/gi, (block, json) => {
        try {
            const data = JSON.parse(json);
            return data && typeof data === 'object' && ['changes','interaction','form','story','bible','pages','premise','title','genre','language','style'].some(key => key in data) ? '' : block;
        } catch (_) { return block; }
    }).trim() || 'Draft changes are ready. Fill the form to review them before saving.';
}
