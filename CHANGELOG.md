# Changelog

## [2.0.0](https://github.com/offload-project/laravel-invite-only/compare/v1.0.0...v2.0.0) (2026-01-07)

### Features

* security, performance, helpers, oh my! ([#3](https://github.com/offload-project/laravel-invite-only/issues/3)) ([eebbd86](https://github.com/offload-project/laravel-invite-only/commit/eebbd86be7e7ee5dd5c8a5631e43f70024ed0af2))
  * Introduced InvitationStatus enum with helper methods replacing string constants
  * Added InviteOnlyContract interface for custom implementations
  * Implemented InvitationFactory with comprehensive state methods for testing
  * Performance improvements: batch updates for expired invitations, database aggregation for statistics
  * Security improvements: email validation, rate limiting, and configurable redirects
  * Lowered PHP requirement from 8.4 to 8.2 for broader compatibility


## 1.0.0 (2025-12-28)


### Miscellaneous Chores

* initial commit ([e43e78f](https://github.com/offload-project/laravel-invite-only/commit/e43e78f8e22e64b8fbf748d3ed88095e544f589e))
