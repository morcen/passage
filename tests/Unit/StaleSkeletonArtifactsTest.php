<?php

/**
 * Regression test for #143: Passage started from the standard
 * spatie/laravel-package-tools skeleton, and a few skeleton leftovers were
 * never cleaned up once the package settled into its current (model-less)
 * shape:
 *
 * - composer.json declared a PSR-4 autoload mapping to a "database/factories"
 *   directory that doesn't exist anywhere in the repo (Passage has no
 *   Eloquent models, so there's nothing to have factories for).
 * - .gitattributes listed several "export-ignore" paths from the skeleton
 *   that don't exist in this repo, and referenced "/UPGRADING.md" under the
 *   skeleton's old name instead of the repo's actual "UPGRADE.md".
 */
describe('composer.json autoload', function () {
    it('does not declare a PSR-4 mapping for a nonexistent database/factories directory', function () {
        $composer = json_decode(file_get_contents(__DIR__.'/../../composer.json'), true);

        expect($composer['autoload']['psr-4'])->not->toHaveKey('Morcen\\Passage\\Database\\Factories\\');
    });
});

describe('.gitattributes', function () {
    it('does not export-ignore paths that do not exist in the repository', function () {
        $gitattributes = file_get_contents(__DIR__.'/../../.gitattributes');

        preg_match_all('/^\/(\S+)\s+export-ignore$/m', $gitattributes, $matches);

        foreach ($matches[1] as $path) {
            expect(file_exists(__DIR__.'/../../'.$path))
                ->toBeTrue("Expected export-ignored path \"{$path}\" to exist in the repository.");
        }
    });

    it("does not reference the skeleton's old UPGRADING.md filename", function () {
        $gitattributes = file_get_contents(__DIR__.'/../../.gitattributes');

        expect($gitattributes)->not->toContain('UPGRADING.md');
    });
});
