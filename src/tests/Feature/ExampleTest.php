<?php

use Tests\TestCase;

uses(TestCase::class);

test('the application returns a successful response', function (): void {
    $response = $this->get('/');

    $response->assertStatus(200);
});
