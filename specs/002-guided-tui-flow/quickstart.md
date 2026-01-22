# Quickstart: Testing Guided TUI Navigation

## 1. Running the Wizard
Start the interactive wizard to see the new guided flow:

```bash
php artisan envform
```

## 2. Navigating Back
While configuring a value:
1. Enter some values for 2-3 variables.
2. Press `Ctrl+C` on the current variable.
3. Observe the TUI clearing and re-rendering the previous questions with your last entry as the default.

## 3. Returning to Menu
1. Start configuring any group (e.g., `app`).
2. At the first question `[1/X]`, press `Ctrl+C`.
3. Observe the TUI returning to the main "Select a configuration file" menu.

## 4. Verifying Determinism
1. Start the wizard and select `database`.
2. Answer questions until you reach a dependency (e.g., `DB_DATABASE`).
3. Go back via `Ctrl+C` to `DB_CONNECTION`.
4. Change the value from `sqlite` to `mysql`.
5. Observe the "🔄 Visibility updated" message and verify new relevant variables appear.

## 5. Running TUI Tests
Execute the specific test suite for interaction flows:

```bash
vendor/bin/phpunit tests/Feature/TuiNavigationTest.php
```
