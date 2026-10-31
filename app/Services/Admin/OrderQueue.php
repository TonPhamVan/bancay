<?php

namespace App\Services\Admin;

use App\Admin\StoreResources;
use App\Models\Order;
use Illuminate\Support\Facades\Validator;

final class OrderQueue
{
    private const KEYS = ['q', 'status', 'origin', 'from', 'to', 'sort', 'page'];

    public function context(mixed $input): array
    {
        Validator::make(['queue' => $input], ['queue' => ['array:'.implode(',', self::KEYS)]])->validate();

        return array_filter(app(StoreQueries::class)->filters($input), fn ($value) => $value !== null && $value !== '');
    }

    public function fromList(array $input): array
    {
        return $this->context(array_intersect_key($input, array_flip(self::KEYS)));
    }

    public function detailUrl(Order $order, array $context): string
    {
        return StoreResources::url('orders', 'orders/'.$order->id).($context ? '?'.http_build_query(['queue' => $context]) : '');
    }

    public function navigation(Order $order, array $context): array
    {
        $back = StoreResources::url('orders', 'orders').($context ? '?'.http_build_query($context) : '');
        $next = null;
        if (! $order->is_legacy && ($context['origin'] ?? '') !== 'legacy') {
            $query = app(StoreQueries::class)->listing('orders', 'orders', $context)->where('is_legacy', false)
                ->whereIn('status', ['NEW', 'CONFIRMED', 'PACKING', 'SHIPPING']);
            match ($context['sort'] ?? 'newest') {
                'oldest' => $query->where('id', '>', $order->id),
                'name' => $query->where(fn ($q) => $q->where('order_number', '>', $order->order_number)
                    ->orWhere(fn ($q) => $q->where('order_number', $order->order_number)->where('id', '>', $order->id))),
                default => $query->where('id', '<', $order->id),
            };
            $next = $query->first();
        }

        return ['back' => $back, 'next' => $next ? $this->detailUrl($next, $context) : null];
    }
}
