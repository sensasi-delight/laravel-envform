# Feature Specification: Value Resolver Service

**Feature Branch**: `005-value-resolver`  
**Created**: 2026-01-23  
**Status**: Draft  
**Input**: User description: "create a clear improvement proposal addressing an undocumented implicit default in laravel configuration: when DB_CACHE_LOCK_TABLE is not set, laravel derives its value by appending _lock to DB_CACHE_TABLE, resulting in an effective default like {DB_CACHE_TABLE}_lock even though this behavior is not stated in config files or documentation. explain that this creates confusion when inspecting configuration, leads to incorrect assumptions by static analysis or env tooling, and causes third-party tools to miss or mis-handle the derived value. argue that making this behavior explicit, either by exposing the derived value directly in configuration, would improve clarity, correctness, and deterministic behavior for both users and tools."

## Clarifications

### Session 2026-01-23
- Q: Internally, should the tool use the ENV key or the Laravel config path for normalization? → A: Config Path: Use `cache.stores.database.lock_table` as the primary internal identifier.
- Q: How should the priority of values be handled? → A: Prioritized Resolution: 1. `formvalue`, 2. `existing` (.env), 3. `default` (config), 4. `derived`.
- Q: Should derived values be part of the Registry? → A: Decoupled: Exclude from Registry/EnvVar DTO. Handled by a dedicated service.
- Q: What is the name of the resolution service? → A: `ValueResolver\Service`.
- Q: Where should derivation logic be stored? → A: `resources/derivations.php`: A map of config keys to a closure that handles the derivation logic.
- Q: What is the signature of the derivation closure? → A: `fn(ValueResolver\Service $resolver): mixed`. This allows the closure to resolve other keys if needed while supporting simple static values.
- Q: How does ValueResolver access config defaults? → A: Registry Lookup: Consumes `Registry\Service` to perform global lookups for any configuration key's metadata (including defaults).

### Session 2026-01-24
- Q: How should the wizard indicate to the user that a value is derived rather than a standard Laravel default? → A: Normal Default: No special visual distinction in the UI.
- Q: Where should the `ValueResolver` classes be located in the directory structure? → A: `src/ValueResolver/`: Follow the project's modular pattern as a top-level namespace.
- Q: How should the `ValueResolver` handle circular dependencies? → A: Fail Fast: Throw a `LogicException` if a circular derivation is detected to prevent stack overflows and ensure configuration correctness.
- Q: How should the derivation rules be loaded and accessed? → A: Repository Pattern: Use `src/ValueResolver/Repository.php` to load rules from `resources/derivations.php`, keeping data access separate from logic.
- Q: Should the `Wizard\Service` delegate all value lookups to the `ValueResolver`? → A: Centralized Lookup: Yes. The `Wizard\Service` will consume `ValueResolver\Service` to resolve all values, ensuring consistent priority (FormValue > DotEnv > Config > Derived) and clean orchestration logic.

## User Scenarios & Testing *(mandatory)*

- **UX-001**: Derived values are presented as the default choice in prompts (e.g., using `Laravel\Prompts\text(default: ...)`), appearing identical to standard Laravel defaults to maintain a clean interface.
- **UX-002**: If a user provides an explicit value via `formvalue`, it overrides the derived value.
...
### Key Entities

- **ValueResolver\Service**: The "Brain" for value resolution. Consumes `Registry\Service`, `FormValue\Service`, and `DotEnv\Service`. Provides a `resolve(string $configPath)` method that applies the priority logic globally.
- **ValueResolver\Repository**: Loads and provides access to rules defined in `resources/derivations.php`.


### Data Model: `resources/derivations.php`

```php
return [
    'cache.stores.database.lock_table' => function ($resolver) {
        $table = $resolver->resolve('cache.stores.database.table') ?? 'cache';
        return $table . '_lock';
    },
];
```

## Success Criteria *(mandatory)*

- **SC-001**: `ValueResolver` correctly returns `cache_lock` when `DB_CACHE_LOCK_TABLE` is missing but `DB_CACHE_TABLE` is `cache`.
- **SC-002**: The `.env` file correctly receives the derived value if the user accepts the suggestion in the wizard.


### Assumptions

- The tool assumes the target project is a Laravel application following standard configuration patterns.
- The derivation logic (`{DB_CACHE_TABLE}_lock`) is a fixed pattern based on current Laravel source code.
- Users have the ability to modify their `.env` files via the tool.

## Success Criteria *(mandatory)*

### Measurable Outcomes

- **SC-001**: 100% of projects using default Laravel database cache configuration without `DB_CACHE_LOCK_TABLE` in `.env` are correctly identified as having an implicit dependency.
- **SC-002**: The wizard successfully guides a user to add `DB_CACHE_LOCK_TABLE` to their `.env` with the correct derived value in under 30 seconds.
- **SC-003**: 0% regression in existing environment variable detection for other configuration keys.