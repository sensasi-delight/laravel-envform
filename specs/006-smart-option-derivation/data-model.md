# Data Model: Smart Options

## Entity: SelectOption
Represents an item in the selection list.

- **Label**: `string` (The display name, e.g., "null" or connection name)
- **Value**: `string|null` (The actual value to be written to `.env`)

## Entity: OptionFilter
Represents the filtering logic for a configuration array.

- **Blacklist**: `string[]` (Keys to be excluded: `client`, `options`, `clusters`)
- **Nullability**: `bool` (Whether `null` should be added as an option)

## Relationships
- `OptionResolver\Service` uses `Registry\Service` to fetch raw keys.
- `OptionResolver\Service` applies `OptionFilter` rules to generate `SelectOption` collection.
