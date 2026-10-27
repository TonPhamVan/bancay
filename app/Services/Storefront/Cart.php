<?php

namespace App\Services\Storefront;

use App\Domain\Commerce\Money;
use App\Models\ProductVariant;
use Illuminate\Contracts\Session\Session;
use Illuminate\Validation\ValidationException;

final class Cart
{
    public function __construct(private readonly Catalog $catalog, private readonly Presentation $display) {}

    public function summary(Session $session): array
    {
        $stored = $session->get('store.cart', []);
        $variants = ProductVariant::whereIn('id', array_keys($stored))->with(['product.images', 'inventory', 'optionValues.option'])->get()->keyBy('id');
        $visibleIds = $this->catalog->categories()->keys()->all();
        $lines = [];
        $total = 0;
        $valid = true;
        foreach ($stored as $id => $entry) {
            $variant = $variants->get($id);
            $error = null;
            $public = ! (! $variant || ! $variant->product || $variant->product->status->value !== 'PUBLISHED'
                || ! $variant->product->published_at || $variant->product->published_at->isFuture()
                || ! in_array($variant->product->category_id, $visibleIds)
                || (int) $entry['product_id'] !== (int) $variant->product_id || $variant->status->value !== 'ACTIVE'
                || $variant->effectivePrice() === null);
            if (! $public) {
                $error = 'Sản phẩm hoặc phân loại không còn khả dụng.';
            } elseif (! $variant->inventory || $variant->inventory->available() < $entry['quantity']) {
                $error = 'Tồn kho đã thay đổi. Hãy giảm số lượng hoặc xóa sản phẩm.';
            }
            $lineTotal = null;
            if (! $error) {
                try {
                    $lineTotal = Money::multiply($variant->effectivePrice(), (int) $entry['quantity']);
                    $total = Money::add($total, $lineTotal);
                } catch (\OverflowException) {
                    $error = 'Giá trị giỏ hàng vượt giới hạn hỗ trợ.';
                }
            }
            $valid = $valid && $error === null;
            $cover = $variant?->product?->images->firstWhere('is_primary', true) ?? $variant?->product?->images->first();
            $lines[] = ['variant_id' => (int) $id, 'product_id' => (int) $entry['product_id'], 'quantity' => (int) $entry['quantity'],
                'name' => $public ? $variant->product->name : 'Sản phẩm cần kiểm tra',
                'url' => $public ? route('store.products.show', $variant->product->slug, false) : null,
                'sku' => $public ? $variant->sku : '',
                'description' => $public ? $variant->optionValues->sortBy('product_option_id')->map(fn ($v) => $v->option->name.': '.$v->value)->join(' / ') : '',
                'image' => $public ? $this->display->image($cover?->path) : '/storefront/fallback.svg',
                'unit_price' => $public ? $variant->effectivePrice() : null, 'subtotal' => $lineTotal,
                'available' => $variant?->inventory?->available() ?? 0, 'error' => $error];
        }

        return ['lines' => $lines, 'subtotal' => $total, 'total' => $total, 'shipping_fee' => 0,
            'count' => array_sum(array_column($lines, 'quantity')), 'can_checkout' => $valid && count($lines) > 0];
    }

    public function set(Session $session, int $productId, int $variantId, int $quantity, bool $add = false): void
    {
        if ($quantity < 1 || $quantity > 1000000) {
            throw ValidationException::withMessages(['quantity' => 'Số lượng không hợp lệ.']);
        }
        $variant = ProductVariant::with('product')->find($variantId);
        if (! $variant || (int) $variant->product_id !== $productId) {
            throw ValidationException::withMessages(['variant_id' => 'Phân loại không thuộc sản phẩm.']);
        }
        $stored = $session->get('store.cart', []);
        if (! isset($stored[$variantId]) && count($stored) >= 50) {
            throw ValidationException::withMessages(['cart' => 'Giỏ hàng tối đa 50 phân loại.']);
        }
        $desired = $quantity + ($add ? ($stored[$variantId]['quantity'] ?? 0) : 0);
        if ($desired > 1000000) {
            throw ValidationException::withMessages(['quantity' => 'Số lượng vượt giới hạn.']);
        }
        $stored[$variantId] = ['product_id' => $variant->product_id, 'quantity' => $desired];
        // Validate the changed line against current catalog/price/reservations before persisting the session.
        $original = $session->get('store.cart', []);
        $session->put('store.cart', [$variantId => $stored[$variantId]]);
        try {
            $summary = $this->summary($session);
        } finally {
            $session->put('store.cart', $original);
        }
        if (! $summary['can_checkout']) {
            throw ValidationException::withMessages(['quantity' => $summary['lines'][0]['error']]);
        }
        $session->put('store.cart', $stored);
    }

    public function update(Session $session, int $variantId, int $quantity): void
    {
        $entry = $session->get('store.cart.'.$variantId);
        if (! $entry) {
            throw ValidationException::withMessages(['cart' => 'Sản phẩm không có trong giỏ hàng.']);
        }
        $this->set($session, (int) $entry['product_id'], $variantId, $quantity);
    }

    public function remove(Session $session, int $variantId): void
    {
        $session->forget('store.cart.'.$variantId);
    }

    public function clear(Session $session): void
    {
        $session->forget('store.cart');
    }

    public function fingerprint(array $summary): string
    {
        $lines = array_map(fn ($line) => [$line['variant_id'], $line['product_id'], $line['quantity'], $line['unit_price']], $summary['lines']);
        usort($lines, fn ($a, $b) => $a[0] <=> $b[0]);

        return hash('sha256', json_encode($lines, JSON_THROW_ON_ERROR));
    }
}
