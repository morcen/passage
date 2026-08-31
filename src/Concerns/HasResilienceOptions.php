<?php

namespace Morcen\Passage\Concerns;

use Closure;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Throwable;

trait HasResilienceOptions
{
    /**
     * Return retry configuration to merge into getOptions().
     *
     * Passage will extract these keys before passing options to Guzzle and
     * apply ->retry() on the PendingRequest automatically. Both parameters
     * are passed straight through to Laravel's PendingRequest::retry(), so
     * they accept the same shapes it does: an array of $times lets you
     * define a fixed number of attempts per backoff step, and a Closure for
     * $sleepMs lets you compute the delay per attempt (e.g. exponential
     * backoff with jitter).
     *
     * @param  int|array<int, int>  $times  Maximum number of retry attempts, or an array of
     *                                      per-attempt delays in milliseconds (its length
     *                                      determines the number of attempts).
     * @param  int|Closure  $sleepMs  Milliseconds to wait between retries, or a
     *                                Closure(int $attempt, Throwable $exception): int
     *                                that computes the delay per attempt.
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
     *
     * Example with exponential backoff and jitter:
     *   public function getOptions(): array
     *   {
     *       return array_merge(
     *           ['base_uri' => 'https://api.example.com/'],
     *           $this->withRetry(3, fn (int $attempt) => (2 ** $attempt) * 100 + random_int(0, 100))
     *       );
     *   }
     */
    protected function withRetry(int|array $times, int|Closure $sleepMs = 100, ?callable $when = null): array
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
