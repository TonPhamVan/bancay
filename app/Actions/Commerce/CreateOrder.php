<?php

namespace App\Actions\Commerce;

use App\Domain\Catalog\PublicationStatus;
use App\Domain\Catalog\RecordStatus;
use App\Domain\Commerce\Money;
use App\Domain\Commerce\OrderStatus;
use App\Models\Customer;
use App\Models\Order;
use App\Models\ProductVariant;
use App\Services\InventoryService;
use App\Services\Storefront\Publication;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

final class CreateOrder
{
    public function __construct(private readonly InventoryService $inventory) {}

    public static function rules(): array
    {
        return [
            'customer_id' => ['prohibited'],
            'customer_name' => ['required', 'string', 'max:255'],
            'customer_email' => ['nullable', 'string', 'not_regex:/[\r\n]/', 'email', 'max:255'],
            'customer_phone' => ['required', 'string', 'regex:/^[+0-9][0-9 .()-]{6,39}$/'],
            'shipping_address' => ['required', 'array:line1,line2,ward,district,province,country_code,postal_code'],
            'shipping_address.line1' => ['required', 'string', 'max:255'],
            'shipping_address.line2' => ['nullable', 'string', 'max:255'],
            'shipping_address.ward' => ['nullable', 'string', 'max:100'],
            'shipping_address.district' => ['nullable', 'string', 'max:100'],
            'shipping_address.province' => ['nullable', 'string', 'max:100'],
            'shipping_address.country_code' => ['required', 'string', 'size:2', 'regex:/^[A-Z]{2}$/'],
            'shipping_address.postal_code' => ['nullable', 'string', 'max:20'],
            'shipping_note' => ['nullable', 'string', 'max:5000'],
            'items' => ['required', 'array', 'min:1', 'max:100'],
            'items.*' => ['array:product_variant_id,quantity'],
            'items.*.product_variant_id' => ['required', 'integer', 'distinct'],
            'items.*.quantity' => ['required', 'integer', 'min:1', 'max:1000000'],
            'status' => ['prohibited'], 'subtotal' => ['prohibited'], 'total' => ['prohibited'],
            'shipping_fee' => ['prohibited'], 'discount_amount' => ['prohibited'], 'is_legacy' => ['prohibited'],
        ];
    }

    public function execute(array $input, ?int $actorId = null, ?Customer $customer = null, bool $storefront = false): Order
    {
        $data = Validator::make($input, self::rules())->validate();

        return DB::transaction(function () use ($data, $actorId, $customer, $storefront) {
            $visibleCategories = $storefront ? app(Publication::class)->categories()->keys()->all() : [];
            $lines = collect($data['items'])->sortBy('product_variant_id');
            $snapshots = [];
            $subtotal = 0;
            foreach ($lines as $line) {
                $variant = ProductVariant::with(['product', 'optionValues.option'])
                    ->lockForUpdate()->find($line['product_variant_id']);
                if (! $variant || $variant->status !== RecordStatus::Active
                    || ! $variant->product || $variant->product->status !== PublicationStatus::Published
                    || ! $variant->product->published_at || $variant->product->published_at->isFuture()
                    || ($storefront && ! in_array($variant->product->category_id, $visibleCategories))
                    || $variant->effectivePrice() === null) {
                    throw ValidationException::withMessages(['items' => 'Variant is not available for ordering.']);
                }
                $price = $variant->effectivePrice();
                try {
                    $lineTotal = Money::multiply($price, (int) $line['quantity']);
                    $subtotal = Money::add($subtotal, $lineTotal);
                } catch (\OverflowException $exception) {
                    throw ValidationException::withMessages(['items' => $exception->getMessage()]);
                }
                $this->inventory->reserve($variant->id, (int) $line['quantity']);
                $snapshots[] = [
                    'product_id' => $variant->product_id, 'product_variant_id' => $variant->id,
                    'product_name' => $variant->product->name, 'sku' => $variant->sku,
                    'variant_description' => $variant->optionValues->sortBy('product_option_id')
                        ->map(fn ($value) => $value->option->name.': '.$value->value)->implode(' / '),
                    'unit_price' => $price, 'quantity' => $line['quantity'], 'subtotal' => $lineTotal,
                ];
            }
            unset($data['items']);
            $order = Order::create(array_merge($data, [
                'order_number' => 'BC-'.Str::ulid(), 'currency' => 'VND', 'status' => OrderStatus::New,
                'subtotal' => $subtotal, 'discount_amount' => 0, 'shipping_fee' => 0, 'total' => $subtotal,
                'is_legacy' => false,
                'customer_id' => $customer?->getKey(),
            ]));
            $order->items()->createMany($snapshots);
            $order->statusHistories()->create([
                'from_status' => null, 'to_status' => OrderStatus::New, 'changed_by' => $actorId,
                'note' => 'Order created with server-calculated snapshots.',
            ]);

            return $order->load('items', 'statusHistories');
        });
    }
}
