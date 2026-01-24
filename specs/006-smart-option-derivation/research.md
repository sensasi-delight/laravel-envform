# Research: Smart Option Derivation

## Decision: Metadata Blacklist
**Decision**: Implement a hardcoded blacklist in `OptionResolver\Service`.
**Rationale**: Laravel's configuration structure for certain drivers (like Redis) mixes connection definitions with configuration metadata at the same level.
**Blacklisted Keys**:
- `client`
- `options`
- `clusters`

## Decision: Hybrid Nullability Detection
**Decision**: Use `Registry\Service::getStaticValue()` to detect if a config key has a default value of `null` in the AST. Allow manual overrides via a new `nullable` configuration or within `hints.php` (if it supports metadata, but better to keep it clean).
**Refinement**: For now, the "auto-detection" will check if the AST-parsed default value is `null`. If it is, `null` is added to the options.

## Decision: Empty Option Handling
**Decision**: Throw a `BackToMenuException` (or similar) with a warning message when `resolveOptions()` returns an empty array.
**Rationale**: Prevents the UI from displaying an empty selection list which would block the user.
**Feedback**: Use `laravel/prompts` warning to inform the user why the field was skipped.

## Decision: Sorting and Positioning
**Decision**: `null` will always be prepended to the option list. All other keys will be sorted alphabetically using `ksort()`.
**Rationale**: Provides a consistent and predictable UI for selection lists.
