export function selectableModels(models, modality = 'text') {
    const company = model => model.name.split(':')[0].trim();
    return models.filter(model =>
        !/\((?:batch|free)\)/i.test(model.name) &&
        (model.architecture?.output_modalities ?? ['text']).includes(modality)
    ).sort((a, b) => company(a).localeCompare(company(b), 'en', {sensitivity:'base'}) ||
        a.name.localeCompare(b.name, 'en', {sensitivity:'base', numeric:true}));
}

export function rememberedModel(key, fallback = '') {
    try { return localStorage.getItem(key) || fallback; } catch (_) { return fallback; }
}

export function rememberModel(key, value) {
    try {
        if (value) localStorage.setItem(key, value);
        else localStorage.removeItem(key);
    } catch (_) { /* The picker also works when browser storage is unavailable. */ }
}

export function modelLabel(model) {
    if (model.catalog_type === 'image') return model.name;
    const price = key => {
        const value = model.pricing?.[key];
        return value != null && value !== '' && Number.isFinite(Number(value)) && Number(value) >= 0
            ? '$' + new Intl.NumberFormat('en-US', {maximumFractionDigits:6}).format(Number(value) * 1000000) : 'N/A';
    };
    return `${model.name} (${price('prompt')}/${price('completion')})`;
}

export function imagePriceLabel(line) {
    if (line.cost_usd == null || !Number.isFinite(Number(line.cost_usd)) || Number(line.cost_usd)<0) return 'Pricing unavailable';
    const token = line.unit === 'token';
    const amount = Number(line.cost_usd) * (token ? 1000000 : 1);
    const cost = new Intl.NumberFormat('en-US',{maximumFractionDigits:6}).format(amount);
    return `${(line.billable ?? 'image').replaceAll('_',' ')}: $${cost}/${token ? '1M tokens' : line.unit}${line.variant ? ' · '+line.variant : ''}`;
}
