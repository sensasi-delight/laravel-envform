# Research & Decisions - Persistent Wizard Header

**Feature**: Persistent Wizard Header
**Status**: Research Complete

## 1. Header Rendering Strategy

**Problem**: How to ensure the header appears at the top of every step without scrolling off?

**Options Considered**:
- **A. Manual Clear & Render**: Call clear() then enderHeader() before every prompt.
- **B. TTY Pinning**: Use ANSI codes to save cursor position (Complex, fragile).
- **C. Output Buffering**: Capture output and prepend header (Overkill).

**Decision**: **Option A (Manual Clear & Render)**
- **Rationale**: laravel/prompts provides a clear() function. This is the standard "wizard" pattern. It's robust and simple.
- **Implementation**: Create a Header::render() static method. In EnvForm::handle and Wizard\Service, ensure clear() + Header::render() is called before each interaction phase.

## 2. Terminal Width Detection (FR-006)

**Problem**: How to detect width to truncate the ASCII art?

**Options Considered**:
- **A. exec('tput cols')**: specific to *nix. Flaky on Windows.
- **B. Symfony\Component\Console\Terminal**: Laravel's underlying component.
- **C. 	ermwind**: If available.

**Decision**: **Option B (Symfony\Component\Console\Terminal)**
- **Rationale**: laravel/prompts depends on symfony/console. The Terminal class is the standard, cross-platform way to get width in PHP.
- **Usage**: (new \Symfony\Component\Console\Terminal())->getWidth()

## 3. No-Color Support (FR-007)

**Problem**: How to strip ANSI codes when NO_COLOR or --no-ansi is present?

**Options Considered**:
- **A. Manual Env Check**: Check getenv('NO_COLOR').
- **B. Output Decorator Check**: Check Laravel's $output->isDecorated().
- **C. Strip ANSI Regex**: Always generate colored, then strip if needed.

**Decision**: **Option C (Strip ANSI Regex via Helper)**
- **Rationale**: laravel/prompts usually handles input coloring, but for our raw ASCII art string, we control the output.
- **Implementation**:
  - We will store the ASCII art *with* ANSI codes (or just plain).
  - Actually, better: Store plain ASCII. Apply colors using 	ermwind or standard ANSI codes *only if* color is supported.
  - *Correction*: The user wants "purple-toned".
  - We will use a standard ANSI regex to strip codes if (new Terminal())->hasColorSupport() is false (or equivalent check).
  - *Refinement*: Laravel\Prompts\Output\ConsoleOutput is internal. We can check stream_isatty(STDOUT) or use Symfony's ConsoleOutput.
  - *Simpler*: We will assume color is enabled unless NO_COLOR env is set (standard), or use 	ermwind if available (it handles this). Since 	ermwind isn't in composer.json, we'll use a simple "Has Color" check helper and preg_replace to strip codes if needed.

## 4. Architecture

- **Class**: EnvForm\Console\Components\Header
- **Method**: public static function render(): void
- **Logic**:
  1. clear() (from Laravel\Prompts\clear)
  2. Get Terminal Width.
  3. Load ASCII Art.
  4. Truncate if wider than terminal.
  5. Apply Purple Color (ANSI \e[35m etc) if color supported.
  6. Print to STDOUT.

## 5. Retrospective (Post-Implementation)

**What Worked:**
- **Manual Clear Strategy**: clear() + Header::render() proved stable and visually consistent.
- **Raw Output**: Using write(STDOUT) for the ASCII art avoided formatting interference from laravel/prompts (which adds margins/boxes to info() or 
ote()).
- **Terminal Component**: Symfony\Component\Console\Terminal worked well for width detection.

**What Changed:**
- **Color Detection**: Terminal->hasColorSupport() can be flaky depending on the exact execution context in Laravel commands. We supplemented it with stream_isatty(STDOUT) and NO_COLOR env check.
- **Simplification**: We removed the stripAnsi logic from the main render flow by storing the *plain* ASCII art and only adding ANSI codes if color is supported. This is more efficient than stripping. stripAnsi is kept as a public helper for testing.

**Learnings:**
- **PowerShell Encoding**: Set-Content in PowerShell can mishandle emojis/UTF-8 characters in large heredocs. Using Set-Content -Encoding UTF8 or specific file writing tools is critical for preserving UI assets like emojis.
