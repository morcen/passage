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
    | be forwarded to the new URL
    |
    */
    'services' => [

    ],
];
