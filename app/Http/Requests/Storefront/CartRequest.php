<?php

namespace App\Http\Requests\Storefront;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class CartRequest extends FormRequest
{
    public function messages(): array
    {
        return ['required' => 'Vui lòng chọn :attribute.', 'integer' => ':attribute phải là số nguyên.',
            'quantity.min' => 'Số lượng phải lớn hơn 0.', 'quantity.max' => 'Số lượng vượt giới hạn hỗ trợ.',
            'prohibited' => 'Không được gửi trường :attribute từ trình duyệt.'];
    }

    public function attributes(): array
    {
        return ['product_id' => 'sản phẩm', 'variant_id' => 'phân loại', 'quantity' => 'số lượng'];
    }

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return ['product_id' => ['required', 'integer', 'min:1'], 'variant_id' => ['required', 'integer', 'min:1'],
            'quantity' => ['required', 'integer', 'min:1', 'max:1000000'], 'intent' => ['sometimes', Rule::in(['cart', 'buy_now'])],
            'price' => ['prohibited'], 'unit_price' => ['prohibited'], 'discount' => ['prohibited'], 'subtotal' => ['prohibited'], 'total' => ['prohibited']];
    }
}
