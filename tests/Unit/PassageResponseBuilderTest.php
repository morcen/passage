<?php

use GuzzleHttp\Psr7\Response as Psr7Response;
use GuzzleHttp\Psr7\Utils;
use Illuminate\Http\Client\Response;
use Illuminate\Http\JsonResponse;
use Morcen\Passage\Http\PassageResponseBuilder;
use Symfony\Component\HttpFoundation\StreamedResponse;

beforeEach(function () {
    $this->builder = new PassageResponseBuilder;
});

/**
 * Build a mock upstream Response.
 */
function upstreamMock(string $body, int $status, string $contentType, array $headers = []): Response
{
    $mock = Mockery::mock(Response::class);
    $mock->shouldReceive('status')->andReturn($status);
    $mock->shouldReceive('body')->andReturn($body);
    $mock->shouldReceive('header')->with('Content-Type')->andReturn($contentType);
    $allHeaders = array_merge(['content-type' => [$contentType]], $headers);
    $mock->shouldReceive('headers')->andReturn($allHeaders);
    if (str_contains($contentType, 'application/json')) {
        $mock->shouldReceive('json')->andReturn(json_decode($body, true));
    }

    return $mock;
}

describe('PassageResponseBuilder::build()', function () {
    describe('JSON responses', function () {
        it('returns a JsonResponse for application/json content type', function () {
            $upstream = upstreamMock('{"id":1,"name":"Alice"}', 200, 'application/json');

            $response = $this->builder->build($upstream);

            expect($response)->toBeInstanceOf(JsonResponse::class);
            expect($response->getStatusCode())->toBe(200);
            expect($response->getData(true))->toBe(['id' => 1, 'name' => 'Alice']);
        });

        it('preserves non-sensitive upstream headers on JSON response', function () {
            $upstream = upstreamMock(
                '{"ok":true}',
                200,
                'application/json',
                ['x-request-id' => ['req-abc'], 'x-rate-limit' => ['100']]
            );

            $response = $this->builder->build($upstream);

            expect($response->headers->get('x-request-id'))->toBe('req-abc');
            expect($response->headers->get('x-rate-limit'))->toBe('100');
        });

        it('preserves the upstream status code', function () {
            $upstream = upstreamMock('{"error":"not found"}', 404, 'application/json');

            $response = $this->builder->build($upstream);

            expect($response->getStatusCode())->toBe(404);
        });
    });

    describe('XML responses', function () {
        it('returns a plain response for application/xml', function () {
            $xml = '<?xml version="1.0"?><root><id>1</id></root>';
            $upstream = upstreamMock($xml, 200, 'application/xml');

            $response = $this->builder->build($upstream);

            expect($response)->not->toBeInstanceOf(JsonResponse::class);
            expect($response->getStatusCode())->toBe(200);
            expect($response->getContent())->toBe($xml);
        });

        it('sets the correct Content-Type on XML response', function () {
            $xml = '<items/>';
            $upstream = upstreamMock($xml, 200, 'application/xml');

            $response = $this->builder->build($upstream);

            expect($response->headers->get('content-type'))->toContain('application/xml');
        });
    });

    describe('plain text responses', function () {
        it('returns a plain response for text/plain', function () {
            $upstream = upstreamMock('pong', 200, 'text/plain');

            $response = $this->builder->build($upstream);

            expect($response)->not->toBeInstanceOf(JsonResponse::class);
            expect($response->getContent())->toBe('pong');
        });
    });

    describe('binary / octet-stream responses', function () {
        it('returns a plain response for application/octet-stream', function () {
            $upstream = upstreamMock("\x89PNG\r\n", 200, 'application/octet-stream');

            $response = $this->builder->build($upstream);

            expect($response->getStatusCode())->toBe(200);
            expect($response->getContent())->toBe("\x89PNG\r\n");
        });
    });

    describe('hop-by-hop header stripping', function () {
        it('strips hop-by-hop headers from upstream response', function () {
            $upstream = Mockery::mock(Response::class);
            $upstream->shouldReceive('status')->andReturn(200);
            $upstream->shouldReceive('body')->andReturn('{}');
            $upstream->shouldReceive('header')->with('Content-Type')->andReturn('application/json');
            $upstream->shouldReceive('json')->andReturn([]);
            $upstream->shouldReceive('headers')->andReturn([
                'content-type' => ['application/json'],
                'connection' => ['keep-alive'],
                'transfer-encoding' => ['chunked'],
                'x-custom' => ['pass-through'],
            ]);

            $response = $this->builder->build($upstream);

            expect($response->headers->has('connection'))->toBeFalse();
            expect($response->headers->has('transfer-encoding'))->toBeFalse();
            expect($response->headers->get('x-custom'))->toBe('pass-through');
        });

        it('strips a Proxy-Authorization header on the upstream response using the published config default', function () {
            $upstream = Mockery::mock(Response::class);
            $upstream->shouldReceive('status')->andReturn(200);
            $upstream->shouldReceive('body')->andReturn('{}');
            $upstream->shouldReceive('header')->with('Content-Type')->andReturn('application/json');
            $upstream->shouldReceive('json')->andReturn([]);
            $upstream->shouldReceive('headers')->andReturn([
                'content-type' => ['application/json'],
                'proxy-authorization' => ['Basic secret-credentials'],
                'x-custom' => ['pass-through'],
            ]);

            $response = $this->builder->build($upstream);

            expect($response->headers->has('proxy-authorization'))->toBeFalse();
            expect($response->headers->get('x-custom'))->toBe('pass-through');
        });
    });

    describe('stale Content-Encoding/Content-Length headers', function () {
        it('strips Content-Encoding and Content-Length since Guzzle already decoded the body', function () {
            // Guzzle's default decode_content transparently gunzips the body but leaves
            // Content-Encoding/Content-Length describing the discarded compressed bytes.
            $upstream = upstreamMock(
                'plain decoded body',
                200,
                'text/plain',
                ['content-encoding' => ['gzip'], 'content-length' => ['36'], 'x-custom' => ['pass-through']]
            );

            $response = $this->builder->build($upstream);

            expect($response->headers->has('content-encoding'))->toBeFalse();
            expect($response->headers->has('content-length'))->toBeFalse();
            expect($response->headers->get('x-custom'))->toBe('pass-through');
            expect($response->getContent())->toBe('plain decoded body');
        });

        it('strips Content-Encoding and Content-Length on JSON responses too', function () {
            $upstream = upstreamMock(
                '{"ok":true}',
                200,
                'application/json',
                ['content-encoding' => ['deflate'], 'content-length' => ['10']]
            );

            $response = $this->builder->build($upstream);

            expect($response->headers->has('content-encoding'))->toBeFalse();
            expect($response->headers->has('content-length'))->toBeFalse();
        });
    });
});

describe('PassageResponseBuilder::buildStreamedResponse()', function () {
    it('strips Content-Encoding and Content-Length from the streamed response', function () {
        $upstream = Mockery::mock(Response::class);
        $upstream->shouldReceive('status')->andReturn(200);
        $upstream->shouldReceive('headers')->andReturn([
            'content-type' => ['text/event-stream'],
            'content-encoding' => ['gzip'],
            'content-length' => ['36'],
            'x-custom' => ['pass-through'],
        ]);

        $stream = Utils::streamFor('data: hello');
        $psr7 = new Psr7Response(200, [], $stream);
        $upstream->shouldReceive('toPsrResponse')->andReturn($psr7);

        $response = $this->builder->buildStreamedResponse($upstream);

        expect($response)->toBeInstanceOf(StreamedResponse::class);
        expect($response->headers->has('content-encoding'))->toBeFalse();
        expect($response->headers->has('content-length'))->toBeFalse();
        expect($response->headers->get('x-custom'))->toBe('pass-through');
    });
});
