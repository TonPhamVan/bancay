<?php

namespace App\Services\Release;

use App\Domain\Commerce\Money;
use App\Domain\Commerce\OrderStatus;

/** Read-only recovery ledger. Never applies writes to Laravel or CI3. Contains private order data. */
final class OrderDelta
{
    public const TABLES = ['orders', 'order_items', 'order_status_histories', 'inventories'];

    public function fingerprint(array $snapshot): string
    {
        return hash('sha256', json_encode($snapshot, JSON_THROW_ON_ERROR));
    }

    public function compare(array $baseline, array $window): array
    {
        if (empty($baseline['lineage']) || $baseline['lineage'] !== ($window['lineage'] ?? null)) {
            throw new \LogicException('Recovery snapshots must have the same verified lineage.');
        }
        foreach (['order_items', 'order_status_histories'] as $table) {
            $current = collect($window[$table])->keyBy('id');
            foreach ($baseline[$table] as $row) {
                if ($current->get($row['id']) !== $row) {
                    throw new \LogicException('Existing snapshot/history was changed or removed; retain raw backups for investigation.');
                }
            }
        }
        $previous = collect($baseline['orders'])->keyBy('id');
        $current = collect($window['orders'])->keyBy('id');
        if ($previous->keys()->diff($current->keys())->isNotEmpty()) {
            throw new \LogicException('An order was removed; retain raw backups for investigation.');
        }
        $ledger = [];
        foreach ($current as $id => $order) {
            $old = $previous->get($id);
            $histories = array_values(array_filter($window['order_status_histories'], fn ($r) => (int) $r['order_id'] === (int) $id));
            $oldHistories = array_values(array_filter($baseline['order_status_histories'], fn ($r) => (int) $r['order_id'] === (int) $id));
            if ($old === $order && $histories === $oldHistories) {
                continue;
            }
            if ($old) {
                // Status/updated_at may advance; commercial/contact snapshots must not be rewritten.
                // customer_id is intentionally excluded: its FK may become NULL without changing contact snapshots.
                foreach (['order_number', 'legacy_id', 'legacy_status', 'is_legacy', 'customer_name', 'customer_email',
                    'customer_phone', 'shipping_address', 'shipping_note', 'currency', 'subtotal',
                    'discount_amount', 'shipping_fee', 'total', 'created_at'] as $field) {
                    if (($old[$field] ?? null) !== ($order[$field] ?? null)) {
                        throw new \LogicException('Existing order snapshot changed; retain raw backups for investigation.');
                    }
                }
            }
            $items = array_values(array_filter($window['order_items'], fn ($r) => (int) $r['order_id'] === (int) $id));
            if (! $items || ! $histories) {
                throw new \LogicException('Recovery order needs items and history.');
            }
            $latest = collect($histories)->sortBy('id')->last();
            if (($latest['to_status'] ?? null) !== $order['status']) {
                throw new \LogicException('Recovery order status does not match its latest history.');
            }
            if (! $order['is_legacy']) {
                $previousStatus = $old['status'] ?? null;
                $knownHistories = array_fill_keys(array_column($oldHistories, 'id'), true);
                foreach (collect($histories)->sortBy('id') as $history) {
                    if (isset($knownHistories[$history['id']])) {
                        continue;
                    }
                    $next = OrderStatus::tryFrom($history['to_status'] ?? '');
                    $valid = $next !== null && ($history['from_status'] ?? null) === $previousStatus
                        && ($previousStatus === null ? $next === OrderStatus::New
                            : (OrderStatus::tryFrom($previousStatus)?->canTransitionTo($next) ?? false));
                    if (! $valid) {
                        throw new \LogicException('Invalid live workflow history in recovery window.');
                    }
                    $previousStatus = $next->value;
                }
                if ($previousStatus !== $order['status']) {
                    throw new \LogicException('Recovery status change has no matching appended history.');
                }
            }
            $subtotal = 0;
            foreach ($items as $item) {
                if ((int) $item['quantity'] < 1 || (int) $item['unit_price'] < 1 || empty($item['product_name'])
                    || (! $order['is_legacy'] && empty($item['sku']))
                    || Money::multiply((int) $item['unit_price'], (int) $item['quantity']) !== (int) $item['subtotal']) {
                    throw new \LogicException('Invalid recovery order snapshot.');
                }
                $subtotal = Money::add($subtotal, (int) $item['subtotal']);
            }
            if ($subtotal !== (int) $order['subtotal']
                || $subtotal - (int) $order['discount_amount'] + (int) $order['shipping_fee'] !== (int) $order['total']) {
                throw new \LogicException('Invalid recovery order total.');
            }
            // A stable reference survives subsequent exports/status updates; operators deduplicate by it.
            $reference = hash('sha256', $baseline['lineage'].'|'.$order['order_number']);
            $ledger[] = ['reference' => $reference, 'change' => $old ? 'UPDATED_EXISTING' : 'NEW_IN_WINDOW',
                'order' => $order, 'items' => $items, 'history' => $histories,
                'operator_action' => $order['is_legacy'] ? 'KEEP_IMPORTED_HISTORY_DO_NOT_FULFILL_OR_RESTOCK' : match ($order['status']) {
                    'CANCELLED' => 'KEEP_CANCELLED_DO_NOT_FULFILL',
                    'SHIPPING', 'COMPLETED', 'RETURNED' => 'RECONCILE_DISPATCH_AND_PHYSICAL_STOCK_DO_NOT_FULFILL_TWICE',
                    'NEW', 'CONFIRMED', 'PACKING' => 'REVIEW_AND_FULFILL_ONCE_USING_REFERENCE',
                    default => throw new \LogicException('Unknown recovery order status.'),
                }];
        }
        $stock = [];
        $beforeStock = collect($baseline['inventories'])->keyBy('product_variant_id');
        if ($beforeStock->keys()->diff(collect($window['inventories'])->pluck('product_variant_id'))->isNotEmpty()) {
            throw new \LogicException('An inventory row was removed; reconciliation is required.');
        }
        foreach ($window['inventories'] as $row) {
            $old = $beforeStock->get($row['product_variant_id']);
            if ($old === $row) {
                continue;
            }
            $stock[] = ['variant_id' => $row['product_variant_id'], 'before' => $old, 'after' => $row,
                'physical_delta' => $old ? (int) $row['quantity'] - (int) $old['quantity'] : null,
                'reserved_delta' => $old ? (int) $row['reserved_quantity'] - (int) $old['reserved_quantity'] : null,
                'operator_action' => 'RECONCILE_MANUALLY_NO_AUTOMATIC_CI3_RESTOCK'];
        }

        return ['format' => 1, 'lineage' => $baseline['lineage'], 'baseline_sha256' => $this->fingerprint($baseline),
            'window_sha256' => $this->fingerprint($window), 'orders' => $ledger, 'inventory' => $stock,
            'automatic_writes' => false, 'contains_private_customer_data' => true];
    }
}
