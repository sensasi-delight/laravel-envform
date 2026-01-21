# Quickstart: Verifying Constitution Compliance

## 1. Static Analysis
Run PHPStan to verify type safety and strictness (Level 8).

```bash
composer lint
# OR
vendor/bin/phpstan analyse --memory-limit=2G
```

## 2. Unit Tests
Run the test suite to ensure no regressions and verify new logic.

```bash
composer test
# OR
vendor/bin/phpunit
```

## 3. Manual Verification
To test the refactored wizard (simulated):

```bash
# Verify no crashes in prompt generation
php artisan env:form --dry-run
```
*(Note: `--dry-run` might need to be added if not present, otherwise just run and exit)*

## 4. Key Checks
- Verify `src/Registry/RepositoryContract.php` is gone.
- Verify `src/Wizard/Service.php` has no `Config::get`.
- Verify `src/KeyGenerator` exists.
