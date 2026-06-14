# Contributing to Laravel Invite Only

Thanks for your interest in contributing! This document outlines the process and standards for contributing to `offload-project/laravel-invite-only`.

## Code of Conduct

By participating in this project, you agree to treat fellow contributors with respect. Be kind, assume good intent, and keep discussions focused on the work.

## Ways to Contribute

- Reporting bugs via the [Bug Report](.github/ISSUE_TEMPLATE/bug_report.md) template
- Proposing new features via the [Feature Request](.github/ISSUE_TEMPLATE/feature_request.md) template
- Improving documentation (`README.md`, `docs/`, `CHANGELOG.md`)
- Fixing bugs or implementing features through pull requests
- Reviewing open pull requests

Before opening a large PR, please open an issue first to discuss the approach.

## Requirements

- PHP **8.2+** (CI matrix runs 8.3, 8.4, 8.5)
- Composer 2
- A local SQLite extension (the test suite uses an in-memory SQLite database via Orchestra Testbench)

## Getting Set Up

1. Fork the repository on GitHub and clone your fork:

   ```bash
   git clone git@github.com:<your-username>/laravel-invite-only.git
   cd laravel-invite-only
   ```

2. Install dependencies:

   ```bash
   composer install
   ```

3. Install the Git hooks (runs Pint pre-commit, validates Conventional Commits on commit-msg, runs tests and static analysis pre-push):

   ```bash
   composer install-hooks
   ```

4. Create a feature branch off `main`:

   ```bash
   git checkout -b feat/short-description
   ```

## Development Workflow

This package supports Laravel 11, 12, and 13 and PHP 8.3–8.5. Changes must work across that matrix.

### Running the Test Suite

```bash
composer test
```

Run with coverage:

```bash
composer test-coverage
```

Tests are written with [Pest](https://pestphp.com/) and live under `tests/`. New behavior should be covered by tests; bug fixes should include a regression test.

### Static Analysis

```bash
composer analyse
```

We use Larastan (PHPStan for Laravel). If you must suppress a finding, prefer narrow ignores via the baseline (`phpstan-baseline.neon`) over loosening the rule set, and explain why in your PR.

### Code Style

```bash
composer pint
```

Pint runs on `pre-commit`. PRs must be Pint-clean — the `code-style.yml` workflow will fail otherwise.

## Commit Messages

We use [Conventional Commits](https://www.conventionalcommits.org/). The `commit-msg` hook validates this; CI/release tooling depends on it.

Format: `<type>(<optional scope>): <description>`

Common types used in this repo:

| Type         | Use for                                                             |
| ------------ | ------------------------------------------------------------------- |
| `feat`       | New user-facing functionality                                       |
| `fix`        | Bug fixes                                                           |
| `deprecate`  | Marking existing API as deprecated                                  |
| `refactor`   | Internal change with no behavior difference                         |
| `test`       | Adding or updating tests                                            |
| `docs`       | Documentation only                                                  |
| `chore`      | Tooling, dependency bumps, repo housekeeping                        |
| `ci`         | Changes to GitHub Actions workflows                                 |

Examples (taken from this project's history):

- `test: getAcceptedInvitations test`
- `deprecate: HasInvitations acceptedInvitations in favor of getAcceptedInvitations`
- `fix: changed return type to ?DateTimeInterface`
- `ci: update release workflow permissions`

Breaking changes: add `!` after the type (e.g., `feat!: rename Invitation::scopePending`) and explain the migration path in the PR body.

## Pull Requests

1. Make sure your branch is up to date with `main`.
2. Run the full local check before pushing:

   ```bash
   composer pint && composer analyse && composer test
   ```

3. Push your branch and open a PR against `main` using the [PR template](.github/pull_request_template.md).
4. Fill in:
   - What changed and why
   - Type of change (bug fix, feature, breaking, deprecation, etc.)
   - How it was tested (PHP/Laravel/DB versions)
   - Whether docs or `CHANGELOG.md` were updated
5. Keep PRs focused. One logical change per PR makes review faster and bisection easier.
6. CI must pass before review:
   - `tests.yml` — Pest across the PHP × Laravel × stability matrix
   - `code-style.yml` — Pint
7. Address review feedback in additional commits rather than force-pushing while review is active.

## Adding or Changing Features

When working on this package, keep these areas in mind:

- **Polymorphic relationships** — invitations can target any model via `HasInvitations`; new behavior should not assume a particular invitable model.
- **Traits** — `HasInvitations` and `CanBeInvited` are part of the public API. Method renames or signature changes are breaking; deprecate first when possible (see the `deprecate:` commits in the history).
- **Events** — invitation lifecycle events are part of the public contract. Adding events is non-breaking; renaming or removing them is breaking.
- **Notifications** — strings must remain translatable via the published language files in `resources/lang`.
- **Config** — new config keys must have safe defaults and be documented in `config/invite-only.php` with a comment.
- **Migrations** — schema changes need a new migration. Don't edit existing published migrations.
- **Exceptions** — follow the existing structured-exception pattern (error code + resolution hint).

## Documentation

If your change affects public API, configuration, or usage, update:

- `README.md` — quick start / feature list
- `docs/getting-started.md`, `docs/concepts.md`, `docs/reference.md`, and relevant `docs/howto/` entries
- `CHANGELOG.md` — under the `Unreleased` section (or note in your PR if you'd like a maintainer to add it)

## Reporting Security Issues

Please do **not** open a public issue for security vulnerabilities. Report them privately via GitHub's "Report a vulnerability" feature on the repository's Security tab so a fix can be coordinated before disclosure.

## License

By contributing, you agree that your contributions will be licensed under the [MIT License](LICENSE) that covers this project.
