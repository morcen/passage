<?php

namespace Morcen\Passage\Http\Controllers;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Morcen\Passage\Services\PassageServiceInterface;
use PharIo\Manifest\InvalidUrlException;
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
            return $this->passageService->callService($request, Http::$serviceName(), $uri);
        }

        return response()->json(['error' => 'Route not found'], ResponseCode::HTTP_NOT_FOUND);
    }
}
