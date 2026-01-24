# Feature Specification: Smart Option Derivation

**Feature Branch**: `006-smart-option-derivation`  
**Created**: 2026-01-24  
**Status**: Draft  
**Input**: User description: "006 EnvForm currently derives select options by mapping config values to array keys in related config paths (e.g. database.default → database.connections, cache.default → cache.stores, cache.stores.database.connection → database.connections). this works for simple cases but fails in two important scenarios. first, some config values legitimately support null in addition to mapped keys, such as cache.default, where null is a valid value even though it is not present in cache.stores. second, some source config arrays mix connection definitions with non-connection metadata at the same depth, such as database.redis, which contains both redis connections (default, cache) and configuration entries (client, options), causing EnvForm to incorrectly include invalid options for fields like cache.stores.redis.connection. as a result, the current mapping logic is too naive and cannot reliably distinguish between valid selectable keys, nullable defaults, and non-selectable configuration entries, leading to incorrect or misleading select prompts."

## User Scenarios & Testing *(mandatory)*

### User Story 1 - Nullable Select Options (Priority: P1)

As a developer using EnvForm, I want to be able to select `null` for configuration values that support it (like `cache.default`), so that I can correctly configure my environment without being forced to choose a defined store or connection.

**Why this priority**: High priority because forcing a selection where `null` is valid prevents correct configuration of certain Laravel features.

**Independent Test**: Can be tested by running EnvForm on a project with `cache.default` set to `null` or requiring `null` configuration, and verifying that "null" is presented as a valid option in the selection list.

**Acceptance Scenarios**:

1. **Given** a configuration key that supports null values (e.g., `cache.default`), **When** prompted for its value in EnvForm, **Then** "null" (or an equivalent "None") should be an available option alongside keys from the related storage array (e.g., `cache.stores`).
2. **Given** the user selects the "null" option, **When** EnvForm saves the configuration, **Then** the value should be correctly written as `null` (or empty string in `.env` if mapped).

---

### User Story 2 - Filtered Selection Lists (Priority: P2)

As a developer, I want EnvForm to only show valid selectable keys (like connections) and exclude non-connection metadata (like `client` or `options` in `database.redis`), so that I don't accidentally select invalid configuration entries.

**Why this priority**: Medium priority as it improves user experience and prevents misconfiguration, though it is less "blocking" than the nullability issue.

**Independent Test**: Can be tested by viewing the selection list for a Redis-related connection field and verifying that metadata keys like `client` and `options` are absent from the list.

**Acceptance Scenarios**:

1. **Given** a configuration array containing both connection definitions and metadata (e.g., `database.redis`), **When** EnvForm derives options for a field mapped to this array, **Then** only the connection keys (e.g., `default`, `cache`) should be displayed.
2. **Given** the selection list is displayed, **When** reviewing the options, **Then** reserved metadata keys (like `client`, `options`, `clusters`) should be excluded.

---

### Edge Cases

- **What happens when a config array is empty?** The system should handle this gracefully, perhaps by only offering `null` (if applicable) or a "Manual entry" fallback.
- **How does the system handle custom metadata keys?** If a user adds non-standard keys to a connection array, the system should ideally have a way to distinguish them from valid connections.

## Requirements *(mandatory)*

### Functional Requirements

- **FR-001**: System MUST support `null` as a selectable option for configuration keys where it is a valid value, even if not present in the target mapping array.
- **FR-002**: System MUST distinguish between "selectable" entries (e.g., connection names) and "metadata" entries (e.g., configuration options) within source configuration arrays.
- **FR-003**: System MUST provide a mechanism to filter out reserved metadata keys from selection lists.
- **FR-004**: System MUST allow configuration of which keys are considered "nullable" for select prompts via explicit configuration or automatic discovery based on current default values.
- **FR-005**: System MUST reliably map configuration values to their respective source arrays while applying these smart filters.

### Key Entities *(include if feature involves data)*

- **Select Option**: A single item presented to the user in a command-line select prompt. Includes a label and a value (can be a string key or `null`).
- **Source Configuration Array**: The array from which options are derived (e.g., `config('database.connections')`).
- **Option Filter**: A rule that defines which keys should be excluded from a specific source configuration array.

## Success Criteria *(mandatory)*

### Measurable Outcomes

- **SC-001**: 100% of standard Laravel "nullable" configuration fields (e.g., `cache.default`) include `null` as a valid selectable option.
- **SC-002**: Select lists for Redis-related connections exclude 100% of reserved metadata keys (`client`, `options`, `clusters`).
- **SC-003**: No "invalid" options (metadata) are presented to the user in selection lists for standard Laravel database and cache configurations.
- **SC-004**: Users can successfully configure a "null" driver/connection without manually editing the `.env` file.

## Assumptions

- We assume that `database.redis` is the primary example of mixed metadata/connections, but the solution should be generic enough for other similar patterns.
- We assume that `null` values should be explicitly labeled (e.g., `null` or `(none)`) to distinguish them from empty strings.