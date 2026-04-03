<?php

use Illuminate\Http\Client\Response;
use Illuminate\Http\Request;
use Morcen\Passage\PassageControllerInterface;
use Morcen\Passage\PassageHandler;

// Concrete handler using PassageHandler base class
class BearerAuthHandler extends PassageHandler
{
    public function getRequest(Request $request): Request
    {
        return $this->withBearerToken($request, 'test-service-token');
    }

    public function getOptions(): array
    {
        return ['base_uri' => 'https://api.example.com/'];
    }
}

class ApiKeyHeaderHandler extends PassageHandler
{
    public function getRequest(Request $request): Request
    {
        return $this->withApiKey($request, 'my-api-key');
    }

    public function getOptions(): array
    {
        return ['base_uri' => 'https://api.example.com/'];
    }
}

class ApiKeyQueryHandler extends PassageHandler
{
    public function getRequest(Request $request): Request
    {
        return $this->withApiKeyQuery($request, 'my-api-key', 'key');
    }

    public function getOptions(): array
    {
        return ['base_uri' => 'https://maps.example.com/'];
    }
}

class HmacHandler extends PassageHandler
{
    public function getRequest(Request $request): Request
    {
        return $this->withHmacSignature($request, 'super-secret');
    }

    public function getOptions(): array
    {
        return ['base_uri' => 'https://partner.example.com/'];
    }
}

describe('HasBearerAuth', function () {
    it('sets Authorization header with Bearer prefix', function () {
        $handler = new BearerAuthHandler;
        $request = Request::create('/test', 'GET');

        $result = $handler->getRequest($request);

        expect($result->header('Authorization'))->toBe('Bearer test-service-token');
    });
});

// Fixture: custom header name
class ApiKeyCustomHeaderHandler extends PassageHandler
{
    public function getRequest(Request $request): Request
    {
        return $this->withApiKey($request, 'my-api-key', 'X-Auth-Token');
    }

    public function getOptions(): array
    {
        return ['base_uri' => 'https://api.example.com/'];
    }
}

// Fixture: custom query param name
class ApiKeyCustomParamHandler extends PassageHandler
{
    public function getRequest(Request $request): Request
    {
        return $this->withApiKeyQuery($request, 'my-api-key', 'access_key');
    }

    public function getOptions(): array
    {
        return ['base_uri' => 'https://api.example.com/'];
    }
}

describe('HasApiKeyAuth', function () {
    it('sets the default X-API-Key header', function () {
        $handler = new ApiKeyHeaderHandler;
        $request = Request::create('/test', 'GET');

        $result = $handler->getRequest($request);

        expect($result->header('X-API-Key'))->toBe('my-api-key');
    });

    it('supports a custom header name', function () {
        $handler = new ApiKeyCustomHeaderHandler;
        $request = Request::create('/test', 'GET');

        $result = $handler->getRequest($request);

        expect($result->header('X-Auth-Token'))->toBe('my-api-key');
    });

    it('injects API key as a query parameter', function () {
        $handler = new ApiKeyQueryHandler;
        $request = Request::create('/test', 'GET');

        $result = $handler->getRequest($request);

        expect($result->query('key'))->toBe('my-api-key');
    });

    it('supports a custom query param name', function () {
        $handler = new ApiKeyCustomParamHandler;
        $request = Request::create('/test', 'GET');

        $result = $handler->getRequest($request);

        expect($result->query('access_key'))->toBe('my-api-key');
    });
});

// Fixture: custom HMAC headers and algorithm
class HmacCustomHandler extends PassageHandler
{
    public function getRequest(Request $request): Request
    {
        return $this->withHmacSignature($request, 'super-secret', 'sha512', 'X-Req-Time', 'X-Req-Sig');
    }

    public function getOptions(): array
    {
        return ['base_uri' => 'https://partner.example.com/'];
    }
}

describe('HasHmacAuth', function () {
    it('adds X-Timestamp and X-Signature headers', function () {
        $handler = new HmacHandler;
        $request = Request::create('/test', 'POST', [], [], [], ['CONTENT_TYPE' => 'application/json'], '{"id":1}');

        $result = $handler->getRequest($request);

        expect($result->header('X-Timestamp'))->not->toBeEmpty();
        expect($result->header('X-Signature'))->not->toBeEmpty();
    });

    it('produces a verifiable HMAC signature', function () {
        $secret = 'my-secret';
        $body = '{"event":"test"}';
        $handler = new HmacHandler; // uses 'super-secret', so re-verify with its secret
        $request = Request::create('/test', 'POST', [], [], [], ['CONTENT_TYPE' => 'application/json'], $body);

        $result = $handler->getRequest($request);

        $timestamp = $result->header('X-Timestamp');
        $signature = $result->header('X-Signature');
        $expected = hash_hmac('sha256', $timestamp.'.'.$body, 'super-secret');

        expect($signature)->toBe($expected);
    });

    it('supports custom header names and algorithm', function () {
        $handler = new HmacCustomHandler;
        $request = Request::create('/test', 'POST');

        $result = $handler->getRequest($request);

        expect($result->header('X-Req-Time'))->not->toBeEmpty();
        expect($result->header('X-Req-Sig'))->not->toBeEmpty();

        $timestamp = $result->header('X-Req-Time');
        $expected = hash_hmac('sha512', $timestamp.'.', 'super-secret');
        expect($result->header('X-Req-Sig'))->toBe($expected);
    });
});

describe('PassageHandler base class', function () {
    it('provides no-op defaults for all three interface methods', function () {
        $handler = new class extends PassageHandler
        {
            public function getOptions(): array
            {
                return ['base_uri' => 'https://api.example.com/'];
            }
        };
        $request = Request::create('/test', 'GET');
        $response = Mockery::mock(Response::class);

        expect($handler->getRequest($request))->toBe($request);
        expect($handler->getResponse($request, $response))->toBe($response);
    });

    it('implements PassageControllerInterface', function () {
        $handler = new class extends PassageHandler
        {
            public function getOptions(): array
            {
                return ['base_uri' => 'https://api.example.com/'];
            }
        };

        expect($handler)->toBeInstanceOf(PassageControllerInterface::class);
    });
});
