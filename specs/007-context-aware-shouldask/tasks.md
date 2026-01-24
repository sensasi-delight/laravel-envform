---

description: "Task list for Context Aware ShouldAsk implementation"
---

# Tasks: Context Aware ShouldAsk

**Input**: Design documents from `/specs/007-context-aware-shouldask/`
**Prerequisites**: plan.md (required), spec.md (required for user stories), research.md, data-model.md, contracts/

**Tests**: Test tasks are included to ensure behavior matches specifications.

**Organization**: Tasks are grouped by user story to enable independent implementation and testing of each story.

## Format: `[ID] [P?] [Story] Description`

- **[P]**: Can run in parallel (different files, no dependencies)
- **[Story]**: Which user story this task belongs to (e.g., US1, US2, US3)
- Include exact file paths in descriptions

## Phase 1: Setup (Shared Infrastructure)

**Purpose**: Project initialization and basic structure

- [X] T001 Create mapping file `resources/services.php` with default Laravel service triggers
- [X] T002 [P] Create `ServiceDefinition` DTO in `src/ServiceDetection/DTO/ServiceDefinition.php`
- [X] T003 [P] Create `ServiceContext` DTO in `src/ServiceDetection/DTO/ServiceContext.php`

---

## Phase 2: Foundational (Blocking Prerequisites)

**Purpose**: Core infrastructure that MUST be complete before ANY user story can be implemented

**⚠️ CRITICAL**: No user story work can begin until this phase is complete

- [X] T004 Implement `Repository` in `src/ServiceDetection/Repository.php` to load service mapping
- [X] T005 [P] Implement `ServiceDetectionInterface` in `src/ServiceDetection/Service.php`
- [X] T006 [P] Implement driver resolution using `ValueResolver` in `src/ServiceDetection/Service.php`

**Checkpoint**: Foundation ready - user story implementation can now begin in parallel

---

## Phase 3: User Story 1 - Smart Service-Aware Detection (Priority: P1) 🎯 MVP

**Goal**: Hide entire blocks of configuration (like Redis or AWS keys) if the service is not active in any part of the application.

**Independent Test**: Configure project with `CACHE_DRIVER=file` and `QUEUE_CONNECTION=sync`, verify `REDIS_*` and `AWS_*` keys are completely hidden in the wizard.

### Implementation for User Story 1

- [X] T007 [US1] Implement `isActive()` logic in `src/ServiceDetection/Service.php`
- [X] T008 [US1] Implement `isKeyRelevant()` logic in `src/ServiceDetection/Service.php`
- [X] T009 [US1] Inject `ServiceDetection\Service` into `ShouldAsk\Service` in `src/ShouldAsk/Service.php`
- [X] T010 [US1] Add service relevancy guard in `ShouldAsk\Service::shouldBeAsked()` in `src/ShouldAsk/Service.php`

**Checkpoint**: At this point, User Story 1 should be fully functional and testable independently

---

## Phase 4: User Story 2 - Cross-Subsystem Service Activation (Priority: P2)

**Goal**: Ensure a service remains active as long as at least one subsystem requires it, and support implicit activation via "Master" keys.

**Independent Test**: Set `CACHE_DRIVER=redis` but `QUEUE_CONNECTION=sync`, verify `REDIS_*` keys remain visible.

### Implementation for User Story 2

- [X] T011 [US2] Update `isActive()` to evaluate all activators for a service in `src/ServiceDetection/Service.php`
- [X] T012 [US2] Implement implicit activation (Master key check) in `src/ServiceDetection/Service.php`

**Checkpoint**: At this point, User Stories 1 AND 2 should both work independently

---

## Phase 5: User Story 3 - Dynamic Relevance Switching (Priority: P3)

**Goal**: Adapt wizard questions immediately when a "driver" variable is changed by the user.

**Independent Test**: Change `CACHE_DRIVER` from `redis` to `file` in the wizard, verify `REDIS_*` questions disappear on the next step or re-run.

### Implementation for User Story 3

- [X] T013 [US3] Ensure `ShouldAsk\Service::refresh()` clears service context cache in `src/ShouldAsk/Service.php`
- [X] T014 [US3] Verify visibility adaptation in `tests/Feature/ServiceFilteringTest.php`

**Checkpoint**: All user stories should now be independently functional

---

## Phase 6: Polish & Cross-Cutting Concerns

**Purpose**: Improvements that affect multiple user stories

- [X] T015 [P] Add comprehensive unit tests for `Service` in `tests/Unit/ServiceDetection/ServiceTest.php`
- [X] T016 [P] Add integration test for TUI service filtering in `tests/Feature/ServiceFilteringTest.php`
- [X] T017 Run PHPStan Level 8 and Laravel Pint across all modified files
- [X] T018 Run `quickstart.md` validation against the completed implementation
- [X] T019 [P] Verify SC-003 by confirming prompt reduction on a standard Laravel install baseline

---

## Dependencies & Execution Order

### Phase Dependencies

- **Setup (Phase 1)**: No dependencies - can start immediately
- **Foundational (Phase 2)**: Depends on Setup completion - BLOCKS all user stories
- **User Stories (Phase 3+)**: All depend on Foundational phase completion
  - User Story 1 is the MVP and should be completed first
  - User Stories 2 and 3 can be worked on in parallel after US1 foundation is laid
- **Polish (Final Phase)**: Depends on all user stories being complete

### Parallel Opportunities

- T002 and T003 can run in parallel
- T005 and T006 can run in parallel
- T015 and T016 can run in parallel

---

## Implementation Strategy

### MVP First (User Story 1 Only)

1. Complete Phase 1: Setup
2. Complete Phase 2: Foundational
3. Complete Phase 3: User Story 1
4. **STOP and VALIDATE**: Verify service-aware hiding works for basic drivers.

### Incremental Delivery

1. Foundation ready (Phase 1 & 2)
2. MVP (US1) delivered
3. Cross-subsystem and implicit triggers (US2) added
4. Dynamic switching (US3) added
5. Final Polish and verification

---

## Notes

- [P] tasks = different files, no dependencies
- [Story] label maps task to specific user story for traceability
- Each user story should be independently completable and testable
- Commit after each task or logical group
- Run tests regularly to ensure no regressions in ValueResolver or ShouldAsk logic
