<?php

namespace Morcen\Passage\Http;

use Illuminate\Http\Client\Response;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

class PassageResponseBuilder
{
    // Fallback hop-by-hop list used when config is not available (e.g. early bootstrap).
    private const HOP_BY_HOP_FALLBACK = [
        'connection', 'transfer-encoding', 'upgrade',
        'keep-alive', 'te', 'trailer', 'proxy-authenticate',
        'proxy-authorization',
    ];

    public function build(Response $upstream): SymfonyResponse
    {
        $status = $upstream->status();
        $body = $upstream->body();
        $headers = $this->resolveResponseHeaders($upstream);
        $contentType = $upstream->header('Content-Type');

        if (str_contains($contentType, 'application/json')) {
            return response()->json(
                $upstream->json() ?? $body,
                $status,
                $headers
            );
        }

        $response = response($body, $status);

        foreach ($headers as $name => $value) {
            $response->header($name, $value);
        }

        return $response;
    }

    private function resolveResponseHeaders(Response $upstream): array
    {
        $hopByHop = config('passage.security.hop_by_hop_headers', self::HOP_BY_HOP_FALLBACK);
        $headers = [];

        foreach ($upstream->headers() as $name => $values) {
            if (in_array(strtolower($name), $hopByHop, strict: true)) {
                continue;
            }
            $headers[$name] = is_array($values) ? implode(', ', $values) : $values;
        }

        return $headers;
    }
}
