# Tasks: Guided TUI Navigation and Error Recovery

**Input**: Design documents from `specs/002-guided-tui-flow/`
**Prerequisites**: plan.md, spec.md, research.md, data-model.md

**Tests**: TUI behavior is treated as first-class interaction code. Comprehensive tests are MANDATORY as per specification.

## Phase 1: Setup (Shared Infrastructure)

- [X] T001 Create test skeleton for TUI interaction tests in `tests/Feature/TuiNavigationTest.php`

## Phase 2: Foundational (Blocking Prerequisites)

- [X] T002 Create `EnvForm\DTO\NavigationSession` to manage ephemeral wizard state in `src/DTO/NavigationSession.php`
- [X] T003 Ensure `EnvForm\DTO\EnvVar` supports necessary metadata for hints in `src/DTO/EnvVar.php`

## Phase 3: User Story 1 - Guided Linear Configuration (Priority: P1) 🎯 MVP

**Goal**: Provide a guided, calm configuration flow with clear hints, progress indicators, and success summaries.

**Independent Test**: Run wizard and verify that each step displays the key name, purpose hint, and progress.

### Tests for User Story 1

- [X] T004 [P] [US1] Implement test for linear forward navigation and hint display in `tests/Feature/TuiNavigationTest.php`

### Implementation for User Story 1

- [X] T005 [US1] Refactor `EnvForm\Wizard\Service::configureGroup()` to use `Laravel\Prompts\FormBuilder` in `src/Wizard/Service.php`
- [X] T006 [US1] Implement contextual hint rendering using `laravel/prompts` hint field in `src/Wizard/Service.php`
- [X] T007 [US1] Add progress/step indicator (e.g., "[3/12]") and visual prefixes (🚀/⚙️) to prompt labels in `src/Wizard/Service.php`
- [X] T019 [US1] Implement "Success Summary" note after completing a configuration group in `src/Wizard/Service.php`

---

## Phase 4: User Story 2 - Reversible Navigation (Priority: P1)

**Goal**: Allow users to navigate back to previous questions using the `Ctrl+C` key without losing state.

**Independent Test**: Enter values for 3 questions, press `Ctrl+C` on the 3rd, and verify return to the 2nd with previous value.

### Tests for User Story 2

- [X] T008 [P] [US2] Implement test for Ctrl+C key reversal, state preservation, and visual re-rendering verification in `tests/Feature/TuiNavigationTest.php`

### Implementation for User Story 2

- [X] T009 [US2] Implement `runPromptWithBackSupport()` wrapper in `src/Wizard/Service.php` that listens for `Key::CTRL_C` and throws `FormRevertedException` or `BackToMenuException`
- [X] T010 [US2] Integrate the back-navigation wrapper into the `FormBuilder` loop in `src/Wizard/Service.php`

---

## Phase 5: User Story 3 - Predictable State & Error Recovery (Priority: P2)

**Goal**: Ensure deterministic state transitions and re-evaluation of dependencies during forward navigation.

**Independent Test**: Change a trigger value on reversal, navigate forward, and verify that dependent questions are re-prompted.

### Tests for User Story 3

- [X] T011 [P] [US3] Implement test for dependency re-evaluation and idempotent .env output in `tests/Feature/TuiNavigationTest.php`
- [X] T020 [P] [US3] Implement test for existing Laravel 12 .env values as defaults in `tests/Feature/TuiNavigationTest.php`
- [X] T021 [P] [US3] Implement unit tests for `NavigationSession` logic in `tests/Unit/DTO/NavigationSessionTest.php`

### Implementation for User Story 3

- [X] T012 [US3] Implement re-evaluation logic in `src/Wizard/Service.php` using dynamic step closures within `FormBuilder` to re-calculate step visibility
- [X] T013 [US3] Ensure `NavigationSession` correctly synchronizes with `EnvForm\FormValue\Service`, treating `FormValue` as the final persistence layer and Session as the navigation buffer

---

## Phase N: Polish & Cross-Cutting Concerns

- [X] T014 [P] Ensure all new files have `declare(strict_types=1);` and pass Laravel Pint
- [X] T015 Run `composer lint` to verify PHPStan Level 8 compliance
- [X] T016 Run all tests via `composer test`
- [X] T017 [P] Final manual validation using `quickstart.md` scenarios

---

## Dependencies & Execution Order

### Phase Dependencies

- **Setup (Phase 1)**: Can start immediately.
- **Foundational (Phase 2)**: Depends on T001. Blocks all US implementation.
- **User Story 1 (P1)**: Depends on Phase 2. This is the MVP.
- **User Story 2 (P1)**: Depends on US1 completion.
- **User Story 3 (P2)**: Depends on US2 completion.
- **Polish (Final Phase)**: Depends on all US phases.

### Parallel Execution Examples

- **Tests [P]**: T004, T008, T011 can be drafted in parallel once the skeleton (T001) is ready.
- **Cleanups [P]**: T014 and T017 can be prepared as stories complete.

---

## Implementation Strategy

### MVP First (User Story 1)

1. Setup the test infrastructure.
2. Build the `NavigationSession` and `FormBuilder` integration.
3. Deliver a solid linear flow with hints.
4. **Validate**: Ensure standard linear configuration is more "guided" than the current implementation.

### Incremental Delivery

1. Add reversal logic (US2). This is a high-value "confidence" feature.
2. Add dependency re-evaluation (US3) to ensure correctness in complex scenarios.
3. Polish and verify idempotency.