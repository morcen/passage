<?php

namespace Morcen\Passage\Concerns;

use Illuminate\Http\Request;
use Morcen\Passage\Support\NestedFileResolver;
use RuntimeException;

trait HasHmacAuth
{
    /**
     * Sign the outbound request with an HMAC signature.
     *
     * The signature is computed over: timestamp + "." + method + "." + path
     * + "." + query string + "." + request body. Binding the method, path,
     * and query string (in addition to the body) prevents an intermediary
     * from rewriting the request target without invalidating the signature
     * — this matters most for GET/HEAD requests, which have no body and
     * would otherwise carry no real signature at all.
     *
     * Two headers are added to the request:
     *   X-Timestamp  — Unix timestamp used in the signature (replay protection)
     *   X-Signature  — HMAC hex digest of the payload described above
     *
     * The header names and algorithm are configurable.
     *
     * Example:
     *   return $this->withHmacSignature($request, config('services.partner.secret'));
     */
    protected function withHmacSignature(
        Request $request,
        string $secret,
        string $algorithm = 'sha256',
        string $timestampHeader = 'X-Timestamp',
        string $signatureHeader = 'X-Signature'
    ): Request {
        $timestamp = (string) time();
        $body = $this->resolveHmacSignedBody($request);
        $payload = implode('.', [
            $timestamp,
            $request->getMethod(),
            $this->resolveHmacSignedPath($request),
            $this->resolveHmacSignedQuery($request),
            $body,
        ]);
        $signature = hash_hmac($algorithm, $payload, $secret);

        $request->headers->set($timestampHeader, $timestamp);
        $request->headers->set($signatureHeader, $signature);

        return $request;
    }

    /**
     * Resolve the path to bind into the signature.
     *
     * PassageController forwards $request->route('path', '') to Guzzle as
     * the upstream request target (see PassageController::handle()), which
     * is only the wildcard remainder of the matched route — not the full
     * inbound URI (any literal prefix segments of the route definition,
     * e.g. Passage::post('accounts/{path?}', ...), are stripped). Signing
     * getPathInfo() would bind the signature to a string the upstream never
     * actually receives, so mirror the same route parameter here. Fall back
     * to getPathInfo() when no route is bound (e.g. calling getRequest()
     * directly, outside of route dispatch).
     */
    private function resolveHmacSignedPath(Request $request): string
    {
        return $request->route() !== null
            ? (string) $request->route('path', '')
            : $request->getPathInfo();
    }

    /**
     * Resolve a canonical (key-sorted) query string so that reordering query
     * parameters cannot change the signed payload.
     */
    private function resolveHmacSignedQuery(Request $request): string
    {
        $query = $request->query();
        ksort($query);

        return http_build_query($query);
    }

    /**
     * Resolve the payload to sign.
     *
     * PHP consumes php://input while populating $_POST/$_FILES for
     * multipart/form-data requests, so getContent() is always empty for
     * them. Sign a stable representation of the parsed fields and, for each
     * uploaded file, its metadata plus a content hash — so a swapped file
     * body invalidates the signature even when name/size/mime are unchanged.
     */
    private function resolveHmacSignedBody(Request $request): string
    {
        if (! str_contains(strtolower($request->header('Content-Type', '')), 'multipart/form-data')) {
            return $request->getContent();
        }

        $files = [];

        foreach (NestedFileResolver::flatten($request->allFiles()) as $key => $singleFile) {
            $files[$key] = [
                'name' => $singleFile->getClientOriginalName(),
                'size' => $singleFile->getSize(),
                'mime' => $singleFile->getMimeType(),
                'hash' => hash('sha256', $singleFile->get()),
            ];
        }

        $encoded = json_encode([
            'fields' => $request->post(),
            'files' => $files,
        ], JSON_INVALID_UTF8_SUBSTITUTE);

        if ($encoded === false) {
            throw new RuntimeException('Unable to encode multipart fields for HMAC signing: '.json_last_error_msg());
        }

        return $encoded;
    }
}
