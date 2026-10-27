<?php

namespace App\Http\Requests\Storefront;

use App\Actions\Commerce\CreateOrder;
use Illuminate\Foundation\Http\FormRequest;

final class CheckoutRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $rules = CreateOrder::rules();
        foreach (array_keys($rules) as $key) {
            if ($key === 'items' || str_starts_with($key, 'items.')) {
                unset($rules[$key]);
            }
        }

        return $rules + ['checkout_token' => ['required', 'string', 'size:64', 'regex:/\A[0-9a-f]{64}\z/'],
            'items' => ['prohibited'], 'price' => ['prohibited'], 'unit_price' => ['prohibited'], 'variant_id' => ['prohibited']];
    }

    public function attributes(): array
    {
        return ['customer_name' => 'họ tên', 'customer_phone' => 'điện thoại', 'customer_email' => 'email',
            'shipping_address.line1' => 'địa chỉ', 'shipping_note' => 'ghi chú'];
    }

    public function messages(): array
    {
        return ['required' => 'Vui lòng nhập :attribute.', 'string' => ':attribute phải là văn bản.',
            'max' => ':attribute vượt quá độ dài cho phép.', 'prohibited' => 'Không được gửi trường :attribute từ trình duyệt.',
            'customer_phone.regex' => 'Số điện thoại không hợp lệ.', 'customer_email.email' => 'Địa chỉ email không hợp lệ.',
            'customer_email.not_regex' => 'Email không được chứa ký tự xuống dòng.',
            'shipping_address.array' => 'Thông tin địa chỉ không hợp lệ.',
            'checkout_token.regex' => 'Phiên thanh toán không hợp lệ. Vui lòng mở lại trang thanh toán.',
            'checkout_token.size' => 'Phiên thanh toán không hợp lệ. Vui lòng mở lại trang thanh toán.'];
    }
}
