<!--
SYNC IMPACT REPORT
- Version change: (None) -> 1.0.0
- Refined for precision and enforceability.
- Clarified Regex usage (supporting signal vs source of truth).
- Strengthened Idempotency rules (output stability).
- Explicitly defined non-goals (no AI/inference).
-->

# Laravel EnvForm Constitution

## 1. Primary Directives

### Source Authority
*   **AST Supremacy**: The Abstract Syntax Tree (via `nikic/php-parser`) is the absolute authority for determining configuration structure and keys.
*   **Supporting Signals**: Regex and string manipulation are permitted ONLY as supporting signals (e.g., formatting, parsing comments). They MUST NOT override or contradict AST findings regarding key existence or structure.

### Idempotent Stability
*   **Analysis Stability**: Repeated executions against the same codebase MUST yield identical internal state.
*   **Output Stability**: File generation MUST be non-destructive to unmanaged content. The tool MUST preserve existing comments, blank lines, and key ordering in target files unless explicitly reconfigured by the user.

### Scope & Non-Goals
*   **No "Intelligence"**: The tool is a deterministic I/O processor, not an advisor. It MUST NOT:
    *   Infer environment types (e.g., guessing "production" vs "local").
    *   Suggest "best practice" values.
    *   Perform semantic validation (e.g., checking if an API key is active).
*   **Explicit Configuration**: We prioritize explicit user input over magic defaults. Hidden framework defaults MUST NOT be backfilled into `.env` without user confirmation.

### Privacy & Isolation
*   **Local-First**: All operations MUST occur within the local filesystem.
*   **Network Ban**: The package MUST NOT include HTTP clients (e.g., Guzzle, Curl) or make outbound network requests.
*   **No Execution**: Configuration files MUST be analyzed statically. The tool MUST NOT boot the user's Laravel application to read config values.

## 2. Architecture & Patterns

### Module Strategy
*   **Cohesion over Isolation**: Module directories (`Registry`, `Wizard`, etc.) represent functional cohesion, not strict bounded contexts.
*   **Asymmetric Abstraction**: Interfaces/Contracts are OPTIONAL. Implement them only for proven variability (e.g., multiple storage strategies). Do not create interfaces for "future-proofing".

### Layer Responsibilities
1.  **Repositories (Data Access)**:
    *   **Role**: Dumb I/O adapters (AST parsing, File reading/writing).
    *   **Constraint**: MUST NOT contain business logic, rules, or complex merging strategies.
    *   **Return Type**: Raw Arrays or simple DTOs.
2.  **Services (Business Logic)**:
    *   **Role**: Orchestration, rule application, state management, and decision making.
    *   **Constraint**: MUST be the only consumers of Repositories.
    *   **Testability**: MUST be testable via unit tests.
3.  **UI/Console (Presentation)**:
    *   **Role**: Interaction only.
    *   **Constraint**: Implemented in `EnvForm\Wizard` using `laravel/prompts`.
    *   **Forbidden**: MUST NOT contain domain logic. Delegates immediately to Services.

## 3. Technical Constraints

### Stack Definition
*   **PHP**: 8.2+
*   **Framework**: Laravel 12+
*   **AST Parser**: `nikic/php-parser` ^5.0

### Quality Mandates
*   **Strict Typing**: `declare(strict_types=1);` is MANDATORY in all PHP files.
*   **Static Analysis**: Code MUST pass PHPStan at **Level 8** (High Strictness).
*   **Style**: Code MUST adhere to **Laravel Pint** standards.
*   **Testing**:
    *   Use **PHPUnit 12+**.
    *   Test **Behavior** (Public Methods), not implementation details.
    *   Do not mock what you do not own (except strictly defined Repositories).

## 4. Governance

### Guardrails
*   This constitution acts as the primary filter for code review.
*   Violations of **Primary Directives** (e.g., adding "smart" inference or HTTP requests) are rejection criteria.

### Versioning
*   **Current Version**: 1.0.0
*   **Ratified**: 2026-01-20
*   **Protocol**: Semantic Versioning. Changes require verification against the core Mission.
