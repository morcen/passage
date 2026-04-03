<?php

namespace Morcen\Passage\Events;

use Illuminate\Http\Request;
use Throwable;

class PassageRequestFailed
{
    /**
     * @param  Request  $request  The transformed request (may be pre-abort state).
     * @param  string  $handler  Fully-qualified handler class name.
     * @param  Throwable  $exception  The transport-level exception that caused the failure.
     * @param  float  $durationMs  Duration from request start to failure in milliseconds.
     */
    public function __construct(
        public readonly Request $request,
        public readonly string $handler,
        public readonly Throwable $exception,
        public readonly float $durationMs,
    ) {}
}
