<?php

/**
 * Regression test for #113: composer.json must declare a require entry for every
 * package whose classes are directly imported ("use"d) under src/, instead of
 * relying on those classes being pulled in transitively by other dependencies.
 */
describe('composer.json dependencies', function () {
    it('declares a require entry for every package directly imported under src/', function () {
        $composer = json_decode(file_get_contents(__DIR__.'/../../composer.json'), true);
        $required = array_keys($composer['require']);

        // Maps a fully-qualified class namespace prefix to the composer package
        // that provides it, so an unmapped prefix in src/ fails loudly instead
        // of silently working thanks to a transitive dependency.
        $namespaceToPackage = [
            'GuzzleHttp\\' => 'guzzlehttp/guzzle',
            'Illuminate\\Console\\' => 'illuminate/console',
            'Illuminate\\Contracts\\' => 'illuminate/contracts',
            'Illuminate\\Http\\' => 'illuminate/http',
            'Illuminate\\Routing\\' => 'illuminate/routing',
            'Illuminate\\Support\\' => 'illuminate/support',
            'Symfony\\Component\\Console\\' => 'symfony/console',
            'Symfony\\Component\\HttpFoundation\\' => 'symfony/http-foundation',
        ];

        $files = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator(__DIR__.'/../../src', FilesystemIterator::SKIP_DOTS)
        );

        $missingPackages = [];

        foreach ($files as $file) {
            if ($file->getExtension() !== 'php') {
                continue;
            }

            $contents = file_get_contents($file->getPathname());

            if (! preg_match_all('/^use\s+([A-Za-z0-9_\\\\]+)\s*(?:as\s+[A-Za-z0-9_]+)?;/m', $contents, $matches)) {
                continue;
            }

            foreach ($matches[1] as $importedClass) {
                foreach ($namespaceToPackage as $prefix => $package) {
                    if (! str_starts_with($importedClass, $prefix)) {
                        continue;
                    }

                    if (! in_array($package, $required, true)) {
                        $missingPackages[$package][] = $file->getPathname().' -> '.$importedClass;
                    }

                    break;
                }
            }
        }

        expect($missingPackages)->toBe([]);
    });
});
