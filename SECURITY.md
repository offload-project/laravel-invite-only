# Security Policy

## Supported versions

Security fixes are applied to the latest minor release line. Older minor versions may receive fixes for critical issues at the maintainers' discretion — when in doubt, please upgrade.

| Version       | Supported              |
| ------------- | ---------------------- |
| `2.4.x`       | ✅                     |
| `2.x` (older) | ⚠️ critical fixes only |
| `< 2.0`       | ❌ (please upgrade)    |

## Reporting a vulnerability

**Please do not open a public GitHub issue for security reports.**

Use [GitHub Security Advisories](https://github.com/offload-project/laravel-invite-only/security/advisories/new) to report privately. This lets us discuss, fix, and coordinate disclosure before details become public.

When reporting, please include:

- A description of the issue and its potential impact.
- Steps to reproduce, or a minimal proof-of-concept.
- Affected package version(s), Laravel version, and PHP version.
- Any suggested fix or mitigation (optional).

## Response expectations

- **Acknowledgement:** within 5 business days.
- **Initial assessment:** within 10 business days.
- **Fix timeline:** depends on severity. Critical issues get prioritized; lower-severity issues may be batched into the next regular release.

We'll keep you updated on progress and credit you in the advisory unless you'd prefer to stay anonymous.

## Scope

Things in scope for this project:

- Vulnerabilities in any code published under `OffloadProject\InviteOnly\` (traits, models, services, notifications, events, facade, console commands).
- Invitation token issues — predictable, leakable, replayable, or improperly scoped tokens that could allow unauthorized acceptance of an invitation.
- Authorization issues in `HasInvitations` / `CanBeInvited` traits that could let a user act on an invitation that wasn't intended for them.
- Information disclosure via notifications, events, or exception messages (e.g., leaking tokens, recipient identifiers, or invitable model data).
- Insecure defaults in the published config or migrations.
- SQL injection, mass-assignment, or query-scope bypass in package-provided Eloquent code.

Things **not** in scope (please report upstream or with the relevant project):

- Vulnerabilities in Laravel itself or other Composer dependencies — please file with the respective project.
- Application-level misconfiguration in a consuming app (e.g., publicly exposing an invitation token via an unauthenticated route, custom notification channel that logs the token, or a route that doesn't validate the recipient).
- Issues caused by user-supplied implementations of the package's extension points (custom notifications, custom invitable models, overridden trait methods).
- Vulnerabilities in the host application's authentication, mail driver, queue driver, or database.

## Disclosure

Once a fix is published, we will:

1. Publish a GitHub Security Advisory with details and credit.
2. Tag a patch release.
3. Update the changelog with a brief mention (without exploit details prior to the disclosure window).

Thanks for helping keep the project and its users safe.
