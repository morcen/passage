<?php

namespace Morcen\Passage\Concerns;

use Illuminate\Http\Request;

trait HasApiKeyAuth
{
    /**
     * Inject an API key as a request header.
     *
     * Example:
     *   return $this->withApiKey($request, config('services.stripe.key'));
     *   return $this->withApiKey($request, config('services.service.key'), 'X-Api-Key');
     */
    protected function withApiKey(
        Request $request,
        string $key,
        string $headerName = 'X-API-Key'
    ): Request {
        $request->headers->set($headerName, $key);

        return $request;
    }

    /**
     * Inject an API key as a query parameter.
     *
     * Example:
     *   return $this->withApiKeyQuery($request, config('services.maps.key'));
     *   return $this->withApiKeyQuery($request, config('services.maps.key'), 'key');
     */
    protected function withApiKeyQuery(
        Request $request,
        string $key,
        string $paramName = 'api_key'
    ): Request {
        $request->query->set($paramName, $key);

        return $request;
    }
}
