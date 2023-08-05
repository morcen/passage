<?php

namespace Morcen\Passage\Http\Controllers;

use Illuminate\Http\Client\Response;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Morcen\Passage\PassageControllerInterface;
use Morcen\Passage\Services\PassageServiceInterface;
use Symfony\Component\HttpFoundation\Response as ResponseCode;

class PassageController extends Controller
{
    /** @var string */
    private const URL_SEPARATOR = '/';

    public function __construct(protected PassageServiceInterface $passageService)
    {
    }

    public function index(Request $request): JsonResponse
    {
        $uriParts = explode(self::URL_SEPARATOR, $request->path());

        $serviceName = array_shift($uriParts);
        $uri = implode(self::URL_SEPARATOR, $uriParts);

        if (Http::hasMacro($serviceName)) {
            $request = $this->getRequest($serviceName, $request);
            $response = $this->passageService->callService(
                $request,
                Http::$serviceName(),
                $uri
            );

            return $this->returnResponse($serviceName, $request, $response);
        }

        return response()->json(['error' => 'Route not found'], ResponseCode::HTTP_NOT_FOUND);
    }

    private function getRequest(string $serviceName, Request $request): Request
    {
        $handler = config('passage.services.'.$serviceName);

        if (is_string($handler) && class_exists($handler)) {
            /** @var PassageControllerInterface $handlerController */
            $handlerController = new $handler;

            $request = $handlerController->getRequest($request);
        }

        return $request;
    }

    private function returnResponse(string $serviceName, Request $request, Response $response): JsonResponse
    {
        $handler = config('passage.services.'.$serviceName);

        if (is_string($handler) && class_exists($handler)) {
            /** @var PassageControllerInterface $handlerController */
            $handlerController = new $handler;

            $response = $handlerController->getResponse($request, $response);
        }

        return response()->json($response->json(), $response->status());
    }
}
