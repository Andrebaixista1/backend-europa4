<?php

test('the ping api returns a successful response', function () {
    $this->getJson('/api/ping')
        ->assertOk()
        ->assertExactJson([
            'message' => 'API online',
        ]);
});
