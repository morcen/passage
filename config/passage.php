<?php

/**
 * Place here the cnfiguration for API services, like:
 *
 * return [
 *     'service' => [
 *         'to' => '',
 *     ]
 * ];
 *
 * Wherein:
 * - "service" is the part of the URL that determines when the external URL will be called.
 * - "to" is the URL that will be called when the "service" is matched
 *
 * For example:
 * return [
 *     'github' => [
 *         'to' => 'https://api.github.com/users',
 *     ]
 * ];
 *
 * If your application received http://127.0.0.1:8000/github/morcen, this package will fetch
 * https://api.github.com/users/morcen and the response will be returned as your app's response.
 */
return [
    /*
    |--------------------------------------------------------------------------
    | Passage Master Switch
    |--------------------------------------------------------------------------
    |
    | This option may be used to disable Passage without the need to remove
    | `Route::passage()` in routes/web.php. This can be a very handy option
    | especially when troubleshooting something that is route-related, to
    | ensure that Passage is not the cause.
    |
    */

    'enabled' => env('PASSAGE_ENABLED', true),

    /*
    |--------------------------------------------------------------------------
    | Passage Route Proxies
    |--------------------------------------------------------------------------
    |
    | This option is a key-pair values of services and their configuration.
    | A service is the first word in the URI, and the rest of the word will
    | be forwarded to the new URL.
    |
    | The format of services value is `service_name => [options]`. Full list of
    | available options is available at
    | https://docs.guzzlephp.org/en/stable/request-options.html
    */
    'services' => [

    ],

    /*
    |--------------------------------------------------------------------------
    | Passage options
    | This is the global options that will be applied to all requests. To
    | override these options, declare the same options inside the service
    | options.
    |
    | Full list of available options is available at
    | https://docs.guzzlephp.org/en/stable/request-options.html
    |--------------------------------------------------------------------------
    */
    'options' => [
        /*
        |--------------------------------------------------------------------------
        | Passage timeout
        |--------------------------------------------------------------------------
        | This option is used to specify the maximum number of seconds to wait for
        | a response. If the given timeout is exceeded, an instance of
        | `Illuminate\Http\Client\ConnectionException` will be thrown.
        |
        | Default is set to 30 seconds. Set this option to zero to wait
        | indefinitely (not recommended, but useful for debugging connection
        | issues).
        */
        'timeout' => env('PASSAGE_TIMEOUT', 30),

        /*
        |--------------------------------------------------------------------------
        | Passage HTTP errors
        |--------------------------------------------------------------------------
        | Use this option to enable or disable throwing on an HTTP protocol errors.
        |
        */
        'http_errors' => false,

        /*
        |--------------------------------------------------------------------------
        | Passage connection timeout
        |--------------------------------------------------------------------------
        | This option is used to specify the maximum number of seconds wait while
        | trying to connect to a server.
        |
        | Default is set to 10 seconds. Set this option to zero to wait
        | indefinitely (not recommended, but useful for debugging connection
        | issues).
        */
        'connect_timeout' => env('PASSAGE_CONNECT_TIMEOUT', 10),
    ],
];
