<?php

use App\Models\Event;
use App\Models\Order;
use App\Models\TicketType;
use App\Models\User;
use Livewire\Features\SupportLockedProperties\CannotUpdateLockedPropertyException;
use Livewire\Livewire;

beforeEach(function () {
    $this->event = Event::factory()->published()->create();
    $this->general = TicketType::factory()->for($this->event)->create(['name' => '一般', 'price' => 3000, 'capacity' => 50]);
    $this->student = TicketType::factory()->for($this->event)->create(['name' => '学生', 'price' => 1500, 'capacity' => 50]);
});

test('selected quantities are saved to the session and lead to the purchase page', function () {
    $this->actingAs(User::factory()->create());

    Livewire::test('pages::events.show', ['event' => $this->event])
        ->set("quantities.{$this->general->id}", '2')
        ->set("quantities.{$this->student->id}", '1')
        ->call('proceed')
        ->assertRedirect(route('events.purchase', $this->event));

    expect(session("purchase.{$this->event->id}"))->toBe([
        $this->general->id => 2,
        $this->student->id => 1,
    ]);
});

test('proceeding without any ticket shows an error', function () {
    Livewire::test('pages::events.show', ['event' => $this->event])
        ->set("quantities.{$this->general->id}", '0')
        ->call('proceed')
        ->assertHasErrors('quantities')
        ->assertNoRedirect();
});

test('ticket types not on sale cannot be selected', function () {
    TicketType::factory()->for($this->event)->notOnSale()->create(['name' => '早割']);

    $this->get(route('events.show', $this->event))
        ->assertSee('販売期間外')
        ->assertSee('aria-label="早割の枚数" disabled', false);
});

test('guests are sent to the login page and back to the purchase page after logging in', function () {
    $user = User::factory()->create();

    $this->withSession(["purchase.{$this->event->id}" => [$this->general->id => 2]])
        ->get(route('events.purchase', $this->event))
        ->assertRedirect(route('login'));

    $this->post(route('login.store'), ['email' => $user->email, 'password' => 'password'])
        ->assertRedirect(route('events.purchase', $this->event));
});

test('purchase page shows the selected tickets with subtotals and total', function () {
    $this->actingAs(User::factory()->create())
        ->withSession(["purchase.{$this->event->id}" => [$this->general->id => 2, $this->student->id => 1]])
        ->get(route('events.purchase', $this->event))
        ->assertOk()
        ->assertSeeInOrder(['一般', '3,000円', '2枚', '6,000円'])
        ->assertSeeInOrder(['学生', '1,500円', '1枚', '1,500円'])
        ->assertSeeInOrder(['合計', '7,500円']);
});

test('purchase page redirects back to the event when nothing is selected', function () {
    $this->actingAs(User::factory()->create())
        ->get(route('events.purchase', $this->event))
        ->assertRedirect(route('events.show', $this->event));
});

test('paying creates the order, clears the session and shows the complete page', function () {
    $user = User::factory()->create();
    $this->actingAs($user);
    session()->put("purchase.{$this->event->id}", [$this->general->id => 2, $this->student->id => 1]);

    $component = Livewire::test('pages::events.purchase', ['event' => $this->event])->call('pay');

    $order = Order::sole();

    $component->assertHasNoErrors()->assertRedirect(route('orders.complete', $order));

    expect($order->user_id)->toBe($user->id)
        ->and($order->total_amount)->toBe(7500)
        ->and(session()->has("purchase.{$this->event->id}"))->toBeFalse();
});

test('purchase errors are shown and no order is created', function () {
    $this->actingAs(User::factory()->create());
    $this->general->update(['capacity' => 1]);
    session()->put("purchase.{$this->event->id}", [$this->general->id => 2]);

    Livewire::test('pages::events.purchase', ['event' => $this->event])
        ->call('pay')
        ->assertHasErrors('purchase')
        ->assertSee('一般の残りは1枚です。')
        ->assertNoRedirect();

    expect(Order::count())->toBe(0);
});

test('paying with a zero quantity in the session shows an error and no order is created', function () {
    $this->actingAs(User::factory()->create());
    session()->put("purchase.{$this->event->id}", [$this->general->id => 0]);

    Livewire::test('pages::events.purchase', ['event' => $this->event])
        ->call('pay')
        ->assertHasErrors('purchase')
        ->assertSee('一般の枚数は1〜10枚で指定してください。')
        ->assertNoRedirect();

    expect(Order::count())->toBe(0);
});

test('paying with a ticket type of another event shows an error and no order is created', function () {
    $otherTicketType = TicketType::factory()->for(Event::factory()->published())->create(['name' => '他イベント券']);
    $this->actingAs(User::factory()->create());
    session()->put("purchase.{$this->event->id}", [$otherTicketType->id => 1]);

    Livewire::test('pages::events.purchase', ['event' => $this->event])
        ->assertDontSee('他イベント券')
        ->call('pay')
        ->assertHasErrors('purchase')
        ->assertSee('選択された券種が見つかりません。')
        ->assertNoRedirect();

    expect(Order::count())->toBe(0);
});

test('nobody can open or pay the purchase page of a draft event, not even its organizer', function () {
    $draft = Event::factory()->create();
    $ticketType = TicketType::factory()->for($draft)->create();
    $this->actingAs($draft->organizer);
    session()->put("purchase.{$draft->id}", [$ticketType->id => 1]);

    $this->get(route('events.purchase', $draft))->assertForbidden();

    Livewire::test('pages::events.purchase', ['event' => $draft])->assertForbidden();

    expect(Order::count())->toBe(0);
});

test('paying twice creates only one order', function () {
    $this->actingAs(User::factory()->create());
    session()->put("purchase.{$this->event->id}", [$this->general->id => 2]);

    Livewire::test('pages::events.purchase', ['event' => $this->event])
        ->call('pay')
        ->call('pay')
        ->assertHasErrors('purchase');

    expect(Order::count())->toBe(1);
});

test('the quantities cannot be changed from the browser', function () {
    $this->actingAs(User::factory()->create());
    session()->put("purchase.{$this->event->id}", [$this->general->id => 2]);

    Livewire::test('pages::events.purchase', ['event' => $this->event])
        ->set('quantities', [$this->general->id => 10]);
})->throws(CannotUpdateLockedPropertyException::class);
