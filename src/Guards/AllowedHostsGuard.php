<?php

namespace Morcen\Passage\Guards;

use Morcen\Passage\Exceptions\DisallowedProxyTargetException;

class AllowedHostsGuard
{
    /**
     * Assert that the given base URI is permitted as an upstream proxy target.
     *
     * When enforce_allowed_hosts is false (default), the check is a no-op and
     * all upstream hosts are permitted. Enable it in production to prevent
     * open-proxy misconfigurations.
     *
     * @throws DisallowedProxyTargetException
     */
    public function check(string $baseUri): void
    {
        if (! config('passage.security.enforce_allowed_hosts', false)) {
            return;
        }

        $host = parse_url($baseUri, PHP_URL_HOST);

        if ($host === false || $host === null) {
            throw new DisallowedProxyTargetException(
                "Could not determine upstream host from base_uri [{$baseUri}]."
            );
        }

        $this->checkHost($host);
    }

    /**
     * Assert that the given host is permitted as an upstream proxy target.
     *
     * Used both for the initial base_uri check and to re-validate each hop
     * of an upstream redirect, so that a redirect issued by an allowlisted
     * host cannot be used to reach a host outside the allowlist.
     *
     * @throws DisallowedProxyTargetException
     */
    public function checkHost(string $host): void
    {
        if (! config('passage.security.enforce_allowed_hosts', false)) {
            return;
        }

        $allowedHosts = array_map('strtolower', config('passage.security.allowed_hosts', []));

        if (! in_array(strtolower($host), $allowedHosts, strict: true)) {
            throw new DisallowedProxyTargetException(
                "Upstream host [{$host}] is not in the passage allowed_hosts list."
            );
        }
    }
}
