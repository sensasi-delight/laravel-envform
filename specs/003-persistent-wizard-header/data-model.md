# Data Model - Persistent Wizard Header

## Components

### Header Component

The Header is a stateless UI component. It does not persist data to disk but relies on runtime terminal state.

**Namespace**: EnvForm\Console\Components

**Properties** (Internal State):
- scii_art: The string constant containing the logo.
- width: Integer, derived from Symfony\Component\Console\Terminal.
- has_color: Boolean, derived from stream_isatty and NO_COLOR.

**Methods**:
- ender(?string  = null): void
  - Clears screen.
  - Prints Logo (Colored/Truncated).
  - Prints optional subtitle (e.g., Privacy notes on first run).

## Configuration

No persistent configuration files required.
