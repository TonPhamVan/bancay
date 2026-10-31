<?php

namespace Tests\Feature;

use App\Admin\StoreResources;
use App\Models\Category;
use App\Services\Admin\RecordEditor;
use ExmentAdminCore\Admin\Form;
use ExmentAdminCore\Admin\Grid;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use Tests\Support\ExmentAdminTestCase;

/** B2 feasibility only: no prototype route, plugin registration or operational data writes. */
final class NativeCategoryPrototypeTest extends ExmentAdminTestCase
{
    private function form(?int $id = null): Form
    {
        $form = new Form(new Category);
        $form->text('name', 'Tên danh mục');
        $form->saving(function (Form $form) use ($id) {
            Gate::authorize('catalog.manage');
            $input = [];
            foreach (array_keys(StoreResources::all()['categories']['fields']) as $field) {
                $input[$field] = $form->input($field);
            }
            $record = app(RecordEditor::class)->save('catalog', 'categories', $input, $id);

            // A supported Response from saving() prevents native generic ORM writes.
            return response()->json(['id' => $record->id]);
        });

        return $form;
    }

    public function test_native_form_can_delegate_create_and_update_to_existing_service_without_duplicate_write(): void
    {
        $this->loginAsRole('product_manager');
        $input = ['name' => 'Prototype category', 'slug' => 'prototype-category', 'status' => 'DRAFT', 'sort_order' => 0, 'featured' => 0];
        $result = $this->form()->store($input);
        $id = $result->getData(true)['id'];
        $this->assertDatabaseCount('categories', 1);
        $this->form($id)->update($id, array_replace($input, ['name' => 'Renamed prototype']));
        $this->assertDatabaseHas('categories', ['id' => $id, 'name' => 'Renamed prototype']);
        $this->assertDatabaseCount('categories', 1);
        try {
            $this->form($id)->update($id, $input + ['parent_id' => $id]);
            $this->fail('Tree cycle must reach the existing service and fail.');
        } catch (ValidationException) {
            $this->assertNull(Category::findOrFail($id)->parent_id);
            $this->assertSame('Renamed prototype', Category::findOrFail($id)->name);
        }
    }

    public function test_saving_hook_does_not_bypass_catalog_authorization(): void
    {
        $this->loginAsRole('content_manager');
        try {
            $this->form()->store(['name' => 'Forbidden']);
            $this->fail('Prototype hook must authorize writes.');
        } catch (AuthorizationException) {
            $this->assertDatabaseCount('categories', 0);
        }
    }

    public function test_native_grid_can_read_the_existing_eloquent_category_model(): void
    {
        $this->loginAsRole('product_manager');
        $category = Category::factory()->create(['name' => 'Native grid prototype']);
        $grid = new Grid(new Category);
        $grid->column('id', 'ID');
        $grid->column('name', 'Tên danh mục');
        $grid->disableCreateButton();
        $grid->disableActions();
        $grid->disableBatchActions();
        $grid->disableExport();
        $html = $grid->render();
        $this->assertStringContainsString('Native grid prototype', $html);
        $this->assertSame($category->id, Category::firstOrFail()->id);
        $this->assertDatabaseCount('categories', 1);
    }
}
