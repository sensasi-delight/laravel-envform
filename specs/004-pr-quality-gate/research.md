# Research: PR Quality Gate for Laravel Package

## Decision: Implementation Strategy for GitHub Actions

### Rationale
To fulfill the requirements of the PR quality gate (PHP matrix, dependency matrix, Pint check, PHPStan, PHPUnit, and Coverage), we will use a single GitHub Actions workflow with multiple jobs or a strategy matrix.

### Best Practices & Findings

#### 1. PHP and Dependency Matrix
- **Decision**: Use `strategy.matrix` to run combinations of PHP (8.2, 8.3, 8.4) and stability (`stable`, `lowest`).
- **Rationale**: This is the industry standard for PHP library testing to ensure broad compatibility.
- **Implementation**: `composer update --prefer-stable` for stable, and `composer update --prefer-lowest --prefer-stable` for lowest.

#### 2. Environment Setup
- **Decision**: Use `shivammathur/setup-php` action.
- **Rationale**: It is the most robust and widely used action for setting up PHP environments in GitHub Actions, supporting version management, extensions, and tool installation (Composer, PHPUnit, etc.).
- **Caching**: Enable caching for Composer dependencies to speed up the workflow.

#### 3. Coding Standards (Pint)
- **Decision**: Run `vendor/bin/pint --test`.
- **Rationale**: The `--test` flag ensures that Pint only checks for violations without modifying the code, as required by FR-005.

#### 4. Static Analysis (PHPStan)
- **Decision**: Run `vendor/bin/phpstan analyse --no-progress`.
- **Rationale**: Standard execution for CI. Using `--no-progress` keeps the logs clean.

#### 5. Code Coverage Reporting
- **Decision**: Use `pcov` as the coverage driver and `phpunit --coverage-text` or a specialized action to report to `GITHUB_STEP_SUMMARY`.
- **Rationale**: `pcov` is faster than `xdebug` for coverage. Writing to the step summary provides immediate visibility to developers as per FR-008.

#### 6. Triggering & Security
- **Decision**: Use `pull_request` trigger targeting `main`.
- **Rationale**: Ensures security for forks while providing the necessary "quality gate" for internal and external contributions.

### Alternatives Considered

- **Alternative**: `pull_request_target`.
- **Rejected**: Too risky for this project as it could expose secrets if tests are modified by a malicious PR. `pull_request` is safer and sufficient.
- **Alternative**: Multi-job workflow (separate jobs for lint, test, analyze).
- **Rejected**: A matrix job is more efficient for testing across multiple PHP versions, though linting (Pint) only needs to run once (e.g., on the highest PHP version) to save resources.

## Decision: YAML Structure
The implementation will be a single file `.github/workflows/tests.yml`.

### Rationale
Centralizing the quality gate logic makes it easier to maintain and ensures that all checks are performed in a consistent environment.
