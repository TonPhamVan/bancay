<?php

namespace App\Services\Admin;

use App\Models\Customer;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

final class CustomerAddresses
{
    public function delete(Customer $customer, int $id): void
    {
        DB::transaction(function () use ($customer, $id) {
            $customer = Customer::lockForUpdate()->findOrFail($customer->id);
            $customer->addresses()->findOrFail($id)->delete();
        });
    }

    public function save(Customer $customer, array $input, ?int $id = null): void
    {
        $rules = ['recipient_name' => ['required', 'string', 'max:255'], 'phone' => ['required', 'string', 'max:30', 'regex:/\A[0-9+(). -]*\z/'],
            'line1' => ['required', 'string', 'max:255'], 'country_code' => ['required', 'string', 'size:2', 'regex:/\A[A-Z]{2}\z/'],
            'is_default' => ['required', 'boolean']];
        foreach (['line2', 'ward', 'district', 'province', 'postal_code', 'label'] as $field) {
            $rules[$field] = ['nullable', 'string', 'max:100'];
        }
        $rules['line2'] = ['nullable', 'string', 'max:255'];
        $rules['postal_code'] = ['nullable', 'string', 'max:20'];
        $data = Validator::make($input, $rules)->validate();
        DB::transaction(function () use ($customer, $data, $id) {
            $customer = Customer::lockForUpdate()->findOrFail($customer->id);
            $address = $id ? $customer->addresses()->findOrFail($id) : $customer->addresses()->make();
            if ($data['is_default']) {
                $customer->addresses()->where('is_default', true)->update(['is_default' => false]);
                if ($address->exists) {
                    $address->refresh();
                }
            }
            $address->fill($data)->save();
        });
    }
}
