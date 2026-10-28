<?php

namespace App\Services\Admin;

use App\Actions\System\SetPublicSetting;
use App\Models\Setting;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;

final class SettingsEditor
{
    public function save(array $input): void
    {
        $keys = config('website.public_setting_keys');
        $data = Validator::make($input, ['settings' => ['required', 'array:'.implode(',', array_diff($keys, ['logo']))],
            'settings.*' => ['nullable', 'string', 'max:5000'], 'logo_upload' => ['nullable', 'file']])->validate();
        $path = null;
        try {
            if (isset($data['logo_upload'])) {
                $path = app(ImageStorage::class)->store($data['logo_upload'], 'settings');
            }
            DB::transaction(function () use ($data, $path) {
                foreach ($data['settings'] as $key => $value) {
                    app(SetPublicSetting::class)->execute($key, $value);
                }
                if ($path) {
                    $old = Setting::where('key', 'logo')->value('value');
                    app(SetPublicSetting::class)->execute('logo', $path);
                    if ($old) {
                        DB::afterCommit(fn () => app(ImageStorage::class)->deleteIfUnused($old));
                    }
                }
            });
        } catch (\Throwable $e) {
            if ($path) {
                Storage::disk('public')->delete($path);
            } throw $e;
        }
    }
}
