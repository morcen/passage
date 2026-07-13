<?php

namespace Morcen\Passage\Services;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Morcen\Passage\Support\ForwardedHeaderResolver;

class PassageService implements PassageServiceInterface
{
    public function callService(Request $request, PendingRequest $service, string $uri): Response
    {
        $method = strtolower($request->method());
        $service = $service->withHeaders(ForwardedHeaderResolver::resolve($request));

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
                foreach (Arr::wrap($file) as $index => $singleFile) {
                    $service = $service->attach(
                        is_array($file) ? "{$key}[{$index}]" : $key,
                        $singleFile->get(),
                        $singleFile->getClientOriginalName(),
                        ['Content-Type' => $singleFile->getMimeType()]
                    );
                }
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
}
