<?php

namespace App\Http\Requests;

use App\Actions\Commerce\CreateOrder;
use Illuminate\Foundation\Http\FormRequest;

final class StoreOrderRequest extends FormRequest
{
    public function authorize(): bool
    {
        // Guest purchases are allowed; no order endpoint is exposed in Phase 1.
        return true;
    }

    public function rules(): array
    {
        return CreateOrder::rules();
    }
}
