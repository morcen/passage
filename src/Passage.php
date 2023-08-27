<?php

namespace Morcen\Passage;

use App\Exceptions\MissingPassageService;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;

class Passage
{
    /**
     * @param  string  $service
     * @return PendingRequest
     * @throws MissingPassageService
     */
    public function getService(string $service): PendingRequest
    {
        if (Http::hasMacro($service)) {
            return Http::$service();
        }

        throw new MissingPassageService("The service \"{$service}\" is not available in your passage services.");
    }
}
