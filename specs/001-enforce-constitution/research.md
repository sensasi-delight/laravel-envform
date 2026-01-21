# Research & Decisions

## 1. AST-Based Configuration Extraction
**Problem**: We need to replace `Config::get('key')` and `config('key')` with static AST analysis to comply with the Constitution.
**Context**: `nikic/php-parser` v5 is available. Config files are PHP files returning arrays.

**Approach**:
- `Registry\Repository` currently finds `env()` calls.
- We need new methods: `getStaticValue` and `getStaticKeys`.
- Strategy:
    1.  Parse the target config file (e.g., `database` -> `config/database.php`).
    2.  Locate the `return` statement.
    3.  Traverse the returned array expression.
    4.  Match nested keys via dot-notation.
    5.  Return the static value (literals/consts) or keys of an array node.

**Decision**: Implement `Registry\Repository::getStaticValue` and `getStaticKeys` using direct AST traversal of the returned array structure.

## 2. APP_KEY Generation
**Decision**: Continue using `Artisan::call('key:generate', ['--show' => true])` as requested. This is wrapped in `KeyGenerator\Service`. It is a justified exception to "No Execution" as it is user-triggered and avoids reimplementing Laravel's internal cryptography.

## 3. Option Resolution
**Problem**: `Wizard` needs to know valid options for variables (e.g., `DB_CONNECTION`).
**Approach**:
- `OptionResolver\Service` provides a mapping between specific config keys (e.g., `database.default`) and their source of options (e.g., `database.connections`).
- It uses `Registry\Service::getStaticKeys()` to fetch the keys.

**Decision**: Use an explicit mapping in `OptionResolver\Service` to resolve options for known Laravel configuration patterns.

## 4. Module Structure
**New Modules**:
- `KeyGenerator`: Service + (No Repository needed, pure logic).
- `OptionResolver`: Service + (Uses Registry).

**Refactoring**:
- `Wizard\Service`: Remove `handleAppKey` and `handleStrictKeys`. Inject `KeyGenerator` and `OptionResolver`.
- `Registry\Repository`: Add `readConfig(string $file)` and generic AST lookup logic.

## 5. Alternatives Considered
- **Reflection**: Cannot use reflection on config files without including them (execution).
- **Regex**: "Supporting signal only" - explicitly forbidden for structural truth.
- **Keeping Artisan**: Rejected to ensure no side effects and speed/isolation.
