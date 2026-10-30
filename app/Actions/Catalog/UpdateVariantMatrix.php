<?php

namespace App\Actions\Catalog;

use App\Models\Inventory;
use App\Models\Product;
use App\Services\Admin\CatalogEditor;
use App\Services\Admin\StockAdjustment;
use App\Services\Admin\VariantMatrix;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

final class UpdateVariantMatrix
{
    public static function rules(): array
    {
        return [
            'variants' => ['required', 'array', 'list', 'min:1', 'max:256'],
            'variants.*' => ['required', 'array:id,version,sku,pricing_type,price,sale_price,image_id,status,quantity,expected_quantity'],
            'variants.*.id' => ['required', 'integer', 'min:1', 'distinct'],
            'variants.*.version' => ['required', 'string', 'regex:/\A[a-f0-9]{64}\z/'],
            'variants.*.sku' => ['required', 'string', 'max:100', 'regex:/\A[A-Za-z0-9][A-Za-z0-9._-]*\z/', 'distinct:ignore_case'],
            'variants.*.pricing_type' => ['required', Rule::in(['FIXED', 'CONTACT'])],
            'variants.*.price' => ['present', 'nullable', 'integer', 'min:1', 'max:'.PHP_INT_MAX],
            'variants.*.sale_price' => ['present', 'nullable', 'integer', 'min:1', 'max:'.PHP_INT_MAX],
            'variants.*.image_id' => ['present', 'nullable', 'integer', 'min:1'],
            'variants.*.status' => ['required', Rule::in(['ACTIVE', 'INACTIVE'])],
            'variants.*.quantity' => ['required', 'integer', 'min:0', 'max:4294967295'],
            'variants.*.expected_quantity' => ['required', 'integer', 'min:0', 'max:4294967295'],
            'stock_reason' => ['nullable', 'string', 'min:3', 'max:500'],
        ];
    }

    public function execute(Product $product, array $input, int $actor): int
    {
        $data = Validator::make($input, self::rules())->validate();

        return DB::transaction(function () use ($product, $data, $actor) {
            $product = Product::lockForUpdate()->findOrFail($product->id);
            $ids = array_column($data['variants'], 'id');
            // Match checkout's variant -> inventory locking order, consistently by ID.
            $variants = $product->variants()->whereKey($ids)->orderBy('id')->lockForUpdate()->get()->keyBy('id');
            abort_unless($variants->count() === count($ids), 404);
            $inventories = Inventory::whereIn('product_variant_id', $ids)->orderBy('product_variant_id')->lockForUpdate()->get()->keyBy('product_variant_id');
            foreach ($data['variants'] as $index => $row) {
                $variant = $variants->get($row['id']);
                $variant->setRelation('inventory', $inventories->get($variant->id));
                if (! $variant->inventory || ! hash_equals(app(VariantMatrix::class)->version($variant), $row['version'])
                    || $variant->inventory->quantity !== (int) $row['expected_quantity']) {
                    throw ValidationException::withMessages(["variants.$index.version" => 'SKU hoặc tồn kho đã đổi. Tải lại trang để kiểm tra trước khi lưu.']);
                }
                if ((int) $row['quantity'] !== (int) $row['expected_quantity'] && empty($data['stock_reason'])) {
                    throw ValidationException::withMessages(['stock_reason' => 'Nhập lý do khi điều chỉnh tồn kho.']);
                }
            }
            foreach ($data['variants'] as $index => $row) {
                try {
                    app(CatalogEditor::class)->updateVariant($product, (int) $row['id'], collect($row)->only([
                        'sku', 'pricing_type', 'price', 'sale_price', 'image_id', 'status',
                    ])->all());
                    if ((int) $row['quantity'] !== (int) $row['expected_quantity']) {
                        app(StockAdjustment::class)->execute($inventories->get($row['id']), [
                            'quantity' => $row['quantity'], 'expected_quantity' => $row['expected_quantity'],
                            'reason' => $data['stock_reason'],
                        ], $actor);
                    }
                } catch (ValidationException $exception) {
                    throw ValidationException::withMessages(collect($exception->errors())
                        ->mapWithKeys(fn ($messages, $field) => ["variants.$index.$field" => $messages])->all());
                }
            }

            return count($data['variants']);
        });
    }
}
