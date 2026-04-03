<?php

namespace Morcen\Passage\Http;

use Illuminate\Http\Client\Response;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

class PassageResponseBuilder
{
    // Hop-by-hop headers that must not be forwarded from upstream to client.
    private const HOP_BY_HOP_HEADERS = [
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
        $headers = [];

        foreach ($upstream->headers() as $name => $values) {
            if (in_array(strtolower($name), self::HOP_BY_HOP_HEADERS, strict: true)) {
                continue;
            }
            $headers[$name] = is_array($values) ? implode(', ', $values) : $values;
        }

        return $headers;
    }
}
