<?php

namespace Morcen\Passage\Concerns;

use Illuminate\Http\Request;

trait HasBearerAuth
{
    /**
     * Inject a Bearer token into the outbound request.
     *
     * Call this from getRequest() to authenticate against the upstream service.
     *
     * Example:
     *   return $this->withBearerToken($request, config('services.github.token'));
     */
    protected function withBearerToken(Request $request, string $token): Request
    {
        $request->headers->set('Authorization', 'Bearer '.$token);

        return $request;
    }
}
