<?php

namespace App\Admin;

use BackedEnum;
use Illuminate\Support\Facades\Validator;

/** Shop vocabulary only; native Exment locale and stored enum values stay intact. */
final class StoreLabels
{
    public static function text(mixed $value): string
    {
        return is_scalar($value) ? (string) $value : '';
    }

    public static function lines(mixed $value): string
    {
        return is_array($value) ? implode("\n", array_filter($value, 'is_scalar')) : (is_scalar($value) ? (string) $value : '');
    }

    public static function status(BackedEnum|string|null $status): string
    {
        $value = $status instanceof BackedEnum ? $status->value : $status;

        return ['DRAFT' => 'Nháp', 'PUBLISHED' => 'Công khai', 'ARCHIVED' => 'Lưu trữ',
            'ACTIVE' => 'Hoạt động', 'INACTIVE' => 'Tạm ngưng', 'NEW' => 'Mới', 'CONFIRMED' => 'Đã xác nhận',
            'PACKING' => 'Đang chuẩn bị', 'SHIPPING' => 'Đang giao', 'COMPLETED' => 'Hoàn thành',
            'CANCELLED' => 'Đã hủy', 'RETURNED' => 'Đã trả hàng'][$value] ?? ($value ?? '—');
    }

    public static function validate(array $input, array $rules, array $attributes = []): array
    {
        return Validator::make($input, $rules, [
            'required' => ':attribute là bắt buộc.', 'required_if' => ':attribute là bắt buộc với lựa chọn hiện tại.',
            'accepted' => 'Vui lòng xác nhận :attribute.', 'distinct' => ':attribute bị trùng; mỗi dòng cần một giá trị khác nhau.',
            'unique' => ':attribute đã được sử dụng.', 'integer' => ':attribute phải là số nguyên.',
            'min' => ['numeric' => ':attribute phải từ :min trở lên.', 'string' => ':attribute cần ít nhất :min ký tự.',
                'array' => ':attribute cần ít nhất :min mục.', 'file' => ':attribute cần ít nhất :min KB.'],
            'max' => ['numeric' => ':attribute không được vượt quá :max.', 'string' => ':attribute không được dài hơn :max ký tự.',
                'array' => ':attribute tối đa :max mục.', 'file' => ':attribute tối đa :max KB.'],
            'array' => ':attribute phải là danh sách.',
            'exists' => ':attribute không còn hợp lệ. Vui lòng chọn lại.', 'date' => ':attribute phải là ngày hợp lệ.',
            'enum' => ':attribute không hợp lệ.', 'string' => ':attribute phải là văn bản.',
        ], $attributes)->validate();
    }
}
