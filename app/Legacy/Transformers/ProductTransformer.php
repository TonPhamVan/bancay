<?php

namespace App\Legacy\Transformers;

use App\Legacy\MigrationFailure;

final class ProductTransformer
{
    public function variants(array $row): array
    {
        if (! trim((string) ($row['phanloai'] ?? ''))) {
            $price = Values::integer($row['giaban']);
            $regular = Values::integer($row['giale']);

            return ['options' => [], 'variants' => [[
                'index' => null, 'original_sku' => (string) ($row['ma'] ?? ''), 'selection' => [],
                'pricing_type' => $price === 0 ? 'CONTACT' : 'FIXED',
                'price' => $price === 0 ? null : max($regular, $price),
                'sale_price' => $price > 0 && $regular > $price ? $price : null,
                'quantity' => Values::integer($row['sl'], 4294967295), 'image' => null,
            ]]];
        }
        try {
            $data = json_decode($row['phanloai'], true, 64, JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            throw new MigrationFailure('INVALID_VARIANT_JSON');
        }
        $groups = $data['pl'] ?? [];
        if (! is_array($groups) || ! array_is_list($groups) || count($groups) < 1 || count($groups) > 2) {
            throw new MigrationFailure('INVALID_VARIANT_GROUPS');
        }
        $columns = ['pl1', 'pl2', 'img', 'price_pl', 'sale_pl', 'ma_pl', 'sl_pl'];
        $count = null;
        foreach ($columns as $column) {
            if (! isset($data[$column]) || ! is_array($data[$column]) || ! array_is_list($data[$column])) {
                throw new MigrationFailure('INVALID_VARIANT_JSON');
            }
            $count ??= count($data[$column]);
            if (count($data[$column]) !== $count) {
                throw new MigrationFailure('VARIANT_ARRAY_LENGTH_MISMATCH');
            }
        }
        if ($count < 1 || $count > 256) {
            throw new MigrationFailure('INVALID_VARIANT_COUNT');
        }
        $options = [];
        $seenNames = [];
        foreach ($groups as $i => $name) {
            $name = Values::text($name, 100, true);
            $key = mb_strtolower(trim($name));
            if (isset($seenNames[$key])) {
                throw new MigrationFailure('DUPLICATE_OPTION_NAME');
            }
            $seenNames[$key] = true;
            $values = [];
            $normalized = [];
            foreach ($data['pl'.($i + 1)] as $value) {
                $value = Values::text($value, 100, true);
                $folded = mb_strtolower(trim($value));
                if (isset($normalized[$folded]) && $normalized[$folded] !== $value) {
                    throw new MigrationFailure('OPTION_VALUE_COLLISION');
                }
                $normalized[$folded] = $value;
                $values[$value] = $value;
            }
            $options[] = ['name' => $name, 'values' => array_values($values)];
        }
        $variants = [];
        $combinations = [];
        for ($i = 0; $i < $count; $i++) {
            if (count($groups) === 1 && (string) $data['pl2'][$i] !== '') {
                throw new MigrationFailure('EXTRA_VARIANT_VALUE');
            }
            $selection = array_map(fn ($g) => $data['pl'.($g + 1)][$i], array_keys($groups));
            $combo = json_encode($selection, JSON_THROW_ON_ERROR);
            if (isset($combinations[$combo])) {
                throw new MigrationFailure('DUPLICATE_COMBINATION');
            }
            $combinations[$combo] = true;
            $price = Values::integer($data['price_pl'][$i], grouped: true);
            $sale = trim((string) $data['sale_pl'][$i]) === '' ? null : Values::integer($data['sale_pl'][$i], grouped: true);
            if ($price < 1 || ($sale !== null && ($sale < 1 || $sale > $price))) {
                throw new MigrationFailure('INVALID_VARIANT_PRICE');
            }
            $variants[] = ['index' => $i, 'original_sku' => Values::text($data['ma_pl'][$i], 191), 'selection' => $selection,
                'pricing_type' => 'FIXED', 'price' => $price, 'sale_price' => $sale,
                'quantity' => Values::integer($data['sl_pl'][$i], 4294967295), 'image' => Values::text($data['img'][$i], 500)];
        }

        return compact('options', 'variants');
    }

    public function gallery(array $row): array
    {
        if (trim((string) ($row['image'] ?? '')) === '') {
            return [];
        }
        try {
            $images = json_decode($row['image'], true, 64, JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            throw new MigrationFailure('INVALID_GALLERY_JSON');
        }
        if (! is_array($images) || ! array_is_list($images) || count($images) > 100) {
            throw new MigrationFailure('INVALID_GALLERY_JSON');
        }
        foreach ($images as $image) {
            Values::text($image, 500, true);
        }

        return $images;
    }
}
