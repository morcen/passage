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
            $service = $service->withBody($request->getContent(), 'application/json');

            return $this->dispatch($service, $method, $uri);
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

            return $this->dispatch($service, $method, $uri, $request->post(), 'multipart');
        }

        $contentType = $request->header('Content-Type', '');

        if (str_contains($contentType, 'application/x-www-form-urlencoded')) {
            return $this->dispatch($service->asForm(), $method, $uri, $request->post(), 'form_params');
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
