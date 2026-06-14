# Description

Please include a summary of the changes and the related issue. Include the motivation and context, and list any package dependencies impacted by this change.

Fixes # (issue)

## Type of change

- [ ] Bug fix (non-breaking change which fixes an issue)
- [ ] New feature (non-breaking change which adds functionality)
- [ ] Breaking change (fix or feature that would cause existing functionality to not work as expected)
- [ ] Deprecation (marks existing API as deprecated)
- [ ] Refactor / internal change (no functional impact)
- [ ] Documentation update
- [ ] CI / tooling change

## How Has This Been Tested?

Describe the tests you ran to verify your changes (Pest tests, manual checks in a host Laravel app, etc.). Provide instructions so reviewers can reproduce.

- [ ] `composer test` passes
- [ ] `composer analyse` (PHPStan / Larastan) passes
- [ ] `composer pint` shows no formatting changes
- [ ] Added or updated tests covering the change

**Test Configuration**:

- PHP version(s): [e.g., 8.2, 8.3, 8.4]
- Laravel version(s): [e.g., 11.x, 12.x, 13.x]
- Database driver: [e.g., SQLite, MySQL, PostgreSQL]

## Checklist

- [ ] My code follows the style guidelines of this project (Pint / PSR-12)
- [ ] I have performed a self-review of my code
- [ ] I have added tests that prove my fix is effective or that my feature works
- [ ] New and existing tests pass locally with my changes
- [ ] I have updated the README / docs where relevant
- [ ] I have updated the CHANGELOG (if applicable)
- [ ] My commit messages follow Conventional Commits
- [ ] Any breaking changes are clearly called out above
