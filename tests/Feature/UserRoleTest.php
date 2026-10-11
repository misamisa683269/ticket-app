<?php

use App\Enums\UserRole;
use App\Models\User;

test('role cannot be mass assigned', function () {
    $user = User::factory()->create();

    $user->update(['role' => 'admin']);

    expect($user->fresh()->role)->toBe(UserRole::Customer);
});
