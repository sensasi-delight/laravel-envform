# Tasks - Persistent Wizard Header

**Feature**: Persistent Wizard Header (003-persistent-wizard-header)
**Spec**: [spec.md](./spec.md)
**Plan**: [plan.md](./plan.md)

## Implementation Strategy
- **MVP**: The entire feature is the MVP. It replaces the existing welcome message and persists a header.
- **Approach**: Build the Header component first with unit tests for its logic (truncation, stripping), then integrate it into the EnvForm command and Wizard service.
- **Testing**: Unit tests for string manipulation logic; manual verification for visual output and persistence.

## Dependencies
- **Story Order**: US1 (Only story)
- **Critical Path**: Header Component -> Integration in EnvForm -> Integration in Wizard

## Parallel Execution
- T003, T004, T005 (Header Logic) can be implemented in parallel if multiple devs worked on it (though overkill for this size).
- T008 (Command Integration) and T009 (Wizard Integration) can be done in parallel after T006.

---

## Phase 1: Setup

- [x] T001 Create component directory src/Console/Components

## Phase 2: Foundational
*(Merged with Phase 1)*

## Phase 3: User Story 1 - Consistent Visual Identity (P1)
**Goal**: Display consistent, responsive "EnvForm" header across all wizard steps.
**Independent Test**: Run php artisan envform and navigate through steps. Header should be static at top.

### Tests
- [x] T002 [US1] Create unit test 	ests/Unit/Console/Components/HeaderTest.php to verify 	runcate and stripAnsi logic (TDD approach recommended).

### Implementation (Component)
- [x] T003 [US1] Create src/Console/Components/Header.php class structure.
- [x] T004 [US1] Implement getAsciiArt() in src/Console/Components/Header.php with purple ANSI codes (use \e[35m).
- [x] T005 [US1] Implement stripAnsi(string ) in src/Console/Components/Header.php to handle NO_COLOR.
- [x] T006 [US1] Implement 	runcate(string , int ) in src/Console/Components/Header.php using Symfony\Component\Console\Terminal.
- [x] T007 [US1] Implement public ender(?string  = null) in src/Console/Components/Header.php combining clear(), logic, and write() output.

### Integration
- [x] T008 [US1] Refactor src/Console/Commands/EnvForm.php to remove displayWelcome() and call Header::render() with Privacy/Analysis notes as subtitle.
- [x] T009 [US1] Update src/Wizard/Service.php to call Header::render() (without subtitle) before each prompt phase (e.g., step, sk).

### Verification
- [x] T010 [US1] Verify "No Color" mode by running NO_COLOR=1 php artisan envform.
- [x] T011 [US1] Verify "Narrow Terminal" mode by resizing terminal and running php artisan envform.

## Final Phase: Polish
- [x] T012 Run phpstan analyze to ensure new component is typed correctly (Level 8).
- [x] T013 Run endor/bin/pint to format new files.
