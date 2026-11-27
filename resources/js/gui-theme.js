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
    vars['--ui-action-gap'] = `${components.dialogue?.action_gap_px ?? 8}px`;
    vars['--ui-scrollbar-thumb'] = components.scrollbar?.thumb || '#a8a29e';
    vars['--ui-scrollbar-track'] = components.scrollbar?.track || '#292524';
    const put = (prefix, layers) => {
        for (const [property, value] of Object.entries(layerBackground(layers))) vars[`--ui-${prefix}-${property}`] = value;
    };
    for (const [role, key] of [['dialog', 'dialogue'], ['intro', 'scene_introduction'], ['inventory', 'inventory_dialogue']]) {
        const c = resolve(components, key);
        put(role, c.layers);
        put(`${role}-corner`, c.corner_decoration ? [c.corner_decoration] : []);
        vars[`--ui-${role}-corner-width`] = `${c.corner_decoration?.size_px ?? 0}px`;
        vars[`--ui-${role}-corner-height`] = `${c.corner_decoration?.height_px ?? c.corner_decoration?.size_px ?? 0}px`;
        vars[`--ui-${role}-corner-tint`] = c.corner_decoration?.tint || 'transparent';
        vars[`--ui-${role}-corner-mask`] = c.corner_decoration?.tint ? vars[`--ui-${role}-corner-image`] : 'none';
        if (c.corner_decoration?.tint) vars[`--ui-${role}-corner-image`] = 'none';
        vars[`--ui-${role}-background`] = c.background_color || 'transparent';
        vars[`--ui-${role}-border`] = c.frame_border || '0';
        vars[`--ui-${role}-header`] = c.header_color || 'transparent';
        vars[`--ui-${role}-shadow`] = c.box_shadow || 'none';
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
