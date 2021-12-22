<?php

use function Pest\Laravel\get;
use function Pest\Laravel\post;
use function Pest\Laravel\put;

beforeEach(function() {
    setupApiX();
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

it('updates user data', function() {
    
    \Illuminate\Support\Facades\DB::table('user')->insert([
        'username' => $username = 'teesting',
        'email' => 'testing@testing.testing',
        'api_key' => $apiKey = \Illuminate\Support\Str::random(32)
    ]);
    
    $user = \Illuminate\Support\Facades\DB::table('user')->where('username', $username)->first();
    
    $response = put('/api/v1.0/user/' . $user->id . '?api_key=' . $apiKey, [
        'username' => 'updated',
    ]);
    
    $response->assertOk();
    $response->assertJsonFragment([
        'username' => 'updated'
    ]);
});

it('creates user', function() {
    
    $post = post('/api/v1.0/user', [
        'username' => $username = 'teesting',
        'email' => 'testing@testing.testing',
    ]);
    
    $post->assertOk();
    $post->assertJsonFragment([
        'username' => $username
    ]);
    
    /** @var stdClass $user */
    $user = \Illuminate\Support\Facades\DB::table('user')->where('username', $username)->first();
    
    expect($user->username)->toBe($username);
});

