<?php

use GuzzleHttp\Exception\ConnectException;
use GuzzleHttp\Exception\TooManyRedirectsException;
use GuzzleHttp\Psr7\Request as Psr7Request;
use Illuminate\Contracts\Debug\ExceptionHandler;
use Illuminate\Http\Client\ConnectionException;
use Morcen\Passage\Http\PassageErrorHandler;
use Symfony\Component\HttpFoundation\Response as ResponseCode;

function passageErrorHandlerRequest(): Psr7Request
{
    return new Psr7Request('GET', 'https://api.example.com/items');
}

it('returns 504 when the connect exception errno matches the curl timeout constant', function () {
    $cause = new ConnectException(
        'Operation timed out',
        passageErrorHandlerRequest(),
        null,
        ['errno' => CURLE_OPERATION_TIMEDOUT]
    );

    $response = (new PassageErrorHandler)->handle(new ConnectionException('Timeout', 0, $cause));

    expect($response->getStatusCode())->toBe(ResponseCode::HTTP_GATEWAY_TIMEOUT);
    expect($response->getData(true))->toBe(['error' => 'Upstream service timed out.']);
});

it('returns 502 when the connect exception errno does not match the curl timeout constant', function () {
    $cause = new ConnectException(
        'Could not resolve host',
        passageErrorHandlerRequest(),
        null,
        ['errno' => CURLE_COULDNT_RESOLVE_HOST]
    );

    $response = (new PassageErrorHandler)->handle(new ConnectionException('DNS failure', 0, $cause));

    expect($response->getStatusCode())->toBe(ResponseCode::HTTP_BAD_GATEWAY);
    expect($response->getData(true))->toBe(['error' => 'Upstream service is unreachable.']);
});

it('returns 502 when the connect exception has no handler context', function () {
    $cause = new ConnectException('Connection refused', passageErrorHandlerRequest());

    $response = (new PassageErrorHandler)->handle(new ConnectionException('Connection refused', 0, $cause));

    expect($response->getStatusCode())->toBe(ResponseCode::HTTP_BAD_GATEWAY);
});

it('does not crash and falls back to 502 when the curl timeout constant is unavailable', function () {
    // Simulates an install without ext-curl, where CURLE_OPERATION_TIMEDOUT is undefined.
    $handler = new class extends PassageErrorHandler
    {
        protected function curlOperationTimedOutErrno(): ?int
        {
            return null;
        }
    };

    $cause = new ConnectException(
        'Operation timed out',
        passageErrorHandlerRequest(),
        null,
        ['errno' => CURLE_OPERATION_TIMEDOUT]
    );

    $response = $handler->handle(new ConnectionException('Timeout', 0, $cause));

    expect($response->getStatusCode())->toBe(ResponseCode::HTTP_BAD_GATEWAY);
    expect($response->getData(true))->toBe(['error' => 'Upstream service is unreachable.']);
});

it('returns 502 for too many redirects', function () {
    $exception = new TooManyRedirectsException('Too many redirects', passageErrorHandlerRequest());

    $response = (new PassageErrorHandler)->handle($exception);

    expect($response->getStatusCode())->toBe(ResponseCode::HTTP_BAD_GATEWAY);
    expect($response->getData(true))->toBe(['error' => 'Upstream service is unreachable.']);
});

it('returns 500 for unexpected exceptions', function () {
    $response = (new PassageErrorHandler)->handle(new RuntimeException('Something broke'));

    expect($response->getStatusCode())->toBe(ResponseCode::HTTP_INTERNAL_SERVER_ERROR);
    expect($response->getData(true))->toBe(['error' => 'An unexpected error occurred.']);
});

it('reports every handled exception through Laravel\'s exception handler', function () {
    $exception = new RuntimeException('Something broke');

    $this->mock(ExceptionHandler::class)
        ->shouldReceive('report')
        ->once()
        ->with($exception);

    (new PassageErrorHandler)->handle($exception);
});

it('still reports a connection-level exception, not just unexpected ones', function () {
    $exception = new ConnectionException('Timeout');

    $this->mock(ExceptionHandler::class)
        ->shouldReceive('report')
        ->once()
        ->with($exception);

    (new PassageErrorHandler)->handle($exception);
});
