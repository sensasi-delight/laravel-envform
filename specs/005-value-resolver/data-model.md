# Data Model: Value Resolver

## Entities

### InferenceRule
- **ConfigPath** (string): The Laravel dot-notation path (e.g., `cache.stores.database.lock_table`).
- **Logic** (Closure): The inference logic.
    - Input: `ValueResolver\Service`
    - Output: `mixed` (The inferred value)

### ValueResolverState (Internal)
- **ResolutionStack** (array<string>): Tracks keys currently being resolved to detect cycles.

## Relationships
- `ValueResolver\Service` consumes `ValueResolver\Repository`.
- `ValueResolver\Repository` loads `resources/inferences.php`.
- `ValueResolver\Service` interacts with:
    - `Registry\Service` (for static defaults)
    - `DotEnv\Service` (for existing values)
    - `FormValue\Service` (for current session values)

## Inference File Structure (`resources/inferences.php`)

```php
<?php

return [
    'cache.stores.database.lock_table' => function (\EnvForm\ValueResolver\Service $resolver) {
        $table = $resolver->resolve('cache.stores.database.table') ?? 'cache';
        return $table . '_lock';
    },
];
```
