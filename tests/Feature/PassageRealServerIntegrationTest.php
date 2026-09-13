<?php

use Illuminate\Http\UploadedFile;
use Morcen\Passage\Facades\Passage;
use Morcen\Passage\PassageHandler;

/**
 * Real-HTTP-transport integration lane.
 *
 * Every other Feature/Unit test proxies through Illuminate\Support\Facades\Http::fake(),
 * which never touches Guzzle's real URI resolution, redirects, multipart
 * encoding, or streaming — exactly where the riskiest bugs live. These tests
 * instead proxy through a real PHP built-in web server (see
 * tests/Fixtures/real-server-router.php) so body encoding, header/query
 * forwarding, streaming, and the allowed-hosts guard are exercised against
 * an actual socket.
 */
class RealServerProcess
{
    private static ?self $instance = null;

    /** @var resource|null */
    private $process = null;

    private ?string $logFile = null;

    private int $port = 0;

    private bool $ready = false;

    public static function instance(): self
    {
        return self::$instance ??= new self;
    }

    public function start(): void
    {
        if ($this->process !== null) {
            return;
        }

        $this->port = $this->findFreePort();

        if ($this->port === 0) {
            return;
        }

        $this->logFile = tempnam(sys_get_temp_dir(), 'passage-real-server-');
        $router = __DIR__.'/../Fixtures/real-server-router.php';

        $process = @proc_open(
            [PHP_BINARY, '-S', "127.0.0.1:{$this->port}", $router],
            [1 => ['file', $this->logFile, 'w'], 2 => ['file', $this->logFile, 'w']],
            $pipes,
        );

        if ($process === false) {
            return;
        }

        $this->process = $process;
        $this->ready = $this->waitUntilAcceptingConnections();
    }

    public function stop(): void
    {
        if ($this->process !== null) {
            proc_terminate($this->process);
            proc_close($this->process);
            $this->process = null;
        }

        if ($this->logFile !== null && file_exists($this->logFile)) {
            unlink($this->logFile);
        }
    }

    public function isReady(): bool
    {
        return $this->ready;
    }

    public function baseUri(): string
    {
        return "http://127.0.0.1:{$this->port}/";
    }

    private function findFreePort(): int
    {
        $socket = @stream_socket_server('tcp://127.0.0.1:0', $errno, $errstr);

        if ($socket === false) {
            return 0;
        }

        $name = stream_socket_get_name($socket, false);
        fclose($socket);

        return (int) substr($name, strrpos($name, ':') + 1);
    }

    private function waitUntilAcceptingConnections(): bool
    {
        $deadline = microtime(true) + 5;

        while (microtime(true) < $deadline) {
            $connection = @fsockopen('127.0.0.1', $this->port, $errno, $errstr, 0.1);

            if ($connection !== false) {
                fclose($connection);

                return true;
            }

            usleep(50_000);
        }

        return false;
    }
}

class RealServerEchoHandler extends PassageHandler
{
    public function getOptions(): array
    {
        return ['base_uri' => RealServerProcess::instance()->baseUri()];
    }
}

class RealServerStreamingHandler extends PassageHandler
{
    public function getOptions(): array
    {
        return [
            'base_uri' => RealServerProcess::instance()->baseUri(),
            'passage_streaming' => true,
        ];
    }
}

class RealServerAllowedHostHandler extends PassageHandler
{
    public function getOptions(): array
    {
        return ['base_uri' => RealServerProcess::instance()->baseUri()];
    }
}

class RealServerDisallowedHostHandler extends PassageHandler
{
    public function getOptions(): array
    {
        return ['base_uri' => 'http://upstream.invalid.test:1/'];
    }
}

beforeAll(function () {
    RealServerProcess::instance()->start();
});

afterAll(function () {
    RealServerProcess::instance()->stop();
});

beforeEach(function () {
    if (! RealServerProcess::instance()->isReady()) {
        $this->markTestSkipped('Could not start the PHP built-in server used for real-server integration tests.');
    }
});

describe('real HTTP transport', function () {
    it('forwards a JSON body, custom headers, and query parameters over a real socket', function () {
        Passage::post('real/json/{path?}', RealServerEchoHandler::class);

        $response = $this->withHeaders(['X-Client-Header' => 'from-client'])
            ->postJson('/real/json/echo?filter=active', ['name' => 'Ada']);

        $response->assertOk();
        $payload = $response->json();

        expect($payload['method'])->toBe('POST')
            ->and($payload['path'])->toBe('/echo')
            ->and($payload['query'])->toBe(['filter' => 'active'])
            ->and(json_decode($payload['body'], true))->toBe(['name' => 'Ada'])
            ->and($payload['headers'])->toHaveKey('X-CLIENT-HEADER', 'from-client');
    });

    it('forwards a urlencoded form body over a real socket', function () {
        Passage::post('real/form/{path?}', RealServerEchoHandler::class);

        // PassageService forwards this branch's raw request body verbatim
        // (see its comment on why it doesn't re-encode $request->post()), so
        // the raw content must be set explicitly here — Laravel's post()
        // helper only populates the parsed request/post array, not the raw
        // body a real client's request would carry.
        $response = $this->call(
            'POST',
            '/real/form/echo',
            content: http_build_query(['username' => 'ada', 'role' => 'admin']),
        );

        $response->assertOk();
        $payload = $response->json();

        expect($payload['post'])->toBe(['username' => 'ada', 'role' => 'admin']);
    });

    it('forwards a multipart file upload byte-for-byte over a real socket', function () {
        Passage::post('real/upload/{path?}', RealServerEchoHandler::class);

        $file = UploadedFile::fake()->createWithContent('avatar.txt', str_repeat('a', 1024));

        $response = $this->post('/real/upload/echo', ['label' => 'profile', 'avatar' => $file]);

        $response->assertOk();
        $payload = $response->json();

        expect($payload['post'])->toBe(['label' => 'profile'])
            ->and($payload['files'])->toHaveKey('avatar')
            ->and($payload['files']['avatar']['name'])->toBe('avatar.txt')
            ->and($payload['files']['avatar']['size'])->toBe(1024);
    });

    it('streams a chunked upstream response back to the client over a real socket', function () {
        Passage::get('real/stream/{path?}', RealServerStreamingHandler::class);

        $response = $this->get('/real/stream/stream');

        $response->assertOk();
        expect($response->streamedContent())
            ->toBe("chunk-0\nchunk-1\nchunk-2\nchunk-3\nchunk-4\n");
    });

    it('rejects an upstream host outside allowed_hosts before making a real network call', function () {
        config()->set('passage.security.enforce_allowed_hosts', true);
        config()->set('passage.security.allowed_hosts', ['127.0.0.1']);

        Passage::get('real/disallowed/{path?}', RealServerDisallowedHostHandler::class);

        $this->get('/real/disallowed/echo')
            ->assertForbidden()
            ->assertJson(['error' => 'Upstream host is not permitted.']);
    });

    it('allows an upstream host in allowed_hosts over a real socket', function () {
        config()->set('passage.security.enforce_allowed_hosts', true);
        config()->set('passage.security.allowed_hosts', ['127.0.0.1']);

        Passage::get('real/allowed/{path?}', RealServerAllowedHostHandler::class);

        $response = $this->get('/real/allowed/echo?ok=1');

        $response->assertOk();
        expect($response->json('query'))->toBe(['ok' => '1']);
    });
});
