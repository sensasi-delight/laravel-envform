# Implementation Plan: Guided TUI Navigation and Error Recovery

**Branch**: `002-guided-tui-flow` | **Date**: 2026-01-22 | **Spec**: `specs/002-guided-tui-flow/spec.md`
**Input**: Feature specification from `specs/002-guided-tui-flow/spec.md`

## Summary

Deliver a "fully user-friendly" TUI experience by transforming the wizard into a highly guided, interactive journey. Key enhancements include:
- **Reversible Navigation**: Using the `Esc` key to go back without losing state.
- **Visual Clarity**: Adding explicit step counts (e.g., [3/12]), distinct visual prefixes (🚀/⚙️), and re-rendering instead of appending.
- **Error Recovery & Confidence**: Contextual hints on every variable and a success summary after each group completion.
- **Predictable State**: Deterministic session management leveraging `laravel/prompts` native `FormBuilder`.

## Technical Context

**Language/Version**: PHP 8.2+
**Primary Dependencies**: laravel/prompts, illuminate/support (^12.0)
**Testing**: PHPUnit 12+, Orchestra Testbench
**Target Platform**: CLI (Laravel 12 Applications)
**Project Type**: Laravel Development Package
**Quality Standards**: strict_types=1, PHPStan Level 8, Laravel Pint

## Constitution Check

*GATE: Must pass before Phase 0 research. Re-check after Phase 1 design.*

- [x] **Deterministic Integrity**: Does this feature rely on AST analysis rather than heuristics? (N/A for TUI, but uses authoritative Registry/AST for data).
- [x] **Strict Boundary Separation**: Does logic live in Services, and raw access in Repositories? (Wizard delegates to Services).
- [x] **Privacy First**: Is all processing 100% local with zero outbound connections?
- [x] **Explicit Configuration**: Does this avoid "magic" defaults in favor of explicit .env values?
- [x] **Quality Mandate**: Will the implementation include `declare(strict_types=1)` and pass PHPStan L8?

## Project Structure

### Documentation (this feature)

```text
specs/002-guided-tui-flow/
├── plan.md              # This file
├── research.md          # Research on FormBuilder and Esc key
├── data-model.md        # NavigationSession and Wizard design
├── quickstart.md        # Testing instructions
└── tasks.md             # (To be generated)
```

### Source Code (repository root)

```text
src/
├── Wizard/              # UI Layer (Refactored to use FormBuilder)
├── DTO/                 # [NEW] NavigationSession (In-memory)
└── ...
```

**Structure Decision**: Single project package structure.

## Complexity Tracking

| Violation | Why Needed | Simpler Alternative Rejected Because |
|-----------|------------|-------------------------------------|
| None | N/A | N/A |
