<?php

use App\Models\Event;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\TicketType;
use App\Models\User;

test('guests can see a published event with its details', function () {
    $event = Event::factory()->published()->create([
        'title' => '盛岡じゃじゃ麺まつり',
        'description' => '名物を食べ比べます。',
        'venue' => '盛岡市 中央公園',
    ]);
    TicketType::factory()->for($event)->create(['name' => '一般', 'price' => 3000, 'capacity' => 50]);
    TicketType::factory()->for($event)->create(['name' => '招待', 'price' => 0, 'capacity' => 10]);

    $this->get(route('events.show', $event))
        ->assertOk()
        ->assertSee('盛岡じゃじゃ麺まつり')
        ->assertSee('名物を食べ比べます。')
        ->assertSee('盛岡市 中央公園')
        ->assertSee($event->organizer->name)
        ->assertSee('3,000円')
        ->assertSee('無料')
        ->assertSee('残り 50 枚 / 定員 50 枚', false)
        ->assertSee('残り 10 枚 / 定員 10 枚', false);
});

test('sold out ticket types are marked', function () {
    $ticketType = TicketType::factory()->create([
        'event_id' => Event::factory()->published(),
        'capacity' => 2,
    ]);
    OrderItem::factory()->for($ticketType)->create([
        'order_id' => Order::factory()->paid()->create(['event_id' => $ticketType->event_id]),
        'quantity' => 2,
    ]);

    $this->get(route('events.show', $ticketType->event))->assertSee('完売');
});

test('drafts return 404 for everyone except the owner and admins', function () {
    $event = Event::factory()->create(['title' => '下書きのイベント']);

    $this->get(route('events.show', $event))->assertNotFound();

    $this->actingAs(User::factory()->create());
    $this->get(route('events.show', $event))->assertNotFound();

    $this->actingAs(User::factory()->organizer()->create());
    $this->get(route('events.show', $event))->assertNotFound();

    $this->actingAs($event->organizer);
    $this->get(route('events.show', $event))->assertOk()->assertSee('下書きのイベント');

    $this->actingAs(User::factory()->admin()->create());
    $this->get(route('events.show', $event))->assertOk();
});

test('unpublished events return 404 for everyone except the owner and admins', function () {
    $event = Event::factory()->unpublished()->create();

    $this->get(route('events.show', $event))->assertNotFound();

    $this->actingAs(User::factory()->create());
    $this->get(route('events.show', $event))->assertNotFound();

    $this->actingAs(User::factory()->organizer()->create());
    $this->get(route('events.show', $event))->assertNotFound();

    $this->actingAs($event->organizer);
    $this->get(route('events.show', $event))->assertOk();

    $this->actingAs(User::factory()->admin()->create());
    $this->get(route('events.show', $event))->assertOk();
});

test('event cards on the list link to the detail page', function () {
    $event = Event::factory()->published()->create();

    $this->get(route('home'))->assertSee(route('events.show', $event), false);
});
