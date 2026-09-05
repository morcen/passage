<?php

/**
 * Regression test for #180: README.md claimed PassageEventSubscriber logs
 * both outgoing requests and responses at "info" level, but
 * onRequestSending() actually logs at "debug" (see
 * PassageEventSubscriberTest, which asserts Log::shouldReceive('debug') for
 * that event). Someone configuring their "passage" log channel at "info"
 * based on the README would silently lose all request-sending log entries.
 *
 * This guards against the README drifting from the documented log levels
 * again.
 */
describe('README PassageEventSubscriber docs', function () {
    it('documents the actual debug/info/error log levels used by PassageEventSubscriber', function () {
        $readme = file_get_contents(__DIR__.'/../../README.md');

        expect($readme)->toContain(
            'This subscriber logs to a `passage` channel at `debug` level for outgoing requests, `info` level for responses, and `error` level for failures.'
        );
    });
});
