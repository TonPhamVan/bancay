<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('categories', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('legacy_id')->nullable()->unique();
            $table->foreignId('parent_id')->nullable()->constrained('categories')->restrictOnDelete();
            $table->string('name');
            $table->string('slug', 191)->unique();
            $table->text('description')->nullable();
            $table->string('image', 500)->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->string('status', 16)->default('DRAFT');
            $table->boolean('featured')->default(false);
            $table->string('meta_title')->nullable();
            $table->text('meta_description')->nullable();
            $table->timestamp('published_at')->nullable();
            $table->timestamps();
            $table->index(['parent_id', 'status', 'sort_order']);
        });

        Schema::create('products', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('legacy_id')->nullable()->unique();
            $table->foreignId('category_id')->constrained()->restrictOnDelete();
            $table->string('name');
            $table->string('slug', 191)->unique();
            $table->text('short_description')->nullable();
            $table->longText('description')->nullable();
            $table->string('status', 16)->default('DRAFT');
            $table->boolean('featured')->default(false);
            $table->string('meta_title')->nullable();
            $table->text('meta_description')->nullable();
            $table->timestamp('published_at')->nullable();
            $table->timestamps();
            $table->softDeletes();
            $table->index(['category_id', 'status', 'featured', 'id'], 'products_browse_index');
            $table->index(['status', 'published_at']);
        });

        Schema::create('product_images', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->string('path', 500);
            $table->string('alt_text')->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('is_primary')->default(false);
            $table->unsignedBigInteger('primary_product_id')->nullable()
                ->storedAs('CASE WHEN is_primary = 1 THEN product_id ELSE NULL END');
            $table->timestamps();
            $table->unique('primary_product_id');
            $table->unique(['id', 'product_id']);
            $table->index(['product_id', 'sort_order']);
        });

        Schema::create('product_options', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->string('name', 100);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
            $table->unique(['product_id', 'name']);
            $table->unique(['id', 'product_id']);
        });

        Schema::create('product_option_values', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('product_option_id');
            $table->unsignedBigInteger('product_id');
            $table->string('value', 100);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
            $table->foreign(['product_option_id', 'product_id'], 'option_values_owner_fk')
                ->references(['id', 'product_id'])->on('product_options')->cascadeOnDelete();
            $table->unique(['product_option_id', 'value']);
            $table->unique(['id', 'product_option_id', 'product_id'], 'option_values_identity_unique');
        });

        Schema::create('product_variants', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained()->restrictOnDelete();
            $table->unsignedInteger('legacy_variant_index')->nullable();
            $table->string('legacy_sku', 191)->nullable();
            $table->string('sku', 100)->unique();
            $table->char('combination_key', 64);
            $table->string('pricing_type', 16)->default('FIXED');
            $table->unsignedBigInteger('price')->nullable();
            $table->unsignedBigInteger('sale_price')->nullable();
            $table->string('status', 16)->default('ACTIVE');
            $table->unsignedBigInteger('image_id')->nullable();
            $table->timestamps();
            $table->unique(['product_id', 'combination_key']);
            $table->unique(['product_id', 'legacy_variant_index']);
            $table->unique(['id', 'product_id']);
            $table->foreign(['image_id', 'product_id'], 'variant_image_owner_fk')
                ->references(['id', 'product_id'])->on('product_images')->restrictOnDelete();
            $table->index(['product_id', 'status']);
        });

        Schema::create('product_variant_values', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('product_variant_id');
            $table->unsignedBigInteger('product_option_id');
            $table->unsignedBigInteger('product_option_value_id');
            $table->unsignedBigInteger('product_id');
            $table->unique(['product_variant_id', 'product_option_id'], 'variant_one_value_per_option');
            $table->foreign(['product_variant_id', 'product_id'], 'variant_values_variant_fk')
                ->references(['id', 'product_id'])->on('product_variants')->cascadeOnDelete();
            $table->foreign(['product_option_value_id', 'product_option_id', 'product_id'], 'variant_values_option_fk')
                ->references(['id', 'product_option_id', 'product_id'])->on('product_option_values')->restrictOnDelete();
        });

        Schema::create('inventories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_variant_id')->unique()->constrained()->restrictOnDelete();
            $table->unsignedInteger('quantity')->default(0);
            $table->unsignedInteger('reserved_quantity')->default(0);
            $table->timestamps();
        });

        Schema::create('customers', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('legacy_id')->nullable()->unique();
            $table->string('name');
            $table->string('email')->nullable()->index();
            $table->string('phone', 40)->nullable()->index();
            $table->string('status', 16)->default('ACTIVE');
            $table->timestamps();
        });

        Schema::create('customer_addresses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('customer_id')->constrained()->cascadeOnDelete();
            $table->string('label', 100)->nullable();
            $table->string('recipient_name');
            $table->string('phone', 40);
            $table->string('line1');
            $table->string('line2')->nullable();
            $table->string('ward', 100)->nullable();
            $table->string('district', 100)->nullable();
            $table->string('province', 100)->nullable();
            $table->char('country_code', 2)->default('VN');
            $table->string('postal_code', 20)->nullable();
            $table->boolean('is_default')->default(false);
            $table->unsignedBigInteger('default_customer_id')->nullable()
                ->storedAs('CASE WHEN is_default = 1 THEN customer_id ELSE NULL END');
            $table->unique('default_customer_id');
            $table->timestamps();
        });

        Schema::create('orders', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('legacy_id')->nullable()->unique();
            $table->unsignedTinyInteger('legacy_status')->nullable();
            $table->boolean('is_legacy')->default(false);
            $table->string('order_number', 40)->unique();
            $table->foreignId('customer_id')->nullable()->constrained()->nullOnDelete();
            $table->string('customer_name');
            $table->string('customer_email')->nullable();
            $table->string('customer_phone', 40);
            $table->json('shipping_address');
            $table->text('shipping_note')->nullable();
            $table->char('currency', 3)->default('VND');
            $table->unsignedBigInteger('subtotal');
            $table->unsignedBigInteger('discount_amount')->default(0);
            $table->unsignedBigInteger('shipping_fee')->default(0);
            $table->unsignedBigInteger('total');
            $table->string('status', 16)->default('NEW');
            $table->timestamps();
            $table->index(['status', 'created_at']);
            $table->index(['customer_id', 'created_at']);
            $table->index('customer_phone');
        });

        Schema::create('order_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained()->restrictOnDelete();
            $table->foreignId('product_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('product_variant_id')->nullable()->constrained()->nullOnDelete();
            $table->string('product_name');
            $table->string('sku', 100)->nullable();
            $table->text('variant_description')->nullable();
            $table->unsignedBigInteger('unit_price');
            $table->unsignedInteger('quantity');
            $table->unsignedBigInteger('subtotal');
            $table->timestamps();
        });

        Schema::create('order_status_histories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained()->restrictOnDelete();
            $table->string('from_status', 16)->nullable();
            $table->string('to_status', 16);
            $table->text('note')->nullable();
            // Exment LoginUser uses unsigned INT, not Laravel's default BIGINT users.
            $table->unsignedInteger('changed_by')->nullable();
            $table->foreign('changed_by')->references('id')->on('login_users')->nullOnDelete();
            $table->timestamp('created_at')->useCurrent();
            $table->index(['order_id', 'created_at']);
        });

        Schema::create('article_categories', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('legacy_id')->nullable()->unique();
            $table->foreignId('parent_id')->nullable()->constrained('article_categories')->restrictOnDelete();
            $table->string('name');
            $table->string('slug', 191)->unique();
            $table->text('description')->nullable();
            $table->string('image', 500)->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('show_in_menu')->default(false);
            $table->string('status', 16)->default('DRAFT');
            $table->string('meta_title')->nullable();
            $table->text('meta_description')->nullable();
            $table->timestamp('published_at')->nullable();
            $table->timestamps();
            $table->index(['parent_id', 'status', 'sort_order']);
        });

        Schema::create('articles', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('legacy_id')->nullable()->unique();
            $table->foreignId('article_category_id')->constrained()->restrictOnDelete();
            $table->string('title');
            $table->string('slug', 191)->unique();
            $table->text('excerpt')->nullable();
            $table->longText('content')->nullable();
            $table->string('image', 500)->nullable();
            $table->string('status', 16)->default('DRAFT');
            $table->boolean('featured')->default(false);
            $table->boolean('show_in_menu')->default(false);
            $table->unsignedBigInteger('view_count')->default(0);
            $table->timestamp('published_at')->nullable();
            $table->string('meta_title')->nullable();
            $table->text('meta_description')->nullable();
            $table->timestamps();
            $table->index(['article_category_id', 'status', 'published_at'], 'articles_browse_index');
        });

        Schema::create('banners', function (Blueprint $table) {
            $table->id();
            $table->string('legacy_source', 16)->nullable();
            $table->unsignedBigInteger('legacy_id')->nullable();
            $table->unique(['legacy_source', 'legacy_id']);
            $table->string('name');
            $table->string('image', 500);
            $table->string('link', 2048)->nullable();
            $table->string('position', 50);
            $table->foreignId('category_id')->nullable()->constrained()->restrictOnDelete();
            $table->unsignedInteger('priority')->default(0);
            $table->unsignedInteger('sort_order')->default(0);
            $table->string('status', 16)->default('INACTIVE');
            $table->timestamp('starts_at')->nullable();
            $table->timestamp('ends_at')->nullable();
            $table->timestamps();
            $table->index(['position', 'status', 'sort_order']);
        });

        Schema::create('settings', function (Blueprint $table) {
            $table->id();
            $table->string('key', 100)->unique();
            $table->json('value')->nullable();
            $table->timestamps();
        });

        // Static DDL only; no user input is interpolated into SQL.
        DB::statement("ALTER TABLE product_variants ADD CONSTRAINT variants_pricing_check CHECK ((pricing_type = 'CONTACT' AND price IS NULL AND sale_price IS NULL) OR (pricing_type = 'FIXED' AND price > 0 AND (sale_price IS NULL OR (sale_price > 0 AND sale_price <= price))))");
        DB::statement('ALTER TABLE inventories ADD CONSTRAINT inventory_available_check CHECK (reserved_quantity <= quantity)');
        DB::statement('ALTER TABLE order_items ADD CONSTRAINT order_item_amount_check CHECK (quantity > 0 AND unit_price > 0 AND subtotal = unit_price * quantity)');
        DB::statement('ALTER TABLE orders ADD CONSTRAINT order_totals_check CHECK (discount_amount <= subtotal AND total = subtotal - discount_amount + shipping_fee)');
        DB::statement("ALTER TABLE orders ADD CONSTRAINT order_status_check CHECK (status IN ('NEW','CONFIRMED','PACKING','SHIPPING','COMPLETED','CANCELLED','RETURNED'))");
        // MariaDB rejects AUTO_INCREMENT id in CHECK expressions. Tree cycles are
        // validated under ordered row locks in CategoryTreeService instead.
        foreach (['categories', 'products', 'article_categories', 'articles'] as $table) {
            DB::statement("ALTER TABLE {$table} ADD CONSTRAINT {$table}_status_check CHECK (status IN ('DRAFT','PUBLISHED','ARCHIVED'))");
        }
        foreach (['product_variants', 'customers', 'banners'] as $table) {
            DB::statement("ALTER TABLE {$table} ADD CONSTRAINT {$table}_status_check CHECK (status IN ('ACTIVE','INACTIVE'))");
        }
        DB::statement('ALTER TABLE banners ADD CONSTRAINT banner_schedule_check CHECK (starts_at IS NULL OR ends_at IS NULL OR ends_at >= starts_at)');
    }

    public function down(): void
    {
        foreach (['settings', 'banners', 'articles', 'article_categories', 'order_status_histories', 'order_items', 'orders', 'customer_addresses', 'customers', 'inventories', 'product_variant_values', 'product_variants', 'product_option_values', 'product_options', 'product_images', 'products', 'categories'] as $table) {
            Schema::dropIfExists($table);
        }
    }
};
