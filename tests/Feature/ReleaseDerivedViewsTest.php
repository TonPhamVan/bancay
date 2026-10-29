<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/** DDL uses only the guarded test database; do not put implicit-commit CREATE VIEW in a test transaction. */
final class ReleaseDerivedViewsTest extends TestCase
{
    public function test_view_rebuild_requires_exact_database_confirmation(): void
    {
        $this->artisan('store:rebuild-derived-views', ['--database-confirm' => 'wrong_database'])->assertFailed();
    }

    public function test_verified_test_restore_can_rebuild_views_without_changing_business_data(): void
    {
        $this->artisan('migrate', ['--force' => true])->assertSuccessful();
        $before = DB::select('CHECKSUM TABLE categories,products,product_variants,inventories,orders,order_items,order_status_histories EXTENDED');
        $this->artisan('store:rebuild-derived-views', ['--database-confirm' => 'bancay_test'])->assertSuccessful();
        $this->assertEquals($before, DB::select('CHECKSUM TABLE categories,products,product_variants,inventories,orders,order_items,order_status_histories EXTENDED'));
        $this->assertSame(0, DB::table('view_workflow_start')->count());
        $this->assertSame(0, DB::table('view_workflow_value_unions')->count());
    }

    public function test_production_rebuild_is_denied_without_maintenance(): void
    {
        $this->app->instance('env', 'production');
        try {
            $this->artisan('store:rebuild-derived-views', ['--database-confirm' => 'bancay_test'])->assertFailed();
        } finally {
            $this->app->instance('env', 'testing');
        }
    }
}
