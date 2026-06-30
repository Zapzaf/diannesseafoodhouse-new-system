<?php

use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('serves the login page with cache protection and autofill-safe fields', function () {
    $response = $this->get(route('login'))
        ->assertOk()
        ->assertSee('autocomplete="username"', false)
        ->assertSee('autocomplete="current-password"', false)
        ->assertSee('data-login-form', false)
        ->assertSee('csrf-token', false);

    expect($response->headers->get('Cache-Control'))
        ->toContain('no-store')
        ->toContain('no-cache')
        ->toContain('must-revalidate')
        ->toContain('max-age=0');
});

it('returns a fresh csrf token for restored login forms', function () {
    $response = $this->getJson(route('csrf-token'))
        ->assertOk()
        ->assertJsonStructure(['token']);

    expect($response->headers->get('Cache-Control'))
        ->toContain('no-store')
        ->toContain('no-cache')
        ->toContain('must-revalidate')
        ->toContain('max-age=0');
});
