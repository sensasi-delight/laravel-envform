# Data Model & Architecture: Guided TUI

## DTOs

### `EnvForm\DTO\NavigationSession`
An ephemeral object representing the sequence of variables for a configuration group.

- `steps`: `Collection<int, EnvVar>` - The sequence of variables.
- `currentIndex`: `int` - Current position in the sequence.

## New Exceptions

### `EnvForm\Exceptions\BackToMenuException`
Signal to exit the current configuration group and return to the main group selection menu.

## Updated Services

### `EnvForm\Wizard\Service`
The orchestrator for the Stable UI and navigation flow.

- `runFormLoop()`: Manages the `FormBuilder` lifecycle, including the "Clear and Re-render" logic.
- `renderStep()`: Captures `FormRevertedException` and syncs results to `FormValue`.
- `runPromptWithBackSupport()`: Intercepts `Ctrl+C` to trigger reversal or menu exit.

## State Transitions

1. **Initialization**: `configureGroup` resets indices and creates a new session.
2. **Forward Move**: `renderStep` writes to `FormValue` and refreshes `ShouldAsk`.
3. **Reversal (`Ctrl+C`)**: 
   - If `hasVisiblePrevious`: throws `FormRevertedException`.
   - If first visible: throws `BackToMenuException`.
4. **Re-render**: `addIf` condition detects next step, clears terminal, and prints historical `info()` lines.