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
