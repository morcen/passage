<?php

namespace Morcen\Passage\Concerns;

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
     * @param  callable|null  $when  Optional callable(Response $response, Throwable $e): bool.
     *                               When null, retries on connection errors and 5xx responses.
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
            'passage_retry_when' => $when,
        ], fn ($v) => $v !== null);
    }
}
