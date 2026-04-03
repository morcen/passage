<?php

namespace Morcen\Passage\Contracts;

/**
 * Optional interface for Passage handlers.
 *
 * By default, Passage strips sensitive client headers (cookie, authorization,
 * proxy-authorization) before the handler sees the inbound request. This
 * prevents client credentials from leaking to upstream services.
 *
 * Implement this interface when your handler legitimately needs to receive and
 * forward one or more of those stripped headers. Only the headers listed in
 * allowedClientHeaders() will be preserved; the rest are still stripped.
 *
 * Example — forward the client's Authorization header to the upstream:
 *
 *   class TokenRelayHandler extends PassageHandler implements AcceptsClientHeaders
 *   {
 *       public function allowedClientHeaders(): array
 *       {
 *           return ['authorization'];
 *       }
 *   }
 */
interface AcceptsClientHeaders
{
    /**
     * Return the list of client headers (lowercase) that this handler wants
     * to preserve from the strip policy.
     *
     * @return string[]  e.g. ['authorization', 'cookie']
     */
    public function allowedClientHeaders(): array;
}
