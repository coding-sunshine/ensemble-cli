# Laravel Ensemble CLI

AI-native Laravel application scaffolding. Extends the Laravel installer with AI-powered schema generation and full-stack code scaffolding.

## Installation

```bash
composer global require coding-sunshine/ensemble-cli
```

## Quick Start

### Create a new project with AI

```bash
ensemble new my-app
```

The CLI will walk you through:
1. **Project name** and directory
2. **AI interview** — describe your app, pick a stack, choose features
3. **Schema generation** — AI produces an `ensemble.json` with models, controllers, pages, and recipes
4. **Project creation** — `composer create-project` with the right starter kit
5. **Scaffolding** — installs recipe packages, writes schema, and runs `ensemble:build`

### Create from a bundled template (no AI needed)

```bash
ensemble new my-app --template=saas
ensemble new my-app --template=blog
ensemble new my-app --template=ecommerce
ensemble new my-app --template=crm
ensemble new my-app --template=api
```

### Create a plain Laravel project (no AI)

```bash
ensemble new my-app --no-ai
```

This behaves like `laravel new`, but the `coding-sunshine/ensemble` package is still installed as a dev dependency so you can add a schema later.

### Generate a schema without creating a project

```bash
ensemble draft
ensemble draft --output=my-schema.json
ensemble draft --template=saas
ensemble draft --provider=ollama --model=llama3.1
```

Then use it later:

```bash
ensemble new my-app --from=my-schema.json
```

### Fully headless mode

```bash
ensemble new my-app --from=schema.json -n
ensemble new my-app --template=saas --pest --database=sqlite -n
```

When `-n` (non-interactive) is set with a schema or template, sensible defaults are auto-applied: starter kit from schema, Pest for testing, SQLite for database.

### Extend an existing schema

```bash
ensemble draft --extend=ensemble.json
```

The AI will read your current schema and add to it rather than starting from scratch.

### Preview before creating (`--dry-run`)

```bash
ensemble new my-app --from=schema.json --dry-run
ensemble init --template=saas --dry-run
```

Shows what would happen — starter kit, packages, models, steps — without creating or modifying anything.

### Validate a schema

```bash
ensemble validate
ensemble validate path/to/ensemble.json
```

Reports errors and warnings without creating anything. Inside a Laravel project, `php artisan ensemble:validate --json` returns machine-readable output with fix suggestions (for AI or automation).

### Compare two schemas

```bash
ensemble diff old-schema.json new-schema.json
```

Shows added, removed, and changed models, fields, controllers, pages, recipes, etc.

### Export schema as documentation or diagram

```bash
ensemble export
ensemble export ensemble.json -o SCHEMA.md
ensemble export --format=mermaid -o schema.mmd
ensemble export --format=schema-graph -o schema-graph.json
```

- **markdown** (default) — Human-readable markdown with tables for models, fields, relationships, controllers, pages, and more.
- **mermaid** — Mermaid ER diagram (entities and relationships). Use `ensemble export --format=mermaid -o schema.mmd` or, inside a Laravel project, `php artisan ensemble:diagram --format=mermaid --output=schema.mmd`.
- **schema-graph** — JSON with `nodes` and `edges` for tooling or a visual builder.

### Add Ensemble to an existing project

```bash
cd existing-laravel-app
ensemble init
ensemble init --from=schema.json
ensemble init --template=crm
```

### After creation: project-level (Artisan) commands

Once a project has `coding-sunshine/ensemble` installed, you can use these Artisan commands inside the project for AI-friendly iteration:

| Command | Description |
|---------|-------------|
| `php artisan ensemble:append <Model> [--controller] [--fields=...]` | Add a model (and optional controller) to `ensemble.json` without replacing the file |
| `php artisan ensemble:reduce <Model> [--erase]` | Remove a model from the schema; `--erase` deletes its generated files |
| `php artisan ensemble:from-database [table] [--build]` | Generate or update `ensemble.json` from your database |
| `php artisan ensemble:apply <fragment.json> [--build]` | Merge a JSON fragment (e.g. AI-generated) into `ensemble.json` |
| `php artisan ensemble:validate [--json]` | Validate schema; `--json` returns errors and fix suggestions for automation |
| `php artisan ensemble:build --dry-run` | Preview what would be generated without writing files |
| `php artisan ensemble:diff` | Compare current schema with last build |

See the [ensemble](https://github.com/coding-sunshine/ensemble) package README for the full command list and schema reference.

## Commands

| Command | Description |
|---------|-------------|
| `ensemble new <name>` | Create a new Laravel project with optional AI scaffolding |
| `ensemble draft` | Generate an `ensemble.json` schema without creating a project |
| `ensemble init` | Add Ensemble scaffolding to an existing Laravel project |
| `ensemble show [path]` | Pretty-print an `ensemble.json` schema |
| `ensemble validate [path]` | Validate schema structure and report errors/warnings |
| `ensemble diff <old> <new>` | Compare two schemas and show differences |
| `ensemble export [path] [--format=markdown\|mermaid\|schema-graph] [-o file]` | Export schema as markdown, Mermaid ER, or schema-graph JSON |
| `ensemble config [action]` | View or modify saved CLI configuration |
| `ensemble doctor` | Check your environment for compatibility |

## AI Providers

| Provider | Flag | Default Model | API Key Env |
|----------|------|---------------|-------------|
| Anthropic | `--provider=anthropic` | `claude-sonnet-4-20250514` | `ANTHROPIC_API_KEY` |
| OpenAI | `--provider=openai` | `gpt-4o` | `OPENAI_API_KEY` |
| OpenRouter | `--provider=openrouter` | `anthropic/claude-sonnet-4` | `OPENROUTER_API_KEY` |
| Ollama | `--provider=ollama` | `llama3.1` | None (local) |

Set `ENSEMBLE_API_KEY` as a universal key that works with any cloud provider. Override the model with `--model=your-model-name`.

For Ollama on a remote host, set `OLLAMA_HOST=http://your-host:11434`.

## Configuration

API keys and default provider are saved to `~/.ensemble/config.json` after first use:

```bash
ensemble config                              # List all saved config
ensemble config get default_provider         # Get a value
ensemble config set default_provider openai  # Set a value
ensemble config clear providers.openai       # Remove a key
```

## Schema Format

The `ensemble.json` file describes your entire application:

```json
{
    "version": 1,
    "app": {
        "name": "project-manager",
        "stack": "livewire",
        "ui": "mary"
    },
    "models": {
        "Project": {
            "fields": {
                "name": "string:200",
                "status": "enum:draft,active,archived default:draft",
                "budget": "decimal:10,2 nullable"
            },
            "relationships": {
                "tasks": "hasMany:Task",
                "owner": "belongsTo:User"
            },
            "softDeletes": true,
            "policies": {
                "update": "owner or admin"
            }
        }
    },
    "controllers": {
        "Project": { "resource": "web" }
    },
    "pages": {
        "dashboard": {
            "layout": "sidebar",
            "sections": [{ "widget": "stats", "data": ["projects_count"] }]
        },
        "projects.index": {
            "layout": "sidebar",
            "columns": ["name", "status", "owner.name"]
        }
    },
    "notifications": {
        "ProjectArchived": {
            "channels": ["mail", "database"],
            "to": "project.members",
            "subject": "Project {project.name} has been archived"
        }
    },
    "workflows": {
        "task_lifecycle": {
            "model": "Task",
            "field": "status",
            "states": ["todo", "in_progress", "review", "done"],
            "transitions": {
                "start": { "from": "todo", "to": "in_progress" },
                "complete": { "from": "review", "to": "done" }
            }
        }
    },
    "recipes": [
        { "name": "roles-permissions", "package": "spatie/laravel-permission" },
        { "name": "notifications", "package": null }
    ]
}
```

Field syntax follows [Laravel Blueprint](https://blueprint.laravelshift.com/) conventions. Schemas are validated on read for structural correctness.

## All Options

### `ensemble new`

| Option | Description |
|--------|-------------|
| `--from=<path>` | Create project from an existing `ensemble.json` |
| `-t, --template=<name>` | Use a bundled template (saas, blog, ecommerce, crm, api) |
| `--dry-run` | Show what would happen without creating anything |
| `--no-ai` | Skip AI, create a plain Laravel project |
| `--provider=<name>` | AI provider (anthropic, openai, openrouter, ollama) |
| `--model=<name>` | Override the default AI model |
| `--api-key=<key>` | API key for the AI provider |
| `--ai-budget=<level>` | Budget for `ensemble:build` (none, low, medium, high) |
| `--react` | Install the React starter kit |
| `--vue` | Install the Vue starter kit |
| `--svelte` | Install the Svelte starter kit |
| `--livewire` | Install the Livewire starter kit |
| `--pest` | Use Pest testing framework |
| `--git` | Initialize a Git repository |
| `--github[=visibility]` | Create a GitHub repository |
| `-f, --force` | Force install even if directory exists |

### `ensemble draft`

| Option | Description |
|--------|-------------|
| `-o, --output=<path>` | Output path (default: `./ensemble.json`) |
| `-t, --template=<name>` | Use a bundled template instead of AI |
| `-e, --extend=<path>` | Extend an existing schema with AI additions |
| `--provider=<name>` | AI provider |
| `--model=<name>` | Override AI model |
| `--api-key=<key>` | API key |

### `ensemble init`

| Option | Description |
|--------|-------------|
| `--path=<dir>` | Path to existing Laravel project (default: `.`) |
| `--from=<path>` | Path to existing `ensemble.json` |
| `-t, --template=<name>` | Use a bundled template |
| `--dry-run` | Show what would happen without making changes |
| `--provider=<name>` | AI provider |
| `--model=<name>` | Override AI model |
| `--api-key=<key>` | API key |
| `--ai-budget=<level>` | Budget for `ensemble:build` |

### `ensemble validate`

```
ensemble validate [path]
```

Validates schema structure and reports errors/warnings. Default path: `./ensemble.json`.

### `ensemble diff`

```
ensemble diff <old-path> <new-path>
```

Compares two schema files and shows added, removed, and changed items across all sections.

### `ensemble export`

| Option | Description |
|--------|-------------|
| `-f, --format=<format>` | Output format: `markdown` (default), `mermaid`, or `schema-graph` |
| `-o, --output=<path>` | Write to a file instead of stdout |

### `ensemble config`

```
ensemble config [action] [key] [value]
```

Actions: `list` (default), `get`, `set`, `clear`.

### `ensemble doctor`

No options. Checks PHP version, extensions, Composer, Git, Node/package managers, AI provider keys, and Ollama connectivity.

## Environment Variables

| Variable | Purpose |
|----------|---------|
| `ENSEMBLE_API_KEY` | Universal API key for any cloud provider |
| `ANTHROPIC_API_KEY` | Anthropic API key |
| `OPENAI_API_KEY` | OpenAI API key |
| `OPENROUTER_API_KEY` | OpenRouter API key |
| `OLLAMA_HOST` | Ollama base URL (default: `http://localhost:11434`) |

API key resolution priority: `--api-key` flag > `ENSEMBLE_API_KEY` > provider-specific env var > saved config > interactive prompt.

## How It Works

1. **Interview** — The CLI uses `laravel/prompts` to ask about your app: description, frontend stack, UI library, and features
2. **AI generation** — Your description is sent to the chosen AI provider with a system prompt that returns structured JSON
3. **Validation** — The response is parsed, validated for structural correctness, and merged with your interactive choices
4. **Project creation** — A Laravel project is created with the appropriate starter kit
5. **Scaffolding** — The companion `coding-sunshine/ensemble` package reads the schema and generates models, migrations, controllers, views, and more

## License

Laravel Ensemble CLI is open-sourced software licensed under the [MIT license](LICENSE.md).
