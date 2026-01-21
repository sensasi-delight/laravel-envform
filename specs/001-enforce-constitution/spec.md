# Feature Specification: Enforce Constitution

**Feature Branch**: `001-enforce-constitution`
**Created**: 2026-01-20
**Status**: Approved
**Input**: User description: "enforce the laravel envform constitution against the current codebase"

## Clarifications
### Session 2026-01-22
- Q: Scope of Work - Tool vs. Refactor? → A: Refactor Only (Option A).
- Q: Destination of Extracted Logic? → A: New Granular Modules (Option A).
- Q: Single-Implementation Interfaces? → A: Delete them (Option A).
- Q: Removal of Runtime Config Dependency? → A: Strict Refactor (Option A).
- Q: APP_KEY Generation Method? → A: Artisan (Exception) (Option A).
- Q: Option Resolution Implementation? → A: Added mapping for common Laravel config keys in `OptionResolver`.

## User Scenarios & Testing *(mandatory)*

### User Story 1 - Compliance & Refactoring (Human Workflow) (Priority: P1)

As a maintainer, I need to refactor the codebase to align with the Constitution, so that the project architecture is clean, testable, and compliant with the "Primary Directives".

**Why this priority**: Immediate compliance is the goal.

**Independent Test**:
1.  **Architecture Check**: Verify `Wizard\Service` contains NO business logic (delegates to Domain Services).
2.  **Strictness Check**: Verify `strict_types=1` in all files.
3.  **Repository Check**: Verify Repositories contain NO conditional business logic.

**Acceptance Scenarios**:
1.  **Given** the `Wizard\Service` class, **When** analyzed, **Then** it must only contain UI interaction code (Prompts) and calls to other Services; it must NOT contain logic for `APP_KEY` generation or Config key mapping.
2.  **Given** the new `KeyGenerator` and `OptionResolver` modules, **When** `Wizard\Service` needs to generate a key or list options, **Then** it delegates to these services.
3.  **Given** any Repository, **When** analyzed, **Then** it must not contain conditional logic based on business rules.
4.  **Given** the codebase, **When** `phpstan` is run, **Then** it must pass at Level 8.
5.  **Given** interfaces with single implementations (`Registry\RepositoryContract`, etc.), **When** analyzed, **Then** they must be removed, and dependencies updated to concrete classes.
6.  **Given** `Wizard` or `ShouldAsk` services, **When** needing a config value (e.g., `database.default`), **Then** they MUST query `Registry` (AST) instead of `Config::get()`.

---

### User Story 2 - Validation (Priority: P2)

As a maintainer, I need to verify compliance using existing tools (PHPStan, Tests) without building a new "Enforcement Gate" application.

**Why this priority**: To ensure the refactoring was successful.

**Independent Test**: Run `vendor/bin/phpstan analyse` and `vendor/bin/phpunit`.

**Acceptance Scenarios**:
1.  **Given** the refactored code, **When** running PHPStan Level 8, **Then** it reports zero errors.
2.  **Given** the refactored code, **When** running the test suite, **Then** all tests pass (verifying no regression).

## Requirements *(mandatory)*

### Functional Requirements

-   **FR-001**: **Wizard Logic Extraction**: `Wizard\Service` MUST be refactored to remove domain logic.
    -   `APP_KEY` logic moved to `KeyGenerator\Service`.
    -   Config key mapping/resolution moved to `OptionResolver\Service`.
-   **FR-002**: **Strict Typing**: All PHP files in `src/` MUST have `declare(strict_types=1);`.
-   **FR-003**: **Repository Purity**: Repositories MUST be reviewed and stripped of any business logic, ensuring they remain "Dumb I/O adapters".
-   **FR-004**: **No Execution Scope**: Verify no runtime execution occurs in `src/`.
    -   Replace all `Config::get()` usages with AST lookups.
    -   Enhance `Registry\Repository` to parse and return static config values (not just `env()` calls).
    -   **Exception**: `KeyGenerator` is explicitly permitted to use `Artisan::call('key:generate')` upon user request.
-   **FR-005**: **Network Ban**: Verify no network calls exist in `src/`.
-   **FR-006**: **Abstraction Discipline**: Remove `RepositoryContract` interfaces unless they have multiple implementations. Update type hints to use `Repository` classes directly.

### Key Entities

-   **Wizard**: UI Layer (Prompts).
-   **KeyGenerator**: Logic for generating values (e.g. APP_KEY).
-   **OptionResolver**: Logic for determining valid options for variables.
-   **Registry**: Single source of truth for Config structure and values (via AST).
-   **Repositories**: Data Access.

## Success Criteria *(mandatory)*

### Measurable Outcomes

-   **SC-001**: `Wizard\Service` lines of code reduced by moving logic to Domain Services.
-   **SC-002**: PHPStan passes at Level 8.
-   **SC-003**: Existing tests pass.
-   **SC-004**: Zero usage of `Config::get()` in `src/` (except inside the justified `KeyGenerator`).
