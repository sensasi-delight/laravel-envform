# Research: Context Aware ShouldAsk

## Decision: Mapping Storage
- **Chosen**: New `resources/services.php` file containing a static array.
- **Rationale**: Follows existing project patterns (`dependencies.php`, `hints.php`). Provides a single source of truth for service-to-driver relationships.
- **Alternatives considered**: Storing in `Registry\Repository` (too coupled), or as JSON (less idiomatic for Laravel packages).

## Decision: Service Activation Logic
- **Chosen**: Hybrid approach. Active if (A) any subsystem driver matches the service, OR (B) a designated "Master" config key is non-null.
- **Rationale**: (A) captures standard Laravel usage. (B) handles "implicit" activation where a user has provided credentials but hasn't switched the driver yet, guiding them towards completion.
- **Alternatives considered**: Only driver-based (too strict, missed partially configured services).

## Decision: Variable Association
- **Chosen**: Explicit list of config key patterns in `resources/services.php`.
- **Rationale**: Aligns with the "Config-First Internal Consistency" directive in the Constitution. Allows grouping by dot-notation paths which are more stable than environment variable names.
- **Alternatives considered**: Regex on env keys (volatile, violates constitution).

## Decision: Global Service Relevance
- **Chosen**: A service is considered active if *any* subsystem triggers it.
- **Rationale**: Prevents false negatives. If Redis is used for Cache but not Queue, Redis variables must still be shown.
- **Alternatives considered**: Subsystem-specific suppression (too complex, leads to credential duplication or confusion).

## Decision: Multi-Key Relevance Logic
- **Chosen**: **OR Logic** (Variable is visible if AT LEAST ONE associated config key is relevant).
- **Rationale**: Prevents accidental suppression of global variables. For example, `APP_NAME` is used by `app.php` (always relevant) but also by `database.php` for Redis prefixes (irrelevant if Redis is inactive). Using AND logic would hide `APP_NAME` if Redis was disabled, which is incorrect.
- **Impact**: Ensures that shared credentials or global settings remain visible as long as at least one active subsystem requires them.

## Decision: Circular Dependency Resolution
- **Chosen**: Parameter Injection for `DotEnv\Service::save()`.
- **Rationale**: `ValueResolver` needs `DotEnv` to read existing values, and `ShouldAsk` needs `ValueResolver` via `ServiceDetection`. By removing the permanent `ShouldAsk` dependency from `DotEnv`'s constructor and passing it only when saving, we maintained strict boundaries without recursion.
