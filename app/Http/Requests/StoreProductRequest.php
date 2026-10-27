<?php

namespace App\Http\Requests;

use App\Actions\Catalog\CreateProduct;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;

final class StoreProductRequest extends FormRequest
{
    public function authorize(): bool
    {
        return Gate::allows('catalog.manage');
    }

    protected function prepareForValidation(): void
    {
        if (! $this->filled('slug')) {
            $this->merge(['slug' => Str::slug((string) $this->input('name', ''))]);
        }
    }

    public function rules(): array
    {
        return CreateProduct::rules();
    }
}
