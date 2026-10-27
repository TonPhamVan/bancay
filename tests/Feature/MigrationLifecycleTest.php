<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class MigrationLifecycleTest extends TestCase
{
    public function test_empty_database_full_rollback_and_remigrate(): void
    {
        // CreatesApplication refuses any database other than dedicated bancay_test.
        $this->assertSame(0, Artisan::call('migrate:fresh', ['--force' => true]));
        $this->assertTrue(Schema::hasTable('order_items'));
        $this->assertTrue(Schema::hasTable('login_users'));
        $this->assertSame(0, Artisan::call('migrate:rollback', ['--force' => true]));
        $this->assertFalse(Schema::hasTable('products'));
        $this->assertSame(0, Artisan::call('migrate', ['--force' => true]));
        $this->assertTrue(Schema::hasTable('inventories'));
        $this->assertTrue(Schema::hasTable('order_status_histories'));
    }
}
