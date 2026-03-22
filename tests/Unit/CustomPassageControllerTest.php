<?php

use Carbon\Carbon;
use Illuminate\Http\Client\Response;
use Illuminate\Http\Request;
use Morcen\Passage\PassageControllerInterface;

describe('PassageControllerInterface Implementation', function () {
    beforeEach(function () {
        // Create a concrete implementation for testing
        $this->controller = new class implements PassageControllerInterface
        {
            public function getRequest(Request $request): Request
            {
                // Example: Add authentication header
                $request->headers->set('Authorization', 'Bearer test-token');

                // Example: Transform request data
                $input = $request->all();
                $input['transformed'] = true;
                $request->replace($input);

                return $request;
            }

            public function getResponse(Request $request, Response $response): Response
            {
                // Example: Transform response data
                $data = $response->json();
                $data['processed_by'] = 'passage_controller';

                // Create a new response with transformed data
                $mockResponse = Mockery::mock(Response::class);
                $mockResponse->shouldReceive('json')->andReturn($data);
                $mockResponse->shouldReceive('status')->andReturn($response->status());

                return $mockResponse;
            }

            public function getOptions(): array
            {
                return [
                    'base_uri' => 'https://api.example.com/',
                    'timeout' => 60,
                    'headers' => [
                        'User-Agent' => 'Passage/1.0',
                    ],
                ];
            }
        };
    });

    it('implements PassageControllerInterface correctly', function () {
        expect($this->controller)->toBeInstanceOf(PassageControllerInterface::class);
    });

    it('can transform request data', function () {
        $originalRequest = Request::create('/test', 'POST', ['name' => 'John']);

        $transformedRequest = $this->controller->getRequest($originalRequest);

        expect($transformedRequest->header('Authorization'))->toBe('Bearer test-token');
        expect($transformedRequest->input('name'))->toBe('John');
        expect($transformedRequest->input('transformed'))->toBe(true);
    });

    it('can transform response data', function () {
        $mockResponse = Mockery::mock(Response::class);
        $mockResponse->shouldReceive('json')
            ->once()
            ->andReturn(['id' => 123, 'name' => 'John']);
        $mockResponse->shouldReceive('status')
            ->once()
            ->andReturn(200);

        $request = Request::create('/test', 'GET');

        $transformedResponse = $this->controller->getResponse($request, $mockResponse);

        expect($transformedResponse->json())->toBe([
            'id' => 123,
            'name' => 'John',
            'processed_by' => 'passage_controller',
        ]);
        expect($transformedResponse->status())->toBe(200);
    });

    it('provides correct options configuration', function () {
        $options = $this->controller->getOptions();

        expect($options)->toBe([
            'base_uri' => 'https://api.example.com/',
            'timeout' => 60,
            'headers' => [
                'User-Agent' => 'Passage/1.0',
            ],
        ]);
    });

    it('handles request validation and transformation', function () {
        // Create a controller that validates and transforms requests
        $validatorController = new class implements PassageControllerInterface
        {
            public function getRequest(Request $request): Request
            {
                // Validate required fields
                if (! $request->has('required_field')) {
                    throw new InvalidArgumentException('Missing required field');
                }

                // Transform data format
                if ($request->has('date')) {
                    $date = Carbon::parse($request->input('date'));
                    $request->merge(['formatted_date' => $date->toISOString()]);
                }

                return $request;
            }

            public function getResponse(Request $request, Response $response): Response
            {
                return $response;
            }

            public function getOptions(): array
            {
                return ['base_uri' => 'https://api.validator.com/'];
            }
        };

        $validRequest = Request::create('/test', 'POST', [
            'required_field' => 'value',
            'date' => '2023-01-01',
        ]);

        $transformedRequest = $validatorController->getRequest($validRequest);

        expect($transformedRequest->input('required_field'))->toBe('value');
        expect($transformedRequest->has('formatted_date'))->toBeTrue();
    });

    it('throws exception for invalid request', function () {
        $validatorController = new class implements PassageControllerInterface
        {
            public function getRequest(Request $request): Request
            {
                if (! $request->has('required_field')) {
                    throw new InvalidArgumentException('Missing required field');
                }

                return $request;
            }

            public function getResponse(Request $request, Response $response): Response
            {
                return $response;
            }

            public function getOptions(): array
            {
                return ['base_uri' => 'https://api.validator.com/'];
            }
        };

        $invalidRequest = Request::create('/test', 'POST', ['optional_field' => 'value']);

        expect(fn () => $validatorController->getRequest($invalidRequest))
            ->toThrow(InvalidArgumentException::class, 'Missing required field');
    });

    it('can modify response status and data', function () {
        $responseController = new class implements PassageControllerInterface
        {
            public function getRequest(Request $request): Request
            {
                return $request;
            }

            public function getResponse(Request $request, Response $response): Response
            {
                $data = $response->json();

                // Add metadata to response
                $data['meta'] = [
                    'timestamp' => Carbon::now()->toISOString(),
                    'version' => '1.0',
                ];

                $mockResponse = Mockery::mock(Response::class);
                $mockResponse->shouldReceive('json')->andReturn($data);
                $mockResponse->shouldReceive('status')->andReturn(200); // Force 200 status

                return $mockResponse;
            }

            public function getOptions(): array
            {
                return ['base_uri' => 'https://api.response.com/'];
            }
        };

        $mockOriginalResponse = Mockery::mock(Response::class);
        $mockOriginalResponse->shouldReceive('json')
            ->once()
            ->andReturn(['data' => 'test']);
        $mockOriginalResponse->shouldReceive('status')
            ->andReturn(404); // Original was 404

        $request = Request::create('/test', 'GET');

        $transformedResponse = $responseController->getResponse($request, $mockOriginalResponse);

        expect($transformedResponse->status())->toBe(200); // Changed to 200
        expect($transformedResponse->json()['data'])->toBe('test');
        expect($transformedResponse->json()['meta'])->toHaveKey('timestamp');
        expect($transformedResponse->json()['meta']['version'])->toBe('1.0');
    });
});
