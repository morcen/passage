<?php

namespace Morcen\Passage\Concerns;

use Illuminate\Http\Request;

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
        $body = $request->getContent();
        $payload = $timestamp.'.'.$body;
        $signature = hash_hmac($algorithm, $payload, $secret);

        $request->headers->set($timestampHeader, $timestamp);
        $request->headers->set($signatureHeader, $signature);

        return $request;
    }
}
