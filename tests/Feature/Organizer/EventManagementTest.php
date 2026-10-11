<?php

use App\Enums\EventStatus;
use App\Models\Event;
use App\Models\User;
use Livewire\Livewire;

/**
 * @return array<string, string>
 */
function validEventInput(): array
{
    return [
        'form.title' => '岩手山 朝焼けトレッキング',
        'form.description' => '朝焼けの岩手山を歩きます。',
        'form.venue' => '八幡平市 馬返し登山口',
        'form.starts_at' => now()->addMonth()->format('Y-m-d\TH:i'),
        'form.ends_at' => now()->addMonth()->addHours(3)->format('Y-m-d\TH:i'),
    ];
}

test('guests are redirected to the login page', function () {
    $this->get(route('organizer.events.index'))->assertRedirect(route('login'));
});

test('customers cannot access the organizer pages', function () {
    $this->actingAs(User::factory()->create());

    $this->get(route('organizer.events.index'))->assertForbidden();
    $this->get(route('organizer.events.create'))->assertForbidden();
});

test('organizers see only their own events', function () {
    $organizer = User::factory()->organizer()->create();
    Event::factory()->for($organizer, 'organizer')->create(['title' => '自分のイベント']);
    Event::factory()->create(['title' => '他人のイベント']);

    $this->actingAs($organizer)
        ->get(route('organizer.events.index'))
        ->assertOk()
        ->assertSee('自分のイベント')
        ->assertDontSee('他人のイベント');
});

test('organizers can create an event as a draft', function () {
    $organizer = User::factory()->organizer()->create();
    $this->actingAs($organizer);

    Livewire::test('pages::organizer.events.create')
        ->set(validEventInput())
        ->call('save')
        ->assertHasNoErrors()
        ->assertRedirect();

    $event = $organizer->events()->sole();
    expect($event->title)->toBe('岩手山 朝焼けトレッキング')
        ->and($event->status)->toBe(EventStatus::Draft);
});

test('event input is validated', function (array $overrides, string $field, string $rule) {
    $this->actingAs(User::factory()->organizer()->create());

    Livewire::test('pages::organizer.events.create')
        ->set([...validEventInput(), ...$overrides])
        ->call('save')
        ->assertHasErrors([$field => $rule]);

    expect(Event::count())->toBe(0);
})->with([
    'title is required' => [['form.title' => ''], 'form.title', 'required'],
    'description is required' => [['form.description' => ''], 'form.description', 'required'],
    'venue is required' => [['form.venue' => ''], 'form.venue', 'required'],
    'starts_at is required' => [['form.starts_at' => ''], 'form.starts_at', 'required'],
    'title is at most 100 characters' => [['form.title' => str_repeat('あ', 101)], 'form.title', 'max'],
    'venue is at most 100 characters' => [['form.venue' => str_repeat('あ', 101)], 'form.venue', 'max'],
    'ends_at must be after starts_at' => [['form.starts_at' => '2030-01-01T10:00', 'form.ends_at' => '2030-01-01T10:00'], 'form.ends_at', 'after'],
]);

test('owners can update their event', function () {
    $event = Event::factory()->create();
    $this->actingAs($event->organizer);

    Livewire::test('pages::organizer.events.edit', ['event' => $event])
        ->set('form.title', '地熱蒸し料理教室')
        ->call('save')
        ->assertHasNoErrors();

    expect($event->fresh()->title)->toBe('地熱蒸し料理教室');
});

test('other organizers cannot open the edit page', function () {
    $event = Event::factory()->create();
    $this->actingAs(User::factory()->organizer()->create());

    $this->get(route('organizer.events.edit', $event))->assertForbidden();
});

test('actions are authorized again after the page is opened', function (string $method) {
    $event = Event::factory()->create(['title' => '元のタイトル']);
    $this->actingAs($event->organizer);

    $component = Livewire::test('pages::organizer.events.edit', ['event' => $event]);

    $event->forceFill(['organizer_id' => User::factory()->organizer()->create()->id])->save();

    $component->set('form.title', '書き換え')
        ->call($method)
        ->assertForbidden();

    $event->refresh();
    expect($event->exists)->toBeTrue()
        ->and($event->title)->toBe('元のタイトル')
        ->and($event->status)->toBe(EventStatus::Draft);
})->with(['save', 'togglePublish', 'deleteEvent']);

test('owners can toggle between draft and published', function () {
    $event = Event::factory()->create();
    $this->actingAs($event->organizer);

    $component = Livewire::test('pages::organizer.events.edit', ['event' => $event]);

    $component->call('togglePublish');
    expect($event->fresh()->status)->toBe(EventStatus::Published);

    $component->call('togglePublish');
    expect($event->fresh()->status)->toBe(EventStatus::Draft);
});

test('owners cannot republish an event unpublished by an admin', function () {
    $event = Event::factory()->unpublished()->create();
    $this->actingAs($event->organizer);

    Livewire::test('pages::organizer.events.edit', ['event' => $event])
        ->call('togglePublish')
        ->assertForbidden();

    expect($event->fresh()->status)->toBe(EventStatus::Unpublished);
});

test('owners can delete their event', function () {
    $event = Event::factory()->create();
    $this->actingAs($event->organizer);

    Livewire::test('pages::organizer.events.edit', ['event' => $event])
        ->call('deleteEvent')
        ->assertRedirect(route('organizer.events.index'));

    expect(Event::find($event->id))->toBeNull();
});
