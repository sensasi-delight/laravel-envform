# Quickstart: Value Resolver

## 1. Adding an Inference Rule

Add a new closure to `resources/inferences.php`:

```php
return [
    'database.connections.mysql.database' => function ($resolver) {
        return 'my_app_db';
    },
];
```

## 2. Using the Service

Inject the `ValueResolver\Service` into your class and call `resolve()`:

```php
$value = $this->valueResolver->resolve('cache.stores.database.lock_table');
```

## 3. Priority Logic

The service automatically checks sources in this order:
1.  **FormValue**: Values entered during the current CLI session.
2.  **DotEnv**: Values already present in the `.env` file.
3.  **Config Default**: Static values found in `config/*.php` via AST analysis.
4.  **Inference**: Logic defined in `resources/inferences.php`.
