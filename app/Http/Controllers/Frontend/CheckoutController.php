<?php

namespace App\Http\Controllers\Frontend;

use App\Http\Controllers\Controller;
use App\Http\Requests\Storefront\CheckoutRequest;
use App\Services\Storefront\Cart;
use App\Services\Storefront\Checkout;
use App\Services\Storefront\Seo;
use Illuminate\Http\Request;

final class CheckoutController extends Controller
{
    public function __construct(private readonly Cart $cart, private readonly Checkout $checkout, private readonly Seo $seo) {}

    public function index(Request $request)
    {
        $cart = $this->cart->summary($request->session());
        if (! $cart['can_checkout']) {
            return redirect()->route('store.cart.index')->withErrors(['cart' => 'Vui lòng kiểm tra giỏ hàng trước khi thanh toán.']);
        }
        $token = $this->checkout->prepare($request->session(), $cart);

        return view('checkout.index', compact('cart', 'token') + ['seo' => $this->seo->page('Thanh toán', '/thanh-toan', null, true)]);
    }

    public function store(CheckoutRequest $request)
    {
        $order = $this->checkout->place($request->session(), $request->validated());

        return redirect()->route('store.orders.show', $order->order_number);
    }

    public function success(Request $request, string $number)
    {
        return view('checkout.success', ['order' => $this->checkout->ownedOrder($request->session(), $number),
            'seo' => $this->seo->page('Đã nhận đơn hàng', route('store.orders.show', $number, false), null, true)]);
    }
}
