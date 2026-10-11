<?php

use App\Models\Order;
use App\Models\OrderItem;
use App\Models\TicketType;

function addOrderItem(TicketType $ticketType, Order $order, int $quantity): void
{
    OrderItem::factory()->create([
        'order_id' => $order->id,
        'ticket_type_id' => $ticketType->id,
        'quantity' => $quantity,
    ]);
}

test('remaining equals capacity when nothing is ordered', function () {
    $ticketType = TicketType::factory()->create(['capacity' => 10]);

    expect($ticketType->remaining())->toBe(10);
});

test('paid orders and recent pending orders reduce remaining', function () {
    $ticketType = TicketType::factory()->create(['capacity' => 10]);

    addOrderItem($ticketType, Order::factory()->paid()->create(['event_id' => $ticketType->event_id]), 3);
    addOrderItem($ticketType, Order::factory()->create(['event_id' => $ticketType->event_id]), 2);

    expect($ticketType->remaining())->toBe(5);
});

test('expired pending, cancelled and refunded orders do not reduce remaining', function () {
    $ticketType = TicketType::factory()->create(['capacity' => 10]);

    $expired = Order::factory()->create(['event_id' => $ticketType->event_id]);
    addOrderItem($ticketType, $expired, 4);
    addOrderItem($ticketType, Order::factory()->cancelled()->create(['event_id' => $ticketType->event_id]), 3);
    addOrderItem($ticketType, Order::factory()->refunded()->create(['event_id' => $ticketType->event_id]), 2);

    $this->travel(TicketType::PENDING_MINUTES + 1)->minutes();

    expect($ticketType->remaining())->toBe(10);
});

test('pending order still holds tickets just before the limit', function () {
    $ticketType = TicketType::factory()->create(['capacity' => 10]);
    addOrderItem($ticketType, Order::factory()->create(['event_id' => $ticketType->event_id]), 4);

    $this->travel(TicketType::PENDING_MINUTES - 1)->minutes();

    expect($ticketType->remaining())->toBe(6);
});

test('withReservedQuantity gives the same result as remaining without extra queries', function () {
    $ticketType = TicketType::factory()->create(['capacity' => 10]);
    addOrderItem($ticketType, Order::factory()->paid()->create(['event_id' => $ticketType->event_id]), 3);

    $loaded = TicketType::withReservedQuantity()->findOrFail($ticketType->id);

    expect($loaded->remaining())->toBe(7)
        ->and($loaded->remaining())->toBe($ticketType->remaining());
});

test('remaining never goes below zero', function () {
    $ticketType = TicketType::factory()->create(['capacity' => 2]);
    addOrderItem($ticketType, Order::factory()->paid()->create(['event_id' => $ticketType->event_id]), 5);

    expect($ticketType->remaining())->toBe(0);
});

test('withReservedQuantity counts only paid and recent pending orders', function () {
    $ticketType = TicketType::factory()->create(['capacity' => 20]);

    addOrderItem($ticketType, Order::factory()->paid()->create(['event_id' => $ticketType->event_id]), 3);
    addOrderItem($ticketType, Order::factory()->cancelled()->create(['event_id' => $ticketType->event_id]), 4);
    addOrderItem($ticketType, Order::factory()->refunded()->create(['event_id' => $ticketType->event_id]), 5);
    addOrderItem($ticketType, Order::factory()->create(['event_id' => $ticketType->event_id]), 2);

    $this->travel(TicketType::PENDING_MINUTES - 1)->minutes();
    $recent = Order::factory()->create(['event_id' => $ticketType->event_id]);
    addOrderItem($ticketType, $recent, 1);

    $this->travel(2)->minutes();

    $loaded = TicketType::withReservedQuantity()->findOrFail($ticketType->id);

    expect($loaded->remaining())->toBe(16)
        ->and($loaded->remaining())->toBe($ticketType->remaining());
});

test('withReservedQuantity also never goes below zero', function () {
    $ticketType = TicketType::factory()->create(['capacity' => 2]);
    addOrderItem($ticketType, Order::factory()->paid()->create(['event_id' => $ticketType->event_id]), 5);

    expect(TicketType::withReservedQuantity()->findOrFail($ticketType->id)->remaining())->toBe(0);
});

test('pending order still holds tickets at exactly the limit', function () {
    $ticketType = TicketType::factory()->create(['capacity' => 10]);
    addOrderItem($ticketType, Order::factory()->create(['event_id' => $ticketType->event_id]), 4);

    $this->travel(TicketType::PENDING_MINUTES)->minutes();

    expect($ticketType->remaining())->toBe(6);
});

test('orders of other ticket types do not reduce remaining', function () {
    $ticketType = TicketType::factory()->create(['capacity' => 10]);
    $other = TicketType::factory()->create(['event_id' => $ticketType->event_id, 'capacity' => 10]);
    addOrderItem($other, Order::factory()->paid()->create(['event_id' => $ticketType->event_id]), 6);

    expect($ticketType->remaining())->toBe(10);
});
