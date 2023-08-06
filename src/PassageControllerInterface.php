<?php

namespace Morcen\Passage;

use Illuminate\Http\Client\Response;
use Illuminate\Http\Request;

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
