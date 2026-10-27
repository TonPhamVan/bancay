<?php

namespace Database\Seeders;

use App\Actions\Catalog\CreateProduct;
use App\Actions\Catalog\CreateVariant;
use App\Actions\Commerce\ChangeOrderStatus;
use App\Actions\Commerce\CreateOrder;
use App\Actions\System\SetPublicSetting;
use App\Domain\Commerce\OrderStatus;
use App\Models\Article;
use App\Models\ArticleCategory;
use App\Models\Banner;
use App\Models\Category;
use App\Models\Customer;
use App\Models\Product;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class DemoDataSeeder extends Seeder
{
    public function run(): void
    {
        if (! app()->environment(['local', 'testing'])) {
            throw new \LogicException('Synthetic seed data is local/testing only.');
        }
        if (Product::where('slug', 'demo-greenhome')->exists()) {
            return;
        }
        DB::transaction(function () {
            $category = Category::factory()->create(['name' => 'Demo Plants', 'slug' => 'demo-plants']);
            $product = app(CreateProduct::class)->execute([
                'category_id' => $category->id, 'name' => 'Demo GreenHome', 'slug' => 'demo-greenhome',
                'status' => 'PUBLISHED', 'published_at' => now()->subDay()->toDateTimeString(),
                'options' => [
                    ['name' => 'Weight', 'values' => ['500g', '1kg']],
                    ['name' => 'Plant', 'values' => ['Rose', 'Orchid']],
                ],
            ]);
            $product->images()->create(['path' => 'products/demo/front.jpg', 'alt_text' => 'Synthetic demo front image', 'is_primary' => true]);
            $product->images()->create(['path' => 'products/demo/side.jpg', 'sort_order' => 1]);
            foreach ($product->options[0]->values as $i => $weight) {
                foreach ($product->options[1]->values as $j => $plant) {
                    app(CreateVariant::class)->execute($product, [
                        'sku' => 'DEMO-'.$i.'-'.$j, 'pricing_type' => 'FIXED',
                        'price' => 120000, 'sale_price' => 99000, 'quantity' => 20,
                        'option_value_ids' => [$weight->id, $plant->id],
                    ]);
                }
            }
            $customer = Customer::factory()->create(['name' => 'Synthetic Customer', 'email' => 'buyer@example.test']);
            $customer->addresses()->create([
                'recipient_name' => $customer->name, 'phone' => '+84999999001', 'line1' => 'Synthetic demo address',
                'country_code' => 'VN', 'is_default' => true,
            ]);
            $order = app(CreateOrder::class)->execute([
                'customer_name' => $customer->name,
                'customer_email' => $customer->email, 'customer_phone' => '+84999999001',
                'shipping_address' => ['line1' => 'Synthetic demo address', 'country_code' => 'VN'],
                'items' => [['product_variant_id' => $product->variants()->firstOrFail()->id, 'quantity' => 2]],
            ], customer: $customer);
            app(ChangeOrderStatus::class)->execute($order, OrderStatus::Confirmed, note: 'Synthetic demonstration only.');
            $articleCategory = ArticleCategory::factory()->create();
            Article::factory()->create(['article_category_id' => $articleCategory->id]);
            Banner::factory()->create();
            app(SetPublicSetting::class)->execute('site_name', 'Synthetic Bancay Store');
        });
    }
}
