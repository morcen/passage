<?php

namespace Morcen\Passage\Services;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Http\Request;

class PassageService implements PassageServiceInterface
{
    protected string $method;

    protected array $headers = [];

    protected array $params = [];

    public function callService(Request $request, PendingRequest $service, string $uri): Response
    {
        $method = strtolower($request->method());
        $params = $request->all();

        return $service->{$method}($uri, $params);
    }

    public function getMethod(): string
    {
        return $this->method;
    }

    public function setMethod(string $method): void
    {
        $this->method = $method;
    }

    public function getHeaders(): array
    {
        return $this->headers;
    }

    public function setHeaders(array $headers): void
    {
        $this->headers = $headers;
    }

    public function getParams(): array
    {
        return $this->params;
    }

    public function setParams(array $params): void
    {
        $this->params = $params;
    }
}
