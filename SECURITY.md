# Security Policy

## Supported Versions

Passage is an API gateway for Laravel that handles authentication secrets
(HMAC signatures, bearer tokens, API keys) and forwards HTTP traffic on your
behalf, so security fixes are taken seriously. Only the latest major version
receives security patches.

| Version | Supported          |
| ------- | ------------------ |
| 3.x     | :white_check_mark: |
| 2.x     | :x:                |
| 1.x     | :x:                |

If you are on an unsupported version, please upgrade to the latest 3.x
release before reporting an issue — it may already be fixed. See
[UPGRADE.md](UPGRADE.md) for migration guidance.

## Reporting a Vulnerability

Please **do not** report security vulnerabilities through public GitHub
issues.

Instead, report them by:

- Using GitHub's [private vulnerability reporting](https://github.com/morcen/passage/security/advisories/new) (preferred), or
- Emailing **hello@morcen.com** with a description of the issue, steps to
  reproduce, and any relevant logs or proof-of-concept code.

You should expect an initial response within **5 business days**. We will
keep you updated as the issue is investigated and fixed, and will credit you
in the release notes unless you prefer to remain anonymous.

## Scope

Examples of issues in scope for this policy include (but aren't limited to):

- Server-Side Request Forgery (SSRF) bypasses of the allowed-hosts guard or
  redirect re-validation
- HMAC/API key/bearer signature forging or verification bypasses
- Header injection, request smuggling, or improper stripping of sensitive
  headers
- Any way to bypass Passage's proxying guards to reach an unintended upstream
