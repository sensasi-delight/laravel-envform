# Tasks: Smart Option Derivation

**Input**: Design documents from `/specs/006-smart-option-derivation/`
**Prerequisites**: plan.md (required), spec.md (required for user stories), research.md, data-model.md, contracts/

## Implementation Strategy

### MVP First (User Story 1 Only)

1. Complete Phase 1: Setup
2. Complete Phase 2: Foundational (Alphabetical sorting)
3. Complete Phase 3: User Story 1 (Nullable options)
4. **STOP and VALIDATE**: Test User Story 1 independently in `tests/Unit/OptionResolver/ServiceTest.php`

### Incremental Delivery

1. Complete Setup + Foundational → Core logic ready
2. Add User Story 1 → Test independently → MVP!
3. Add User Story 2 → Test filtering independently
4. Add Polish → Handle empty states and UI feedback

---

## Phase 1: Setup (Shared Infrastructure)

**Purpose**: Project initialization and basic structure

- [x] T001 Define `METADATA_BLACKLIST` constant in `src/OptionResolver/Service.php`
- [x] T002 Create directory `tests/Unit/OptionResolver`

---

## Phase 2: Foundational (Blocking Prerequisites)

**Purpose**: Core infrastructure that MUST be complete before ANY user story can be implemented

- [x] T003 Update `src/OptionResolver/Service.php` to include alphabetical sorting in `resolve()` method

---

## Phase 3: User Story 1 - Nullable Select Options (Priority: P1) 🎯 MVP

**Goal**: Allow users to select `null` for configuration values that support it.

**Independent Test**: `tests/Unit/OptionResolver/ServiceTest.php` verifies `null` is prepended to options for nullable keys.

### Tests for User Story 1

- [x] T004 [P] [US1] Create unit test for nullability detection and `null` option prepending in `tests/Unit/OptionResolver/ServiceTest.php`

### Implementation for User Story 1

- [x] T005 [US1] Update `src/Hint/Service.php` to support manual `nullable` overrides in hints metadata
- [x] T006 [US1] Update `src/OptionResolver/Service.php` to detect field nullability using `Registry\Service::getStaticValue()` and `Hint\Service`
- [x] T007 [US1] Modify `src/OptionResolver/Service.php` to prepend literal `null` to the options list if a field is nullable
- [x] T008 [US1] Ensure `null` selection is correctly handled by the `ValueResolver\Service` logic in `src/ValueResolver/Service.php`
- [x] T009 [P] [US1] Create integration test for common nullable fields (cache.default, database.default) in `tests/Feature/ValueResolver/NullableFieldsTest.php`

---

## Phase 4: User Story 2 - Filtered Selection Lists (Priority: P2)

**Goal**: Exclude internal metadata from selection lists.

**Independent Test**: `tests/Unit/OptionResolver/ServiceTest.php` verifies `client`, `options`, and `clusters` are filtered out.

### Tests for User Story 2

- [x] T010 [P] [US2] Add unit tests for metadata filtering (Redis example) in `tests/Unit/OptionResolver/ServiceTest.php`

### Implementation for User Story 2

- [x] T011 [US2] Implement metadata key filtering in `src/OptionResolver/Service.php` using `METADATA_BLACKLIST`

---

## Phase 5: Polish & Cross-Cutting Concerns

**Purpose**: Improvements that affect multiple user stories

- [x] T012 [US1] Update `src/OptionResolver/Service.php` to throw `BackToMenuException` when the resolved options list is empty
- [x] T013 [US1] Update `src/Wizard/Service.php` to catch `BackToMenuException` and display a warning using `Laravel\Prompts\warning()`
- [x] T014 [P] Run `php artisan test` to verify all changes
- [x] T015 [P] Run quickstart.md validation

---

## Dependencies & Execution Order

### Phase Dependencies

- **Setup (Phase 1)**: No dependencies - can start immediately
- **Foundational (Phase 2)**: Depends on Setup completion
- **User Stories (Phase 3+)**: All depend on Foundational phase completion
- **Polish (Final Phase)**: Depends on all desired user stories being complete

### Parallel Opportunities

- T001 and T002 can run in parallel.
- T004 [US1] and T008 [US2] can be written in parallel.
- Once Foundational logic is in place, US1 and US2 implementation can proceed in parallel if needed.

---

## Parallel Example: User Stories

```bash
# Launch tests for both user stories
Task: "Create unit test for nullability detection in tests/Unit/OptionResolver/ServiceTest.php"
Task: "Add unit tests for metadata filtering in tests/Unit/OptionResolver/ServiceTest.php"
```

---

## Notes

- **Why not just update `resources/dependencies.php`?**: `dependencies.php` is a data mapping file. Adding filtering and nullability logic there would violate SRP and require manual maintenance for every key. The `OptionResolver\Service` approach is "smart" because it applies these rules programmatically across all mapped dependencies.
- [P] tasks = different files or independent test cases.
- [Story] label maps task to specific user story for traceability.
- Each user story is independently testable via unit tests in `ServiceTest`.