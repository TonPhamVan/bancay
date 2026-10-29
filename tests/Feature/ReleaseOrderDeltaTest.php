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

    public function test_existing_totals_cannot_change_even_when_appended_items_balance_them(): void
    {
        $baseline = $this->withOrder($this->snapshot());
        $window = $baseline;
        $extra = $window['order_items'][0];
        $extra['id'] = 2;
        $window['order_items'][] = $extra;
        $window['orders'][0]['subtotal'] = 400000;
        $window['orders'][0]['total'] = 400000;
        $this->expectException(\LogicException::class);
        (new OrderDelta)->compare($baseline, $window);
    }

    public function test_existing_contact_snapshot_cannot_change_in_a_verified_delta(): void
    {
        $baseline = $this->withOrder($this->snapshot());
        $baseline['orders'][0]['customer_name'] = 'Synthetic original buyer';
        $baseline['orders'][0]['shipping_address'] = '{"line1":"Synthetic original address"}';
        $window = $baseline;
        $window['orders'][0]['customer_name'] = 'Synthetic changed buyer';
        $window['orders'][0]['shipping_address'] = '{"line1":"Synthetic changed address"}';
        $this->expectException(\LogicException::class);
        (new OrderDelta)->compare($baseline, $window);
    }

    public function test_current_order_status_must_agree_with_latest_history(): void
    {
        $baseline = $this->snapshot();
        $window = $this->withOrder($baseline);
        $window['orders'][0]['status'] = 'CANCELLED';
        $this->expectException(\LogicException::class);
        (new OrderDelta)->compare($baseline, $window);
    }

    public function test_customer_link_can_become_null_while_contact_and_prices_stay_unchanged(): void
    {
        $baseline = $this->withOrder($this->snapshot());
        $baseline['orders'][0]['customer_id'] = 7;
        $baseline['orders'][0]['customer_name'] = 'Synthetic snapshot buyer';
        $window = $baseline;
        $window['orders'][0]['customer_id'] = null;
        $result = (new OrderDelta)->compare($baseline, $window);
        $this->assertNull($result['orders'][0]['order']['customer_id']);
        $this->assertSame('Synthetic snapshot buyer', $result['orders'][0]['order']['customer_name']);
        $this->assertSame(100000, $result['orders'][0]['items'][0]['unit_price']);
    }

    public function test_an_illegal_live_transition_cannot_be_exported_as_verified(): void
    {
        $baseline = $this->snapshot();
        $window = $this->withOrder($baseline);
        $window['order_status_histories'][] = ['id' => 2, 'order_id' => 1, 'from_status' => 'COMPLETED', 'to_status' => 'NEW'];
        $this->expectException(\LogicException::class);
        (new OrderDelta)->compare($baseline, $window);
    }

    public function test_imported_history_never_gets_a_live_fulfillment_instruction(): void
    {
        $baseline = $this->snapshot();
        $window = $this->withOrder($baseline);
        $window['orders'][0]['is_legacy'] = 1;
        $window['orders'][0]['status'] = 'CONFIRMED';
        $window['order_status_histories'][0]['to_status'] = 'CONFIRMED';
        $result = (new OrderDelta)->compare($baseline, $window);
        $this->assertSame('KEEP_IMPORTED_HISTORY_DO_NOT_FULFILL_OR_RESTOCK', $result['orders'][0]['operator_action']);
    }
}
