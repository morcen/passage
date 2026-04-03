<?php

namespace Morcen\Passage\Events;

use Illuminate\Http\Request;

class PassageRequestSending
{
    /**
     * @param  Request  $request  The transformed request (after getRequest() ran).
     * @param  string  $handler  Fully-qualified handler class name.
     * @param  string  $upstreamUri  Full upstream URI (base_uri + path).
     * @param  float  $startedAt  microtime(true) at the start of the proxy call.
     */
    public function __construct(
        public readonly Request $request,
        public readonly string $handler,
        public readonly string $upstreamUri,
        public readonly float $startedAt,
    ) {}
}
