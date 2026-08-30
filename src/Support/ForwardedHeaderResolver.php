<?php

namespace Morcen\Passage\Support;

use Illuminate\Http\Request;

/**
 * Resolves the set of headers Passage forwards upstream for a given request,
 * stripping hop-by-hop headers per RFC 7230.
 *
 * Shared by PassageService (to build the outbound request) and
 * PassageCacheManager (to key cached responses by the credentials/identity
 * headers that were actually sent), so the two never disagree about what
 * was forwarded.
 */
class ForwardedHeaderResolver
{
    // Hop-by-hop headers are always stripped and are not configurable (RFC 7230).
    // Public so PassageResponseBuilder can share it instead of maintaining its own copy.
    public const HOP_BY_HOP_HEADERS = [
        'host', 'connection', 'transfer-encoding', 'upgrade',
        'keep-alive', 'te', 'trailer', 'proxy-authenticate',
        'proxy-authorization',
    ];

    public static function resolve(Request $request): array
    {
        $hopByHop = self::HOP_BY_HOP_HEADERS;
        $headers = [];

        foreach ($request->headers->all() as $name => $values) {
            if (in_array(strtolower($name), $hopByHop, strict: true)) {
                continue;
            }
            // Convert to HTTP title-case (e.g. authorization → Authorization,
            // x-request-id → X-Request-Id) so header key lookups work correctly.
            $titleName = ucwords(strtolower($name), '-');
            $headers[$titleName] = implode(', ', $values);
        }

        return $headers;
    }

    /**
     * Standard proxy headers that tell the upstream service about the
     * original client, since Passage's own Host header is stripped as
     * hop-by-hop and the upstream otherwise only ever sees the gateway's
     * IP, host, and scheme. This breaks per-client rate limiting, geo
     * logic, and audit logging on services behind Passage.
     *
     * Appends to an existing X-Forwarded-For chain (e.g. one set by an
     * upstream load balancer) rather than replacing it, per convention.
     */
    public static function forwardedHeaders(Request $request): array
    {
        $chain = array_filter([$request->header('X-Forwarded-For'), $request->ip()]);

        return [
            'X-Forwarded-For' => implode(', ', $chain),
            'X-Forwarded-Host' => $request->getHost(),
            'X-Forwarded-Proto' => $request->getScheme(),
        ];
    }
}
