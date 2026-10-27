export function selectedVariant(options, variants, selection) {
    if (!options.every(option => selection[option.id] != null)) return null;
    return variants.find(variant => options.every(option => String(variant.values[option.id]) === String(selection[option.id]))) ?? null;
}

export function possibleChoice(options, variants, selection, optionId, valueId) {
    const candidate = {...selection, [optionId]: String(valueId)};
    return variants.some(variant => options.every(option => !candidate[option.id] || String(variant.values[option.id]) === String(candidate[option.id])));
}

export function picker(data) {
    const initial = data.variants.find(variant => variant.available > 0 && !variant.contact) ?? data.variants[0];
    return {
        data, selection: initial ? {...initial.values} : {}, quantity: 1, image: initial?.image ?? data.image,
        get variant() { return selectedVariant(this.data.options, this.data.variants, this.selection); },
        get canBuy() { return this.variant && !this.variant.contact && this.variant.available >= this.quantity && this.quantity >= 1; },
        choose(optionId, valueId) {
            this.selection = possibleChoice(this.data.options, this.data.variants, this.selection, optionId, valueId)
                ? {...this.selection, [optionId]: String(valueId)} : {[optionId]: String(valueId)};
            this.quantity = 1; this.image = this.variant?.image ?? this.data.image;
        },
        possible(optionId, valueId) { return possibleChoice(this.data.options, this.data.variants, {}, optionId, valueId); },
        step(delta) { this.quantity = Math.max(1, Math.min(1000000, (this.variant?.available || 1), Number(this.quantity || 1) + delta)); },
    };
}
