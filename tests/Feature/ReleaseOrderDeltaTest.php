<?php

namespace Tests\Feature;

use App\Services\Release\OrderDelta;
use Tests\TestCase;

final class ReleaseOrderDeltaTest extends TestCase
{
    private function snapshot(): array
    {
        return ['lineage' => 'synthetic-verified-clone', 'orders' => [], 'order_items' => [], 'order_status_histories' => [],
            'inventories' => [['id' => 1, 'product_variant_id' => 1, 'quantity' => 10, 'reserved_quantity' => 0]]];
    }

    private function withOrder(array $snapshot): array
    {
        $snapshot['orders'][] = ['id' => 1, 'order_number' => 'SYNTHETIC-1', 'is_legacy' => 0, 'status' => 'NEW', 'subtotal' => 200000, 'discount_amount' => 0, 'shipping_fee' => 0, 'total' => 200000];
        $snapshot['order_items'][] = ['id' => 1, 'order_id' => 1, 'sku' => 'SYNTHETIC-SKU', 'product_name' => 'Synthetic plant', 'unit_price' => 100000, 'quantity' => 2, 'subtotal' => 200000];
        $snapshot['order_status_histories'][] = ['id' => 1, 'order_id' => 1, 'from_status' => null, 'to_status' => 'NEW'];
        $snapshot['inventories'][0]['reserved_quantity'] = 2;

        return $snapshot;
    }

    public function test_new_window_order_ledger_preserves_snapshot_stock_and_is_deterministic(): void
    {
        $service = new OrderDelta;
        $baseline = $this->snapshot();
        $window = $this->withOrder($baseline);
        $first = $service->compare($baseline, $window);
        $this->assertSame($first, $service->compare($baseline, $window));
        $this->assertSame(100000, $first['orders'][0]['items'][0]['unit_price']);
        $this->assertSame('NEW_IN_WINDOW', $first['orders'][0]['change']);
        $this->assertSame(0, $first['inventory'][0]['physical_delta']);
        $this->assertSame(2, $first['inventory'][0]['reserved_delta']);
        $this->assertFalse($first['automatic_writes']);
    }

    public function test_status_delta_includes_preexisting_order_and_keeps_stable_recovery_reference(): void
    {
        $service = new OrderDelta;
        $baseline = $this->withOrder($this->snapshot());
        $window = $baseline;
        $window['orders'][0]['status'] = 'CANCELLED';
        $window['inventories'][0]['reserved_quantity'] = 0;
        $window['order_status_histories'][] = ['id' => 2, 'order_id' => 1, 'from_status' => 'NEW', 'to_status' => 'CANCELLED'];
        $delta = $service->compare($baseline, $window);
        $this->assertSame('UPDATED_EXISTING', $delta['orders'][0]['change']);
        $this->assertSame('KEEP_CANCELLED_DO_NOT_FULFILL', $delta['orders'][0]['operator_action']);
        $this->assertSame($service->compare($this->snapshot(), $baseline)['orders'][0]['reference'], $delta['orders'][0]['reference']);
        $this->assertSame(-2, $delta['inventory'][0]['reserved_delta']);
    }

    public function test_mixed_lineage_and_changed_immutable_snapshots_are_rejected(): void
    {
        $service = new OrderDelta;
        $baseline = $this->withOrder($this->snapshot());
        $window = $baseline;
        $window['order_items'][0]['unit_price'] = 150000;
        $this->expectException(\LogicException::class);
        $service->compare($baseline, $window);
    }

    public function test_wrong_lineage_cannot_be_used_as_a_cutoff(): void
    {
        $baseline = $this->snapshot();
        $window = $baseline;
        $window['lineage'] = 'another-database';
        $this->expectException(\LogicException::class);
        (new OrderDelta)->compare($baseline, $window);
    }

    public function test_invalid_totals_are_not_silently_exported_as_verified(): void
    {
        $baseline = $this->snapshot();
        $window = $this->withOrder($baseline);
        $window['orders'][0]['total'] = 1;
        $this->expectException(\LogicException::class);
        (new OrderDelta)->compare($baseline, $window);
    }

    public function test_removed_inventory_cannot_be_omitted_from_reconciliation(): void
    {
        $baseline = $this->snapshot();
        $window = $baseline;
        $window['inventories'] = [];
        $this->expectException(\LogicException::class);
        (new OrderDelta)->compare($baseline, $window);
    }
}
