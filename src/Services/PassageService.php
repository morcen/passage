<?php

namespace Morcen\Passage\Services;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Http\Request;
use Morcen\Passage\Support\ForwardedHeaderResolver;
use Morcen\Passage\Support\NestedFileResolver;

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
            $service = $service->withBody($request->getContent(), $request->header('Content-Type', 'application/json'));

            return $this->dispatch($service, $method, $uri);
        }

        $contentType = $request->header('Content-Type', '');

        // PHP consumes php://input while populating $_POST/$_FILES for
        // multipart/form-data requests, so getContent() is always empty for
        // them — even when the request has no file fields, only text ones.
        // Detect the content type directly rather than relying on
        // allFiles() being non-empty, so plain multipart forms aren't
        // dropped into the raw-passthrough branch below with no body.
        if (count($request->allFiles()) > 0 || str_contains(strtolower($contentType), 'multipart/form-data')) {
            foreach (NestedFileResolver::flatten($request->allFiles()) as $key => $singleFile) {
                $service = $service->attach(
                    $key,
                    $singleFile->get(),
                    $singleFile->getClientOriginalName(),
                    ['Content-Type' => $singleFile->getMimeType()]
                );
            }

            return $this->dispatch($service, $method, $uri, $request->post(), 'multipart');
        }

        if (str_contains($contentType, 'application/x-www-form-urlencoded')) {
            // Forward the raw body verbatim instead of re-encoding $request->post()
            // via asForm(): the parsed array has already been url-decoded and, for
            // duplicate keys, collapsed to the last value, so re-encoding it can
            // produce bytes that differ from what HasHmacAuth::resolveHmacSignedBody()
            // signed (which signs the raw body for non-multipart requests).
            $service = $service->withBody($request->getContent(), $contentType);

            return $this->dispatch($service, $method, $uri);
        }

        $body = $request->getContent();

        if ($body !== '') {
            $service = $service->withBody($body, $contentType ?: 'application/octet-stream');

            return $this->dispatch($service, $method, $uri);
        }

        return $this->dispatch($service, $method, $uri);
    }

    /**
     * Dispatch the outbound call. `PendingRequest` only defines magic verb
     * methods for get/head/post/put/patch/delete — there is no `options()`
     * method and no macro registered for it, so an OPTIONS request (e.g. a
     * CORS preflight routed through `Passage::any()`) is sent via Guzzle's
     * generic `send()` method instead of the undefined magic call.
     */
    private function dispatch(PendingRequest $service, string $method, string $uri, array $data = [], ?string $optionKey = null): Response
    {
        if ($method !== 'options') {
            return $optionKey === null ? $service->{$method}($uri) : $service->{$method}($uri, $data);
        }

        return $service->send('OPTIONS', $uri, $optionKey === null ? [] : [$optionKey => $data]);
    }
}
