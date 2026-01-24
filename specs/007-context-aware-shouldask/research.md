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

## Decision: Unmapped Variables
- **Chosen**: Always show if missing/invalid.
- **Rationale**: Ensures critical keys like `APP_KEY` or `APP_DEBUG` are never accidentally hidden just because they aren't part of a "Service".
- **Alternatives considered**: Hide by default (too risky, blocks basic setup).
