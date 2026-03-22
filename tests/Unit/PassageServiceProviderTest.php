<?php

use Morcen\Passage\Services\PassageServiceInterface;

describe('PassageServiceProvider', function () {
    it('binds PassageServiceInterface when passage is enabled', function () {
        expect($this->app->bound(PassageServiceInterface::class))->toBeTrue();
    });

    it('does not register Route::passage() macro', function () {
        expect(\Illuminate\Support\Facades\Route::hasMacro('passage'))->toBeFalse();
    });
});
