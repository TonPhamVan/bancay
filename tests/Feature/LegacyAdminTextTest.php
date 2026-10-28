<?php

namespace Tests\Feature;

use App\Models\Category;
use Tests\Support\ExmentAdminTestCase;

final class LegacyAdminTextTest extends ExmentAdminTestCase
{
    public function test_import_sized_category_description_can_be_saved_and_byte_overflow_is_rejected(): void
    {
        $this->loginAsRole('product_manager');
        $category = Category::factory()->create();
        $data = ['name' => $category->name, 'slug' => $category->slug, 'status' => 'DRAFT', 'sort_order' => 0, 'featured' => 0, 'description' => str_repeat('a', 37105)];
        $url = $this->storeUrl('catalog', 'categories/'.$category->id);
        $this->put($url, $data)->assertRedirect()->assertSessionHasNoErrors();
        $this->assertSame($data['description'], strip_tags($category->fresh()->description));
        $data['description'] = str_repeat('🌱', 17000);
        $this->putJson($url, $data)->assertUnprocessable()->assertJsonValidationErrors('description');
        $this->assertSame(37105, strlen(strip_tags($category->fresh()->description)));
        $data['description'] = str_repeat('a', 65530);
        $this->putJson($url, $data)->assertUnprocessable()->assertJsonValidationErrors('description');
    }
}
