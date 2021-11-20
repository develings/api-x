<?php

use ApiX\Facade\ApiX;
use function Pest\Laravel\get;

beforeEach(function() {
    ApiX::load(__DIR__ . '/../../examples/simple.json');
    \Illuminate\Support\Facades\Artisan::call('api:migrate');
    
    ApiX::getInstance()->setRoutes();
});

it('should be able to run a test', function () {
    expect(true)->toBe(true);
});

it('initialize api x and not fail', function() {
    $response = get('/api/v1.0/user');
    $response->assertStatus(200)
             ->assertJsonFragment(['current_page' => 1]);
});


it('make sure not existing route fails with 403', function() {
    get('/api/v1.0/not')->assertStatus(403);
});

it('make sure not existing route fails with 404', function() {
    get('/api/v1/not')->assertStatus(404);
});
