import test from 'node:test';
import assert from 'node:assert/strict';
import {selectedVariant, possibleChoice, picker} from '../../resources/js/product-picker.js';

const options = [{id: '9007199254740993'}, {id: '2'}];
const variants = [
    {id: '9007199254740995', values: {'9007199254740993': 'a', '2': 'x'}, available: 5, contact: false, image: '/a.webp'},
    {id: '9007199254740996', values: {'9007199254740993': 'b', '2': 'y'}, available: 0, contact: false},
    {id: '3', values: {'9007199254740993': 'b', '2': 'z'}, available: 20, contact: true},
];
test('selects the exact complete combination without rounding BIGINT identifiers', () => {
    assert.equal(selectedVariant(options, variants, {'9007199254740993': 'a', '2': 'x'}).id, '9007199254740995');
    assert.equal(selectedVariant(options, variants, {'2': 'x'}), null);
    assert.equal(selectedVariant(options, variants, {'9007199254740993': 'a', '2': 'y'}), null);
});
test('choice availability respects partial selections and nonexistent values', () => {
    assert.equal(possibleChoice(options, variants, {'2': 'x'}, '9007199254740993', 'b'), false);
    assert.equal(possibleChoice(options, variants, {}, '9007199254740993', 'b'), true);
    assert.equal(possibleChoice(options, variants, {}, '2', 'never'), false);
});
test('disconnected combinations remain reachable and stock/contact cannot enable purchase', () => {
    const state = picker({options, variants, image: '/fallback.webp'});
    assert.equal(state.variant.id, variants[0].id);
    state.choose('9007199254740993', 'b');
    assert.equal(state.variant, null);
    state.choose('2', 'y');
    assert.equal(state.variant.id, variants[1].id);
    assert.equal(state.canBuy, false);
    state.choose('2', 'z');
    assert.equal(state.variant.contact, true);
    assert.equal(state.canBuy, false);
});
test('quantity controls clamp to stock and keep positive quantities', () => {
    const state = picker({options, variants, image: '/fallback.webp'});
    state.step(-100); assert.equal(state.quantity, 1);
    state.step(99); assert.equal(state.quantity, 5);
    assert.equal(state.canBuy, true);
    state.quantity = 6; assert.equal(state.canBuy, false);
});
test('simple products and products without active variants render safely', () => {
    assert.equal(picker({options: [], variants: [variants[0]]}).variant.id, variants[0].id);
    assert.equal(picker({options, variants: []}).canBuy, null);
});
