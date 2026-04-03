<?php

namespace Morcen\Passage\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Morcen\Passage\Exceptions\InvalidBaseUriException;
use Morcen\Passage\Http\PassageResponseBuilder;
use Morcen\Passage\PassageControllerInterface;
use Morcen\Passage\Services\PassageServiceInterface;
use Symfony\Component\HttpFoundation\Response;

class PassageController extends Controller
{
    // Stripped from the inbound client request before the handler sees it.
    // Prevents client credentials from leaking to upstream services.
    // Phase 2 will make this list configurable via config('passage.security.strip_client_headers').
    private const SENSITIVE_CLIENT_HEADERS = ['cookie', 'authorization', 'proxy-authorization'];

    public function __construct(
        protected readonly PassageServiceInterface $passageService,
        protected readonly PassageResponseBuilder $responseBuilder,
    ) {}

    public function handle(Request $request): Response
    {
        $handler = $request->route()->defaults['_passage_handler'] ?? null;
        $path = (string) $request->route('path', '');

        if (! $handler
            || ! class_exists($handler)
            || ! is_subclass_of($handler, PassageControllerInterface::class)
        ) {
            return response()->json(['error' => 'Route not found'], Response::HTTP_NOT_FOUND);
        }

        $handlerInstance = new $handler;
        $options = array_merge(config('passage.options', []), $handlerInstance->getOptions());

        if (empty($options['base_uri'])) {
            throw new InvalidBaseUriException("Passage handler [{$handler}] must return a 'base_uri' from getOptions().");
        }

        $pendingRequest = Http::withOptions($options);

        // Strip sensitive client headers before the handler processes the request.
        // The handler may re-add these with service-level credentials (e.g. via HasBearerAuth).
        foreach (self::SENSITIVE_CLIENT_HEADERS as $header) {
            $request->headers->remove($header);
        }

        $request = $handlerInstance->getRequest($request);
        $upstream = $this->passageService->callService($request, $pendingRequest, $path);
        $upstream = $handlerInstance->getResponse($request, $upstream);

        return $this->responseBuilder->build($upstream);
    }
}
