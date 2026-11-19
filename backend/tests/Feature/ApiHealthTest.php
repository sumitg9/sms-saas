<?php

test('api v1 health endpoint returns successful response', function () {
    $response = $this->getJson('/api/v1/health');

    $response->assertStatus(200)
        ->assertJson([
            'success' => true,
            'message' => 'School ERP SaaS API is operational',
            'version' => 'v1',
        ])
        ->assertJsonStructure([
            'success',
            'message',
            'version',
            'timestamp',
        ]);
});

test('api returns standardized 404 error envelope for missing endpoints', function () {
    $response = $this->getJson('/api/v1/non-existent-route');

    $response->assertStatus(404)
        ->assertJson([
            'success' => false,
            'code' => 'NOT_FOUND',
            'message' => 'The requested resource was not found.',
        ])
        ->assertJsonStructure([
            'success',
            'code',
            'message',
            'timestamp',
        ]);
});

test('api returns standardized 401 error envelope when unauthenticated', function () {
    $response = $this->getJson('/api/v1/user');

    $response->assertStatus(401)
        ->assertJson([
            'success' => false,
            'code' => 'UNAUTHENTICATED',
        ])
        ->assertJsonStructure([
            'success',
            'code',
            'message',
            'timestamp',
        ]);
});

