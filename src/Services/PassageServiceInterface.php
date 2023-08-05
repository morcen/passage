<?php

namespace Morcen\Passage\Services;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Http\Request;

interface PassageServiceInterface
{
    public function callService(Request $request, PendingRequest $service, string $uri): Response;
}
