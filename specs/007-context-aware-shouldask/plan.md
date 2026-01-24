# Implementation Plan: Context Aware ShouldAsk

**Branch**: `007-context-aware-shouldask` | **Date**: 2026-01-25 | **Spec**: [/specs/007-context-aware-shouldask/spec.md]
**Input**: Feature specification for a smarter, context-aware `ShouldAsk` decision system.

## Summary
Implement a deterministic service-detection layer that understands whether third-party services (Redis, AWS, Mailgun, etc.) are actually active in the application configuration. By resolving effective drivers via AST (ValueResolver), the system will suppress environment variable prompts for unused services, reducing wizard noise and preventing misconfiguration.

## Technical Context

**Language/Version**: PHP 8.2+
**Primary Dependencies**: laravel/prompts, illuminate/support (^12.0), nikic/php-parser
**Testing**: PHPUnit 12+, Orchestra Testbench
**Target Platform**: CLI (Laravel 12 Applications)
**Project Type**: Laravel Development Package
**Quality Standards**: strict_types=1, PHPStan Level 8, Laravel Pint

## Constitution Check

- [x] **Deterministic Integrity**: Relies on `ValueResolver` (AST analysis) to resolve effective config values.
- [x] **Strict Boundary Separation**: Logic lives in `ServiceDetection\Service`, raw access in `ServiceDetection\Repository`.
- [x] **Privacy First**: 100% local processing; no external calls to verify service status.
- [x] **Explicit Configuration**: Uses explicit mapping in `resources/services.php` rather than guessing.
- [x] **Quality Mandate**: Full type hinting, `strict_types=1`, and compliant with PHPStan L8.

## Project Structure

### Documentation (this feature)

```text
specs/007-context-aware-shouldask/
├── plan.md              # This file
├── research.md          # Decisions on mapping and activation logic
├── data-model.md        # ServiceDefinition and ServiceContext entities
├── quickstart.md        # Example of service definition
├── contracts/           
│   └── service.md       # Interfaces for Detection service and Repository
└── tasks.md             # Implementation tasks
```

### Source Code (repository root)

```text
src/
├── ServiceDetection/    # NEW: Service detection logic
│   ├── Repository.php   # Loads services.php
│   └── Service.php      # Evaluates activity
├── ShouldAsk/           
│   └── Service.php      # Updated to consume ServiceDetection
├── ValueResolver/       # Used to resolve effective config
└── Registry/            # Provides config key metadata

resources/
├── services.php         # NEW: Service-to-driver mapping
└── dependencies.php     
```

**Structure Decision**: Integrated as a new module `ServiceDetection` to maintain clean boundaries.

## Implementation Phases

### Phase 1: Infrastructure & Data
- Create `resources/services.php` with initial mappings (Redis, AWS, Mail, etc.).
- Implement `ServiceDetection\Repository` to load this map.
- Implement `ServiceDetection\DTO\ServiceDefinition` for structured access.

### Phase 2: Detection Engine
- Implement `ServiceDetection\Service` which evaluates `ServiceDefinition` against current config.
- Support "Explicit Activation" (driver checks).
- Support "Implicit Activation" (Master key checks).

### Phase 3: Integration
- Update `ShouldAsk\Service` to inject `ServiceDetection\Service`.
- **Architectural Adjustment**: Refactor `DotEnv\Service::save()` to accept `ShouldAsk\Service` as a parameter. This breaks the circular dependency chain: `DotEnv` -> `ShouldAsk` -> `ServiceDetection` -> `ValueResolver` -> `DotEnv`.
- Add a guard in `shouldBeAsked()` that checks `ServiceDetection::isKeyRelevant()` using **OR logic** (visible if ANY config key is relevant).

### Phase 4: Verification
- Unit tests for `ServiceDetection\Service` (Mocking `ValueResolverInterface`).
- Feature tests for `ServiceFilteringTest` and `BaselinePromptCountTest`.
- Fixed pluralization bug in `resources/dependencies.php` (`filesystems`).

## Complexity Tracking

*No constitution violations identified.*
