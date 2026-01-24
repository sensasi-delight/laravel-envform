# Value Resolver Service Contract

```php
namespace EnvForm\ValueResolver;

interface ValueResolverInterface
{
    /**
     * Resolves a value for a given config path or environment key.
     * Priority: FormValue > DotEnv > Config Default > Inference
     *
     * @param string $key Dot-notation config path or Env Key
     * @return mixed
     * @throws \LogicException On circular dependencies
     */
    public function resolve(string $key): mixed;
}
```

# Value Resolver Repository Contract

```php
namespace EnvForm\ValueResolver;

interface RepositoryInterface
{
    /**
     * @return array<string, \Closure>
     */
    public function all(): array;

    public function find(string $configPath): ?\Closure;
}
```
