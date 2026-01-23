# Tasks: Create standardized PR quality gate

**Input**: Design documents from `/specs/004-pr-quality-gate/`
**Prerequisites**: plan.md, spec.md, research.md, data-model.md, contracts/

**Tests**: Tests for this feature involve verifying the GitHub Action execution in a PR environment.

**Organization**: Tasks are grouped by user story. Note that User Story 1 (Validation) depends on the environment setup and build steps typically associated with User Story 2, so the foundational build logic is implemented as part of the P1 MVP (US1).

## Format: `[ID] [P?] [Story] Description`

- **[P]**: Can run in parallel (different files, no dependencies)
- **[Story]**: Which user story this task belongs to (e.g., US1, US2)

## Phase 1: Setup (Shared Infrastructure)

**Purpose**: Project initialization and basic structure

- [x] T001 Create workflow directory and skeleton file in `.github/workflows/tests.yml`
- [x] T002 Configure basic workflow triggers and permissions in `.github/workflows/tests.yml`

---

## Phase 2: Foundational (Blocking Prerequisites)

**Purpose**: Core infrastructure for the CI matrix and PHP environment

**⚠️ CRITICAL**: The matrix and environment setup must be defined before implementing quality checks.

- [x] T003 Define strategy matrix for PHP (8.2, 8.3, 8.4) and stability (lowest, stable) in `.github/workflows/tests.yml`
- [x] T004 Implement `shivammathur/setup-php` step with required extensions (pcov, mbstring) in `.github/workflows/tests.yml`
- [x] T005 Configure Composer caching and dependency installation logic in `.github/workflows/tests.yml`

**Checkpoint**: Foundation ready - the CI environment can now execute PHP commands.

---

## Phase 3: User Story 1 - Automated PR Quality Validation (Priority: P1) 🎯 MVP

**Goal**: Automatically verify code against tests, static analysis, and style standards.

**Independent Test**: Open a PR with failing tests or lint errors and verify the workflow fails with specific logs.

### Implementation for User Story 1

- [x] T006 [US1] Implement Pint coding standard check (check-only mode) in `.github/workflows/tests.yml`
- [x] T007 [US1] Implement PHPStan static analysis (Level 8) in `.github/workflows/tests.yml`
- [x] T008 [US1] Implement PHPUnit test execution step in `.github/workflows/tests.yml`
- [x] T009 [US1] Ensure workflow fails if any quality check fails in `.github/workflows/tests.yml`

**Checkpoint**: User Story 1 is functional. PRs are now validated against quality gates.

---

## Phase 4: User Story 2 - Build Integrity Verification (Priority: P2)

**Goal**: Ensure every PR can be successfully built at the infrastructure level.

**Independent Test**: Introduce a dependency conflict in `composer.json` and verify the workflow fails at the installation step.

### Implementation for User Story 2

- [x] T010 [US2] Implement matrix stability logic (uses 'composer update' for prefer-lowest) in .github/workflows/tests.yml
- [x] T011 [US2] Optimize standard build performance using 'composer install --prefer-dist --no-progress' for stable dependencies in .github/workflows/tests.yml

**Checkpoint**: Build integrity is explicitly verified across the matrix.

---

## Phase 5: Polish & Cross-Cutting Concerns

**Purpose**: Improvements to visibility and developer experience.

- [x] T012 Implement code coverage reporting to GITHUB_STEP_SUMMARY using pcov in .github/workflows/tests.yml
- [x] T013 Verify log accessibility and failure reporting (SC-004) in `.github/workflows/tests.yml`
- [x] T014 Final validation of all Success Criteria (SC-001 to SC-002, SC-004)
- [x] T015 Verify or document GitHub repository settings to enforce the "Tests" workflow as a "Required Status Check" (satisfies SC-003)

---

## Dependencies & Execution Order

### Phase Dependencies

- **Setup (Phase 1)**: No dependencies.
- **Foundational (Phase 2)**: Depends on Setup (T001-T002).
- **User Story 1 (Phase 3)**: Depends on Foundational (T003-T005).
- **User Story 2 (Phase 4)**: Refines the build logic established in US1/Foundational.
- **Polish (Phase 5)**: Depends on completion of US1.

### User Story Dependencies

- **User Story 1 (P1)**: Independent of US2, but requires the "Build" foundational work.
- **User Story 2 (P2)**: Extends US1/Foundational with explicit build integrity focus.

### Parallel Opportunities

- T006, T007, and T008 (Pint, PHPStan, PHPUnit) could technically be separate jobs to run in parallel, though currently designed as steps within a matrix job.
- Documentation and Polish tasks (T012-T015) can be worked on after US1 is stable.

---

## Implementation Strategy

### MVP First (User Story 1 Only)

1. Complete Phase 1 & 2 to have a working PHP environment in CI.
2. Complete Phase 3 to enforce tests and linting.
3. This delivers the primary value: stopping broken code from merging.

### Incremental Delivery

1. Foundation: PHP Matrix + Composer Setup.
2. MVP: US1 (Tests + Lint + PHPStan).
3. Quality: US2 (Refined Build Integrity).
4. Polish: Coverage reports and summary.