<?php

use App\Enums\EventStatus;
use App\Models\Event;
use App\Models\User;
use Livewire\Livewire;

test('organizers can open the create and edit pages over HTTP', function () {
    $event = Event::factory()->create(['title' => '蒸し料理教室']);
    $this->actingAs($event->organizer);

    $this->get(route('organizer.events.create'))->assertOk();
    $this->get(route('organizer.events.edit', $event))->assertOk()->assertSee('蒸し料理教室');
});

test('created event is stored as a draft with the submitted values', function () {
    $organizer = User::factory()->organizer()->create();
    $this->actingAs($organizer);

    Livewire::test('pages::organizer.events.create')
        ->set([
            'form.title' => '南部鉄器づくり体験',
            'form.description' => '鉄器づくりを体験します。',
            'form.venue' => '盛岡市 工房',
            'form.starts_at' => '2030-05-01T10:00',
            'form.ends_at' => '2030-05-01T12:30',
        ])
        ->call('save')
        ->assertHasNoErrors();

    $event = Event::sole();

    expect($event->organizer_id)->toBe($organizer->id)
        ->and($event->status)->toBe(EventStatus::Draft)
        ->and($event->description)->toBe('鉄器づくりを体験します。')
        ->and($event->venue)->toBe('盛岡市 工房')
        ->and($event->starts_at->format('Y-m-d H:i'))->toBe('2030-05-01 10:00')
        ->and($event->ends_at->format('Y-m-d H:i'))->toBe('2030-05-01 12:30');
});

test('create redirects to the edit page of the new event', function () {
    $this->actingAs(User::factory()->organizer()->create());

    Livewire::test('pages::organizer.events.create')
        ->set([
            'form.title' => '遷移確認',
            'form.description' => '説明',
            'form.venue' => '会場',
            'form.starts_at' => '2030-05-01T10:00',
            'form.ends_at' => '2030-05-01T11:00',
        ])
        ->call('save')
        ->assertRedirect(route('organizer.events.edit', Event::sole()));
});

test('customers and admins cannot create events', function (string $state) {
    $user = $state === 'customer' ? User::factory()->create() : User::factory()->admin()->create();
    $this->actingAs($user);

    $this->get(route('organizer.events.create'))->assertForbidden();

    Livewire::test('pages::organizer.events.create')->assertForbidden();

    expect(Event::count())->toBe(0);
})->with(['customer', 'admin']);

test('customers cannot open the edit page over HTTP', function () {
    $event = Event::factory()->create();
    $this->actingAs(User::factory()->create());

    $this->get(route('organizer.events.edit', $event))->assertForbidden();
});

test('update changes only the submitted fields', function () {
    $event = Event::factory()->create(['title' => '元のタイトル', 'venue' => '元の会場']);
    $this->actingAs($event->organizer);

    Livewire::test('pages::organizer.events.edit', ['event' => $event])
        ->set('form.title', '新しいタイトル')
        ->call('save')
        ->assertHasNoErrors();

    $fresh = $event->fresh();
    expect($fresh->title)->toBe('新しいタイトル')
        ->and($fresh->venue)->toBe('元の会場')
        ->and($fresh->status)->toBe(EventStatus::Draft)
        ->and($fresh->organizer_id)->toBe($event->organizer_id);
});

test('other organizers cannot open the edit component and the event stays unchanged', function () {
    $event = Event::factory()->create(['title' => '元のタイトル']);
    $this->actingAs(User::factory()->organizer()->create());

    Livewire::test('pages::organizer.events.edit', ['event' => $event])->assertForbidden();

    expect($event->fresh()->title)->toBe('元のタイトル');
});

test('ends_at boundary: one minute after starts_at is valid, equal or earlier is invalid', function (string $endsAt, bool $valid) {
    $event = Event::factory()->create();
    $this->actingAs($event->organizer);

    $component = Livewire::test('pages::organizer.events.edit', ['event' => $event])
        ->set('form.starts_at', '2030-01-01T10:00')
        ->set('form.ends_at', $endsAt)
        ->call('save');

    if ($valid) {
        $component->assertHasNoErrors();
        expect($event->fresh()->ends_at->format('Y-m-d H:i'))->toBe('2030-01-01 10:01');
    } else {
        $component->assertHasErrors(['form.ends_at' => 'after']);
        expect($event->fresh()->starts_at->format('Y-m-d H:i'))->not->toBe('2030-01-01 10:00');
    }
})->with([
    'one minute after' => ['2030-01-01T10:01', true],
    'same time' => ['2030-01-01T10:00', false],
    'one minute before' => ['2030-01-01T09:59', false],
    'previous day' => ['2029-12-31T10:00', false],
]);

test('ends_at is required', function () {
    $this->actingAs(User::factory()->organizer()->create());

    Livewire::test('pages::organizer.events.create')
        ->set([
            'form.title' => 'タイトル',
            'form.description' => '説明',
            'form.venue' => '会場',
            'form.starts_at' => '2030-01-01T10:00',
            'form.ends_at' => '',
        ])
        ->call('save')
        ->assertHasErrors(['form.ends_at' => 'required']);

    expect(Event::count())->toBe(0);
});

test('invalid update does not change the stored event', function () {
    $event = Event::factory()->create(['title' => '元のタイトル']);
    $this->actingAs($event->organizer);

    Livewire::test('pages::organizer.events.edit', ['event' => $event])
        ->set('form.title', '')
        ->call('save')
        ->assertHasErrors(['form.title' => 'required']);

    expect($event->fresh()->title)->toBe('元のタイトル');
});

test('a published event can be returned to draft without touching other fields', function () {
    $event = Event::factory()->published()->create(['title' => '公開中']);
    $this->actingAs($event->organizer);

    Livewire::test('pages::organizer.events.edit', ['event' => $event])
        ->call('togglePublish')
        ->assertHasNoErrors();

    $fresh = $event->fresh();
    expect($fresh->status)->toBe(EventStatus::Draft)
        ->and($fresh->title)->toBe('公開中');
});

test('other organizers cannot publish an event they do not own', function () {
    $event = Event::factory()->create();
    $this->actingAs(User::factory()->organizer()->create());

    Livewire::test('pages::organizer.events.edit', ['event' => $event])->assertForbidden();

    expect($event->fresh()->status)->toBe(EventStatus::Draft);
});
