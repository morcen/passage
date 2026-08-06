<?php

namespace Morcen\Passage\Concerns;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Throwable;

trait HasResilienceOptions
{
    /**
     * Return retry configuration to merge into getOptions().
     *
     * Passage will extract these keys before passing options to Guzzle and
     * apply ->retry() on the PendingRequest automatically.
     *
     * @param  int  $times  Maximum number of retry attempts.
     * @param  int  $sleepMs  Milliseconds to wait between retries.
     * @param  callable|null  $when  Optional callable(Throwable $exception, PendingRequest $request): bool.
     *                               When null, retries on connection errors and 5xx responses only —
     *                               NOT on 4xx client errors, since Laravel's own retry default would
     *                               otherwise retry any non-2xx response, including non-idempotent
     *                               requests that already failed for a client-side reason (e.g. 409,
     *                               422).
     *
     * Example:
     *   public function getOptions(): array
     *   {
     *       return array_merge(
     *           ['base_uri' => 'https://api.example.com/'],
     *           $this->withRetry(3, 200)
     *       );
     *   }
     */
    protected function withRetry(int $times, int $sleepMs = 100, ?callable $when = null): array
    {
        return array_filter([
            'passage_retry_times' => $times,
            'passage_retry_sleep_ms' => $sleepMs,
            'passage_retry_when' => $when ?? self::defaultRetryWhen(),
        ], fn ($v) => $v !== null);
    }

    /**
     * Default retry predicate: connection errors and 5xx responses only.
     *
     * Laravel's own retry() default (when no $when callback is supplied)
     * retries on any non-2xx response, including 4xx client errors. That is
     * rarely what a Passage handler wants — retrying a 409/422 from a
     * non-idempotent upstream operation can cause duplicate side effects.
     */
    private static function defaultRetryWhen(): callable
    {
        return function (Throwable $exception) {
            if ($exception instanceof ConnectionException) {
                return true;
            }

            return $exception instanceof RequestException && $exception->response->serverError();
        };
    }
}
