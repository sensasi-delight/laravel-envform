# Feature Specification: Persistent Wizard Header

**Feature Branch**: `003-persistent-wizard-header`  
**Created**: 2026-01-23  
**Status**: Completed  
**Input**: User description: "003 replace the current one-time welcome message with a persistent header that is rendered consistently across all phases of the EnvForm flow, featuring a simple, readable ASCII art logo spelling “EnvForm” styled with a wizardy, purple-toned feel; this header should act as a stable visual anchor that reinforces product identity, improves orientation as users move between phases, and reduces cognitive load by clearly signaling that they are still inside the same guided configuration experience from start to finish."

## Clarifications
### Session 2026-01-23
- Q: Should the existing "Privacy" and "Local Analysis" notes be removed or preserved when replacing the welcome message? → A: Keep notes below header (Option B).
- Q: How should the ASCII header behave on narrow terminals? → A: Truncate overflow characters (Option A).
- Q: How to render the header when color is disabled (NO_COLOR)? → A: Render ASCII art in plain text by stripping styles (Option A).

## User Scenarios & Testing *(mandatory)*

### User Story 1 - Consistent Visual Identity (Priority: P1)

As a developer running the EnvForm tool (`php artisan envform`), I want to see a consistent "EnvForm" header at the top of every screen so that I always know I am within the configuration wizard and feel a sense of stability.

**Why this priority**: This is the core visual change requested. It establishes the "wizard" feel and reduces cognitive load by providing a constant visual anchor.

**Independent Test**: Can be fully tested by running `php artisan envform`, observing the header on the welcome screen (followed by privacy notes), proceeding to the next step, and verifying the header remains identical and in the same position.

**Acceptance Scenarios**:

1. **Given** the user starts the EnvForm tool, **When** the first screen loads, **Then** a purple-toned ASCII art header spelling "EnvForm" is displayed at the top, followed by the existing Local Analysis/Privacy notes.
2. **Given** the user is on the welcome screen, **When** they proceed to the form wizard, **Then** the same header remains visible at the top of the new screen (without the notes).
3. **Given** the user completes the wizard, **When** the summary or success message is shown, **Then** the header is still present.
4. **Given** the terminal window is narrower than the header logo, **When** the header renders, **Then** the excess characters on the right are truncated (not wrapped).
5. **Given** color is disabled in the terminal, **When** the tool is run, **Then** the "EnvForm" ASCII logo is rendered as plain text without styling.

### Edge Cases

- **Narrow Terminals**: Handled via truncation to prevent layout breakage.
- **No-Color Support**: Handled via plain-text rendering (stripping ANSI styles).

## Requirements *(mandatory)*

### Functional Requirements

- **FR-001**: System MUST display a persistent header at the very top of the terminal output for every step of the EnvForm execution (Select .env, Select config group, Form input, Save/Exit).
- **FR-002**: The header MUST feature the text "EnvForm" rendered as a simple, readable ASCII art logo.
- **FR-003**: The header styling MUST use a purple/magenta color palette to evoke a "wizardy" feel.
- **FR-004**: The system MUST replace the existing ephemeral welcome message with this persistent header, but PRESERVE the existing "Local Analysis" and "Privacy" notes on the first screen only.
- **FR-005**: The header layout (spacing, margins) MUST be consistent to prevent visual "jumping" when transitioning between steps.
- **FR-006**: If the terminal width is narrower than the header, the system MUST truncate the header content rather than wrapping it.
- **FR-007**: In environments without color support or where color is disabled (e.g., `NO_COLOR=1`), the system MUST render the header in plain ASCII text.

### Key Entities *(include if feature involves data)*

- **Header Component**: A dedicated UI component or function responsible for rendering the consistent header.

## Success Criteria *(mandatory)*

### Measurable Outcomes

- **SC-001**: The "EnvForm" ASCII header is verified to be present on 100% of the interactive screens.
- **SC-002**: Visual transition between steps feels stable, with the header acting as a static anchor.
- **SC-003**: Subjective assessment confirms the "wizardy" feel (purple tones, ASCII art) is distinct from standard CLI output.
