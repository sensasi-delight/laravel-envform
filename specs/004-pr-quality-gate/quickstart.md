# Quickstart: PR Quality Gate

## For Contributors

1. **Submit a PR**: Open a Pull Request targeting the `main` branch.
2. **Watch the Checks**: The "Tests" workflow will automatically trigger. You can view the progress in the "Checks" tab of your PR.
3. **Review Failures**:
   - If **Pint** fails: Run `./vendor/bin/pint` locally to fix styling.
   - If **PHPStan** fails: Run `./vendor/bin/phpstan analyse` locally to identify type or logic errors.
   - If **PHPUnit** fails: Run `./vendor/bin/phpunit` locally to debug failing tests.
4. **Coverage**: After tests complete, check the "Workflow Summary" in GitHub to see the code coverage report.

## For Maintainers

1. **Required Checks**: Ensure that the `Tests` workflow is configured as a "Required Status Check" in the GitHub branch protection settings for `main`.
2. **Reviewing Logs**: Click "Details" on any failed check to see the exact step and error message in the CI log.
