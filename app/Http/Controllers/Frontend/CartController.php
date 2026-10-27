<?php

namespace App\Http\Controllers\Frontend;

use App\Http\Controllers\Controller;
use App\Http\Requests\Storefront\CartRequest;
use App\Services\Storefront\Cart;
use App\Services\Storefront\Seo;
use Illuminate\Http\Request;

final class CartController extends Controller
{
    public function __construct(private readonly Cart $cart, private readonly Seo $seo) {}

    public function index(Request $request)
    {
        return view('cart.index', ['cart' => $this->cart->summary($request->session()), 'seo' => $this->seo->page('Giỏ hàng', '/gio-hang', null, true)]);
    }

    public function store(CartRequest $request)
    {
        $data = $request->validated();
        $this->cart->set($request->session(), (int) $data['product_id'], (int) $data['variant_id'], (int) $data['quantity'], true);

        return redirect()->route(($data['intent'] ?? 'cart') === 'buy_now' ? 'store.checkout.index' : 'store.cart.index')->with('store_notice', 'Đã thêm sản phẩm vào giỏ hàng.');
    }

    public function update(Request $request, string $variant)
    {
        abort_unless(ctype_digit($variant) && strlen($variant) <= 18 && (int) $variant > 0, 404);
        $data = $request->validate(['quantity' => ['required', 'integer', 'min:1', 'max:1000000'],
            'price' => ['prohibited'], 'unit_price' => ['prohibited'], 'discount' => ['prohibited'], 'subtotal' => ['prohibited'], 'total' => ['prohibited']]);
        $this->cart->update($request->session(), (int) $variant, (int) $data['quantity']);

        return redirect()->route('store.cart.index')->with('store_notice', 'Đã cập nhật giỏ hàng.');
    }

    public function destroy(Request $request, string $variant)
    {
        abort_unless(ctype_digit($variant) && strlen($variant) <= 18 && (int) $variant > 0, 404);
        $this->cart->remove($request->session(), (int) $variant);

        return redirect()->route('store.cart.index');
    }

    public function clear(Request $request)
    {
        $this->cart->clear($request->session());

        return redirect()->route('store.cart.index');
    }
}
