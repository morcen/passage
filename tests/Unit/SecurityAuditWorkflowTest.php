&lt;?php

/**
 * Regression test for #157: the "Check for known vulnerabilities in PHP
 * extensions" step in the security-audit workflow could never fail.
 *
 * The step ran `php -m | grep -E "(openssl|curl|libxml)" || echo "..."`.
 * `grep`'s exit code was discarded by `||`, and `echo` always exits 0, so
 * even if none of the extensions were loaded, the step (and therefore the
 * job) still reported success — it only printed a message nobody was
 * required to read.
 */
describe('security-audit.yml PHP extension check', function () {
    it('fails the workflow when the core security extensions are missing', function () {
        $workflow = file_get_contents(__DIR__.'/../../.github/workflows/security-audit.yml');

        expect($workflow)-&gt;not-&gt;toContain('|| echo "Core security extensions not found"');
        expect($workflow)-&gt;toContain('grep -qE "(openssl|curl|libxml)" || { echo "Core security extensions not found"; exit 1; }');
    });
});
