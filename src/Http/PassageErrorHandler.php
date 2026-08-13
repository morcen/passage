<?php

namespace Morcen\Passage\Http;

use GuzzleHttp\Exception\ConnectException;
use GuzzleHttp\Exception\TooManyRedirectsException;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

class PassageErrorHandler
{
    /**
     * Map a transport-level exception to an appropriate HTTP error response.
     *
     * Upstream 4xx/5xx responses are NOT exceptions and must NOT be passed here —
     * they travel through PassageResponseBuilder as normal responses.
     * Only connection-level failures (timeouts, DNS, TLS, redirect loops) reach this handler.
     *
     * Every exception handled here is reported through Laravel's normal exception
     * pipeline (report()), so it always reaches the configured log channel and any
     * wired error tracker (Sentry, Bugsnag, ...) — regardless of whether the optional
     * PassageEventSubscriber is registered. Without this, a genuine bug (as opposed to
     * an upstream connectivity failure) would be silently converted into a generic
     * error response with no trace anywhere.
     */
    public function handle(Throwable $e): JsonResponse
    {
        report($e);

        $status = $this->resolveStatus($e);

        return response()->json(
            ['error' => $this->resolveMessage($e, $status)],
            $status
        );
    }

    private function resolveStatus(Throwable $e): int
    {
        // Laravel wraps Guzzle connect/timeout errors in ConnectionException.
        if ($e instanceof ConnectionException) {
            // Distinguish timeout from other connection failures via the wrapped Guzzle cause.
            $cause = $e->getPrevious();
            if ($cause instanceof ConnectException) {
                $context = $cause->getHandlerContext();
                $timeoutErrno = $this->curlOperationTimedOutErrno();
                if (isset($context['errno']) && $timeoutErrno !== null && $context['errno'] === $timeoutErrno) {
                    return Response::HTTP_GATEWAY_TIMEOUT; // 504
                }
            }

            return Response::HTTP_BAD_GATEWAY; // 502
        }

        if ($e instanceof TooManyRedirectsException) {
            return Response::HTTP_BAD_GATEWAY; // 502
        }

        return Response::HTTP_INTERNAL_SERVER_ERROR; // 500
    }

    /**
     * `CURLE_OPERATION_TIMEDOUT` only exists when PHP's curl extension is loaded;
     * referencing it directly would throw a fatal error on curl-less installs.
     */
    protected function curlOperationTimedOutErrno(): ?int
    {
        return defined('CURLE_OPERATION_TIMEDOUT') ? CURLE_OPERATION_TIMEDOUT : null;
    }

    private function resolveMessage(Throwable $e, int $status): string
    {
        return match ($status) {
            Response::HTTP_GATEWAY_TIMEOUT => 'Upstream service timed out.',
            Response::HTTP_BAD_GATEWAY => 'Upstream service is unreachable.',
            default => 'An unexpected error occurred.',
        };
    }
}
