<?php

use App\Enums\OrderStatus;
use App\Models\Order;
use App\Models\OrderItem;
use Illuminate\Database\QueryException;

test('a new order is pending by default', function () {
    $order = new Order(['total_amount' => 3000]);

    expect($order->status)->toBe(OrderStatus::Pending)
        ->and($order->isPaid())->toBeFalse();
});

test('order belongs to user and event and has items', function () {
    $item = OrderItem::factory()->create();
    $order = $item->order;

    expect($order->user->orders->first()->is($order))->toBeTrue()
        ->and($order->event->orders->first()->is($order))->toBeTrue()
        ->and($order->items->first()->is($item))->toBeTrue()
        ->and($item->ticketType->orderItems->first()->is($item))->toBeTrue();
});

test('paid order casts status and paid_at', function () {
    $order = Order::factory()->paid()->create()->fresh();

    expect($order->status)->toBe(OrderStatus::Paid)
        ->and($order->isPaid())->toBeTrue()
        ->and($order->paid_at)->not->toBeNull();
});

test('deleting an order deletes its items', function () {
    $item = OrderItem::factory()->create();

    $item->order->delete();

    expect(OrderItem::find($item->id))->toBeNull();
});

test('an event with orders cannot be deleted', function () {
    $order = Order::factory()->create();

    $order->event->delete();
})->throws(QueryException::class);

test('a ticket type that has been ordered cannot be deleted', function () {
    $item = OrderItem::factory()->create();

    $item->ticketType->delete();
})->throws(QueryException::class);

test('order status has japanese labels', function (OrderStatus $status, string $label) {
    expect($status->label())->toBe($label);
})->with([
    [OrderStatus::Pending, '支払い待ち'],
    [OrderStatus::Paid, '支払い済み'],
    [OrderStatus::Cancelled, 'キャンセル'],
    [OrderStatus::Refunded, '返金済み'],
]);
