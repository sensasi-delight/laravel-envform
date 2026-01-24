# Quickstart: Smart Option Derivation

## Purpose
Ensure EnvForm provides accurate and filtered options for configuration selection, including support for `null` values and excluding internal metadata.

## Implementation Steps

1.  **Update `OptionResolver\Service`**:
    - Implement `BLACKLIST` constant: `['client', 'options', 'clusters']`.
    - Enhance `resolve()` to filter and sort keys.
    - Enhance `resolveOptions()` to detect nullability via `Registry\Service::getStaticValue()`.
    - Prepend `null` if the field is nullable.
    - Handle empty results by throwing `BackToMenuException`.

2.  **Update `Wizard\Service`**:
    - Ensure it catches `BackToMenuException` (if not already handled) and displays the warning message.

3.  **Verification**:
    - Run EnvForm and navigate to `CACHE_DRIVER` or `DB_CONNECTION`.
    - Verify `null` is an option if appropriate.
    - Verify `database.redis` options do not show `client` or `options`.
