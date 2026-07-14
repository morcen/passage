<?php

namespace Morcen\Passage\Http\Controllers;

use Illuminate\Contracts\Foundation\Application;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Http;
use Illuminate\Validation\ValidationException;
use Morcen\Passage\Contracts\AcceptsClientHeaders;
use Morcen\Passage\Contracts\ValidatesInboundRequest;
use Morcen\Passage\Events\PassageRequestFailed;
use Morcen\Passage\Events\PassageRequestSending;
use Morcen\Passage\Events\PassageResponseReceived;
use Morcen\Passage\Exceptions\DisallowedProxyTargetException;
use Morcen\Passage\Exceptions\InvalidBaseUriException;
use Morcen\Passage\Exceptions\PassageRequestAbortedException;
use Morcen\Passage\Guards\AllowedHostsGuard;
use Morcen\Passage\Http\PassageCacheManager;
use Morcen\Passage\Http\PassageErrorHandler;
use Morcen\Passage\Http\PassageResponseBuilder;
use Morcen\Passage\PassageControllerInterface;
use Morcen\Passage\Services\PassageServiceInterface;
use Morcen\Passage\Support\ForwardedHeaderResolver;
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
        protected readonly Application $app,
    ) {}

    public function handle(Request $request): Response
    {
        if (! config('passage.enabled', true)) {
            return response()->json(['error' => 'Route not found'], Response::HTTP_NOT_FOUND);
        }

        $handler = $request->route()->defaults['_passage_handler'] ?? null;
        $path = (string) $request->route('path', '');

        if (! $handler
            || ! class_exists($handler)
            || ! is_subclass_of($handler, PassageControllerInterface::class)
        ) {
            return response()->json(['error' => 'Route not found'], Response::HTTP_NOT_FOUND);
        }

        if ($this->containsDotSegment($path)) {
            return response()->json(['error' => 'Invalid path'], Response::HTTP_BAD_REQUEST);
        }

        $handlerInstance = $this->app->make($handler);
        $mergedOptions = array_merge(config('passage.options', []), $handlerInstance->getOptions());

        try {
            if (empty($mergedOptions['base_uri'])) {
                throw new InvalidBaseUriException("Passage handler [{$handler}] must return a 'base_uri' from getOptions().");
            }

            $this->allowedHostsGuard->check($mergedOptions['base_uri']);
        } catch (InvalidBaseUriException $e) {
            return response()->json(['error' => $e->getMessage()], Response::HTTP_INTERNAL_SERVER_ERROR);
        } catch (DisallowedProxyTargetException $e) {
            return response()->json(['error' => $e->getMessage()], Response::HTTP_FORBIDDEN);
        }

        // Extract Passage reserved keys before passing options to Guzzle.
        [$passageOptions, $guzzleOptions] = $this->extractPassageOptions($mergedOptions);

        if (config('passage.security.enforce_allowed_hosts', false)) {
            $guzzleOptions['allow_redirects'] = $this->guardRedirects($guzzleOptions['allow_redirects'] ?? true);
        }

        $pendingRequest = Http::withOptions($guzzleOptions);

        if (isset($passageOptions['passage_retry_times'])) {
            $pendingRequest = $pendingRequest->retry(
                $passageOptions['passage_retry_times'],
                $passageOptions['passage_retry_sleep_ms'] ?? 100,
                $passageOptions['passage_retry_when'] ?? null,
            );
        }

        // Strip sensitive client headers, but honour AcceptsClientHeaders overrides.
        $allowedClientHeaders = $handlerInstance instanceof AcceptsClientHeaders
            ? array_map('strtolower', $handlerInstance->allowedClientHeaders())
            : [];

        foreach (config('passage.security.strip_client_headers', []) as $header) {
            if (! in_array(strtolower($header), $allowedClientHeaders, strict: true)) {
                $request->headers->remove($header);
            }
        }

        // Run validation before transformation if the handler declares rules.
        if ($handlerInstance instanceof ValidatesInboundRequest) {
            try {
                $request->validate($handlerInstance->rules());
            } catch (ValidationException $e) {
                return response()->json([
                    'error' => 'Validation failed.',
                    'errors' => $e->errors(),
                ], Response::HTTP_UNPROCESSABLE_ENTITY);
            }
        }

        try {
            $request = $handlerInstance->getRequest($request);
        } catch (PassageRequestAbortedException $e) {
            return response()->json(['error' => $e->getMessage()], $e->getHttpStatus());
        }

        // Check cache before making the upstream call.
        $cacheTtl = $passageOptions['passage_cache_ttl'] ?? null;
        $fullUrl = rtrim($mergedOptions['base_uri'], '/').'/'.$path;
        // Same headers PassageService forwards upstream, so a cache entry is never
        // shared between requests that carry different credentials/identity.
        $forwardedHeaders = ForwardedHeaderResolver::resolve($request);

        if ($cacheTtl !== null) {
            $cached = $this->cacheManager->get($request->method(), $fullUrl, $cacheTtl, $guzzleOptions, $request->query(), $forwardedHeaders);
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
        } catch (DisallowedProxyTargetException $e) {
            $durationMs = (microtime(true) - $startedAt) * 1000;
            $this->fireEvent(new PassageRequestFailed($request, $handler, $e, $durationMs));

            return response()->json(['error' => $e->getMessage()], Response::HTTP_FORBIDDEN);
        } catch (ConnectionException|Throwable $e) {
            $durationMs = (microtime(true) - $startedAt) * 1000;
            $this->fireEvent(new PassageRequestFailed($request, $handler, $e, $durationMs));

            return $this->errorHandler->handle($e);
        }

        $durationMs = (microtime(true) - $startedAt) * 1000;

        if ($cacheTtl !== null) {
            $this->cacheManager->put($request->method(), $fullUrl, $cacheTtl, $guzzleOptions, $upstream, $request->query(), $forwardedHeaders);
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

    /**
     * Wrap the resolved allow_redirects Guzzle option so every redirect hop
     * is re-validated against the allowed_hosts list before it is followed.
     *
     * Prevents an allowlisted upstream from bypassing enforce_allowed_hosts
     * by issuing a redirect to a host outside the allowlist.
     */
    private function guardRedirects(mixed $allowRedirects): array|false
    {
        if ($allowRedirects === false) {
            return false;
        }

        $settings = is_array($allowRedirects) ? $allowRedirects : [];
        $previousOnRedirect = $settings['on_redirect'] ?? null;

        $settings['on_redirect'] = function ($request, $response, $uri) use ($previousOnRedirect) {
            $this->allowedHostsGuard->checkHost($uri->getHost());

            if ($previousOnRedirect !== null) {
                $previousOnRedirect($request, $response, $uri);
            }
        };

        return $settings;
    }

    private function fireEvent(object $event): void
    {
        if (config('passage.events.enabled', true)) {
            Event::dispatch($event);
        }
    }

    private function containsDotSegment(string $path): bool
    {
        $decoded = str_replace('\\', '/', $path);

        do {
            foreach (explode('/', $decoded) as $segment) {
                if ($segment === '.' || $segment === '..') {
                    return true;
                }
            }

            $previous = $decoded;
            $decoded = rawurldecode($decoded);
        } while ($decoded !== $previous);

        return false;
    }
}
