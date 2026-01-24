# Implementation Plan: Smart Option Derivation (COMPLETED)

**Branch**: `006-smart-option-derivation` | **Date**: 2026-01-24 | **Spec**: [spec.md](spec.md)
**Status**: Completed 2026-01-24

## Summary

Enhance the `OptionResolver\Service` to provide "smarter" selection options for environment variables. This includes:
1.  **Nullable Options**: Automatically including `null` in selection lists for configuration keys that support it (based on current default value or explicit hints).
2.  **Metadata Filtering**: Excluding reserved metadata keys (like `client`, `options`, `clusters` in Redis) from selection lists using a hardcoded blacklist.
3.  **Positioning & Sorting**: Ensuring `null` appears at the top, with other options sorted alphabetically.
4.  **Empty State Handling**: Gracefully skipping fields with empty source arrays while providing user feedback.

## Technical Context

**Language/Version**: PHP 8.2+
**Primary Dependencies**: laravel/prompts, illuminate/support (^12.0), nikic/php-parser
**Testing**: PHPUnit 12+, Orchestra Testbench
**Target Platform**: CLI (Laravel 12 Applications)
**Project Type**: Laravel Development Package
**Quality Standards**: strict_types=1, PHPStan Level 8, Laravel Pint

## Constitution Check

*GATE: Must pass before Phase 0 research. Re-check after Phase 1 design.*

- [x] **Deterministic Integrity**: Uses AST-derived configuration keys as the source of truth for options.
- [x] **Strict Boundary Separation**: Logic for filtering and nullability lives in `OptionResolver\Service`, while `Registry\Service` provides the raw data.
- [x] **Privacy First**: All configuration analysis is performed locally via AST.
- [x] **Explicit Configuration**: Enhances the user's ability to explicitly select `null` where appropriate.
- [x] **Quality Mandate**: Adheres to strict typing and PHPStan L8.

## Project Structure

### Documentation (this feature)

```text
specs/006-smart-option-derivation/
├── plan.md              # This file
├── research.md          # Phase 0 output
├── data-model.md        # Phase 1 output
├── quickstart.md        # Phase 1 output
├── contracts/           # Phase 1 output
└── tasks.md             # Phase 2 output
```

### Source Code (repository root)

```text
src/
├── OptionResolver/      # Enhanced for smart filtering and nullability
├── Registry/            # Provides AST-based config keys
├── Wizard/              # Updated to handle "back" on empty arrays
├── Hint/                # Used for explicit nullability overrides
└── ValueResolver/       # Updated to handle null value mapping correctly
```

**Structure Decision**: Enhancements focused on `OptionResolver` and `Wizard` services to integrate smart selection logic.

## Complexity Tracking

*No constitution violations identified.*
