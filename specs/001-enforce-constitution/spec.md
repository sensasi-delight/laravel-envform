# Feature Specification: Enforce Constitution

**Feature Branch**: `001-enforce-constitution`
**Created**: 2026-01-20
**Status**: Draft
**Input**: User description: "enforce the laravel envform constitution against the current codebase"

## User Scenarios & Testing *(mandatory)*

### User Story 1 - The Enforcement Gate (Tool Behavior) (Priority: P1)

As a CI/CD pipeline, I need a static analysis tool that scans the codebase and exits with specific error codes based on violation severity, so that non-compliant code is automatically rejected.

**Why this priority**: This is the core "product" of this feature—the automated guardrail.

**Independent Test**:
1.  **Critical Test**: Inject `curl_exec` -> Run Tool -> Assert Exit Code 2.
2.  **Major Test**: Inject a logic-heavy Repository -> Run Tool -> Assert Exit Code 1.
3.  **Minor Test**: Inject a single-impl interface -> Run Tool -> Assert Exit Code 0 (with Warning output).

**Acceptance Scenarios**:
1.  **Given** a violation of Network Isolation, **When** the tool runs, **Then** it outputs the file/line and exits with Code 2.
2.  **Given** a violation of Layer Responsibility, **When** the tool runs, **Then** it outputs the violation and exits with Code 1.
3.  **Given** only Minor violations, **When** the tool runs, **Then** it outputs warnings but exits with Code 0.

---

### User Story 2 - Compliance & Refactoring (Human Workflow) (Priority: P2)

As a maintainer, I use the Enforcement Gate's report to identify and manually refactor the codebase, ensuring the project aligns with the Constitution.

**Why this priority**: The tool identifies issues; this story covers the actual cleanup of the existing codebase using the tool's feedback.

**Independent Test**: Run the Enforcement Gate against the *clean* codebase and verify it returns Exit Code 0 with no Critical/Major errors.

**Acceptance Scenarios**:
1.  **Given** the current `Wizard` service, **When** I refactor it to remove domain branching, **Then** the Enforcement Gate no longer flags it as a Major violation.
2.  **Given** current interfaces, **When** I remove those with single implementations (or add justification attributes), **Then** the Enforcement Gate no longer flags them.
3.  **Given** regex parsers, **When** I restrict them strictly to comments/formatting, **Then** the gate approves the usage.

## Requirements *(mandatory)*

### Violation Severity & Exit Codes

The system MUST categorize violations and exit accordingly:

*   **CRITICAL (Exit Code 2)**:
    *   Network access (Guzzle, Curl, file_get_contents http).
    *   App Bootstrapping / Runtime Execution in `src/`.
    *   Heuristics overriding AST (Regex used for extraction truth).
*   **MAJOR (Exit Code 1)**:
    *   Business logic in Repositories.
    *   Domain-driven branching in Wizard/UI.
    *   Missing `strict_types=1`.
*   **MINOR (Exit Code 0 + Warning)**:
    *   Single-implementation interfaces (Abstraction Discipline) without `#[Justification]` attribute.
    *   Code Style violations (Pint).

### Functional Requirements

-   **FR-001**: **Exit Semantics**: The tool MUST return distinct exit codes (2 for Critical, 1 for Major, 0 for Success/Minor) to allow granular CI control.
-   **FR-002**: **AST Supremacy**: If Regex results contradict AST results, AST MUST be taken as the absolute truth. Regex usage MUST be restricted to non-structural tasks.
-   **FR-003**: **Repository Boundaries**: Repositories MUST ONLY contain filesystem I/O, AST traversal, and serialization. They may transform *structure*, not *meaning*. Any conditional based on semantic intent is a Major violation.
-   **FR-004**: **No Execution Scope**: Static analysis MUST verify that no Laravel bootstrapping occurs in the `src/` directory. The `tests/` directory is explicitly excluded from this check.
-   **FR-005**: **Network Ban**: Static analysis MUST verify zero usage of network functions or libraries in `src/`.
-   **FR-006**: **Abstraction Discipline**: Interfaces with <2 implementations MUST be flagged as a Minor violation unless explicitly marked with a custom attribute (e.g., `#[Justification]`).
-   **FR-007**: **Output Stability**: The tool MUST verify that generated artifacts (specifically `.env` files) remain byte-for-byte identical on repeated runs without input changes. Logs and console output are excluded from this requirement.
-   **FR-008**: **Wizard Logic**: `Wizard` classes MUST NOT contain **domain-driven branching** (e.g., "If user is admin..."). UI-level flow control (loops, input normalization) is allowed.

### Key Entities

-   **Constitution**: The strict rulebook.
-   **Gate**: The CLI command implementing the static analysis.
-   **Violation Report**: Structured output (JSON/Console) detailing issues.

## Success Criteria *(mandatory)*

### Measurable Outcomes

-   **SC-001**: CI pipeline fails with Exit Code 2 for Critical violations and Exit Code 1 for Major violations.
-   **SC-002**: Codebase contains 0 *unjustified* usages of Single-Implementation Interfaces.
-   **SC-003**: Codebase contains 0 Repositories with conditional business logic.
-   **SC-004**: Idempotency tests confirm 100% stability of `.env` generation.