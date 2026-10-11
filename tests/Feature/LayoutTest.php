<?php

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Testing\TestView;

function renderAppLayout(): TestView
{
    return test()->blade('<x-layouts::app>本文</x-layouts::app>');
}

test('guests can see the layout with login and register links', function () {
    renderAppLayout()
        ->assertSee('イベント一覧')
        ->assertSee('ログイン')
        ->assertSee('会員登録')
        ->assertDontSee('マイチケット')
        ->assertDontSee('ログアウト');
});

test('navigation changes by role', function (UserRole $role, array $visible, array $hidden) {
    $this->actingAs(User::factory()->create(['role' => $role]));

    $view = renderAppLayout()
        ->assertSee(['イベント一覧', 'マイチケット', '設定', 'ログアウト'])
        ->assertDontSee('会員登録');

    foreach ($visible as $label) {
        $view->assertSee($label);
    }

    foreach ($hidden as $label) {
        $view->assertDontSee($label);
    }
})->with([
    'customer' => [UserRole::Customer, [], ['主催者メニュー', 'イベント管理', 'ユーザー管理']],
    'organizer' => [UserRole::Organizer, ['主催者メニュー'], ['イベント管理', 'ユーザー管理']],
    'admin' => [UserRole::Admin, ['イベント管理', 'ユーザー管理'], ['主催者メニュー']],
]);
