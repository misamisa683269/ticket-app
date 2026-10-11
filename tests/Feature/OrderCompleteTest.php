<?php

use App\Models\Order;
use App\Models\User;

test('the buyer can see the complete page', function () {
    $order = Order::factory()->create(['total_amount' => 7500]);

    $this->actingAs($order->user)
        ->get(route('orders.complete', $order))
        ->assertOk()
        ->assertSee('注文を受け付けました')
        ->assertSee('7,500円');
});

test('other users cannot see the complete page', function () {
    $order = Order::factory()->create();

    $this->actingAs(User::factory()->create())
        ->get(route('orders.complete', $order))
        ->assertForbidden();
});

test('guests are sent to the login page from the complete page', function () {
    $order = Order::factory()->create();

    $this->get(route('orders.complete', $order))
        ->assertRedirect(route('login'));
});
