# Contract: GitHub Actions Workflow YAML

## Schema: `.github/workflows/tests.yml`

This YAML file follows the [GitHub Actions Workflow Schema](https://docs.github.com/en/actions/using-workflows/workflow-syntax-for-github-actions).

### Key Requirements
1. **Name**: `Tests`
2. **On**:
   - `pull_request`:
     - `branches`: `[main]`
3. **Jobs**:
   - `tests`:
     - `runs-on`: `ubuntu-latest`
     - `strategy`:
       - `fail-fast`: `false`
       - `matrix`:
         - `php`: `[8.2, 8.3, 8.4]`
         - `stability`: `[prefer-lowest, prefer-stable]`
     - `steps`:
       - `name`: `Checkout code`
       - `name`: `Setup PHP`
       - `name`: `Install dependencies`
       - `name`: `Check Linting` (Condition: PHP 8.4 & prefer-stable)
       - `name`: `Static Analysis`
       - `name`: `Run Tests`
