<?php

namespace Morcen\Passage\Events;

use Illuminate\Http\Client\Response;
use Illuminate\Http\Request;

class PassageResponseReceived
{
    /**
     * @param  Request  $request  The transformed request.
     * @param  Response  $response  The upstream response (after getResponse() ran).
     * @param  string  $handler  Fully-qualified handler class name.
     * @param  float  $durationMs  Total proxy call duration in milliseconds.
     * @param  bool  $cached  Whether the response was served from cache.
     */
    public function __construct(
        public readonly Request $request,
        public readonly Response $response,
        public readonly string $handler,
        public readonly float $durationMs,
        public readonly bool $cached,
    ) {}
}
