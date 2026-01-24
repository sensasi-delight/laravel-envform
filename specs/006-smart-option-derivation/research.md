# Research: Smart Option Derivation

## Decision: Metadata Blacklist
**Decision**: Implement a hardcoded blacklist in `OptionResolver\Service`.
**Rationale**: Laravel's configuration structure for certain drivers (like Redis) mixes connection definitions with configuration metadata at the same level.
**Blacklisted Keys**:
- `client`
- `options`
- `clusters`

## Decision: Hybrid Nullability Detection
**Decision**: Use `Registry\Service::getStaticValue()` to detect if a config key has a default value of `null` in the AST.
**Refinement**: For explicit overrides (keys that support null but have non-null AST defaults), we use an internal `NULLABLE_OVERRIDES` constant within `OptionResolver\Service`. Manual overrides via `hints.php` were deferred to keep the initial implementation focused and avoid circular dependencies between services during this phase.

## Decision: Empty Option Handling
**Decision**: Throw a `BackToMenuException` (or similar) with a warning message when `resolveOptions()` returns an empty array.
**Rationale**: Prevents the UI from displaying an empty selection list which would block the user.
**Feedback**: Use `laravel/prompts` warning to inform the user why the field was skipped.

## Decision: Sorting and Positioning
**Decision**: `null` will always be prepended to the option list. All other keys will be sorted alphabetically using `ksort()`.
**Rationale**: Provides a consistent and predictable UI for selection lists.
