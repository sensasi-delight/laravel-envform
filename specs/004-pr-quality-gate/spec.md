# Feature Specification: Create standardized PR quality gate

**Feature Branch**: `004-pr-quality-gate`  
**Created**: 2026-01-23  
**Status**: Draft  
**Input**: User description: "004 create a standardized github action that automatically runs on every pull request targeting the main branch to act as a mandatory quality gate before merging. the goal is to ensure that no change reaches main without consistent validation, reducing the risk of regressions and broken releases caused by human oversight or inconsistent local checks. this workflow must verify that the project remains healthy by enforcing essential quality signals such as test execution, basic static checks, and successful build verification, and it must be configured as a required check for merging. the reason for this is to protect branch stability, establish a single source of truth for validation, and make quality enforcement systematic rather than optional or dependent on individual discipline."

## Clarifications

### Session 2026-01-23

- Q: Should the quality gate run against a matrix of PHP versions? → A: Matrix: Run all checks against PHP 8.2, 8.3, and 8.4
- Q: How should the quality gate handle PRs from forks regarding secrets? → A: Standard: Use pull_request (Safe, no secrets available from forks)
- Q: Should the quality gate also test against the "lowest" dependency versions? → A: Matrix: Test both --prefer-lowest and latest stable dependencies
- Q: Should the PR quality gate fail if code coverage drops below a certain threshold? → A: Report: Generate and display coverage reports without failing the build
- Q: Should the quality gate automatically commit style fixes back to the PR branch? → A: Check-only: Fail the PR and require the developer to fix style issues locally

## User Scenarios & Testing *(mandatory)*

### User Story 1 - Automated PR Quality Validation (Priority: P1)

As a developer submitting a Pull Request, I want the system to automatically verify my changes against the project's quality standards (tests, static analysis, and code style) so that I can be confident my code is healthy before it is reviewed or merged.

**Why this priority**: This is the core purpose of the feature. It establishes the "Quality Gate" that prevents broken code from entering the main branch.

**Independent Test**: Can be tested by opening a PR with a failing test or lint error and verifying that the GitHub Action triggers and correctly identifies the failure.

**Acceptance Scenarios**:

1. **Given** a Pull Request is opened targeting the `main` branch, **When** the workflow finishes successfully, **Then** a positive status check is reported back to the PR.
2. **Given** a Pull Request contains code that violates PHPStan rules or failing tests, **When** the workflow runs, **Then** it must fail and report the specific errors in the GitHub UI.

---

### User Story 2 - Build Integrity Verification (Priority: P2)

As a maintainer, I want to ensure that every PR can at least be "built" (dependencies installed and environment set up) so that we don't merge code that is fundamentally broken at the infrastructure level.

**Why this priority**: Ensures that the execution environment is consistent and that `composer.json`/`composer.lock` are in a valid state.

**Independent Test**: Can be tested by introducing a dependency conflict or syntax error in a PR and observing the workflow failure during the installation or setup phase.

**Acceptance Scenarios**:

1. **Given** a PR with valid `composer.json` and `composer.lock`, **When** the workflow runs, **Then** `composer install` should complete without errors using a cached or fresh environment.

---

### Edge Cases

- **Flaky Tests**: How does the system handle tests that fail intermittently? (Assumption: Standard failure behavior applies; developers must fix or mark as skipped).
- **GitHub API/Action Downtime**: If GitHub Actions is down, the "Required Check" might block merges. (Assumption: This is an external infrastructure risk handled by GitHub).
- **Secret Access**: Use standard `pull_request` trigger to ensure fork PRs are handled safely without exposing repository secrets.

## Requirements *(mandatory)*

### Functional Requirements

- **FR-001**: The workflow MUST trigger automatically on every `pull_request` event targeting the `main` branch.
- **FR-002**: The system MUST verify the "build" by successfully executing `composer install` with appropriate flags for CI (`--prefer-dist --no-progress`).
- **FR-003**: The system MUST execute the full test suite using `phpunit`.
- **FR-004**: The system MUST perform static analysis using `phpstan` at the level defined in the project's `phpstan.neon`.
- **FR-005**: The system MUST verify coding standards using `pint` in check-only mode (failing if violations exist without modifying code).
- **FR-006**: The workflow MUST fail if any of the following steps fail: build, test, static analysis, or style check.
- **FR-007**: The system MUST execute the quality gate across a matrix of supported PHP versions (8.2, 8.3, 8.4) and dependency stability (lowest, stable).
- **FR-008**: The system SHOULD generate a code coverage report during test execution and make it available in the workflow summary.

### Key Entities *(include if feature involves data)*

- **Quality Gate Workflow**: The orchestration of checks defined as a GitHub Action.
- **Build Artifacts**: Temporary outputs (like `vendor/` directory) used during the execution of the gate.
- **Status Check**: The signal sent to GitHub indicating the PASS/FAIL state of the PR.

## Success Criteria *(mandatory)*

### Measurable Outcomes

- **SC-001**: 100% of Pull Requests targeting `main` trigger the Quality Gate workflow.
- **SC-002**: The workflow completes all checks (Build, Test, Static Analysis, Lint) in under 5 minutes for the current codebase size.
- **SC-003**: No PR can be merged into `main` if the Quality Gate status is "Failed" (when configured as a required check in GitHub repository settings).
- **SC-004**: Developers can access detailed logs of failures directly from the GitHub PR interface within 2 clicks.