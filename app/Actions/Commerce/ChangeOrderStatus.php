<?php

namespace App\Actions\Commerce;

use App\Domain\Commerce\OrderStatus;
use App\Models\Order;
use App\Services\InventoryService;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class ChangeOrderStatus
{
    public function __construct(private readonly InventoryService $inventory) {}

    public function execute(Order $order, OrderStatus $next, ?int $actorId = null, ?string $note = null): Order
    {
        if ($note !== null && mb_strlen($note) > 5000) {
            throw ValidationException::withMessages(['note' => 'Note is too long.']);
        }

        return DB::transaction(function () use ($order, $next, $actorId, $note) {
            $order = Order::query()->lockForUpdate()->findOrFail($order->id);
            if ($order->is_legacy || ! $order->status->canTransitionTo($next)) {
                throw ValidationException::withMessages(['status' => 'Invalid transition or immutable imported history.']);
            }
            $from = $order->status;
            if (in_array($next, [OrderStatus::Cancelled, OrderStatus::Shipping], true)) {
                foreach ($order->items()->orderBy('product_variant_id')->get() as $item) {
                    if (! $item->product_variant_id) {
                        throw ValidationException::withMessages(['items' => 'Referenced stock no longer exists.']);
                    }
                    $this->inventory->releaseOrShip($item->product_variant_id, $item->quantity, $next === OrderStatus::Shipping);
                }
            }
            // RETURNED records the workflow only; restock requires physical inspection later.
            $order->update(['status' => $next]);
            $order->statusHistories()->create([
                'from_status' => $from, 'to_status' => $next, 'changed_by' => $actorId, 'note' => $note,
            ]);

            return $order->load('items', 'statusHistories');
        });
    }
}
