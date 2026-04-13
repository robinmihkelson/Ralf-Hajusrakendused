<?php

use App\Models\Car;
use App\Models\User;

test('cars api requires session or api key', function () {
    $response = $this->getJson('/api/cars');

    $response
        ->assertUnauthorized()
        ->assertJson([
            'error' => 'Authentication required. Use a logged-in session or a Cars API key.',
        ]);
});

test('authenticated users can generate a cars api key', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user)->postJson('/api/cars/keys', [
        'name' => 'Integration key',
    ]);

    $response
        ->assertCreated()
        ->assertJsonPath('key.name', 'Integration key');

    expect($response->json('api_key'))
        ->toBeString()
        ->toStartWith('cars_');

    $this->assertDatabaseHas('cars_api_keys', [
        'user_id' => $user->id,
        'name' => 'Integration key',
    ]);
});

test('cars api key can be used to list cars', function () {
    $user = User::factory()->create();
    $otherUser = User::factory()->create();

    Car::query()->create([
        'user_id' => $user->id,
        'title' => 'Owner car',
        'image' => 'https://example.com/owner-car.jpg',
        'description' => 'Owner description',
        'brand' => 'BMW',
        'production_year' => 2024,
        'horsepower' => 500,
    ]);

    Car::query()->create([
        'user_id' => $otherUser->id,
        'title' => 'Other car',
        'image' => 'https://example.com/other-car.jpg',
        'description' => 'Other description',
        'brand' => 'Audi',
        'production_year' => 2023,
        'horsepower' => 450,
    ]);

    $createKeyResponse = $this->actingAs($user)->postJson('/api/cars/keys', [
        'name' => 'Reader',
    ]);

    $apiKey = $createKeyResponse->json('api_key');

    $response = $this->withHeaders([
        'Authorization' => 'Bearer '.$apiKey,
    ])->getJson('/api/cars?scope=mine');

    $response
        ->assertOk()
        ->assertJsonPath('meta.scope', 'mine')
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.title', 'Owner car');
});

test('cars api key can be used to create cars', function () {
    $user = User::factory()->create();

    $createKeyResponse = $this->actingAs($user)->postJson('/api/cars/keys');
    $apiKey = $createKeyResponse->json('api_key');

    $response = $this->withHeaders([
        'X-API-Key' => $apiKey,
    ])->postJson('/api/cars', [
        'title' => 'BMW M3',
        'image' => 'https://example.com/m3.jpg',
        'description' => 'Sports sedan',
        'brand' => 'BMW',
        'production_year' => 2024,
        'horsepower' => 503,
    ]);

    $response->assertCreated();

    $this->assertDatabaseHas('cars', [
        'user_id' => $user->id,
        'title' => 'BMW M3',
    ]);
});
