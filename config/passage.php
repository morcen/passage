<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Passage Master Switch
    |--------------------------------------------------------------------------
    |
    | This option may be used to disable Passage without the need to remove
    | route definitions. This can be a very handy option especially when
    | troubleshooting something that is route-related, to ensure that
    | Passage is not the cause.
    |
    */

    'enabled' => env('PASSAGE_ENABLED', true),

    /*
    |--------------------------------------------------------------------------
    | Passage Global Options
    |--------------------------------------------------------------------------
    |
    | These options are applied to all Passage proxy requests. Any option
    | defined in a handler's getOptions() will override these values.
    |
    | Full list of available options:
    | https://docs.guzzlephp.org/en/stable/request-options.html
    |
    */

    'options' => [
        /*
        | Maximum seconds to wait for a response. Set to 0 to wait
        | indefinitely (not recommended).
        */
        'timeout' => env('PASSAGE_TIMEOUT', 30),

        /*
        | Disable throwing exceptions on HTTP protocol errors.
        */
        'http_errors' => false,

        /*
        | Maximum seconds to wait while trying to connect to a server.
        | Set to 0 to wait indefinitely (not recommended).
        */
        'connect_timeout' => env('PASSAGE_CONNECT_TIMEOUT', 10),
    ],

    /*
    |--------------------------------------------------------------------------
    | Passage Security
    |--------------------------------------------------------------------------
    |
    | These options control the security behaviour of the proxy layer.
    |
    */

    'security' => [
        /*
        | When true, only hosts listed in allowed_hosts may be used as
        | upstream targets. Passage will refuse to proxy to any other host.
        | Recommended for production deployments.
        */
        'enforce_allowed_hosts' => env('PASSAGE_ENFORCE_ALLOWED_HOSTS', false),

        /*
        | List of allowed upstream hostnames. Only checked when
        | enforce_allowed_hosts is true.
        |
        | Example: ['api.github.com', 'api.stripe.com']
        */
        'allowed_hosts' => [],

        /*
        | Headers stripped from the inbound client request before it
        | reaches the handler. This prevents client credentials from
        | leaking to upstream services.
        |
        | Handlers may re-add these with service-level credentials via
        | getRequest() or by using the HasBearerAuth / HasApiKeyAuth traits.
        |
        | To allow a header through on a specific handler, implement
        | AcceptsClientHeaders (Phase 4) or remove it from this list globally.
        */
        'strip_client_headers' => ['cookie', 'authorization', 'proxy-authorization'],

        /*
        | Hop-by-hop headers are always stripped and cannot be configured.
        | These are transport-level headers that must not be forwarded by
        | any proxy (RFC 7230).
        */
        'hop_by_hop_headers' => [
            'host', 'connection', 'transfer-encoding', 'upgrade',
            'keep-alive', 'te', 'trailer', 'proxy-authenticate',
            'proxy-authorization',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Passage Response Cache
    |--------------------------------------------------------------------------
    |
    | GET and HEAD responses can be cached per-route by returning
    | passage_cache_ttl (seconds) from a handler's getOptions().
    |
    | Uses Laravel's Cache facade with the store configured below.
    | null = use the application's default cache store.
    |
    */

    'cache' => [
        'store' => env('PASSAGE_CACHE_STORE', null),

        /*
        | Forwarded headers excluded from the cache key. Some headers are
        | deliberately regenerated on every request (e.g. HasHmacAuth's
        | X-Timestamp/X-Signature), so including them makes every request
        | produce a different key and caching never hits. Add any other
        | per-request volatile header your handlers set here.
        */
        'volatile_headers' => ['X-Timestamp', 'X-Signature'],
    ],

    /*
    |--------------------------------------------------------------------------
    | Passage Events
    |--------------------------------------------------------------------------
    |
    | When enabled, Passage fires Laravel events around each proxy call:
    |   PassageRequestSending, PassageResponseReceived, PassageRequestFailed
    |
    | Register PassageEventSubscriber in your EventServiceProvider to log them.
    |
    */

    'events' => [
        'enabled' => env('PASSAGE_EVENTS', true),
    ],
];
