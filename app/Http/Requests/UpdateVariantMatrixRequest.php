<?php

namespace App\Http\Requests;

use App\Actions\Catalog\UpdateVariantMatrix;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;

final class UpdateVariantMatrixRequest extends FormRequest
{
    public function authorize(): bool
    {
        return Gate::allows('catalog.manage');
    }

    public function rules(): array
    {
        return UpdateVariantMatrix::rules();
    }
}
