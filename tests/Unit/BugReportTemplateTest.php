<?php

/**
 * Regression test for #155: .github/ISSUE_TEMPLATE/bug.yml's example
 * placeholders were stale — "Package Version" suggested 2.0.0 and
 * "Laravel Version" suggested 9.0.0, even though the README's Requirements
 * section and CHANGELOG.md show only Laravel 11.x/12.x are supported as of
 * v2.0.0, and the package has since moved well past 2.0.0. A reporter
 * skimming the placeholders could reasonably infer those old, unsupported
 * versions were still current/in-scope examples.
 */
describe('.github/ISSUE_TEMPLATE/bug.yml', function () {
    beforeEach(function () {
        $this->template = file_get_contents(__DIR__.'/../../.github/ISSUE_TEMPLATE/bug.yml');
    });

    it('does not suggest the pre-v2.0.0 package version as an example', function () {
        expect($this->template)->not->toMatch('/placeholder:\s*2\.0\.0/');
    });

    it('does not suggest the dropped Laravel 9.x as an example version', function () {
        expect($this->template)->not->toMatch('/placeholder:\s*9\.0\.0/');
    });

    it('suggests a Laravel version placeholder within the currently supported 11.x/12.x range', function () {
        preg_match('/label: Laravel Version.*?placeholder:\s*(\S+)/s', $this->template, $matches);

        expect($matches[1] ?? null)->toMatch('/^1[12]\./');
    });
});
