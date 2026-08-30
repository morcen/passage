<?php

/**
 * Regression test for #151: README.md and .github/ISSUE_TEMPLATE/config.yml
 * both link to a GitHub-generated security policy page, which only resolves
 * to real content once a SECURITY.md exists in the repository. Without it,
 * both links silently 404 to GitHub's generic "no security policy" page.
 */
describe('security policy', function () {
    it('has a SECURITY.md at the repository root', function () {
        expect(file_exists(__DIR__.'/../../SECURITY.md'))->toBeTrue();
    });

    it('documents how to report a vulnerability and which versions are supported', function () {
        $contents = file_get_contents(__DIR__.'/../../SECURITY.md');

        expect($contents)
            ->toContain('Supported Versions')
            ->toContain('Reporting a Vulnerability');
    });

    it('is linked from the README security section', function () {
        $readme = file_get_contents(__DIR__.'/../../README.md');

        expect($readme)->toContain('../../security/policy');
    });

    it('is linked from the bug report issue template config', function () {
        $config = file_get_contents(__DIR__.'/../../.github/ISSUE_TEMPLATE/config.yml');

        expect($config)->toContain('https://github.com/morcen/passage/security/policy');
    });
});
