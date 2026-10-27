<?php

namespace App\Actions\System;

use App\Models\Setting;
use App\Rules\PublicImagePath;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

final class SetPublicSetting
{
    public function execute(string $key, ?string $value): Setting
    {
        $rules = ['nullable', 'string', 'max:5000'];
        if ($key === 'logo') {
            $rules[] = new PublicImagePath;
        }
        if ($key === 'email') {
            $rules[] = 'not_regex:/[\r\n]/';
            $rules[] = 'email';
        }
        if (in_array($key, ['facebook', 'map'], true)) {
            $rules[] = 'url:http,https';
        }
        $data = Validator::make(compact('key', 'value'), [
            'key' => ['required', Rule::in(config('website.public_setting_keys'))],
            'value' => $rules,
        ])->validate();

        // Public settings are text, never executable analytics/editor HTML or secrets.
        return Setting::updateOrCreate(['key' => $data['key']], ['value' => $data['value'] === null ? null : strip_tags($data['value'])]);
    }
}
