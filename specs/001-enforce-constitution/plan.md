# Implementation Plan: Enforce Constitution

**Branch**: `001-enforce-constitution` | **Date**: 2026-01-22 | **Spec**: `specs/001-enforce-constitution/spec.md`
**Input**: Feature specification from `specs/001-enforce-constitution/spec.md`

## Summary

Refactor the codebase to strictly enforce the "Constitution" by removing domain logic from `Wizard` and `Repositories`, eliminating runtime `Config::get()` calls in favor of AST analysis, and enforcing strict typing and architectural boundaries.

## Technical Context

**Language/Version**: PHP 8.2+
**Primary Dependencies**: laravel/prompts, illuminate/support (^12.0), nikic/php-parser (^5.0)
**Testing**: PHPUnit 12+, Orchestra Testbench
**Target Platform**: CLI (Laravel 12 Applications)
**Project Type**: Laravel Development Package
**Quality Standards**: strict_types=1, PHPStan Level 8, Laravel Pint

## Constitution Check

*GATE: Must pass before Phase 0 research. Re-check after Phase 1 design.*

- [x] **Deterministic Integrity**: Does this feature rely on AST analysis rather than heuristics? (Refactoring to ensure this)
- [x] **Strict Boundary Separation**: Does logic live in Services, and raw access in Repositories? (Refactoring to ensure this)
- [x] **Privacy First**: Is all processing 100% local with zero outbound connections?
- [x] **Explicit Configuration**: Does this avoid "magic" defaults in favor of explicit .env values?
- [x] **Quality Mandate**: Will the implementation include `declare(strict_types=1)` and pass PHPStan L8?

## Project Structure

### Documentation (this feature)

```text
specs/001-enforce-constitution/
├── plan.md              # This file (/speckit.plan command output)
├── research.md          # Phase 0 output (/speckit.plan command)
├── data-model.md        # Phase 1 output (/speckit.plan command)
├── quickstart.md        # Phase 1 output (/speckit.plan command)
├── contracts/           # Phase 1 output (/speckit.plan command)
└── tasks.md             # Phase 2 output (/speckit.tasks command - NOT created by /speckit.plan)
```

### Source Code (repository root)

```text
src/
├── Wizard/              # UI Layer (Presentation Only)
├── Registry/            # AST Scanner (Repository/Service)
├── DotEnv/              # .env Manager
├── KeyGenerator/        # [NEW] Logic for value generation
├── OptionResolver/      # [NEW] Logic for options
├── DTO/                 # Data Transfer Objects
└── [Module]/            # Other conceptual boundaries

tests/
├── Unit/
└── TestCase.php
```

**Structure Decision**: Single project package structure with conceptual module boundaries.

## Complexity Tracking

| Violation | Why Needed | Simpler Alternative Rejected Because |
|-----------|------------|-------------------------------------|
| **No Execution** (Artisan Call) | Uses `Artisan::call` for `key:generate`. | Avoids duplicating Laravel's internal encryption logic (cipher/length) and satisfies user preference. |
