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
];
