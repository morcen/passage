<?php

use GuzzleHttp\Psr7\Response as Psr7Response;
use Illuminate\Http\Client\Response;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Morcen\Passage\Events\PassageRequestFailed;
use Morcen\Passage\Events\PassageRequestSending;
use Morcen\Passage\Events\PassageResponseReceived;
use Morcen\Passage\Listeners\PassageEventSubscriber;

describe('PassageEventSubscriber', function () {
    it('logs a debug message when a request is sending', function () {
        $request = Request::create('/proxy/users', 'POST');
        $event = new PassageRequestSending(
            $request,
            'App\\Http\\Controllers\\Passages\\UsersHandler',
            'https://api.example.com/users',
            microtime(true),
        );

        Log::shouldReceive('channel')
            ->once()
            ->with('passage')
            ->andReturnSelf();
        Log::shouldReceive('debug')
            ->once()
            ->with('Passage: forwarding request', [
                'handler' => 'App\\Http\\Controllers\\Passages\\UsersHandler',
                'uri' => 'https://api.example.com/users',
                'method' => 'POST',
            ]);

        (new PassageEventSubscriber)->onRequestSending($event);
    });

    it('logs an info message when a response is received', function () {
        $request = Request::create('/proxy/users', 'GET');
        $response = new Response(new Psr7Response(202, ['Content-Type' => 'application/json'], '{"ok":true}'));
        $event = new PassageResponseReceived(
            $request,
            $response,
            'App\\Http\\Controllers\\Passages\\UsersHandler',
            18.236,
            true,
        );

        Log::shouldReceive('channel')
            ->once()
            ->with('passage')
            ->andReturnSelf();
        Log::shouldReceive('info')
            ->once()
            ->with('Passage: response received', [
                'handler' => 'App\\Http\\Controllers\\Passages\\UsersHandler',
                'status' => 202,
                'duration_ms' => 18.24,
                'cached' => true,
            ]);

        (new PassageEventSubscriber)->onResponseReceived($event);
    });

    it('logs an error message when a request fails', function () {
        $request = Request::create('/proxy/users', 'GET');
        $event = new PassageRequestFailed(
            $request,
            'App\\Http\\Controllers\\Passages\\UsersHandler',
            new RuntimeException('Upstream timeout'),
            42.678,
        );

        Log::shouldReceive('channel')
            ->once()
            ->with('passage')
            ->andReturnSelf();
        Log::shouldReceive('error')
            ->once()
            ->with('Passage: request failed', [
                'handler' => 'App\\Http\\Controllers\\Passages\\UsersHandler',
                'error' => 'Upstream timeout',
                'duration_ms' => 42.68,
            ]);

        (new PassageEventSubscriber)->onRequestFailed($event);
    });
});
