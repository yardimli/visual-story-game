export const actionLabels = {
    take: ['Take'],
    open: ['Open'],
    close: ['Close'],
    look: ['Look closer'],
    enter: ['Enter'],
    search: ['Search'],
    read: ['Read'],
    talk: ['Talk'],
    continue: ['Continue'],
    back: ['Back'],
    press: ['Press'],
    open_close: ['Open', 'Close'],
    pull_push: ['Pull', 'Push'],
    turn_on_off: ['Turn on', 'Turn off'],
    insert_remove: ['Insert', 'Remove'],
};

export function defaultActionPreset(area) {
    if (area.action === 'state_v2' && !area.auto_state?.enabled) return 'open_close';
    if (['pickup', 'pickup_v2'].includes(area.action)) return 'take';
    if (area.action === 'inventory') return 'look';
    return 'continue';
}

export function actionLabel(area, changed = false) {
    const preset = area.action_preset || defaultActionPreset(area);
    if (preset === 'custom_single' || preset === 'custom_dual')
        return (changed && preset === 'custom_dual' ? area.changed_action_label : area.action_label) || 'Continue';
    const labels = actionLabels[preset] || ['Continue'];
    return labels[changed ? Math.min(1, labels.length - 1) : 0];
}
