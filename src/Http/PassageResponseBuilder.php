<?php

namespace Morcen\Passage\Http;

use Illuminate\Http\Client\Response;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;

class PassageResponseBuilder
{
    // Fallback hop-by-hop list used when config is not available (e.g. early bootstrap).
    private const HOP_BY_HOP_FALLBACK = [
        'host', 'connection', 'transfer-encoding', 'upgrade',
        'keep-alive', 'te', 'trailer', 'proxy-authenticate',
        'proxy-authorization',
    ];

    // Guzzle's default `decode_content` option transparently decompresses gzip/deflate/br
    // bodies (Passage never disables it), but leaves Content-Encoding/Content-Length on the
    // response describing the discarded compressed representation. Relaying them verbatim
    // alongside the already-decoded body corrupts the response for every compressing
    // upstream, so they must always be stripped rather than passed through.
    private const STALE_CONTENT_HEADERS = ['content-encoding', 'content-length'];

    public function build(Response $upstream): SymfonyResponse
    {
        $status = $upstream->status();
        $body = $upstream->body();
        $headers = $this->resolveResponseHeaders($upstream);
        $contentType = $upstream->header('Content-Type');

        if (str_contains($contentType, 'application/json') && $body !== '') {
            json_decode($body);

            if (json_last_error() === JSON_ERROR_NONE) {
                return JsonResponse::fromJsonString($body, $status, $headers);
            }
        }

        $response = response($body, $status);

        foreach ($headers as $name => $value) {
            $response->header($name, $value);
        }

        return $response;
    }

    /**
     * Build a StreamedResponse, reading from the upstream PSR-7 body stream.
     *
     * Note: The getResponse() handler hook is NOT called for streamed responses —
     * the body cannot be inspected before it is sent.
     */
    public function buildStreamedResponse(Response $upstream): StreamedResponse
    {
        $status = $upstream->status();
        $headers = $this->resolveResponseHeaders($upstream);
        $stream = $upstream->toPsrResponse()->getBody();

        $response = new StreamedResponse(
            function () use ($stream) {
                while (! $stream->eof()) {
                    echo $stream->read(4096);
                    flush();
                }
            },
            $status
        );

        foreach ($headers as $name => $value) {
            $response->headers->set($name, $value);
        }

        return $response;
    }

    private function resolveResponseHeaders(Response $upstream): array
    {
        $hopByHop = config('passage.security.hop_by_hop_headers', self::HOP_BY_HOP_FALLBACK);
        $headers = [];

        foreach ($upstream->headers() as $name => $values) {
            $lowerName = strtolower($name);

            if (in_array($lowerName, $hopByHop, strict: true) || in_array($lowerName, self::STALE_CONTENT_HEADERS, strict: true)) {
                continue;
            }
            $headers[$name] = is_array($values) ? implode(', ', $values) : $values;
        }

        return $headers;
    }
}
