<?php

use App\Models\User;
use Illuminate\Support\Facades\Route;

beforeEach(function () {
    Route::middleware(['web', 'auth', 'role:organizer'])
        ->get('/_test/organizer-only', fn () => 'ok');
});

test('users with the required role can access the route', function () {
    $this->actingAs(User::factory()->organizer()->create());

    $this->get('/_test/organizer-only')->assertOk();
});

test('users without the required role are forbidden', function () {
    $this->actingAs(User::factory()->create());

    $this->get('/_test/organizer-only')->assertForbidden();
});

test('admins are forbidden from organizer-only routes', function () {
    $this->actingAs(User::factory()->admin()->create());

    $this->get('/_test/organizer-only')->assertForbidden();
});

test('any of the listed roles can access a multi-role route', function () {
    Route::middleware(['web', 'auth', 'role:organizer,admin'])
        ->get('/_test/organizer-or-admin', fn () => 'ok');

    $this->actingAs(User::factory()->admin()->create());
    $this->get('/_test/organizer-or-admin')->assertOk();

    $this->actingAs(User::factory()->create());
    $this->get('/_test/organizer-or-admin')->assertForbidden();
});
