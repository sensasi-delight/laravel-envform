# Tasks: Value Resolver Service

**Input**: Design documents from `/specs/005-value-resolver/`
**Prerequisites**: plan.md (required), spec.md (required for user stories), research.md, data-model.md, contracts/

**Organization**: Tasks are grouped by user story to enable independent implementation and testing of each story.

## Format: `[ID] [P?] [Story] Description`

- **[P]**: Can run in parallel (different files, no dependencies)
- **[Story]**: Which user story this task belongs to (e.g., US1, US2, US3)
- Include exact file paths in descriptions

## Phase 1: Setup (Shared Infrastructure)

**Purpose**: Project initialization and basic structure

- [X] T001 Create contract interfaces in `src/ValueResolver/ValueResolverInterface.php` and `src/ValueResolver/RepositoryInterface.php`
- [X] T002 Create initial implicit rules file in `resources/inferences.php`
- [X] T003 [P] Add `ValueResolver\Service` and `ValueResolver\Repository` to `EnvFormServiceProvider.php`

---

## Phase 2: Foundational (Blocking Prerequisites)

**Purpose**: Core infrastructure that MUST be complete before ANY user story can be implemented

**⚠️ CRITICAL**: No user story work can begin until this phase is complete

- [X] T004 Implement `ValueResolver\Repository` to load rules from `resources/inferences.php` in `src/ValueResolver/Repository.php`
- [X] T005 Create `ValueResolver\Service` class skeleton in `src/ValueResolver/Service.php` with constructor dependencies
- [X] T006 [P] Create base test case for ValueResolver in `tests/Unit/ValueResolver/ValueResolverTestCase.php`

**Checkpoint**: Foundation ready - user story implementation can now begin in parallel

---

## Phase 3: User Story 1 & 2 - Priority-Based Value Resolution (Priority: P1) 🎯 MVP

**Goal**: Implement the core resolution logic with the specified priority: FormValue > DotEnv > Config > Implicit.

**Independent Test**: Verify that `resolve('cache.stores.database.lock_table')` returns `cache_lock` when no other values are set, and respects overrides from .env or form.

### Tests for User Story 1 & 2

> **NOTE: Write these tests FIRST, ensure they FAIL before implementation**

- [X] T007 [P] [US1] Unit test for repository loading in `tests/Unit/ValueResolver/RepositoryTest.php`
- [X] T008 [P] [US1] Unit test for basic implicit logic in `tests/Unit/ValueResolver/ServiceTest.php`
- [X] T009 [P] [US2] Unit test for priority overrides (FormValue > DotEnv > Config) in `tests/Unit/ValueResolver/PriorityTest.php`

### Implementation for User Story 1 & 2

- [X] T010 [US1] Implement `resolve()` method in `src/ValueResolver/Service.php` to handle Config Default and Implicit sources
- [X] T011 [US2] Update `resolve()` in `src/ValueResolver/Service.php` to include FormValue and DotEnv sources in the priority chain
- [X] T012 [US1] Add initial implicit rule for `cache.stores.database.lock_table` in `resources/inferences.php`

**Checkpoint**: At this point, the Value Resolver should be fully functional for core resolution and priority logic.

---

## Phase 4: User Story 3 - Circular Dependency Detection (Priority: P2)

**Goal**: Prevent infinite loops in implicit logic by detecting circular dependencies.

**Independent Test**: Verify that a `LogicException` is thrown when two implicit rules depend on each other.

### Tests for User Story 3

- [X] T013 [P] [US3] Unit test for circular dependency detection in `tests/Unit/ValueResolver/CircularDependencyTest.php`

### Implementation for User Story 3

- [X] T014 [US3] Implement recursion stack tracking in `src/ValueResolver/Service.php` within the `resolve()` method
- [X] T015 [US3] Add validation logic to throw `LogicException` when a cycle is detected in `src/ValueResolver/Service.php`

**Checkpoint**: The service is now robust against misconfigured derivation rules.

---

## Phase 5: User Story 4 - Wizard Integration (Priority: P3)

**Goal**: Refactor the Wizard to use the Value Resolver as the central source of truth for all variable lookups.

**Independent Test**: Run the `envform` command and verify that `DB_CACHE_LOCK_TABLE` is suggested with its derived value when missing.

### Tests for User Story 4

- [X] T016 [P] [US4] Feature test for Wizard derivation suggestion in `tests/Feature/ValueResolver/WizardIntegrationTest.php`

### Implementation for User Story 4

- [X] T017 [US4] Update `Wizard\Service` to inject `ValueResolver\Service` and use it in `askForValue()` and `handleAppKey()` in `src/Wizard/Service.php`
- [X] T018 [US4] Refactor `Wizard\Service` to remove redundant value lookup logic (Registry defaults, DotEnv, FormValue) across its private methods
- [X] T019 [US4] Update `EnvFormServiceProvider.php` to ensure correct dependency injection for updated `Wizard\Service`

**Checkpoint**: All user stories are now integrated and the system is using the centralized resolver.

---

## Phase 6: Polish & Cross-Cutting Concerns

**Purpose**: Improvements that affect multiple user stories

- [X] T020 [P] Ensure all new files have `declare(strict_types=1);` and proper namespace
- [X] T021 Run PHPStan at level 8 to ensure type safety in `src/ValueResolver/`
- [X] T022 Run Laravel Pint to ensure code style consistency
- [X] T023 [P] Update `README.md` if the new derivation capability needs public documentation
- [X] T024 Validate implementation against `specs/005-value-resolver/quickstart.md`

---

## Dependencies & Execution Order

### Phase Dependencies

- **Setup (Phase 1)**: No dependencies - can start immediately
- **Foundational (Phase 2)**: Depends on Phase 1 completion
- **User Stories (Phase 3-5)**: All depend on Phase 2 completion
  - Phase 3 (Priority: P1) MUST complete before Phase 5
  - Phase 4 (Priority: P2) can run in parallel with Phase 3 but should ideally follow it
- **Polish (Phase 6)**: Depends on all user stories being complete

### Parallel Opportunities

- T003 (ServiceProvider) can be done in parallel with T001/T002.
- T006 (Base Test) can be done in parallel with T004/T005.
- Tests (T007, T008, T009) can be written in parallel.
- Circular dependency tests (T013) can be written in parallel with US1 implementation.

---

## Parallel Example: User Story 1 & 2

```powershell
# Launch all core tests for ValueResolver together:
Task: "Unit test for repository loading in tests/Unit/ValueResolver/RepositoryTest.php"
Task: "Unit test for basic derivation in tests/Unit/ValueResolver/ServiceTest.php"
Task: "Unit test for priority overrides in tests/Unit/ValueResolver/PriorityTest.php"
```

---

## Implementation Strategy

### MVP First (User Story 1 & 2 Only)

1. Complete Setup and Foundational phases.
2. Implement core resolution and priority in Phase 3.
3. **STOP and VALIDATE**: Ensure `ValueResolver` works correctly as a standalone service.

### Incremental Delivery

1. Foundation ready.
2. Core resolution (MVP) ready.
3. Safety feature (Circular detection) added.
4. UI integration (Wizard) completed.
5. Final polish and type checking.

---

## Notes

- Use `LogicException` for circular dependencies as per research.
- Ensure `ValueResolver\Service` is stateless regarding the resolution stack (reset stack after each top-level `resolve()` call).
- Avoid modifying `Registry\Service`; it should only be a data source for the resolver.
