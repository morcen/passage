<?php

namespace Morcen\Passage\Listeners;

use Illuminate\Events\Dispatcher;
use Illuminate\Support\Facades\Log;
use Morcen\Passage\Events\PassageRequestFailed;
use Morcen\Passage\Events\PassageRequestSending;
use Morcen\Passage\Events\PassageResponseReceived;

/**
 * Optional log subscriber for Passage events.
 *
 * Not registered automatically. To enable, add this to your EventServiceProvider:
 *
 *   protected $subscribe = [
 *       \Morcen\Passage\Listeners\PassageEventSubscriber::class,
 *   ];
 *
 * Logs to the 'passage' channel if configured, otherwise falls back to the
 * default channel. Configure a dedicated channel in config/logging.php:
 *
 *   'passage' => [
 *       'driver' => 'daily',
 *       'path'   => storage_path('logs/passage.log'),
 *       'level'  => 'debug',
 *       'days'   => 7,
 *   ],
 */
class PassageEventSubscriber
{
    public function onRequestSending(PassageRequestSending $event): void
    {
        Log::channel($this->channel())->debug('Passage: forwarding request', [
            'handler' => $event->handler,
            'uri' => $event->upstreamUri,
            'method' => $event->request->method(),
        ]);
    }

    public function onResponseReceived(PassageResponseReceived $event): void
    {
        Log::channel($this->channel())->info('Passage: response received', [
            'handler' => $event->handler,
            'status' => $event->response->status(),
            'duration_ms' => round($event->durationMs, 2),
            'cached' => $event->cached,
        ]);
    }

    public function onRequestFailed(PassageRequestFailed $event): void
    {
        Log::channel($this->channel())->error('Passage: request failed', [
            'handler' => $event->handler,
            'error' => $event->exception->getMessage(),
            'duration_ms' => round($event->durationMs, 2),
        ]);
    }

    public function subscribe(Dispatcher $events): array
    {
        return [
            PassageRequestSending::class => 'onRequestSending',
            PassageResponseReceived::class => 'onResponseReceived',
            PassageRequestFailed::class => 'onRequestFailed',
        ];
    }

    private function channel(): string
    {
        return 'passage';
    }
}
