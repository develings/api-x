<?php

use function Pest\Laravel\get;
use function Pest\Laravel\post;
use function Pest\Laravel\put;

beforeEach(function() {
    setupApiX();
});

it('initialize api x and not fail', function() {
    $response = get('/api/v1.0/team');
    $response->assertStatus(200)
             ->assertJsonFragment(['current_page' => 1]);
});

it('updates team data', function() {
    
    \Illuminate\Support\Facades\DB::table('team')->insert([
        'name' => $name = 'teesting',
    ]);
    
    /** @var stdClass $team */
    $team = \Illuminate\Support\Facades\DB::table('team')->where('name', $name)->first();
    
    $user = createValidUser();
    
    $response = put('/api/v1.0/team/' . $team->id . '?api_key=' . $user->api_key, [
        'name' => $name . '.updated',
    ]);
    
    $response->assertOk();
    $response->assertJsonFragment([
        'name' => $name . '.updated'
    ]);
});

it('create team', function() {
    $user = createValidUser();
    
    $post = post('/api/v1.0/team?api_key=' . $user->api_key, [
        'name' => $name = 'teesting',
    ]);
    
    $post->assertOk();
    $post->assertJsonFragment([
        'name' => $name
    ]);
    
    $id = $post->json('id');
    
    /** @var stdClass $user */
    $team = \Illuminate\Support\Facades\DB::table('team')->find($id);
    
    expect($team->name)->toBe($name);
});

