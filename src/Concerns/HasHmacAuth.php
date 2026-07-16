<?php

namespace Morcen\Passage\Concerns;

use Illuminate\Http\Request;
use Illuminate\Support\Arr;

trait HasHmacAuth
{
    /**
     * Sign the outbound request with an HMAC signature.
     *
     * The signature is computed over: timestamp + "." + request body.
     * Two headers are added to the request:
     *   X-Timestamp  — Unix timestamp used in the signature (replay protection)
     *   X-Signature  — HMAC hex digest: hash_hmac($algorithm, "$timestamp.$body", $secret)
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
        $payload = $timestamp.'.'.$body;
        $signature = hash_hmac($algorithm, $payload, $secret);

        $request->headers->set($timestampHeader, $timestamp);
        $request->headers->set($signatureHeader, $signature);

        return $request;
    }

    /**
     * Resolve the payload to sign.
     *
     * PHP consumes php://input while populating $_POST/$_FILES for
     * multipart/form-data requests, so getContent() is always empty for
     * them. Sign a stable representation of the parsed fields and file
     * metadata instead, so the signature still covers what was submitted.
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
                ];
            }
        }

        return json_encode([
            'fields' => $request->post(),
            'files' => $files,
        ]);
    }
}
