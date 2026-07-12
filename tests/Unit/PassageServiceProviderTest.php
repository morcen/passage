<?php

use Illuminate\Support\Facades\Route;
use Morcen\Passage\PassageServiceProvider;
use Morcen\Passage\Services\PassageServiceInterface;

describe('PassageServiceProvider', function () {
    it('binds PassageServiceInterface when passage is enabled', function () {
        expect($this->app->bound(PassageServiceInterface::class))->toBeTrue();
    });

    it('keeps PassageServiceInterface resolvable when passage is disabled', function () {
        config(['passage.enabled' => false]);
        $this->app->offsetUnset(PassageServiceInterface::class);

        (new PassageServiceProvider($this->app))->packageBooted();

        expect($this->app->bound(PassageServiceInterface::class))->toBeTrue();
    });

    it('does not register Route::passage() macro', function () {
        expect(Route::hasMacro('passage'))->toBeFalse();
    });
});
