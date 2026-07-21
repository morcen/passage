<?php

namespace Morcen\Passage\Concerns;

use Illuminate\Http\Request;
use Illuminate\Support\Arr;
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
            $request->getPathInfo(),
            $this->resolveHmacSignedQuery($request),
            $body,
        ]);
        $signature = hash_hmac($algorithm, $payload, $secret);

        $request->headers->set($timestampHeader, $timestamp);
        $request->headers->set($signatureHeader, $signature);

        return $request;
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
        if (! str_contains($request->header('Content-Type', ''), 'multipart/form-data')) {
            return $request->getContent();
        }

        $files = [];

        foreach ($request->allFiles() as $key => $file) {
            foreach (Arr::wrap($file) as $index => $singleFile) {
                $files[is_array($file) ? "{$key}[{$index}]" : $key] = [
                    'name' => $singleFile->getClientOriginalName(),
                    'size' => $singleFile->getSize(),
                    'mime' => $singleFile->getMimeType(),
                    'hash' => hash('sha256', $singleFile->get()),
                ];
            }
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
