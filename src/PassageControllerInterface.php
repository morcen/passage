<?php

namespace Morcen\Passage;

use Illuminate\Http\Client\Response;
use Illuminate\Http\Request;

/**
 * Core contract for Passage handlers.
 *
 * Implementations define how inbound requests are transformed before they are
 * forwarded upstream, how upstream responses are transformed before returning
 * to the client, and which upstream options should be used for the route.
 *
 * In most cases you should extend PassageHandler instead of implementing this
 * interface directly, since PassageHandler provides no-op defaults for all
 * three methods and includes the built-in auth and resilience helpers.
 *
 * Example:
 *
 *   class GithubHandler extends PassageHandler
 *   {
 *       public function getOptions(): array
 *       {
 *           return ['base_uri' => 'https://api.github.com/'];
 *       }
 *   }
 */
interface PassageControllerInterface
{
    /**
     * Transform and/or validate the request before it is sent to the service.
     */
    public function getRequest(Request $request): Request;

    /**
     * Transform or validate the response before it is sent back to the client.
     */
    public function getResponse(Request $request, Response $response): Response;

    /**
     * Set the route options when the service is instantiated.
     */
    public function getOptions(): array;
}
