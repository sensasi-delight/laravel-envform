# Quickstart: Context Aware ShouldAsk

## 1. Defining a Service
Add your service rules to `resources/services.php`.

```php
return [
    'redis' => [
        'activators' => [
            'cache.default' => ['redis'],
            'queue.default' => ['redis'],
        ],
        'master_keys' => [
            'database.redis.default.host',
        ],
        'patterns' => [
            'database.redis.*',
        ],
    ],
];
```

## 2. Integration
The `ShouldAsk\Service` will automatically filter questions based on these rules.

```php
// If cache.default = 'file' and queue.default = 'sync'
// AND database.redis.default.host is null
// THEN any env variable mapping to database.redis.* will be hidden.
```

## 3. Testing
Verify your rules using the `TuiNavigationTest` or by mocking the `ServiceDetection\Repository`.
The system is deterministic: if the config resolves to a non-matching driver, the keys disappear.
