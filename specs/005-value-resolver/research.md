# Research: Value Resolver Logic

## Decision: Circular Dependency Detection
- **Chosen**: Use a recursion stack (array of keys) tracked during a single `resolve()` call sequence.
- **Rationale**: This is a standard, low-overhead way to detect cycles in recursive resolution. If a key is encountered that is already in the current resolution stack, a `LogicException` is thrown.
- **Alternatives Considered**: 
    - **Graph analysis at startup**: Too complex for a dynamic list of closures where dependency isn't always static.
    - **Depth limiting**: Arbitrary and doesn't explicitly identify the cycle.

## Decision: Closure Resolution Pattern
- **Chosen**: Pass the `ValueResolver\Service` instance to the closure as its first argument: `fn(Service $resolver) => ...`.
- **Rationale**: This matches the spec requirement and allows the closure to recursively call the resolver for other keys while maintaining the same resolution context (stack).
- **Alternatives Considered**: 
    - **Static access**: Violates DI principles and makes testing harder.
    - **Passing raw data**: Closures might need to resolve keys they don't know the raw data for yet.

## Decision: Resource File Location
- **Chosen**: `resources/inferences.php`.
- **Rationale**: Consistent with `resources/hints.php` and `resources/dependencies.php`.
- **Alternatives Considered**: 
    - **Config directory**: This is a package resource, not user configuration.
