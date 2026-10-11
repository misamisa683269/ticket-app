<?php

use App\Models\Event;
use App\Models\TicketType;
use App\Models\User;

test('guests can see published upcoming events', function () {
    Event::factory()->published()->create(['title' => '盛岡じゃじゃ麺まつり']);

    $this->get(route('home'))
        ->assertOk()
        ->assertSee('盛岡じゃじゃ麺まつり')
        ->assertSee('ログイン');
});

test('logged in users can also see the list', function () {
    Event::factory()->published()->create(['title' => '盛岡じゃじゃ麺まつり']);
    $this->actingAs(User::factory()->create());

    $this->get(route('home'))->assertOk()->assertSee('盛岡じゃじゃ麺まつり');
});

test('drafts, unpublished and ended events are not listed', function () {
    Event::factory()->create(['title' => '下書きのイベント']);
    Event::factory()->unpublished()->create(['title' => '非公開のイベント']);
    Event::factory()->published()->past()->create(['title' => '終了したイベント']);

    $this->get(route('home'))
        ->assertOk()
        ->assertDontSee('下書きのイベント')
        ->assertDontSee('非公開のイベント')
        ->assertDontSee('終了したイベント');
});

test('ongoing events are still listed', function () {
    Event::factory()->published()->create([
        'title' => '開催中のイベント',
        'starts_at' => now()->subHour(),
        'ends_at' => now()->addHour(),
    ]);

    $this->get(route('home'))->assertSee('開催中のイベント');
});

test('events are ordered by start time', function () {
    Event::factory()->published()->create(['title' => '後のイベント', 'starts_at' => now()->addWeeks(2), 'ends_at' => now()->addWeeks(2)->addHours(2)]);
    Event::factory()->published()->create(['title' => '先のイベント', 'starts_at' => now()->addWeek(), 'ends_at' => now()->addWeek()->addHours(2)]);

    $this->get(route('home'))->assertSeeInOrder(['先のイベント', '後のイベント']);
});

test('the cheapest price is shown', function () {
    $paid = Event::factory()->published()->create();
    TicketType::factory()->for($paid)->create(['price' => 3000]);
    TicketType::factory()->for($paid)->create(['price' => 1500]);

    $free = Event::factory()->published()->create();
    TicketType::factory()->for($free)->create(['price' => 0]);

    Event::factory()->published()->create();

    $this->get(route('home'))
        ->assertSee('1,500円〜')
        ->assertDontSee('3,000円〜')
        ->assertSee('無料')
        ->assertSee('価格未定');
});

test('events are paginated by 12', function () {
    Event::factory()->published()->count(12)->sequence(fn ($sequence) => [
        'starts_at' => now()->addDays($sequence->index + 1),
        'ends_at' => now()->addDays($sequence->index + 1)->addHours(2),
    ])->create();
    Event::factory()->published()->create([
        'title' => '13番目のイベント',
        'starts_at' => now()->addMonths(6),
        'ends_at' => now()->addMonths(6)->addHours(2),
    ]);

    $this->get(route('home'))->assertDontSee('13番目のイベント');
    $this->get(route('home', ['page' => 2]))->assertSee('13番目のイベント');
});
