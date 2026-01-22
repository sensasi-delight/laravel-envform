# Research & Decisions: Guided TUI Navigation

## 1. Native "Back" Support in `laravel/prompts`
**Problem**: The user wants "Back" navigation using the `Ctrl+C` key.
**Discovery**: 
- `Laravel\Prompts\FormBuilder` has a `submit()` loop that catches `FormRevertedException` and decrements the index (navigates back).
- `Laravel\Prompts\Prompt` triggers `FormRevertedException` when `Key::CTRL_U` is pressed and a "revert handler" is active.
- There is no native mapping for `Key::CTRL_C` to revert; it usually exits the process.

**Decision**: 
- Use `Laravel\Prompts\FormBuilder` for the Wizard's navigation flow.
- We wrap the prompts and manually listen for `Key::CTRL_C` to throw `FormRevertedException`.
- If `Ctrl+C` is pressed at the first visible question of a group, we throw `BackToMenuException` to return to the main menu.

## 2. Deterministic State & Idempotency
**Problem**: The flow must maintain a "single authoritative state" and be replayed deterministically.
**Decision**: 
- Implement a `NavigationSession` DTO that holds the sequence of `EnvVar` objects.
- Responses are synced immediately to `FormValue\Service` during `renderStep` to ensure `ShouldAsk` re-evaluation works in real-time.

## 3. Visual Feedback: Stable UI Algorithm
**Problem**: Navigating back should not append questions or cause visual clutter.
**Decision**: 
- Implement a "Clear and Re-render" strategy.
- Before every prompt, the TUI calls `clear()`, re-displays the summary table, and prints the formatted history of all *previously* answered and *visible* questions.
- History entries use the same progress labels and prefixes as active prompts for visual consistency.

## 4. Testing TUI Flows
**Problem**: TUI must be treated as a first-class testable interaction.
**Decision**: 
- Use `Laravel\Prompts\Prompt::fallbackWhen(true)` in tests to bypass platform checks.
- Implement custom `fallbackUsing` callbacks that simulate `Ctrl+C` by throwing the appropriate exceptions.
- Validate final state in `FormValue\Service` and mock `KeyGenerator` for predictable outcomes.

## 5. Alternatives Considered
- **Esc Key**: (Rejected: User preferred `Ctrl+C` as it felt more natural).
- **Native Erase**: (Rejected: Line height calculation was unreliable for complex layouts; "Clear and Re-render" proved more robust).