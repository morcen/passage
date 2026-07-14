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
    // Fallback hop-by-hop list used when config is not available (e.g. early bootstrap).
    private const HOP_BY_HOP_FALLBACK = [
        'host', 'connection', 'transfer-encoding', 'upgrade',
        'keep-alive', 'te', 'trailer', 'proxy-authenticate',
    ];

    public static function resolve(Request $request): array
    {
        $hopByHop = config('passage.security.hop_by_hop_headers', self::HOP_BY_HOP_FALLBACK);
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
}
