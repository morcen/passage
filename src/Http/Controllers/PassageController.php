<?php

namespace Morcen\Passage\Http\Controllers;

use Illuminate\Http\Client\PendingRequest;
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
use Morcen\Passage\Support\PassageRouteRegistry;
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
        protected readonly PassageRouteRegistry $routeRegistry,
    ) {}

    /**
     * Handle an inbound Passage request.
     *
     * Each stage below either returns early (a client-facing Response, or an
     * exception mapped to one) or hands prepared data to the next stage.
     */
    public function handle(Request $request): Response
    {
        if (! config('passage.enabled', true)) {
            return $this->notFoundResponse();
        }

        $handler = $this->routeRegistry->handlerClassFor($request->route());
        $path = (string) $request->route('path', '');

        if (! $this->routeRegistry->isValidHandler($handler)) {
            return $this->notFoundResponse();
        }

        if ($this->containsDotSegment($path) || $this->containsSchemeOrAuthority($path)) {
            return response()->json(['error' => 'Invalid path'], Response::HTTP_BAD_REQUEST);
        }

        $handlerInstance = $this->routeRegistry->resolveHandler($handler);
        $mergedOptions = $this->routeRegistry->optionsFor($handlerInstance);

        if ($response = $this->guardUpstreamTarget($mergedOptions, $request, $handler)) {
            return $response;
        }

        [$passageOptions, $guzzleOptions, $cacheableOptions] = $this->prepareGuzzleOptions($mergedOptions);
        $pendingRequest = $this->buildPendingRequest($guzzleOptions, $passageOptions);

        $this->stripSensitiveHeaders($request, $handlerInstance);

        if ($response = $this->validateInboundRequest($request, $handlerInstance)) {
            return $response;
        }

        try {
            $request = $handlerInstance->getRequest($request);
        } catch (PassageRequestAbortedException $e) {
            return response()->json(['error' => $e->getMessage()], $e->getHttpStatus());
        }

        // Streaming reads the upstream body as a lazy, single-pass PSR-7 stream (see the
        // `stream => true` Guzzle option set in prepareGuzzleOptions()), so nothing else may
        // consume that stream first. Caching is therefore disabled whenever streaming is
        // enabled: a cache write would exhaust the stream before buildStreamedResponse() can
        // read it, producing an empty/truncated response, and a cache hit would silently
        // bypass streaming (and its getResponse() skip) for a response the handler asked to
        // stream.
        $isStreaming = ! empty($passageOptions['passage_streaming']);
        $cacheTtl = $isStreaming ? null : ($passageOptions['passage_cache_ttl'] ?? null);
        $fullUrl = rtrim($mergedOptions['base_uri'], '/').'/'.$path;
        // Same headers PassageService forwards upstream, so a cache entry is never
        // shared between requests that carry different credentials/identity.
        $forwardedHeaders = ForwardedHeaderResolver::resolve($request);

        if ($cacheTtl !== null) {
            if ($response = $this->respondFromCache($request, $handlerInstance, $handler, $fullUrl, $cacheableOptions, $forwardedHeaders)) {
                return $response;
            }
        }

        $startedAt = microtime(true);
        $this->fireEvent(new PassageRequestSending($request, $handler, $fullUrl, $startedAt));

        try {
            $upstream = $this->passageService->callService($request, $pendingRequest, $path);
        } catch (DisallowedProxyTargetException $e) {
            $durationMs = (microtime(true) - $startedAt) * 1000;

            return $this->reportFailure($request, $handler, $e, $durationMs, Response::HTTP_FORBIDDEN, 'Upstream host is not permitted.');
        } catch (Throwable $e) {
            $durationMs = (microtime(true) - $startedAt) * 1000;
            $this->fireEvent(new PassageRequestFailed($request, $handler, $e, $durationMs));

            return $this->errorHandler->handle($e);
        }

        $durationMs = (microtime(true) - $startedAt) * 1000;

        if ($cacheTtl !== null) {
            $this->cacheManager->put($request->method(), $fullUrl, $cacheTtl, $cacheableOptions, $upstream, $request->query(), $forwardedHeaders);
        }

        return $this->buildFinalResponse($request, $upstream, $handler, $handlerInstance, $durationMs, $isStreaming);
    }

    /**
     * Confirm the handler's base_uri is present and permitted, reporting and
     * mapping any failure to a client-facing Response.
     */
    private function guardUpstreamTarget(array $mergedOptions, Request $request, string $handler): ?Response
    {
        try {
            if (empty($mergedOptions['base_uri'])) {
                throw new InvalidBaseUriException("Passage handler [{$handler}] must return a 'base_uri' from getOptions().");
            }

            $this->allowedHostsGuard->check($mergedOptions['base_uri']);
        } catch (InvalidBaseUriException $e) {
            return $this->reportFailure($request, $handler, $e, 0.0, Response::HTTP_INTERNAL_SERVER_ERROR, 'Upstream configuration error.');
        } catch (DisallowedProxyTargetException $e) {
            return $this->reportFailure($request, $handler, $e, 0.0, Response::HTTP_FORBIDDEN, 'Upstream host is not permitted.');
        }

        return null;
    }

    /**
     * Split merged options into Passage's own reserved keys and the Guzzle
     * options that should reach the HTTP client, then apply the
     * cache-key snapshot and redirect/streaming adjustments that only the
     * latter should carry.
     *
     * @return array{0: array<string,mixed>, 1: array<string,mixed>, 2: array<string,mixed>}
     */
    private function prepareGuzzleOptions(array $mergedOptions): array
    {
        [$passageOptions, $guzzleOptions] = $this->extractPassageOptions($mergedOptions);

        // Snapshot before the redirect guard below injects a Closure into
        // allow_redirects: PassageCacheManager builds its cache key with
        // serialize(), which throws on Closures, and the guard closure has
        // no bearing on cached response content anyway.
        $cacheableOptions = $guzzleOptions;

        if (config('passage.security.enforce_allowed_hosts', false)) {
            $guzzleOptions['allow_redirects'] = $this->guardRedirects($guzzleOptions['allow_redirects'] ?? true);
        }

        // Tell Guzzle to keep the upstream body as a lazily-read stream instead of
        // buffering it into memory, so buildStreamedResponse() can actually stream
        // it to the client rather than re-chunking an already-downloaded body.
        if (! empty($passageOptions['passage_streaming'])) {
            $guzzleOptions['stream'] = true;
        }

        return [$passageOptions, $guzzleOptions, $cacheableOptions];
    }

    /**
     * Build the outbound HTTP client, wiring up retry behaviour when the
     * handler's options ask for it.
     */
    private function buildPendingRequest(array $guzzleOptions, array $passageOptions): PendingRequest
    {
        $pendingRequest = Http::withOptions($guzzleOptions);

        if (isset($passageOptions['passage_retry_times'])) {
            // throw: false — Passage always forwards the upstream response as-is (see
            // "Upstream 4xx and 5xx responses are passed through unchanged" below);
            // without this, Laravel's retry() throws a RequestException for the final
            // non-2xx response once $tries > 1, regardless of what $when decided, and
            // that exception would be swallowed by the generic catch below and reported
            // as an opaque 500 instead of the real upstream status/body.
            $pendingRequest = $pendingRequest->retry(
                $passageOptions['passage_retry_times'],
                $passageOptions['passage_retry_sleep_ms'] ?? 100,
                $passageOptions['passage_retry_when'] ?? null,
                throw: false,
            );
        }

        return $pendingRequest;
    }

    /**
     * Strip sensitive client headers, honouring AcceptsClientHeaders overrides.
     */
    private function stripSensitiveHeaders(Request $request, PassageControllerInterface $handlerInstance): void
    {
        $allowedClientHeaders = $handlerInstance instanceof AcceptsClientHeaders
            ? array_map('strtolower', $handlerInstance->allowedClientHeaders())
            : [];

        foreach (config('passage.security.strip_client_headers', []) as $header) {
            if (! in_array(strtolower($header), $allowedClientHeaders, strict: true)) {
                $request->headers->remove($header);
            }
        }
    }

    /**
     * Run validation before transformation if the handler declares rules.
     */
    private function validateInboundRequest(Request $request, PassageControllerInterface $handlerInstance): ?Response
    {
        if (! $handlerInstance instanceof ValidatesInboundRequest) {
            return null;
        }

        try {
            $request->validate($handlerInstance->rules());
        } catch (ValidationException $e) {
            return response()->json([
                'error' => 'Validation failed.',
                'errors' => $e->errors(),
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        return null;
    }

    /**
     * Return the cached upstream response, transformed and event-fired like a
     * live one, when the cache holds an entry for this request.
     */
    private function respondFromCache(Request $request, PassageControllerInterface $handlerInstance, string $handler, string $fullUrl, array $cacheableOptions, array $forwardedHeaders): ?Response
    {
        $cached = $this->cacheManager->get($request->method(), $fullUrl, $cacheableOptions, $request->query(), $forwardedHeaders);

        if ($cached === null) {
            return null;
        }

        $upstream = $handlerInstance->getResponse($request, $cached);
        $this->fireEvent(new PassageResponseReceived($request, $upstream, $handler, 0.0, true));

        return $this->responseBuilder->build($upstream);
    }

    /**
     * Fire the failure event and map it to the given client-facing Response.
     */
    private function reportFailure(Request $request, string $handler, Throwable $e, float $durationMs, int $status, string $message): Response
    {
        $this->fireEvent(new PassageRequestFailed($request, $handler, $e, $durationMs));

        return response()->json(['error' => $message], $status);
    }

    /**
     * Cache the upstream response (when applicable), fire the response event,
     * and build the client-facing Response — streamed or buffered.
     */
    private function buildFinalResponse(Request $request, mixed $upstream, string $handler, PassageControllerInterface $handlerInstance, float $durationMs, bool $isStreaming): Response
    {
        // Streaming: skip getResponse() hook and return directly.
        if ($isStreaming) {
            $this->fireEvent(new PassageResponseReceived($request, $upstream, $handler, $durationMs, false));

            return $this->responseBuilder->buildStreamedResponse($upstream);
        }

        $upstream = $handlerInstance->getResponse($request, $upstream);
        $this->fireEvent(new PassageResponseReceived($request, $upstream, $handler, $durationMs, false));

        return $this->responseBuilder->build($upstream);
    }

    private function notFoundResponse(): Response
    {
        return response()->json(['error' => 'Route not found'], Response::HTTP_NOT_FOUND);
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
            $decoded = str_replace('\\', '/', rawurldecode($decoded));
        } while ($decoded !== $previous);

        return false;
    }

    /**
     * Detect a path that would resolve to an absolute URI or one carrying
     * its own authority. Guzzle's base_uri + relative-reference merge
     * (RFC 3986 §5.3) discards the configured base_uri entirely once the
     * reference has a scheme (e.g. "http://evil.example.com/x") or an
     * authority (a leading "//", or a colon in the first path segment,
     * which PSR-7's Uri parser also treats as introducing a scheme/host),
     * letting a crafted path redirect the outbound request to an
     * attacker-chosen host.
     */
    private function containsSchemeOrAuthority(string $path): bool
    {
        $decoded = $path;

        do {
            if (str_starts_with($decoded, '//')) {
                return true;
            }

            if (str_contains(explode('/', $decoded, 2)[0], ':')) {
                return true;
            }

            $previous = $decoded;
            $decoded = rawurldecode($decoded);
        } while ($decoded !== $previous);

        return false;
    }
}
