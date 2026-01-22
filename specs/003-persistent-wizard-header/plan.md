# Implementation Plan - Persistent Wizard Header

**Feature**: Persistent Wizard Header (003-persistent-wizard-header)
**Status**: Designed
**Spec**: [spec.md](./spec.md)

## Technical Context

### Architecture & Dependencies

- **Component**: New Header component in src/Console/Components (or similar UI namespace).
- **Integration**: EnvForm\Console\Commands\EnvForm command will invoke this header.
- **Library**: laravel/prompts for output rendering (	ext, info, etc.) and 	ermwind (if available via Laravel) or plain PHP string manipulation for ASCII art.
- **State Management**: The header needs to be stateless or receive context (like current step) if dynamic, but requirements say "consistent" (static), so likely stateless.

### Unknowns & Risks

- **[RESOLVED]**: Does laravel/prompts have a built-in "header" or "persistent top bar" feature? Or do we need to manually clear() and re-render?
  - *Decision*: Manual clear() + Header::render() is standard and robust.
- **[RESOLVED]**: How to detect terminal width reliably in a pure PHP CLI environment compatible with Laravel 12+?
  - *Decision*: Use Symfony\Component\Console\Terminal.
- **[RESOLVED]**: Best way to handle "No Color" detection?
  - *Decision*: Helper using stream_isatty and NO_COLOR env check + preg_replace to strip.

## Constitution Check

### Compliance Matrix

| Rule | Status | Notes |
|---|---|---|
| **AST Supremacy** | N/A | Feature is purely UI/Presentation. |
| **Idempotent Stability** | N/A | No file generation involved. |
| **No "Intelligence"** | Pass | Header is static/deterministic. |
| **Local-First** | Pass | No network requests. |
| **Layer Responsibilities** | Check | Logic must stay in Wizard service or Console layer. No business logic in header. |
| **UI/Console Constraints** | Check | Must use laravel/prompts. Must respect php artisan envform naming. |

### Gate Evaluation

- [x] **Core Mandates**: No violations.
- [x] **Architecture**: Aligns with UI/Console layer responsibility.
- [x] **Tech Constraints**: PHP 8.2+, Laravel 12+ compatible.

## Phase 0: Research & Decisions

### Research Tasks

1.  **Task**: Research laravel/prompts and 	ermwind capabilities for persistent headers and clearing screens.
    - *Goal*: Determine the most stable way to "pin" content to the top.
2.  **Task**: Research terminal width detection in Laravel/Symfony Console.
    - *Goal*: Implement FR-006 (Truncation).
3.  **Task**: Research NO_COLOR standard support in Laravel.
    - *Goal*: Implement FR-007 (Plain text fallback).

### Design Decisions (to be filled in research.md)

- **Header Rendering Strategy**: clear() + ender() vs Native Terminal Codes.
- **ASCII Art Source**: Embedded string vs external file.
- **Color Implementation**: 	ermwind CSS-like styles vs ANSI codes.

## Phase 1: Design & Contracts

### Data Model (data-model.md)

- See data-model.md. Minimal state (terminal width, color support).

### API Contracts (contracts/)

- *None required* (Internal component).

### Agent Context

- Updated cli_help and GEMINI.md.
