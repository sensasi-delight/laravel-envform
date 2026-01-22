# Quickstart - Persistent Wizard Header

## Running the Feature

1.  **Execute the main command**:
    `ash
    php artisan envform
    `

2.  **Verify Header**:
    - Observe the purple "EnvForm" ASCII art at the top.
    - Note the "Privacy/Local Analysis" text below it on this first screen.

3.  **Verify Persistence**:
    - Proceed through the wizard steps (Select .env, etc.).
    - Ensure the header remains fixed at the top, but the "Privacy" text disappears after the first screen.

4.  **Verify Edge Cases**:
    - **Narrow Terminal**: Resize window < 80 chars. Run command. Header should cut off, not wrap.
    - **No Color**: Run NO_COLOR=1 php artisan envform. Header should be plain text.
