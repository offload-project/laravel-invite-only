# Changelog

## v2.4.1 - 2026-04-03

### Fixed
- Changed return type to ?DateTimeInterface ([0653ea2](https://github.com/offload-project/laravel-invite-only/commit/0653ea25358164f7570135accb4f405ad168d75c))

### Other
- Update release workflow permissions [#18](https://github.com/offload-project/laravel-invite-only/pull/18)

## v2.4.0 - 2026-03-31

### Added

- Allow localization of date [#15](https://github.com/offload-project/laravel-invite-only/pull/15)
- Notification message customization via
  translation/lang [#15](https://github.com/offload-project/laravel-invite-only/pull/15)

### Fixed

- Remove final for
  extend/override ([ac2ee9c](https://github.com/offload-project/laravel-invite-only/commit/ac2ee9cb32b292058dbea5c1f2c1a35a317bfe55))

### Changed

- Add git attrs [#14](https://github.com/offload-project/laravel-invite-only/pull/14)

### Other

- Update workflows, use release champion [#14](https://github.com/offload-project/laravel-invite-only/pull/14)

## [2.3.0](https://github.com/offload-project/laravel-invite-only/compare/v2.2.0...v2.3.0) (2026-03-31)

### Features

* passive support for PHP
  8.2 ([#12](https://github.com/offload-project/laravel-invite-only/issues/12)) ([df879ed](https://github.com/offload-project/laravel-invite-only/commit/df879ed7b14e6713e6687571b56c3b4df5c3304b))

## [2.2.0](https://github.com/offload-project/laravel-invite-only/compare/v2.1.0...v2.2.0) (2026-03-30)

### Features

* add Laravel 13
  support ([f6259e9](https://github.com/offload-project/laravel-invite-only/commit/f6259e9f4a4964878dccd4b64646e1554fe88816))

## [2.1.0](https://github.com/offload-project/laravel-invite-only/compare/v2.0.0...v2.1.0) (2026-01-07)

### Features

* bulk invites, numerous other improvements and
  fixes ([#5](https://github.com/offload-project/laravel-invite-only/issues/5)) ([33ae178](https://github.com/offload-project/laravel-invite-only/commit/33ae1786d39daa1ed223018565b41b1b61e43ccd))

## [2.0.0](https://github.com/offload-project/laravel-invite-only/compare/v1.0.0...v2.0.0) (2026-01-07)

### Features

* security, performance, helpers, oh
  my! ([#3](https://github.com/offload-project/laravel-invite-only/issues/3)) ([eebbd86](https://github.com/offload-project/laravel-invite-only/commit/eebbd86be7e7ee5dd5c8a5631e43f70024ed0af2))
    * Introduced InvitationStatus enum with helper methods replacing string constants
    * Added InviteOnlyContract interface for custom implementations
    * Implemented InvitationFactory with comprehensive state methods for testing
    * Performance improvements: batch updates for expired invitations, database aggregation for statistics
    * Security improvements: email validation, rate limiting, and configurable redirects
    * Lowered PHP requirement from 8.4 to 8.2 for broader compatibility

## 1.0.0 (2025-12-28)

### Miscellaneous Chores

* initial
  commit ([e43e78f](https://github.com/offload-project/laravel-invite-only/commit/e43e78f8e22e64b8fbf748d3ed88095e544f589e))
