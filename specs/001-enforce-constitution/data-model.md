# Data Model & Architecture

## DTOs

### `EnvForm\DTO\EnvVar`
(Existing - No changes required)
- `key`: string
- `default`: mixed
- `group`: string
- `configKeys`: Collection
- `isTrigger`: bool

## New Services

### `EnvForm\KeyGenerator\Service`
Service wrapper for invoking Artisan commands to generate new keys.

```php
final class Service {
    /** Invokes Artisan key:generate --show and returns the output string */
    public function generate(): string;
}
```

### `EnvForm\OptionResolver\Service`
Logic for determining valid options for strict config keys.

```php
final class Service {
    public function __construct(private Registry\Service $registry);

    /** @return array<string, string> [value => label] */
    public function resolve(string $configKey): array;

    /** @return array<string, string>|null */
    public function resolveOptions(EnvVar $envVar): ?array;
}
```

## Updated Services

### `EnvForm\Registry\Service`
Enhanced to support static config lookup.

```php
final class Service {
    // ... existing methods ...

    /** Retrieve a static value from config (AST-based) */
    public function getStaticValue(string $configKey): mixed;

    /** Retrieve keys of a config array (AST-based) */
    public function getStaticKeys(string $configKey): array;
}
```

### `EnvForm\Registry\Repository` (Concrete)
(Interface `RepositoryContract` will be deleted)

```php
final class Repository {
    // ... existing scan methods ...

    public function getStaticValue(string $file, string $dotPath): mixed;
    public function getStaticKeys(string $file, string $dotPath): array;
}
```

### `EnvForm\Wizard\Service`
Refactored to remove logic.

```php
final class Service {
    public function __construct(
        private DotEnv\Service $dotEnv,
        private FormValue\Service $formValue,
        private Hint\Service $hint,
        private Registry\Service $registry,
        private ShouldAsk\Service $shouldAsk,
        private KeyGenerator\Service $keyGenerator,     // Injected
        private OptionResolver\Service $optionResolver  // Injected
    );
    // ...
}
```
