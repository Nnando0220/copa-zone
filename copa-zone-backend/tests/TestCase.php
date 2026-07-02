<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Testing\TestResponse;

abstract class TestCase extends BaseTestCase
{
    /**
     * @param array<string, mixed> $data
     * @param array<string, string> $headers
     */
    protected function postJsonWithCsrf(string $uri, array $data = [], array $headers = []): TestResponse
    {
        $token = 'test-csrf-token';

        return $this
            ->withSession(['_token' => $token])
            ->withHeader('X-CSRF-TOKEN', $token)
            ->postJson($uri, $data, $headers);
    }
}
