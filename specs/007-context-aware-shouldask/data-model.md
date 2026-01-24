# Data Model: Context Aware ShouldAsk

## Entities

### ServiceDefinition
The static mapping defining a third-party service and its triggers.
- `name`: Unique identifier (e.g., `redis`, `aws`).
- `activators`: Map of subsystem config keys to expected driver values (e.g., `queue.default` => `['redis']`).
- `master_keys`: List of config keys that trigger implicit activation if non-null.
- `patterns`: List of config key patterns owned by this service (e.g., `database.redis.*`).

### ServiceContext
The runtime evaluated state of all services.
- `activeServices`: Set of service names currently considered active.
- `keyToServiceMap`: Optimized lookup from config key to service name.

## Logic / State Transitions

1. **Initialization**: Load `resources/services.php`.
2. **Detection**:
   - For each service:
     - Check `activators`: Resolve effective driver via `ValueResolver`. If matches, mark Active.
     - Check `master_keys`: Resolve value via `ValueResolver`. If non-null, mark Active.
3. **Filtering**:
   - For each `EnvVar`:
     - Determine its `configKey`.
     - Find associated `Service`.
     - If `Service` exists and is Inactive, return `ShouldAsk = false`.
     - Else, proceed to standard dependency checks.
