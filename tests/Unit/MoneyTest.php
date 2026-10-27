<?php

namespace Tests\Unit;

use App\Domain\Commerce\Money;
use App\Domain\Commerce\OrderStatus;
use PHPUnit\Framework\TestCase;

class MoneyTest extends TestCase
{
    public function test_money_is_exact_integer_arithmetic(): void
    {
        $this->assertSame(297000, Money::multiply(99000, 3));
        $this->assertSame(300000, Money::add(297000, 3000));
    }

    public function test_money_overflow_is_rejected(): void
    {
        $this->expectException(\OverflowException::class);
        Money::multiply(PHP_INT_MAX, 2);
    }

    public function test_terminal_order_status_does_not_reopen(): void
    {
        $this->assertFalse(OrderStatus::Cancelled->canTransitionTo(OrderStatus::New));
        $this->assertFalse(OrderStatus::Returned->canTransitionTo(OrderStatus::Shipping));
        $this->assertFalse(OrderStatus::New->canTransitionTo(OrderStatus::Completed));
        $this->assertTrue(OrderStatus::Completed->canTransitionTo(OrderStatus::Returned));
    }
}
