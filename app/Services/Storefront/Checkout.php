<?php

namespace App\Services\Storefront;

use App\Actions\Commerce\CreateOrder;
use App\Models\CheckoutAttempt;
use App\Models\Order;
use App\Models\ProductVariant;
use Illuminate\Contracts\Session\Session;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class Checkout
{
    public function __construct(private readonly Cart $cart, private readonly CreateOrder $orders) {}

    public function prepare(Session $session, array $summary): string
    {
        if (! $summary['can_checkout']) {
            throw ValidationException::withMessages(['cart' => 'Vui lòng cập nhật giỏ hàng trước khi thanh toán.']);
        }
        $token = $session->get('store.checkout_token');
        $attempt = $token ? CheckoutAttempt::where('token_hash', hash('sha256', $token))->first() : null;
        $quote = $this->cart->fingerprint($summary);
        $owner = hash('sha256', $session->getId());
        if (! $attempt || $attempt->order_id || $attempt->quote_hash !== $quote || $attempt->owner_hash !== $owner || $attempt->created_at->lt(now()->subMinutes(config('storefront.checkout_token_minutes')))) {
            $token = bin2hex(random_bytes(32));
            CheckoutAttempt::create(['token_hash' => hash('sha256', $token), 'owner_hash' => $owner, 'quote_hash' => $quote]);
            $session->put('store.checkout_token', $token);
        }

        return $token;
    }

    public function place(Session $session, array $input): Order
    {
        $token = $input['checkout_token'];
        unset($input['checkout_token']);
        [$order, $quote] = DB::transaction(function () use ($session, $input, $token) {
            $attempt = CheckoutAttempt::where('token_hash', hash('sha256', $token))->lockForUpdate()->first();
            if (! $attempt || ! hash_equals($attempt->owner_hash, hash('sha256', $session->getId()))) {
                throw ValidationException::withMessages(['checkout_token' => 'Phiên thanh toán không hợp lệ. Vui lòng mở lại trang thanh toán.']);
            }
            if ($attempt->order_id) {
                return [Order::with('items', 'statusHistories')->findOrFail($attempt->order_id), $attempt->quote_hash];
            }
            // Hold the exact variants while comparing the quote and while CreateOrder snapshots them.
            // This closes the price-update window between quote validation and the existing order Action.
            ProductVariant::whereIn('id', array_keys($session->get('store.cart', [])))->orderBy('id')->lockForUpdate()->get();
            $summary = $this->cart->summary($session);
            if ($attempt->created_at->lt(now()->subMinutes(config('storefront.checkout_token_minutes'))) || ! $summary['can_checkout']
                || ! hash_equals($attempt->quote_hash, $this->cart->fingerprint($summary))) {
                throw ValidationException::withMessages(['cart' => 'Giỏ hàng, giá hoặc tồn kho đã thay đổi. Vui lòng kiểm tra lại trước khi đặt hàng.']);
            }
            $input['items'] = array_map(fn ($line) => ['product_variant_id' => $line['variant_id'], 'quantity' => $line['quantity']], $summary['lines']);
            $order = $this->orders->execute($input, storefront: true);
            $attempt->update(['order_id' => $order->id]);

            return [$order, $attempt->quote_hash];
        });
        // Grant ownership only after the order/attempt commit. Replay can repair a lost response/session save.
        $grants = $session->get('store.orders', []);
        $grants[$order->order_number] = $order->id;
        $session->put('store.orders', array_slice($grants, -20, null, true));
        if ($session->get('store.checkout_token') === $token
            && hash_equals($quote, $this->cart->fingerprint($this->cart->summary($session)))) {
            $this->cart->clear($session);
            $session->forget('store.checkout_token');
        }

        return $order;
    }

    public function ownedOrder(Session $session, string $number): Order
    {
        $id = $session->get('store.orders.'.$number);
        abort_unless($id, 404);

        return Order::whereKey($id)->where('order_number', $number)->with('items')->firstOrFail();
    }
}
