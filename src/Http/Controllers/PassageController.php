<?php

namespace Morcen\Passage\Http\Controllers;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Symfony\Component\HttpFoundation\Response as ResponseCode;

class PassageController extends Controller
{
    /** @var string */
    private const URL_SEPARATOR = '/';

    protected array $headers = [];

    public function index(Request $request): JsonResponse
    {
        $uriParts = explode(self::URL_SEPARATOR, $request->path());

        if ($uriParts) {
            $serviceName = array_shift($uriParts);
            $uri = implode(self::URL_SEPARATOR, $uriParts);

            if (Http::hasMacro($serviceName)) {
                return $this->callService($request, Http::$serviceName(), $uri);
            }
        }

        return response()->json(['error' => 'Route not found'], ResponseCode::HTTP_NOT_FOUND);
    }

    protected function callService(Request $request, PendingRequest $service, string $uri): JsonResponse
    {
        // TODO: prepare headers
        $method = strtolower($request->method());
        $params = $request->all();

        /** @var Response $response */
        $response = $service->{$method}($uri, $params);

        return response()->json($response->object(), $response->status());
    }
}
