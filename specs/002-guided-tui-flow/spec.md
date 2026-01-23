# Feature Specification: Guided TUI Navigation and Error Recovery

**Feature Branch**: `002-guided-tui-flow`  
**Created**: 2026-01-22  
**Status**: Completed  
**Input**: User description: "improve the existing tui flow by focusing on user clarity, error recovery, and confidence rather than adding new features. the goal is to make env value entry feel guided, reversible, and predictable so users never feel trapped or afraid of making a mistake. redesign the flow so each step clearly communicates what is being asked, why it matters, and what will happen next, while keeping the interaction linear and calm. introduce an explicit mechanism to go back to the previous question when filling an env key value, allowing users to revise earlier answers without restarting the wizard or corrupting state. ensure the flow maintains a single authoritative state that can be replayed deterministically. in parallel, define comprehensive tests that validate the tui behavior end to end, including forward navigation, backward navigation, cancellation, repeated runs, and idempotent outcomes, so the tui is treated as a first-class, testable interaction flow rather than incidental glue code."

## Clarifications

### Session 2026-01-22

- Q: If the wizard is cancelled and restarted, should it resume or start fresh? → A: Fresh start; history is lost on exit.
- Q: If a user changes a value that others depend on, should subsequent history be cleared? → A: No, keep answers; re-evaluate/re-prompt only when navigating forward.
- Q: Visual feedback on reversal: Re-render or append? → A: Re-render; replace current question with previous one for a clean flow.

## User Scenarios & Testing *(mandatory)*

### User Story 1 - Guided Linear Configuration (Priority: P1)

As a developer, I want to be guided through the environment configuration process with clear explanations for each variable so that I can configure the system with confidence and without referring to external documentation.

**Why this priority**: Core value of the feature; sets the foundation for "clarity" and "calm" interaction.

**Independent Test**: Can be tested by running the wizard and verifying that each question displays a descriptive "why it matters" hint and a "what happens next" indicator.

**Acceptance Scenarios**:

1. **Given** the wizard is started, **When** a configuration key is presented, **Then** it must display the key name, its purpose/hint, and the current step progress.
2. **Given** a valid value is entered, **When** the user proceeds, **Then** the system must clearly indicate that the value was accepted and show the next relevant question.

---

### User Story 2 - Reversible Navigation (Priority: P1)

As a developer, I want to be able to go back to a previous question if I realize I made a mistake, so that I can correct earlier answers without having to restart the entire wizard.

**Why this priority**: Essential for "error recovery" and preventing the "trapped" feeling.

**Independent Test**: Can be tested by entering values for three questions, triggering the "Back" action at the third question, and verifying the cursor/focus returns to the second question with its previous value intact.

**Acceptance Scenarios**:

1. **Given** the user is on the second or subsequent question, **When** they press the `Ctrl+C` key, **Then** the TUI must return to the previous question.
2. **Given** the user is on the first visible question of a group, **When** they press the `Ctrl+C` key, **Then** the TUI must return to the main configuration menu.
3. **Given** the user has navigated back to a previous question, **When** they modify the value and proceed forward, **Then** the system must re-validate the new value and maintain a consistent state for subsequent questions.

---

### User Story 3 - Predictable State & Error Recovery (Priority: P2)

As a developer, I want the TUI to handle invalid inputs gracefully and maintain a deterministic state, so that I can recover from errors predictably and trust the final configuration.

**Why this priority**: Ensures "predictable" behavior and "confidence" in the final outcome.

**Independent Test**: Can be tested by intentionally entering invalid data, observing the error message, navigating back/forward, and verifying that the final `.env` output matches the expected deterministic result of the session history.

**Acceptance Scenarios**:

1. **Given** an invalid input is entered, **When** the system rejects it, **Then** it must provide a clear explanation of *why* it was rejected and how to fix it, without losing current progress.
2. **Given** a sequence of forward and backward navigation steps, **When** the wizard is completed, **Then** the resulting configuration must be identical to a direct linear run with the same final values (idempotency).

---

### Edge Cases

- **First Question Navigation**: What happens when the user tries to go "Back" from the very first question? (Expected: Action is disabled or explains that no previous question exists).
- **Dependency Changes**: How does the system handle going back and changing a value that other (already answered) questions depend on? (Expected: Subsequent history is preserved; when navigating forward, the system MUST re-evaluate if existing answers are still valid/relevant and re-prompt if necessary).
- **Cancellation**: How does the system handle a hard exit (Ctrl+C) during a navigation sequence? (Expected: No partial/corrupt state is persisted to the `.env` file).

## Requirements *(mandatory)*

### Functional Requirements

- **FR-001**: System MUST display contextual "Hints" for every environment variable, explaining its purpose and impact.
- **FR-002**: System MUST provide an explicit mechanism to navigate to the previous question (Back navigation).
- **FR-003**: System MUST maintain a NavigationSession history to allow multi-step reversals.
- **FR-004**: System MUST re-validate and re-evaluate all inputs when navigating forward; if a dependency change makes a previous answer invalid or irrelevant, the system MUST re-prompt the user.
- **FR-005**: System MUST ensure that the TUI state transition is deterministic and can be reproduced in automated tests.
- **FR-006**: System MUST treat the TUI as a first-class testable component, providing end-to-end test coverage for all navigation paths.
- **FR-007**: System MUST re-render the current terminal line(s) when navigating backward (via `Ctrl+C`) to replace the active question, rather than appending to the scrollback buffer.
- **FR-008**: System MUST display an explicit progress indicator (e.g., "[Step 3/12]") in the prompt label for every variable.
- **FR-009**: System MUST provide a "Success Summary" note after completing a configuration group, summarizing the values updated.
- **FR-010**: System MUST use distinct visual prefixes (e.g., 🚀 for triggers, ⚙️ for standard) to help users distinguish between critical and optional configuration steps.

### Key Entities *(include if feature involves data)*

- **NavigationSession**: Represents the ephemeral, in-memory state of the current wizard run, including the history of questions asked, answers provided, and the current pointer in the flow. This state is not persisted between runs.
- **EnvVar DTO**: The data object representing an environment variable, now enriched with display metadata (hints, validation rules).

## Success Criteria *(mandatory)*

### Measurable Outcomes

- SC-001: Users can navigate back to any previously answered question within the current session in under 1 second.
- SC-002: 100% of functional TUI navigation paths (forward, back, cancel) are covered by automated end-to-end tests.
- SC-003: 0% corruption of .env files regardless of navigation complexity (idempotency).
- SC-004: Average time to recover from an input error is reduced by 30% by providing immediate, contextual feedback and reversal options.
