<?php

/**
 * Router script for PHP's built-in web server, used by
 * tests/Feature/PassageRealServerIntegrationTest.php to exercise Passage
 * against a real HTTP connection instead of Illuminate\Support\Facades\Http::fake().
 *
 * `/stream` returns a chunked, flushed body so the streaming code path can be
 * exercised for real. Every other path echoes back everything PHP itself
 * parsed from the request (method, path, query, headers, raw body, parsed
 * POST fields, and uploaded files) as JSON, so the test can assert on what
 * actually arrived over the wire rather than what Passage intended to send.
 */
$uri = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);

if ($uri === '/stream') {
    header('Content-Type: text/plain');

    for ($i = 0; $i < 5; $i++) {
        echo "chunk-{$i}\n";
        if (function_exists('ob_flush')) {
            @ob_flush();
        }
        flush();
        usleep(10_000);
    }

    return;
}

$headers = [];

foreach ($_SERVER as $key => $value) {
    if (str_starts_with($key, 'HTTP_')) {
        $headers[str_replace('_', '-', substr($key, 5))] = $value;
    } elseif (in_array($key, ['CONTENT_TYPE', 'CONTENT_LENGTH'], true) && $value !== '') {
        $headers[str_replace('_', '-', $key)] = $value;
    }
}

$files = array_map(
    fn (array $file) => ['name' => $file['name'], 'size' => $file['size']],
    $_FILES
);

header('Content-Type: application/json');
echo json_encode([
    'method' => $_SERVER['REQUEST_METHOD'],
    'path' => $uri,
    'query' => $_GET,
    'headers' => $headers,
    'body' => file_get_contents('php://input'),
    'post' => $_POST,
    'files' => $files,
]);
