<?php

use Illuminate\Http\Request;
use Morcen\Passage\Support\ForwardedHeaderResolver;

describe('ForwardedHeaderResolver::HOP_BY_HOP_FALLBACK', function () {
    it('matches the published config default for hop_by_hop_headers', function () {
        expect(ForwardedHeaderResolver::HOP_BY_HOP_FALLBACK)
            ->toBe(config('passage.security.hop_by_hop_headers'));
    });

    it('includes proxy-authorization, since it is also a hop-by-hop header', function () {
        expect(ForwardedHeaderResolver::HOP_BY_HOP_FALLBACK)->toContain('proxy-authorization');
    });
});

describe('ForwardedHeaderResolver::resolve()', function () {
    it('strips Proxy-Authorization from the forwarded request when config is unavailable', function () {
        $security = config('passage.security');
        unset($security['hop_by_hop_headers']);
        config(['passage.security' => $security]);

        $request = Request::create('/test', 'GET');
        $request->headers->set('Proxy-Authorization', 'Basic secret-credentials');
        $request->headers->set('X-Custom', 'keep-me');

        $headers = ForwardedHeaderResolver::resolve($request);

        expect($headers)->not->toHaveKey('Proxy-Authorization');
        expect($headers)->toHaveKey('X-Custom');
    });
});

describe('ForwardedHeaderResolver::forwardedHeaders()', function () {
    it('sets X-Forwarded-For to the client IP when no chain exists yet', function () {
        $request = Request::create('/test', 'GET', server: ['REMOTE_ADDR' => '203.0.113.5']);

        $headers = ForwardedHeaderResolver::forwardedHeaders($request);

        expect($headers['X-Forwarded-For'])->toBe('203.0.113.5');
    });

    it('appends the client IP to an existing X-Forwarded-For chain instead of replacing it', function () {
        $request = Request::create('/test', 'GET', server: ['REMOTE_ADDR' => '203.0.113.5']);
        $request->headers->set('X-Forwarded-For', '198.51.100.1');

        $headers = ForwardedHeaderResolver::forwardedHeaders($request);

        expect($headers['X-Forwarded-For'])->toBe('198.51.100.1, 203.0.113.5');
    });

    it('sets X-Forwarded-Host to the original request host', function () {
        $request = Request::create('http://gateway.example.com/test', 'GET');

        $headers = ForwardedHeaderResolver::forwardedHeaders($request);

        expect($headers['X-Forwarded-Host'])->toBe('gateway.example.com');
    });

    it('sets X-Forwarded-Proto to the request scheme', function () {
        $request = Request::create('https://gateway.example.com/test', 'GET');

        $headers = ForwardedHeaderResolver::forwardedHeaders($request);

        expect($headers['X-Forwarded-Proto'])->toBe('https');
    });
});
