# model-update

Update neural models with new data.

## Usage
```bash
npx @claude-flow/cli@3.80.1 training model-update [options]
```

## Options
- `--model <name>` - Model to update
- `--incremental` - Incremental update
- `--validate` - Validate after update

## Examples
```bash
# Update all models
npx @claude-flow/cli@3.80.1 training model-update

# Specific model
npx @claude-flow/cli@3.80.1 training model-update --model agent-selector

# Incremental with validation
npx @claude-flow/cli@3.80.1 training model-update --incremental --validate
```
