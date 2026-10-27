<?php

namespace App\Services\Admin;

use App\Models\Inventory;
use App\Services\InventoryService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

final class StockAdjustment
{
    public function execute(Inventory $inventory, array $input, int $actor): void
    {
        $data = Validator::make($input, ['quantity' => ['required', 'integer', 'min:0', 'max:4294967295'],
            'expected_quantity' => ['required', 'integer', 'min:0', 'max:4294967295'],
            'reason' => ['required', 'string', 'min:3', 'max:500']])->validate();
        DB::transaction(function () use ($inventory, $data, $actor) {
            $inventory = Inventory::lockForUpdate()->findOrFail($inventory->id);
            if ($inventory->quantity !== (int) $data['expected_quantity']) {
                throw ValidationException::withMessages(['quantity' => 'Tồn kho đã đổi. Tải lại trang trước khi điều chỉnh.']);
            }
            $before = $inventory->quantity;
            app(InventoryService::class)->adjust($inventory->product_variant_id, (int) $data['quantity'] - $before);
            DB::afterCommit(fn () => Log::info('store.inventory.adjusted', ['actor_id' => $actor, 'inventory_id' => $inventory->id,
                'before' => $before, 'after' => (int) $data['quantity'], 'reason' => $data['reason']]));
        });
    }
}
