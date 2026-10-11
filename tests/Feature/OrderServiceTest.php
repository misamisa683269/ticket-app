<?php

use App\Enums\OrderStatus;
use App\Exceptions\PurchaseException;
use App\Models\Event;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\TicketType;
use App\Models\User;
use App\Services\OrderService;

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->event = Event::factory()->published()->create();
});

test('creates a pending order with items at the current prices', function () {
    $general = TicketType::factory()->for($this->event)->create(['price' => 3000, 'capacity' => 10]);
    $student = TicketType::factory()->for($this->event)->create(['price' => 1500, 'capacity' => 10]);

    $order = app(OrderService::class)->createOrder($this->user, $this->event, [
        $general->id => 2,
        $student->id => '3',
    ]);

    $general->update(['price' => 9999]);

    $order = $order->fresh('items');

    expect($order->status)->toBe(OrderStatus::Pending)
        ->and($order->user_id)->toBe($this->user->id)
        ->and($order->event_id)->toBe($this->event->id)
        ->and($order->total_amount)->toBe(3000 * 2 + 1500 * 3)
        ->and($order->items)->toHaveCount(2)
        ->and($order->items->firstWhere('ticket_type_id', $general->id))
        ->unit_price->toBe(3000)
        ->quantity->toBe(2)
        ->and($order->items->firstWhere('ticket_type_id', $student->id))
        ->unit_price->toBe(1500)
        ->quantity->toBe(3);
});

test('fails without creating an order when not enough tickets remain', function () {
    $ticketType = TicketType::factory()->for($this->event)->create(['name' => '一般', 'capacity' => 3]);

    expect(fn () => app(OrderService::class)->createOrder($this->user, $this->event, [$ticketType->id => 4]))
        ->toThrow(PurchaseException::class, '一般の残りは3枚です。');

    expect(Order::count())->toBe(0)
        ->and(OrderItem::count())->toBe(0);
});

test('fails when the ticket type is not on sale', function () {
    $ticketType = TicketType::factory()->for($this->event)->notOnSale()->create(['name' => '一般']);

    expect(fn () => app(OrderService::class)->createOrder($this->user, $this->event, [$ticketType->id => 1]))
        ->toThrow(PurchaseException::class, '一般は販売期間外です。');

    expect(Order::count())->toBe(0);
});

test('fails when the quantity is out of range', function (int $quantity) {
    $ticketType = TicketType::factory()->for($this->event)->create(['name' => '一般', 'capacity' => 100]);

    expect(fn () => app(OrderService::class)->createOrder($this->user, $this->event, [$ticketType->id => $quantity]))
        ->toThrow(PurchaseException::class, '一般の枚数は1〜10枚で指定してください。');

    expect(Order::count())->toBe(0);
})->with([0, OrderService::MAX_QUANTITY + 1]);

test('fails when a ticket type of another event is given', function () {
    $otherTicketType = TicketType::factory()->create();

    expect(fn () => app(OrderService::class)->createOrder($this->user, $this->event, [$otherTicketType->id => 1]))
        ->toThrow(PurchaseException::class);

    expect(Order::count())->toBe(0);
});

test('fails when the event is not published', function () {
    $event = Event::factory()->create();
    $ticketType = TicketType::factory()->for($event)->create();

    expect(fn () => app(OrderService::class)->createOrder($this->user, $event, [$ticketType->id => 1]))
        ->toThrow(PurchaseException::class, 'このイベントは現在購入できません。');

    expect(Order::count())->toBe(0);
});

test('ordering again cancels the unpaid order so tickets are not held twice', function () {
    $ticketType = TicketType::factory()->for($this->event)->create(['capacity' => 5]);
    $service = app(OrderService::class);

    $first = $service->createOrder($this->user, $this->event, [$ticketType->id => 4]);
    $second = $service->createOrder($this->user, $this->event, [$ticketType->id => 4]);

    expect($first->fresh()->status)->toBe(OrderStatus::Cancelled)
        ->and($second->fresh()->status)->toBe(OrderStatus::Pending)
        ->and($ticketType->remaining())->toBe(1);
});

test('a failed order keeps the unpaid order of the user', function () {
    $ticketType = TicketType::factory()->for($this->event)->create(['capacity' => 5]);
    $service = app(OrderService::class);

    $first = $service->createOrder($this->user, $this->event, [$ticketType->id => 2]);

    expect(fn () => $service->createOrder($this->user, $this->event, [$ticketType->id => 6]))
        ->toThrow(PurchaseException::class);

    expect($first->fresh()->status)->toBe(OrderStatus::Pending);
});

test('paid orders and orders of other users are not cancelled', function () {
    $ticketType = TicketType::factory()->for($this->event)->create(['capacity' => 20]);
    $paid = Order::factory()->paid()->create(['user_id' => $this->user->id, 'event_id' => $this->event->id]);
    $others = Order::factory()->create(['event_id' => $this->event->id]);

    app(OrderService::class)->createOrder($this->user, $this->event, [$ticketType->id => 1]);

    expect($paid->fresh()->status)->toBe(OrderStatus::Paid)
        ->and($others->fresh()->status)->toBe(OrderStatus::Pending);
});
