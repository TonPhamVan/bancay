<?php

namespace App\Services;

use App\Models\Inventory;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class InventoryService
{
    public function adjust(int $variantId, int $delta): Inventory
    {
        return DB::transaction(function () use ($variantId, $delta) {
            $inventory = Inventory::where('product_variant_id', $variantId)->lockForUpdate()->firstOrFail();
            if ($delta > 0 && $delta > 4294967295 - $inventory->quantity) {
                throw ValidationException::withMessages(['quantity' => 'Stock exceeds supported range.']);
            }
            $quantity = $inventory->quantity + $delta;
            if ($quantity < $inventory->reserved_quantity) {
                throw ValidationException::withMessages(['quantity' => 'Stock cannot be below reserved quantity.']);
            }
            $inventory->update(['quantity' => $quantity]);

            return $inventory;
        });
    }

    /** Caller holds the transaction and locks variants in ascending ID order. */
    public function reserve(int $variantId, int $quantity): void
    {
        if (DB::transactionLevel() < 1) {
            throw new \LogicException('Reservations require the enclosing order transaction.');
        }
        $inventory = Inventory::where('product_variant_id', $variantId)->lockForUpdate()->firstOrFail();
        if ($quantity <= 0 || $inventory->available() < $quantity) {
            throw ValidationException::withMessages(['items' => 'Insufficient stock.']);
        }
        $inventory->increment('reserved_quantity', $quantity);
    }

    public function releaseOrShip(int $variantId, int $quantity, bool $ship): void
    {
        if (DB::transactionLevel() < 1) {
            throw new \LogicException('Reservation changes require the enclosing order transaction.');
        }
        $inventory = Inventory::where('product_variant_id', $variantId)->lockForUpdate()->firstOrFail();
        if ($quantity <= 0 || $inventory->reserved_quantity < $quantity) {
            throw ValidationException::withMessages(['items' => 'Reservation no longer matches this order.']);
        }
        $inventory->update([
            'reserved_quantity' => $inventory->reserved_quantity - $quantity,
            'quantity' => $inventory->quantity - ($ship ? $quantity : 0),
        ]);
    }
}
