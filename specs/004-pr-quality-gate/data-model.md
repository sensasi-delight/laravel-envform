# Data Model: PR Quality Gate Workflow

## Workflow: `Tests`
The top-level entity representing the entire quality gate.

| Attribute | Description | Value/Logic |
|-----------|-------------|-------------|
| Trigger | Event that starts the workflow | `pull_request` on `main` branch |
| Permissions | GitHub GITHUB_TOKEN permissions | `contents: read` |

## Job: `test-matrix`
The primary execution unit that runs across different environments.

### Strategy Matrix
| Dimension | Values |
|-----------|--------|
| PHP Version | `8.2`, `8.3`, `8.4` |
| Stability | `stable`, `lowest` |
| OS | `ubuntu-latest` |

### Steps
1. **Checkout**: Retrieves source code.
2. **Setup PHP**: Installs PHP version, extensions (pcov, mbstring), and tools (composer).
3. **Cache Dependencies**: Restores/Saves `vendor/` based on `composer.lock`.
4. **Install Dependencies**: `composer update` based on stability matrix.
5. **Lint (Pint)**: Runs coding standard check (only on PHP 8.4/stable to avoid redundancy).
6. **Static Analysis (PHPStan)**: Runs level 8 analysis.
7. **Tests (PHPUnit)**: Executes test suite with coverage reporting.
8. **Summary**: Outputs coverage data to Job Summary.

## State Transitions
| Current State | Action | Next State |
|---------------|--------|------------|
| Queued | PR Opened | Running |
| Running | Step Failure | Failed (PR Blocked) |
| Running | All Steps Success | Success (PR Allowed) |
