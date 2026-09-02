<?php

/**
 * Regression test for #92: composer.json hardcoded a "version" field, which
 * Packagist advises against. A forgotten manual bump silently drifts out of
 * sync with the actual tagged release (as happened repeatedly: #104, #205,
 * and #220 each had to bump it by hand), so the field must stay absent and
 * versioning must be driven by git tags/Packagist instead.
 */
describe('composer.json version field', function () {
    it('does not hardcode a version field', function () {
        $composer = json_decode(file_get_contents(__DIR__.'/../../composer.json'), true);

        expect($composer)->not->toHaveKey('version');
    });
});
