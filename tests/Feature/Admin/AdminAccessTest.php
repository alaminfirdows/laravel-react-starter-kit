<?php

use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

test('admin opens the admin area', function () {
    $this->actingAs(User::factory()->admin()->create())
        ->get('/admin')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('admin/index')
            ->has('counts.tasks')
            ->where('auth.user.is_admin', true)
        );
});

test('non admin is forbidden', function () {
    $this->actingAs(User::factory()->create())
        ->get('/admin')
        ->assertForbidden();
});

test('guest is redirected to login', function () {
    $this->get('/admin')->assertRedirect('/login');
});

test('admin gate follows the is_admin flag', function () {
    expect(User::factory()->admin()->create()->can('admin'))->toBeTrue()
        ->and(User::factory()->create()->can('admin'))->toBeFalse();
});
