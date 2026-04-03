<?php

namespace Morcen\Passage;

use Illuminate\Http\Client\Response;
use Illuminate\Http\Request;
use Morcen\Passage\Concerns\HasApiKeyAuth;
use Morcen\Passage\Concerns\HasBearerAuth;
use Morcen\Passage\Concerns\HasHmacAuth;
use Morcen\Passage\Concerns\HasResilienceOptions;

/**
 * Optional abstract base class for Passage handlers.
 *
 * Extend this instead of implementing PassageControllerInterface directly
 * to get no-op defaults for all three interface methods plus the auth helper
 * traits (HasBearerAuth, HasApiKeyAuth, HasHmacAuth) available out of the box.
 *
 * You only need to override the methods relevant to your handler:
 *
 *   class GithubHandler extends PassageHandler
 *   {
 *       public function getRequest(Request $request): Request
 *       {
 *           return $this->withBearerToken($request, config('services.github.token'));
 *       }
 *
 *       public function getOptions(): array
 *       {
 *           return ['base_uri' => 'https://api.github.com/'];
 *       }
 *   }
 */
abstract class PassageHandler implements PassageControllerInterface
{
    use HasApiKeyAuth, HasBearerAuth, HasHmacAuth, HasResilienceOptions;

    public function getRequest(Request $request): Request
    {
        return $request;
    }

    public function getResponse(Request $request, Response $response): Response
    {
        return $response;
    }

    public function getOptions(): array
    {
        return [];
    }
}
