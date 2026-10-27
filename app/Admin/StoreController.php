<?php

namespace App\Admin;

use App\Actions\Catalog\CreateVariant;
use App\Actions\Commerce\ChangeOrderStatus;
use App\Domain\Commerce\OrderStatus;
use App\Models\ArticleCategory;
use App\Models\Category;
use App\Models\Setting;
use App\Services\Admin\CatalogEditor;
use App\Services\Admin\CustomerAddresses;
use App\Services\Admin\ProductImages;
use App\Services\Admin\RecordEditor;
use App\Services\Admin\SettingsEditor;
use App\Services\Admin\StockAdjustment;
use App\Services\Admin\StoreQueries;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/** HTTP adapter only; all multi-step mutations live in application services. */
final class StoreController
{
    private function access(string $module, ?string $entity = null): array
    {
        abort_unless(\Exment::user(), 401);
        Gate::authorize(StoreResources::ABILITIES[$module] ?? 'store.invalid');

        return $entity ? StoreResources::get($module, $entity) : [];
    }

    private function id(mixed $id): int
    {
        abort_unless(is_scalar($id) && preg_match('/\A[1-9][0-9]{0,17}\z/', (string) $id), 404);

        return (int) $id;
    }

    private function optionalId(string $field): ?int
    {
        $value = request($field);

        return $value === null || $value === '' ? null : $this->id($value);
    }

    public function index(string $module)
    {
        if ($module === 'dashboard') {
            abort_unless(\Exment::user(), 401);

            return view('admin.store.dashboard', app(StoreQueries::class)->dashboard());
        }
        $this->access($module);

        return redirect(StoreResources::url($module, StoreResources::MODULES[$module][0]));
    }

    public function listing(string $module, string $entity)
    {
        $definition = $this->access($module, $entity);
        if ($entity === 'settings') {
            return view('admin.store.settings', ['settings' => Setting::pluck('value', 'key')]);
        }
        $rows = app(StoreQueries::class)->listing($module, $entity, request()->query())->paginate(25)->withQueryString();
        $filterChoices = match ($entity) {
            'products' => Category::orderBy('name')->pluck('name', 'id'),
            'articles' => ArticleCategory::orderBy('name')->pluck('name', 'id'),
            'categories', 'article-categories' => $definition['model']::orderBy('name')->pluck('name', 'id'),
            default => collect(),
        };

        return view('admin.store.list', compact('module', 'entity', 'definition', 'rows', 'filterChoices'));
    }

    public function form(string $module, string $entity, mixed $id = null)
    {
        $definition = $this->access($module, $entity);
        abort_if(in_array($entity, ['orders', 'inventory', 'settings'], true), 405);
        $record = $id !== null ? $definition['model']::findOrFail($this->id($id)) : new $definition['model'];
        $parents = in_array($entity, ['categories', 'article-categories'], true)
            ? $definition['model']::where('id', '<>', $record->id ?? 0)->orderBy('name')->pluck('name', 'id') : collect();
        $choices = ['parents' => $parents, 'categories' => Category::orderBy('name')->pluck('name', 'id'),
            'article-categories' => ArticleCategory::orderBy('name')->pluck('name', 'id')];

        return view('admin.store.form', compact('module', 'entity', 'definition', 'record', 'choices'));
    }

    public function save(string $module, string $entity, mixed $id = null)
    {
        $this->access($module, $entity);
        if ($entity === 'settings') {
            return $this->settings();
        }

        return $this->write(function () use ($module, $entity, $id) {
            $record = app(RecordEditor::class)->save($module, $entity, request()->all(), $id !== null ? $this->id($id) : null);

            return redirect(StoreResources::url($module, $entity.'/'.$record->id))->with('store_success', 'Đã lưu.');
        });
    }

    public function destroy(string $module, string $entity, mixed $id)
    {
        $this->access($module, $entity);
        Validator::make(request()->all(), ['confirm' => ['accepted']])->validate();

        return $this->write(function () use ($module, $entity, $id) {
            app(RecordEditor::class)->delete($module, $entity, $this->id($id));

            return redirect(StoreResources::url($module, $entity))->with('store_success', 'Đã lưu trữ/xóa theo chính sách dữ liệu.');
        });
    }

    public function show(string $module, string $entity, mixed $id)
    {
        $definition = $this->access($module, $entity);
        $record = $definition['model']::findOrFail($this->id($id));
        if ($entity === 'products') {
            $record->load(['category', 'images', 'options.values', 'variants.optionValues', 'variants.inventory']);

            return view('admin.store.product', compact('module', 'entity', 'definition', 'record'));
        }
        if ($entity === 'orders') {
            $record->load(['items', 'statusHistories.actor.base_user']);

            return view('admin.store.order', compact('record'));
        }
        if ($entity === 'customers') {
            $record->load('addresses');
            $orders = Gate::allows('commerce.manage') ? $record->orders()->latest('id')->paginate(15) : null;

            return view('admin.store.customer', compact('record', 'orders'));
        }
        if ($entity === 'inventory') {
            $record->load('variant.product');

            return view('admin.store.inventory', compact('record'));
        }
        abort_if($entity === 'settings', 405);

        return view('admin.store.detail', compact('module', 'entity', 'definition', 'record'));
    }

    public function command(string $module, string $entity, mixed $id, string $command)
    {
        $definition = $this->access($module, $entity);
        $id = $this->id($id);

        return $this->write(function () use ($module, $entity, $id, $command, $definition) {
            $record = $definition['model']::findOrFail($id);
            $input = request()->all();
            if ($entity === 'products') {
                switch ($command) {
                    case 'upload': app(ProductImages::class)->upload($record, request()->file('images', []));
                        break;
                    case 'image': app(ProductImages::class)->update($record, $this->id(request('image_id')), $input);
                        break;
                    case 'delete-image':
                        Validator::make($input, ['confirm' => ['accepted']])->validate();
                        app(ProductImages::class)->delete($record, $this->id(request('image_id')));
                        break;
                    case 'option':
                        $input['values'] = is_string($input['values'] ?? null) ? preg_split('/\r\n|\r|\n/', trim($input['values'])) : ($input['values'] ?? []);
                        app(CatalogEditor::class)->option($record, $input, $this->optionalId('option_id'));
                        break;
                    case 'delete-option':
                        Validator::make($input, ['confirm' => ['accepted']])->validate();
                        app(CatalogEditor::class)->deleteOption($record, $this->id(request('option_id')));
                        break;
                    case 'variant':
                        if ($variantId = $this->optionalId('variant_id')) {
                            app(CatalogEditor::class)->updateVariant($record, $variantId, $input);
                        } else {
                            app(CreateVariant::class)->execute($record, $input);
                        }
                        break;
                    case 'generate': app(CatalogEditor::class)->generate($record, $input);
                        break;
                    default: abort(404);
                }
            } elseif ($entity === 'inventory' && $command === 'adjust') {
                app(StockAdjustment::class)->execute($record, $input, \Exment::user()->id);
            } elseif ($entity === 'orders' && $command === 'status') {
                $data = Validator::make($input, ['status' => ['required', Rule::enum(OrderStatus::class)],
                    'expected_status' => ['required', Rule::enum(OrderStatus::class)], 'note' => ['nullable', 'string', 'max:5000']])->validate();
                app(ChangeOrderStatus::class)->execute($record, OrderStatus::from($data['status']), \Exment::user()->id,
                    $data['note'] ?? null, OrderStatus::from($data['expected_status']));
            } elseif ($entity === 'customers' && $command === 'address') {
                app(CustomerAddresses::class)->save($record, $input, $this->optionalId('address_id'));
            } elseif ($entity === 'customers' && $command === 'delete-address') {
                Validator::make($input, ['confirm' => ['accepted']])->validate();
                app(CustomerAddresses::class)->delete($record, $this->id(request('address_id')));
            } else {
                abort(405);
            }

            return redirect(StoreResources::url($module, $entity.'/'.$id))->with('store_success', 'Đã thực hiện thao tác.');
        });
    }

    private function settings()
    {
        app(SettingsEditor::class)->save(request()->all());

        return redirect(StoreResources::url('settings', 'settings'))->with('store_success', 'Đã lưu cấu hình.');
    }

    public function export(string $module, string $entity)
    {
        $this->access($module, $entity);
        abort_unless(in_array($entity, ['products', 'inventory', 'orders', 'customers'], true), 405);
        $query = app(StoreQueries::class)->listing($module, $entity, request()->query());
        Log::info('store.export', ['actor_id' => \Exment::user()->id, 'entity' => $entity]);

        return response()->streamDownload(function () use ($query, $entity) {
            $out = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBF");
            $columns = match ($entity) {
                'products' => ['id', 'name', 'slug', 'category_id', 'status', 'variants_count'],
                'inventory' => ['id', 'sku', 'quantity', 'reserved_quantity', 'available'],
                'orders' => ['order_number', 'customer_name', 'customer_phone', 'total', 'currency', 'status', 'created_at'],
                'customers' => ['id', 'name', 'phone', 'email', 'status', 'orders_count'],
            };
            fputcsv($out, $columns);
            $query->chunk(200, function ($rows) use ($out, $columns) {
                foreach ($rows as $row) {
                    $values = [];
                    foreach ($columns as $column) {
                        $value = match ($column) {
                            'sku' => $row->variant?->sku, 'available' => $row->available(), default => $row->$column
                        };
                        if ($value instanceof \BackedEnum) {
                            $value = $value->value;
                        }
                        $text = (string) $value;
                        $values[] = preg_match('/\A\s*[=+@\-\t\r\n]/', $text) ? "'".$text : $text;
                    }
                    fputcsv($out, $values);
                }
            });
            fclose($out);
        }, 'bancay-'.$entity.'.csv', ['Content-Type' => 'text/csv; charset=UTF-8', 'Cache-Control' => 'no-store, private']);
    }

    private function write(callable $callback)
    {
        try {
            return $callback();
        } catch (QueryException $e) {
            if (in_array($e->errorInfo[1] ?? null, [1062, 1451, 1452, 4025], true)) {
                throw ValidationException::withMessages(['record' => 'Dữ liệu trùng, đang được sử dụng hoặc không còn hợp lệ. Kiểm tra và tải lại trang.']);
            }
            throw $e;
        }
    }
}
