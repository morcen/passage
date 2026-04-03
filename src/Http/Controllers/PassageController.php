<?php

namespace Morcen\Passage\Http\Controllers;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Http;
use Morcen\Passage\Events\PassageRequestFailed;
use Morcen\Passage\Events\PassageRequestSending;
use Morcen\Passage\Events\PassageResponseReceived;
use Morcen\Passage\Exceptions\InvalidBaseUriException;
use Morcen\Passage\Exceptions\PassageRequestAbortedException;
use Morcen\Passage\Guards\AllowedHostsGuard;
use Morcen\Passage\Http\PassageCacheManager;
use Morcen\Passage\Http\PassageErrorHandler;
use Morcen\Passage\Http\PassageResponseBuilder;
use Morcen\Passage\PassageControllerInterface;
use Morcen\Passage\Services\PassageServiceInterface;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

class PassageController extends Controller
{
    // Reserved option keys consumed by Passage before passing options to Guzzle.
    private const PASSAGE_KEYS = [
        'passage_retry_times',
        'passage_retry_sleep_ms',
        'passage_retry_when',
        'passage_cache_ttl',
        'passage_streaming',
    ];

    public function __construct(
        protected readonly PassageServiceInterface $passageService,
        protected readonly PassageResponseBuilder $responseBuilder,
        protected readonly AllowedHostsGuard $allowedHostsGuard,
        protected readonly PassageCacheManager $cacheManager,
        protected readonly PassageErrorHandler $errorHandler,
    ) {}

    public function handle(Request $request): Response
    {
        $handler = $request->route()->defaults['_passage_handler'] ?? null;
        $path = (string) $request->route('path', '');

        if (! $handler
            || ! class_exists($handler)
            || ! is_subclass_of($handler, PassageControllerInterface::class)
        ) {
            return response()->json(['error' => 'Route not found'], Response::HTTP_NOT_FOUND);
        }

        $handlerInstance = new $handler;
        $mergedOptions = array_merge(config('passage.options', []), $handlerInstance->getOptions());

        if (empty($mergedOptions['base_uri'])) {
            throw new InvalidBaseUriException("Passage handler [{$handler}] must return a 'base_uri' from getOptions().");
        }

        $this->allowedHostsGuard->check($mergedOptions['base_uri']);

        // Extract Passage reserved keys before passing options to Guzzle.
        [$passageOptions, $guzzleOptions] = $this->extractPassageOptions($mergedOptions);

        $pendingRequest = Http::withOptions($guzzleOptions);

        if (isset($passageOptions['passage_retry_times'])) {
            $pendingRequest = $pendingRequest->retry(
                $passageOptions['passage_retry_times'],
                $passageOptions['passage_retry_sleep_ms'] ?? 100,
                $passageOptions['passage_retry_when'] ?? null,
            );
        }

        // Strip sensitive client headers before the handler processes the request.
        foreach (config('passage.security.strip_client_headers', []) as $header) {
            $request->headers->remove($header);
        }

        try {
            $request = $handlerInstance->getRequest($request);
        } catch (PassageRequestAbortedException $e) {
            return response()->json(['error' => $e->getMessage()], $e->getHttpStatus());
        }

        // Check cache before making the upstream call.
        $cacheTtl = $passageOptions['passage_cache_ttl'] ?? null;
        $fullUrl = rtrim($mergedOptions['base_uri'], '/').'/'.$path;

        if ($cacheTtl !== null) {
            $cached = $this->cacheManager->get($request->method(), $fullUrl, $cacheTtl, $guzzleOptions);
            if ($cached !== null) {
                $upstream = $handlerInstance->getResponse($request, $cached);
                $this->fireEvent(new PassageResponseReceived($request, $upstream, $handler, 0.0, true));

                return $this->responseBuilder->build($upstream);
            }
        }

        $startedAt = microtime(true);
        $this->fireEvent(new PassageRequestSending($request, $handler, $fullUrl, $startedAt));

        try {
            $upstream = $this->passageService->callService($request, $pendingRequest, $path);
        } catch (ConnectionException|Throwable $e) {
            $durationMs = (microtime(true) - $startedAt) * 1000;
            $this->fireEvent(new PassageRequestFailed($request, $handler, $e, $durationMs));

            return $this->errorHandler->handle($e);
        }

        $durationMs = (microtime(true) - $startedAt) * 1000;

        if ($cacheTtl !== null) {
            $this->cacheManager->put($request->method(), $fullUrl, $cacheTtl, $guzzleOptions, $upstream);
        }

        // Streaming: skip getResponse() hook and return directly.
        if (! empty($passageOptions['passage_streaming'])) {
            $this->fireEvent(new PassageResponseReceived($request, $upstream, $handler, $durationMs, false));

            return $this->responseBuilder->buildStreamedResponse($upstream);
        }

        $upstream = $handlerInstance->getResponse($request, $upstream);
        $this->fireEvent(new PassageResponseReceived($request, $upstream, $handler, $durationMs, false));

        return $this->responseBuilder->build($upstream);
    }

    /**
     * Split merged options into [passage_*, guzzle_options].
     *
     * @return array{0: array<string,mixed>, 1: array<string,mixed>}
     */
    private function extractPassageOptions(array $options): array
    {
        $passage = [];
        $guzzle = [];

        foreach ($options as $key => $value) {
            if (in_array($key, self::PASSAGE_KEYS, strict: true)) {
                $passage[$key] = $value;
            } else {
                $guzzle[$key] = $value;
            }
        }

        return [$passage, $guzzle];
    }

    private function fireEvent(object $event): void
    {
        if (config('passage.events.enabled', true)) {
            Event::dispatch($event);
        }
    }
}
