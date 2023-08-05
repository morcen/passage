<?php

namespace Morcen\Passage;

use Illuminate\Http\Client\Response;
use Illuminate\Http\Request;

interface PassageControllerInterface
{
    /**
     * Dynamically update the request before it is sent to the service.
     */
    public function getRequest(Request $request): Request;

    /**
     * Handle the response before sending back to the client.
     */
    public function getResponse(Request $request, Response $response): Response;

    /**
     * Set the route options when the service is instantiated.
     */
    public function getOptions(): array;
}
