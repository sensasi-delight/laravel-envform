# OptionResolver Service Contract

## Public Methods

### `resolve(string $configKey): array<string, string|null>`
Resolves available options for a given configuration key.

- **Input**: `$configKey` (e.g., `database.connections`)
- **Output**: Array of options where key is the display label and value is the actual value.
- **Logic**:
    1. Fetch keys from `Registry\Service`.
    2. Apply metadata blacklist filter (`client`, `options`, `clusters`).
    3. Sort keys alphabetically.
    4. Return options.

### `resolveOptions(EnvVar $envVar): ?array<string, string|null>`
Resolves options for an environment variable based on its mapped configuration keys.

- **Input**: `EnvVar` DTO.
- **Output**: Array of options or `null` if no options are derived.
- **Logic**:
    1. Identify the reference config path (e.g., `cache.stores`).
    2. Check nullability (Hybrid: default value in AST is `null` OR explicit override).
    3. Call `resolve()` on the reference path.
    4. If nullability is TRUE, prepend `null` to the result.
    5. If result is empty, throw `BackToMenuException` with warning.
