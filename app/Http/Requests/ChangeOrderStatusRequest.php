<?php

namespace App\Http\Requests;

use App\Domain\Commerce\OrderStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

final class ChangeOrderStatusRequest extends FormRequest
{
    public function authorize(): bool
    {
        return Gate::allows('commerce.manage');
    }

    public function rules(): array
    {
        return ['status' => ['required', Rule::enum(OrderStatus::class)], 'note' => ['nullable', 'string', 'max:5000']];
    }
}
