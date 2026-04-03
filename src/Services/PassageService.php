<?php

namespace Morcen\Passage\Services;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Http\Request;

class PassageService implements PassageServiceInterface
{
    // Fallback hop-by-hop list used when config is not available (e.g. early bootstrap).
    private const HOP_BY_HOP_FALLBACK = [
        'host', 'connection', 'transfer-encoding', 'upgrade',
        'keep-alive', 'te', 'trailer', 'proxy-authenticate',
    ];

    public function callService(Request $request, PendingRequest $service, string $uri): Response
    {
        $method = strtolower($request->method());
        $service = $service->withHeaders($this->resolveForwardedHeaders($request));

        if (in_array($method, ['get', 'head'])) {
            return $service->{$method}($uri, $request->query());
        }

        // Always forward query params separately from the body.
        if (count($request->query()) > 0) {
            $service = $service->withQueryParameters($request->query());
        }

        if ($request->isJson()) {
            return $service
                ->withBody($request->getContent(), 'application/json')
                ->{$method}($uri);
        }

        if (count($request->allFiles()) > 0) {
            foreach ($request->allFiles() as $key => $file) {
                $service = $service->attach(
                    $key,
                    $file->get(),
                    $file->getClientOriginalName(),
                    ['Content-Type' => $file->getMimeType()]
                );
            }

            return $service->{$method}($uri, $request->post());
        }

        $contentType = $request->header('Content-Type', '');

        if (str_contains($contentType, 'application/x-www-form-urlencoded')) {
            return $service->asForm()->{$method}($uri, $request->post());
        }

        $body = $request->getContent();

        if ($body !== '') {
            return $service
                ->withBody($body, $contentType ?: 'application/octet-stream')
                ->{$method}($uri);
        }

        return $service->{$method}($uri);
    }

    private function resolveForwardedHeaders(Request $request): array
    {
        $hopByHop = config('passage.security.hop_by_hop_headers', self::HOP_BY_HOP_FALLBACK);
        $headers = [];

        foreach ($request->headers->all() as $name => $values) {
            if (in_array(strtolower($name), $hopByHop, strict: true)) {
                continue;
            }
            // Convert to HTTP title-case (e.g. authorization → Authorization,
            // x-request-id → X-Request-Id) so header key lookups work correctly.
            $titleName = implode('-', array_map('ucfirst', explode('-', $name)));
            $headers[$titleName] = implode(', ', $values);
        }

        return $headers;
    }
}
