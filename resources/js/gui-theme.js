// The manifest uses bottom-to-top layers; CSS backgrounds use front-to-back.
export function layerBackground(layers = []) {
    const images = [], sizes = [], positions = [];
    for (const layer of [...layers].reverse()) {
        if (!layer.url) continue;
        const [x, y, w, h] = layer.rect || [0, 0, 1, 1];
        const [left, top, right, bottom] = layer.source_crop || [0, 0, layer.width, layer.height];
        const sx = w * layer.width / (right - left), sy = h * layer.height / (bottom - top);
        const ox = x - w * left / (right - left), oy = y - h * top / (bottom - top);
        const position = (origin, scale) => Math.abs(1 - scale) < 1e-8 ? 0 : origin / (1 - scale) * 100;
        images.push(`url(${JSON.stringify(layer.url)})`);
        sizes.push(`${sx * 100}% ${sy * 100}%`);
        positions.push(`${position(ox, sx)}% ${position(oy, sy)}%`);
    }
    return { image: images.join(',') || 'none', size: sizes.join(',') || '100% 100%', position: positions.join(',') || '0% 0%' };
}

function resolve(entries, key, seen = new Set()) {
    if (seen.has(key)) return {};
    seen.add(key);
    const value = entries[key] || {};
    return value.same_as ? { ...resolve(entries, value.same_as, seen), ...value } : value;
}

export function guiThemeStyle(theme) {
    if (theme?.renderer !== 'png') return {};
    const vars = {}, components = theme.components || {};
    const put = (prefix, layers) => {
        for (const [property, value] of Object.entries(layerBackground(layers))) vars[`--ui-${prefix}-${property}`] = value;
    };
    for (const [role, key] of [['dialog', 'dialogue'], ['intro', 'scene_introduction'], ['inventory', 'inventory_dialogue']]) {
        const c = resolve(components, key);
        put(role, c.layers);
        vars[`--ui-${role}-text`] = c.text_color || '#fff';
        vars[`--ui-${role}-padding`] = (c.content_padding_px || [18,22,18,22]).map(n => `${n}px`).join(' ');
    }
    for (const [role, key] of [['choice', 'choice_button'], ['close', 'close_button']]) {
        const c = resolve(components, key);
        vars[`--ui-${role}-text`] = c.text_color || '#fff';
        vars[`--ui-${role}-padding`] = (c.content_padding_px || [10,18,10,18]).map(n => `${n}px`).join(' ');
        vars[`--ui-${role}-height`] = `${c.min_height_px || 44}px`;
        for (const state of ['normal', 'hover', 'focus', 'pressed', 'disabled']) {
            const s = resolve(c.states || {}, state);
            put(`${role}-${state}`, s.layers);
            vars[`--ui-${role}-${state}-filter`] = s.css?.filter || 'none';
            vars[`--ui-${role}-${state}-opacity`] = s.css?.opacity ?? 1;
        }
    }
    return vars;
}

export function guiThemePreview(selected, themes) {
    return {
        selected,
        get theme() { return themes[this.selected] || themes.text; },
        get previewStyle() { return guiThemeStyle(this.theme); },
    };
}
