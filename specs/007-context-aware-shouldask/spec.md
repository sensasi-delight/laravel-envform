# Feature Specification: Context Aware ShouldAsk

**Feature Branch**: `007-context-aware-shouldask`
**Created**: 2026-01-25
**Status**: Completed
**Input**: User description: "design and implement a smarter ShouldAsk decision system that is aware of actual service usage, not just changes in dependent values. the goal is to ensure env questions are shown only when a third-party service is truly active in the application and completely hidden when it is not used anywhere. this matters because prompting users for env keys of unused services creates noise, confusion, and misconfiguration risk, while missing required keys for active services leads to runtime failures. the solution should infer service activation from effective configuration choices and consistently decide which groups of env keys are relevant, so the env wizard reflects real application behavior, improves signal-to-noise ratio, and guides users to configure only what actually matters."

## Clarifications

### Session 2026-01-25
- Q: Where should the Service-to-Driver mapping be stored? → A: New `resources/services.php` file (static array mapping).
- Q: How are environment variables associated with a Service? → A: Explicit list of config key patterns.
- Q: How is implicit activation triggered? → A: Triggered only by specific "Master" config keys (e.g., API Secret).
- Q: How to handle unmapped variables? → A: Always show (follow standard validation).
- Q: How to handle custom/unknown drivers? → A: Treat as generic (hide all known service-specific keys).

## User Scenarios & Testing

### User Story 1 - Smart Service-Aware Detection (Priority: P1)

As a developer, I want the wizard to hide entire blocks of configuration (like Redis or AWS keys) if the service is not active in any part of the application (Cache, Session, Queue, etc.), so that I only see what is relevant to my current stack.

**Why this priority**: Solves the "naive" dependency issue where keys are asked just because they exist in a file, rather than being truly required.

**Independent Test**:
1. Set `CACHE_DRIVER=file`, `SESSION_DRIVER=file`, `QUEUE_CONNECTION=sync`. Verify `REDIS_*` keys are completely hidden.
2. Change `SESSION_DRIVER=redis`. Verify `REDIS_*` keys appear because Redis is now active in at least one subsystem.

**Acceptance Scenarios**:

1. **Given** no subsystem (Cache, Queue, Session, Database, Mail) is configured to use `redis`, **When** the wizard runs, **Then** it MUST hide all `REDIS_*` environment variables.
2. **Given** at least one subsystem (e.g., `QUEUE_CONNECTION=sqs`) is configured to use `aws`, **When** the wizard runs, **Then** it MUST show relevant `AWS_*` variables.
3. **Given** a service like `mailgun` is defined in `services.php`, **When** `MAIL_MAILER` is NOT `mailgun`, **Then** Mailgun-specific keys MUST be hidden.

---

### User Story 2 - Cross-Subsystem Service Activation (Priority: P2)

As a user, I want a third-party service to remain "Active" as long as at least one component of my app requires it, preventing the wizard from hiding keys that are still needed elsewhere.

**Why this priority**: Prevents "false negatives" where a service is disabled in one place but still needed in another.

**Acceptance Scenarios**:

1. **Given** `CACHE_DRIVER=redis` but `QUEUE_CONNECTION=sync`, **Then** Redis variables MUST remain visible because Cache still needs them.
2. **Given** `DB_CONNECTION=mysql` and `QUEUE_CONNECTION=redis`, **Then** Redis variables MUST remain visible because Queue needs them.

---

### User Story 3 - Dynamic Relevance Switching (Priority: P3)

[Same as previous Story 2, but updated to "Service" context]

**Acceptance Scenarios**:

1. **Given** I change `CACHE_DRIVER` from `redis` to `file`, and no other subsystem uses Redis, **Then** the `REDIS_*` prompts should disappear immediately in the next step or re-run.

### Edge Cases

- **Unmapped Variables**: Variables not associated with any service in `resources/services.php` (e.g., `APP_KEY`, `APP_DEBUG`) will always follow standard `ShouldAsk` logic (shown if missing/invalid).
- **Custom/Unknown Drivers**: If a driver is set to a value not mapped in `services.php`, it is treated as generic. Known service-specific keys (like `REDIS_*`) remain hidden, but any unmapped keys discovered via AST are shown.
- **Partial Service Configuration**: If `REDIS_HOST` is filled but `CACHE_DRIVER=file` and nothing else uses Redis, the system should still hide the other `REDIS_*` keys (or potentially warn that an unused service has values). *Decision*: Hide to reduce noise; the wizard is for what is *required*.
- **Shared Credentials (Global Services)**: AWS credentials often serve multiple drivers (S3, SQS, SES). The "AWS Service" is active if *any* of those drivers are selected.
- **Default Driver Fallbacks**: If `QUEUE_CONNECTION` is missing from `.env`, the system MUST resolve the default value from `config/queue.php` (via AST) to determine if a service is active by default.

## Requirements

### Functional Requirements

- **FR-001**: The system MUST define a mapping in `resources/services.php` between **Services** and their **Activators** (the specific Subsystem driver values that trigger activation, e.g., `queue.default = sqs`).
- **FR-002**: The system MUST resolve the **Effective Driver** for every major Laravel subsystem (Database, Cache, Session, Queue, Mail, Filesystem, Broadcasting, Scout) using `ValueResolver`.
- **FR-003**: A **Service** MUST be considered **Active** if at least one subsystem is configured to use a driver associated with that service.
- **FR-004**: The system MUST allow grouping environment variables by their parent **Service** using an explicit list of config key patterns (e.g., `database.redis.*`).
- **FR-005**: The `ShouldAsk` logic MUST suppress all variables belonging to a **Service** that is not **Active**, overriding individual dependency rules.
- **FR-006**: The system MUST support **Implicit Activation**: if a designated "Master" config key (defined in the mapping) is non-null, the service is considered active even if the driver doesn't match.

### Key Entities

- **Service**: A third-party integration or infrastructure component (e.g., "Redis").
- **Subsystem**: A Laravel feature area (e.g., "Queue") that can be powered by different Services.
- **ServiceDefinition**: The static mapping defining a Service, its **Activators** (drivers that trigger it), its **Master Keys**, and its config patterns.
- **ServiceContext**: The registry of currently active Services for the current session.

## Success Criteria

### Measurable Outcomes

- **SC-001**: **Zero False Positives**: Users are never asked for variables belonging to a driver that is explicitly not selected (e.g., no SQS questions when `QUEUE_CONNECTION=sync`).
- **SC-002**: **100% Coverage of Active Services**: All required variables for the currently active drivers are presented.
- **SC-003**: **Reduction in Steps**: For a default Laravel install (Sync/File/Log drivers), the number of prompts should be reduced to only the essentials (App Key, DB if applicable), skipping unused cloud service keys.

## Assumptions

- We assume standard Laravel configuration structures (`config/queue.php`, etc.) are present or can be analyzed via AST.
- We assume "Drivers" are the primary mechanism for switching service implementations.