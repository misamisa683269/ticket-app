<?php

use App\Enums\UserRole;
use App\Models\User;
use Laravel\Fortify\Features;

beforeEach(function () {
    $this->skipUnlessFortifyHas(Features::registration());
});

test('registration screen can be rendered', function () {
    $response = $this->get(route('register'));

    $response->assertOk();
});

test('new users can register', function () {
    $response = $this->post(route('register.store'), [
        'name' => 'John Doe',
        'email' => 'test@example.com',
        'password' => 'password',
        'password_confirmation' => 'password',
        'role' => 'customer',
    ]);

    $response->assertSessionHasNoErrors()
        ->assertRedirect(route('dashboard', absolute: false));

    $this->assertAuthenticated();
    expect(User::firstWhere('email', 'test@example.com')->role)->toBe(UserRole::Customer);
});

test('new users can register as an organizer', function () {
    $response = $this->post(route('register.store'), [
        'name' => 'Jane Doe',
        'email' => 'organizer@example.com',
        'password' => 'password',
        'password_confirmation' => 'password',
        'role' => 'organizer',
    ]);

    $response->assertSessionHasNoErrors()
        ->assertRedirect(route('dashboard', absolute: false));

    $this->assertAuthenticated();
    expect(User::firstWhere('email', 'organizer@example.com')->role)->toBe(UserRole::Organizer);
});

test('new users cannot register as an admin', function () {
    $response = $this->post(route('register.store'), [
        'name' => 'Mallory',
        'email' => 'admin@example.com',
        'password' => 'password',
        'password_confirmation' => 'password',
        'role' => 'admin',
    ]);

    $response->assertSessionHasErrors('role');

    $this->assertGuest();
    $this->assertDatabaseMissing('users', ['email' => 'admin@example.com']);
});

test('an invalid role shows an error message on the registration screen', function () {
    $response = $this->from(route('register'))
        ->followingRedirects()
        ->post(route('register.store'), [
            'name' => 'Mallory',
            'email' => 'mallory@example.com',
            'password' => 'password',
            'password_confirmation' => 'password',
            'role' => 'unknown',
        ]);

    $response->assertOk()
        ->assertSee(__('validation.enum', ['attribute' => __('validation.attributes.role')]));
});
