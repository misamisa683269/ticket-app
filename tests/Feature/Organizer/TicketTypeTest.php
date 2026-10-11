<?php

use App\Enums\EventStatus;
use App\Models\Event;
use App\Models\TicketType;
use App\Models\User;
use Livewire\Livewire;

/**
 * @return array<string, int|string>
 */
function validTicketTypeInput(): array
{
    return [
        'ticketTypeForm.name' => '一般',
        'ticketTypeForm.price' => '3000',
        'ticketTypeForm.capacity' => '100',
        'ticketTypeForm.sales_start_at' => '2030-04-01T10:00',
        'ticketTypeForm.sales_end_at' => '2030-04-30T23:59',
    ];
}

test('owners can add a ticket type', function () {
    $event = Event::factory()->create();
    $this->actingAs($event->organizer);

    Livewire::test('pages::organizer.events.edit', ['event' => $event])
        ->set(validTicketTypeInput())
        ->call('saveTicketType')
        ->assertHasNoErrors()
        ->assertSet('ticketTypeForm.name', '');

    $ticketType = $event->ticketTypes()->sole();

    expect($ticketType->name)->toBe('一般')
        ->and($ticketType->price)->toBe(3000)
        ->and($ticketType->capacity)->toBe(100)
        ->and($ticketType->sales_start_at->format('Y-m-d H:i'))->toBe('2030-04-01 10:00')
        ->and($ticketType->sales_end_at->format('Y-m-d H:i'))->toBe('2030-04-30 23:59');
});

test('editing fills the form and saving updates the ticket type', function () {
    $ticketType = TicketType::factory()->create(['name' => '一般', 'price' => 3000]);
    $this->actingAs($ticketType->event->organizer);

    Livewire::test('pages::organizer.events.edit', ['event' => $ticketType->event])
        ->call('editTicketType', $ticketType->id)
        ->assertSet('ticketTypeForm.name', '一般')
        ->assertSet('ticketTypeForm.price', 3000)
        ->set('ticketTypeForm.name', '学生')
        ->set('ticketTypeForm.price', '1500')
        ->call('saveTicketType')
        ->assertHasNoErrors();

    expect(TicketType::sole())
        ->id->toBe($ticketType->id)
        ->name->toBe('学生')
        ->price->toBe(1500);
});

test('owners can delete a ticket type', function () {
    $ticketType = TicketType::factory()->create();
    $this->actingAs($ticketType->event->organizer);

    Livewire::test('pages::organizer.events.edit', ['event' => $ticketType->event])
        ->call('deleteTicketType', $ticketType->id);

    expect(TicketType::count())->toBe(0);
});

test('ticket type input is validated', function (array $overrides, string $field, string $rule) {
    $event = Event::factory()->create();
    $this->actingAs($event->organizer);

    Livewire::test('pages::organizer.events.edit', ['event' => $event])
        ->set([...validTicketTypeInput(), ...$overrides])
        ->call('saveTicketType')
        ->assertHasErrors([$field => $rule]);

    expect(TicketType::count())->toBe(0);
})->with([
    'name too long' => [['ticketTypeForm.name' => str_repeat('あ', 51)], 'ticketTypeForm.name', 'max'],
    'negative price' => [['ticketTypeForm.price' => '-1'], 'ticketTypeForm.price', 'min'],
    'empty price' => [['ticketTypeForm.price' => ''], 'ticketTypeForm.price', 'required'],
    'empty capacity' => [['ticketTypeForm.capacity' => ''], 'ticketTypeForm.capacity', 'required'],
    'empty name' => [['ticketTypeForm.name' => ''], 'ticketTypeForm.name', 'required'],
    'price over the limit' => [['ticketTypeForm.price' => '1000001'], 'ticketTypeForm.price', 'max'],
    'capacity over the limit' => [['ticketTypeForm.capacity' => '100001'], 'ticketTypeForm.capacity', 'max'],
    'price not an integer' => [['ticketTypeForm.price' => 'abc'], 'ticketTypeForm.price', 'integer'],
    'decimal price' => [['ticketTypeForm.price' => '1.5'], 'ticketTypeForm.price', 'integer'],
    'sales end before start' => [['ticketTypeForm.sales_end_at' => '2030-03-31T10:00'], 'ticketTypeForm.sales_end_at', 'after'],
    'zero capacity' => [['ticketTypeForm.capacity' => '0'], 'ticketTypeForm.capacity', 'min'],
    'sales end equals start' => [['ticketTypeForm.sales_end_at' => '2030-04-01T10:00'], 'ticketTypeForm.sales_end_at', 'after'],
]);

test('price and capacity at their limits are accepted', function () {
    $event = Event::factory()->create();
    $this->actingAs($event->organizer);

    Livewire::test('pages::organizer.events.edit', ['event' => $event])
        ->set([...validTicketTypeInput(), 'ticketTypeForm.price' => '1000000', 'ticketTypeForm.capacity' => '100000'])
        ->call('saveTicketType')
        ->assertHasNoErrors();

    expect($event->ticketTypes()->sole())->price->toBe(1_000_000)->capacity->toBe(100_000);
});

test('free tickets with price 0 are allowed', function () {
    $event = Event::factory()->create();
    $this->actingAs($event->organizer);

    Livewire::test('pages::organizer.events.edit', ['event' => $event])
        ->set([...validTicketTypeInput(), 'ticketTypeForm.price' => '0'])
        ->call('saveTicketType')
        ->assertHasNoErrors();

    expect($event->ticketTypes()->sole()->price)->toBe(0);
});

test('ticket type actions are forbidden once the user no longer owns the event', function (string $method) {
    $ticketType = TicketType::factory()->create(['name' => '一般']);
    $event = $ticketType->event;
    $this->actingAs($event->organizer);

    $component = Livewire::test('pages::organizer.events.edit', ['event' => $event])
        ->set(validTicketTypeInput());

    $event->forceFill(['organizer_id' => User::factory()->organizer()->create()->id])->save();

    $component->call($method, ...($method === 'saveTicketType' ? [] : [$ticketType->id]))
        ->assertForbidden();

    expect(TicketType::sole()->name)->toBe('一般');
})->with(['saveTicketType', 'editTicketType', 'deleteTicketType']);

test('other organizers cannot open the edit component to add ticket types', function () {
    $event = Event::factory()->create();
    $this->actingAs(User::factory()->organizer()->create());

    Livewire::test('pages::organizer.events.edit', ['event' => $event])
        ->assertForbidden();

    expect(TicketType::count())->toBe(0);
});

test('ticket types of other events cannot be edited or deleted', function (string $method) {
    $event = Event::factory()->create();
    $otherTicketType = TicketType::factory()->create();
    $this->actingAs($event->organizer);

    Livewire::test('pages::organizer.events.edit', ['event' => $event])
        ->call($method, $otherTicketType->id)
        ->assertNotFound();

    expect($otherTicketType->fresh())->not->toBeNull();
})->with(['editTicketType', 'deleteTicketType']);

test('events without ticket types cannot be published', function () {
    $event = Event::factory()->create();
    $this->actingAs($event->organizer);

    Livewire::test('pages::organizer.events.edit', ['event' => $event])
        ->call('togglePublish');

    expect($event->fresh()->status)->toBe(EventStatus::Draft);
});

test('events with a ticket type can be published', function () {
    $event = Event::factory()->has(TicketType::factory())->create();
    $this->actingAs($event->organizer);

    Livewire::test('pages::organizer.events.edit', ['event' => $event])
        ->call('togglePublish');

    expect($event->fresh()->status)->toBe(EventStatus::Published);
});

test('the last ticket type of a published event cannot be deleted', function () {
    $event = Event::factory()->published()->has(TicketType::factory())->create();
    $this->actingAs($event->organizer);

    Livewire::test('pages::organizer.events.edit', ['event' => $event])
        ->call('deleteTicketType', $event->ticketTypes()->sole()->id)
        ->assertHasNoErrors()
        ->assertDispatched('toast-show');

    expect($event->ticketTypes()->count())->toBe(1);
});

test('a published event can delete a ticket type while another remains', function () {
    $event = Event::factory()->published()->has(TicketType::factory()->count(2))->create();
    $this->actingAs($event->organizer);

    Livewire::test('pages::organizer.events.edit', ['event' => $event])
        ->call('deleteTicketType', $event->ticketTypes()->first()->id);

    expect($event->ticketTypes()->count())->toBe(1);
});

test('a draft event can delete its last ticket type', function () {
    $event = Event::factory()->has(TicketType::factory())->create();
    $this->actingAs($event->organizer);

    Livewire::test('pages::organizer.events.edit', ['event' => $event])
        ->call('deleteTicketType', $event->ticketTypes()->sole()->id);

    expect($event->ticketTypes()->count())->toBe(0);
});

test('deleting the ticket type being edited returns the form to add mode', function () {
    $ticketType = TicketType::factory()->create(['name' => '一般']);
    $this->actingAs($ticketType->event->organizer);

    Livewire::test('pages::organizer.events.edit', ['event' => $ticketType->event])
        ->call('editTicketType', $ticketType->id)
        ->call('deleteTicketType', $ticketType->id)
        ->assertSet('ticketTypeForm.ticketTypeId', null)
        ->assertSet('ticketTypeForm.name', '')
        ->assertSee('券種の追加');

    expect(TicketType::count())->toBe(0);
});

test('saving a ticket type that was deleted elsewhere resets the form without an error page', function () {
    $ticketType = TicketType::factory()->create();
    $event = $ticketType->event;
    $this->actingAs($event->organizer);

    $component = Livewire::test('pages::organizer.events.edit', ['event' => $event])
        ->call('editTicketType', $ticketType->id);

    $ticketType->delete();

    $component->call('saveTicketType')
        ->assertOk()
        ->assertSet('ticketTypeForm.ticketTypeId', null)
        ->assertDispatched('toast-show');

    expect(TicketType::count())->toBe(0);
});

test('isOnSale is true only within the sales period', function (string $now, bool $expected) {
    $ticketType = TicketType::factory()->make([
        'sales_start_at' => '2030-04-01 10:00:00',
        'sales_end_at' => '2030-04-30 23:59:00',
    ]);

    $this->travelTo($now);

    expect($ticketType->isOnSale())->toBe($expected);
})->with([
    'before start' => ['2030-04-01 09:59:59', false],
    'at start' => ['2030-04-01 10:00:00', true],
    'at end' => ['2030-04-30 23:59:00', true],
    'after end' => ['2030-04-30 23:59:01', false],
]);
