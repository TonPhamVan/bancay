<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    private function checks(): array
    {
        // Money/Eloquent casts use signed 64-bit PHP integers, even though storage is unsigned.
        $max = '9223372036854775807';

        return [
            'product_variants' => ['variants_money_domain_check', "(pricing_type = 'CONTACT' AND price IS NULL AND sale_price IS NULL) OR (pricing_type = 'FIXED' AND price IS NOT NULL AND price > 0 AND price <= {$max} AND (sale_price IS NULL OR (sale_price > 0 AND sale_price <= price)))"],
            'orders' => ['orders_money_domain_check', "subtotal <= {$max} AND discount_amount <= {$max} AND shipping_fee <= {$max} AND total <= {$max}"],
            'order_items' => ['items_money_domain_check', "unit_price <= {$max} AND subtotal <= {$max}"],
        ];
    }

    public function up(): void
    {
        $invalid = [];
        foreach ($this->checks() as $table => [$name, $condition]) {
            $count = DB::table($table)->whereRaw('NOT COALESCE(('.$condition.'), 0)')->count();
            if ($count > 0) {
                $invalid[$table] = $count;
            }
        }
        if ($invalid) {
            throw new LogicException('Money-domain preflight failed; quarantine/review existing rows before retry: '.json_encode($invalid));
        }
        foreach ($this->checks() as $table => [$name, $condition]) {
            DB::statement("ALTER TABLE {$table} ADD CONSTRAINT {$name} CHECK ({$condition})");
        }
    }

    public function down(): void
    {
        foreach (array_reverse($this->checks(), true) as $table => [$name]) {
            DB::statement("ALTER TABLE {$table} DROP CONSTRAINT {$name}");
        }
    }
};
