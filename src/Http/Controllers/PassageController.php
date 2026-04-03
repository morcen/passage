<?php

namespace Morcen\Passage\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Morcen\Passage\PassageControllerInterface;
use Morcen\Passage\Services\PassageServiceInterface;
use Symfony\Component\HttpFoundation\Response as ResponseCode;

class PassageController extends Controller
{
    public function __construct(
        protected readonly PassageServiceInterface $passageService
    ) {}

    public function handle(Request $request): JsonResponse
    {
        $handler = $request->route()->defaults['_passage_handler'] ?? null;
        $path = (string) $request->route('path', '');

        if (! $handler
            || ! class_exists($handler)
            || ! is_subclass_of($handler, PassageControllerInterface::class)
        ) {
            return response()->json(['error' => 'Route not found'], ResponseCode::HTTP_NOT_FOUND);
        }

        $handlerInstance = new $handler;
        $options = array_merge(config('passage.options', []), $handlerInstance->getOptions());
        $pendingRequest = Http::withOptions($options);

        $request = $handlerInstance->getRequest($request);
        $response = $this->passageService->callService($request, $pendingRequest, $path);
        $response = $handlerInstance->getResponse($request, $response);

        return response()->json($response->json(), $response->status());
    }
}
