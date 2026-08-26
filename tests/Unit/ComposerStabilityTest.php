<?php

/**
 * Regression test for #153: composer.json declared "minimum-stability": "dev"
 * even though every require/require-dev entry resolves to a stable release.
 * With minimum-stability set to "dev", Composer is allowed (not merely
 * preferred away from, since prefer-stable only breaks ties) to resolve any
 * transitive dependency to a dev/alpha/beta version, which is an avoidable
 * supply-chain risk for a package that other applications require.
 */
describe('composer.json stability', function () {
    it('requires stable releases instead of allowing dev/alpha/beta ones', function () {
        $composer = json_decode(file_get_contents(__DIR__.'/../../composer.json'), true);

        expect($composer['minimum-stability'])->toBe('stable');
        expect($composer['prefer-stable'])->toBeTrue();
    });
});
