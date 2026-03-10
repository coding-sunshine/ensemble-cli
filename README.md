# Laravel Ensemble CLI

AI-native Laravel application scaffolding. Extends the Laravel installer with AI-powered schema generation and full-stack code scaffolding.

Part of the [Ensemble](https://github.com/coding-sunshine) ecosystem:
- **ensemble-cli** (this package) — Global CLI for creating Laravel projects with AI-powered schema generation
- **[ensemble](https://github.com/coding-sunshine/ensemble)** — Project-level code generation from schemas (installed into your Laravel project)

## Installation

```bash
composer global require coding-sunshine/ensemble-cli
```

## Quick Start

### Create a new project with AI

```bash
ensemble new my-app
```

The CLI walks you through:
1. **Project name** and directory
2. **AI interview** — describe your app, pick a stack, choose features
3. **Schema generation** — AI produces an `ensemble.json` with models, controllers, pages, and recipes
4. **Project creation** — `composer create-project` with the right starter kit (or any GitHub repository via `--using`)
5. **Scaffolding** — installs recipe packages, writes schema, and runs `ensemble:build`

### Create from a bundled template (no AI needed)

```bash
ensemble new my-app --template=saas
ensemble new my-app --template=blog
ensemble new my-app --template=ecommerce
ensemble new my-app --template=crm
ensemble new my-app --template=api
ensemble new my-app --template=project-management
```

### Use a custom GitHub starter kit

```bash
ensemble new my-app --using=owner/repo
ensemble new my-app --using=github:owner/repo
ensemble new my-app --using=https://github.com/owner/repo
```

Passes the value straight to `npx tiged@latest`, so all GitHub shorthand formats work — just like `laravel new --using`. The `coding-sunshine/ensemble` package is automatically added to the resulting project.

### Create a plain Laravel project (no AI)

```bash
ensemble new my-app --no-ai
```

Behaves like `laravel new`, but `coding-sunshine/ensemble` is still installed so you can add a schema later.

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

When `-n` (non-interactive) is set with a schema or template, sensible defaults are auto-applied.

### Extend an existing schema

```bash
ensemble draft --extend=ensemble.json
```

The AI reads your current schema and adds to it rather than starting from scratch.

### Preview before creating (`--dry-run`)

```bash
ensemble new my-app --from=schema.json --dry-run
ensemble init --template=saas --dry-run
```

### Validate a schema

```bash
ensemble validate
ensemble validate path/to/ensemble.json
```

### Compare two schemas

```bash
ensemble diff old-schema.json new-schema.json
```

### Export schema as documentation or diagram

```bash
ensemble export
ensemble export ensemble.json -o SCHEMA.md
ensemble export --format=mermaid -o schema.mmd
ensemble export --format=schema-graph -o schema-graph.json
```

- **markdown** (default) — Human-readable tables for models, fields, relationships, controllers, pages
- **mermaid** — Mermaid ER diagram
- **schema-graph** — JSON nodes and edges for tooling or a visual builder

### Add Ensemble to an existing project

```bash
cd existing-laravel-app
ensemble init
ensemble init --from=schema.json
ensemble init --template=crm
```

### Modify a schema with natural language

```bash
ensemble ai "add a comments model with a body field and a belongsTo Post"
ensemble ai "add soft deletes to all models" --provider=openai
```

### Autonomous AI agent (self-correcting loop)

```bash
ensemble agent "Add a complete SaaS billing system with plans, subscriptions and invoices"
ensemble agent "Fix all validation errors in my schema" --max-iterations=5
ensemble agent "..." --dry-run          # Show final diff, do not write
ensemble agent "..." --apply            # Skip confirmation prompt
```

The agent runs a prompt → patch → validate loop. If the patched schema still has validation errors, it automatically feeds them back to the AI and tries again — up to `--max-iterations` times (default 3). Stops as soon as the schema is clean or the limit is reached, then shows a cumulative diff before asking for confirmation.

### Apply an AI-generated schema patch

```bash
ensemble update patch.yaml
ensemble update patch.yaml --yes    # Apply without confirmation
```

### Manage recipes

```bash
ensemble recipe list                        # List all known recipes
ensemble recipe add roles-permissions       # Add a recipe to ensemble.json
ensemble recipe add spatie/laravel-medialibrary
ensemble recipe remove roles-permissions
```

## Commands

| Command | Description |
|---------|-------------|
| `ensemble new <name>` | Create a new Laravel project with optional AI scaffolding |
| `ensemble draft` | Generate an `ensemble.json` schema without creating a project |
| `ensemble init` | Add Ensemble scaffolding to an existing Laravel project |
| `ensemble ai <prompt>` | Modify a schema using a natural language prompt |
| `ensemble agent <prompt> [--max-iterations=N]` | **New** — Autonomous AI agent: prompt → patch → validate, self-corrects until clean |
| `ensemble update <patch>` | Apply a YAML schema patch to `ensemble.json` |
| `ensemble recipe list\|add\|remove` | Manage recipe entries in `ensemble.json` |
| `ensemble template list\|show\|install` | Browse and install bundled project templates (12 templates: saas, blog, ecommerce, crm, api, project-management, marketplace, booking, inventory, helpdesk, lms, social) |
| `ensemble show [path]` | Pretty-print an `ensemble.json` schema |
| `ensemble validate [path]` | Validate schema structure and report errors/warnings |
| `ensemble diff <old> <new>` | Compare two schemas and show differences |
| `ensemble export [path] [--format=markdown\|mermaid\|schema-graph] [-o file]` | Export schema |
| `ensemble config [action]` | View or modify saved CLI configuration |
| `ensemble doctor` | Check environment and **detect local AI** (claude-cli, gemini-cli, LM Studio); recommends provider if none set |
| `ensemble mcp` | **New** — Run MCP server over stdio (tools: get_schema, update_schema, validate_schema, create_project, build_project, append_model, list_recipes, snapshot_schema, audit_project, search_packages) |
| `ensemble watch [path] [--auto-build]` | **New** — Watch schema file for changes; optional `--auto-build` to run build on change (debounced) |

## After creation: Artisan commands

Once a project has `coding-sunshine/ensemble` installed, use these Artisan commands for AI-friendly iteration.

**Bringing an existing app into Ensemble?** Use `ensemble:adopt` to scan your existing models, relationships, controllers, and installed packages and produce a complete `ensemble.json` in one step. Or load one of the bundled demo schemas with `ensemble:demo` to start from a realistic baseline. The Studio **Adopt** panel (`⊕` icon) provides a visual adoption report showing your models, detected packages, and actionable suggestions.

| Command | Description |
|---------|-------------|
| `php artisan ensemble:check [path]` | Validate + lint + dry-run plan in one checklist |
| `php artisan ensemble:ci [path] [--plan]` | **New** — CI health gate: validate + lint, exit 1 on any error |
| `php artisan ensemble:build [--dry-run]` | Generate code (or preview without writing) |
| `php artisan ensemble:fix [--dry-run]` | AI-powered repair of validation errors |
| `php artisan ensemble:audit [--modified-only] [--json]` | **New** — Show generated files modified since last build |
| `php artisan ensemble:snapshot [name] [--list] [--rollback] [--delete]` | **New** — Save/restore named schema snapshots |
| `php artisan ensemble:why <file>` | Show which generator produced a file and its source model |
| `php artisan ensemble:packages [--all]` | List installed packages and available Ensemble features |
| `php artisan ensemble:validate [--json]` | Validate schema; `--json` for automation |
| `php artisan ensemble:lint [--fix]` | Lint for design errors: FK indexes, N+1 risks, security |
| `php artisan ensemble:append <Model>` | Add a model to `ensemble.json` |
| `php artisan ensemble:reduce <Model>` | Remove a model from schema |
| `php artisan ensemble:from-database [--watch]` | **New `--watch`** — Generate schema from DB; re-run on migration changes |
| `php artisan ensemble:apply <fragment.json>` | Merge a JSON fragment into `ensemble.json` |
| `php artisan ensemble:diff` | Compare current schema with last build |
| `php artisan ensemble:relationship` | Add a relationship between models (previews both sides) |
| `php artisan ensemble:trace [--write-schema] [--dry-run]` | Reverse-engineer existing models; `--write-schema` also updates `ensemble.json` |
| `php artisan ensemble:adopt [--dry-run] [--force]` | **New** — Full app adoption: scan models, relationships, controllers, packages → `ensemble.json` |
| `php artisan ensemble:demo [name] [--list] [--apply]` | **New** — Browse/apply pre-built demo schemas (saas, blog, ecommerce, crm, api, project-management, marketplace, booking, inventory, helpdesk, lms, social) |
| `php artisan ensemble:install [--force]` | **New** — First-run setup wizard: publish config, detect AI, init schema |
| `php artisan ensemble:diagram` | Export schema as ER diagram or graph JSON |
| `php artisan ensemble:analyze` | Detect stack, packages, and database |

See the [ensemble](https://github.com/coding-sunshine/ensemble) package README for the full command reference, schema format, and configuration options.

## AI Providers

| Provider | Flag | Default Model | API Key Env |
|----------|------|---------------|-------------|
| Anthropic | `--provider=anthropic` | `claude-sonnet-4-20250514` | `ANTHROPIC_API_KEY` |
| OpenAI | `--provider=openai` | `gpt-4o` | `OPENAI_API_KEY` |
| OpenRouter | `--provider=openrouter` | `anthropic/claude-sonnet-4` | `OPENROUTER_API_KEY` |
| Ollama | `--provider=ollama` | `llama3.1` | None (local) |
| Claude CLI | `--provider=claude-cli` | — | None — uses the `claude` CLI tool |
| Gemini CLI | `--provider=gemini-cli` | — | None — uses the `gemini` CLI tool |
| LM Studio | `--provider=lmstudio` | Any loaded model | None — runs at `localhost:1234` |
| Prism | `--provider=prism` | Configured in `config/prism.php` | Via Prism config |

Set `ENSEMBLE_API_KEY` as a universal key that works with any cloud provider.

**Free local AI (no API key):** Install the `claude` CLI (`npm install -g @anthropic-ai/claude`) or `gemini` CLI, or run [LM Studio](https://lmstudio.ai). Run `ensemble doctor` to auto-detect which tools are available and get the right `ensemble config set` suggestion.

**Prism:** Requires `composer require prism-php/prism` and a Laravel application context. Use Prism to access any LLM supported by the prism-php library through a unified interface.

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
            "meta": {
                "seeder_count": 20,
                "seeder_states": ["active"]
            },
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
| `-t, --template=<name>` | Use a bundled template (saas, blog, ecommerce, crm, api, project-management, marketplace, booking, inventory, helpdesk, lms, social) |
| `--using=<repo>` | GitHub repo / URL for a custom starter kit (owner/repo, github:owner/repo, https://…) |
| `--dry-run` | Show what would happen without creating anything |
| `--no-ai` | Skip AI, create a plain Laravel project |
| `--provider=<name>` | AI provider (anthropic, openai, openrouter, ollama, prism) |
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
| `-t, --template=<name>` | Use a bundled template instead of AI (saas, blog, ecommerce, crm, api, project-management, marketplace, booking, inventory, helpdesk, lms, social) |
| `-e, --extend=<path>` | Extend an existing schema with AI additions |
| `--provider=<name>` | AI provider |
| `--model=<name>` | Override AI model |
| `--api-key=<key>` | API key |

### `ensemble init`

| Option | Description |
|--------|-------------|
| `--path=<dir>` | Path to existing Laravel project (default: `.`) |
| `--from=<path>` | Path to existing `ensemble.json` |
| `-t, --template=<name>` | Use a bundled template (saas, blog, ecommerce, crm, api, project-management, marketplace, booking, inventory, helpdesk, lms, social) |
| `--dry-run` | Show what would happen without making changes |
| `--provider=<name>` | AI provider |
| `--model=<name>` | Override AI model |
| `--api-key=<key>` | API key |
| `--ai-budget=<level>` | Budget for `ensemble:build` |

### `ensemble ai`

```
ensemble ai <prompt> [--provider=...] [--model=...] [--api-key=...] [--schema=path]
```

Interprets natural language to modify `ensemble.json`. Examples:

```bash
ensemble ai "add an Order model with a total decimal and a belongsTo User"
ensemble ai "give all models soft deletes"
ensemble ai "add a comments recipe"
```

### `ensemble update`

```
ensemble update <patch.yaml> [--yes] [--provider=...] [--model=...]
```

Applies a YAML schema patch. Use `--yes` to skip confirmation.

### `ensemble recipe`

```
ensemble recipe list
ensemble recipe add <feature-key-or-package>
ensemble recipe remove <feature-key-or-package>
```

Known feature keys: `roles-permissions`, `saas-billing`, `media-uploads`, `search`, `activity-log`, `admin-panel`, `multi-tenancy`, `api-auth`, `notifications`, `api-docs`, `openapi-cli`, `feature-flags`, `event-sourcing`, `data-transfer`, `query-builder`, `rate-limiting`, `api-versioning`.

Run `ensemble recipe list` for the full catalog with descriptions.

### `ensemble template`

```
ensemble template list                   # List all 12 bundled templates
ensemble template show <name>            # Show details and model list for a template
ensemble template install <name>         # Write the template schema to ensemble.json
```

Available templates:

| Template | Description |
|----------|-------------|
| `saas` | SaaS starter (teams, subscriptions, billing) |
| `blog` | Blog platform (posts, categories, tags, comments) |
| `ecommerce` | E-commerce store (products, orders, reviews) |
| `crm` | CRM (contacts, companies, deals, activities) |
| `api` | API service (tokens, webhooks, rate limiting) |
| `project-management` | Project management (workspaces, boards, tasks, time tracking) |
| `marketplace` | Multi-vendor marketplace (vendors, products, orders) |
| `booking` | Booking & scheduling (services, providers, appointments) |
| `inventory` | Inventory management (warehouses, stock, purchase orders) |
| `helpdesk` | Help desk (tickets, replies, knowledge base) |
| `lms` | Learning management system (courses, lessons, enrollments) |
| `social` | Social app (profiles, posts, follows, messaging) |

You can also load external templates from GitHub:

```bash
ensemble new my-app --using=github:owner/repo
ensemble new my-app --using=https://raw.githubusercontent.com/owner/repo/main/ensemble.json
```


### `ensemble validate`

```
ensemble validate [path]
```

Validates schema structure and reports errors/warnings. Default path: `./ensemble.json`.

### `ensemble diff`

```
ensemble diff <old-path> <new-path>
```

### `ensemble export`

| Option | Description |
|--------|-------------|
| `-f, --format=<format>` | `markdown` (default), `mermaid`, or `schema-graph` |
| `-o, --output=<path>` | Write to a file instead of stdout |

### `ensemble config`

```
ensemble config [action] [key] [value]
```

Actions: `list` (default), `get`, `set`, `clear`.

### `ensemble doctor`

No options. Checks PHP version, extensions, Composer, Git, Node/package managers, and AI setup. **Local AI detection:** if no API key is set, doctor checks for `claude-cli`, `gemini-cli`, and LM Studio (localhost:1234) and recommends a provider. Use with Studio or `ensemble draft` for key-free local workflows.

### `ensemble mcp`

Runs a Model Context Protocol (MCP) server over stdio. Exposes tools for schema read/write, validation, project creation, build, append model, recipes, snapshots, and audit. For AI agents or IDE integrations that speak MCP.

### `ensemble watch`

Watches the schema file (default `ensemble.json` in the current directory) for changes. Use `--auto-build` to run a build after each change (debounced). Run from the project root where `ensemble.json` and `php artisan` are available.

## Environment Variables

| Variable | Purpose |
|----------|---------|
| `ENSEMBLE_API_KEY` | Universal API key for any cloud provider |
| `ANTHROPIC_API_KEY` | Anthropic API key |
| `OPENAI_API_KEY` | OpenAI API key |
| `OPENROUTER_API_KEY` | OpenRouter API key |
| `OLLAMA_HOST` | Ollama base URL (default: `http://localhost:11434`) |

**Local AI (no API key):** set provider to `claude-cli`, `gemini-cli`, or `lmstudio` in config or via `ensemble config set provider <name>`. Doctor will suggest one if available.

API key resolution priority: `--api-key` flag > `ENSEMBLE_API_KEY` > provider-specific env var > saved config > interactive prompt.

## How It Works

1. **Interview** — The CLI uses `laravel/prompts` to ask about your app: description, frontend stack, UI library, and features
2. **AI generation** — Your description is sent to the chosen AI provider with a system prompt that returns structured JSON
3. **Validation** — The response is parsed, validated for structural correctness, and merged with your interactive choices
4. **Project creation** — A Laravel project is created with the appropriate starter kit (or a custom GitHub repo via `--using`)
5. **Scaffolding** — The companion `coding-sunshine/ensemble` package reads the schema and generates models, migrations, controllers, views, and more

## Security

### API Keys

Store API keys in environment variables (`ANTHROPIC_API_KEY`, `OPENAI_API_KEY`, etc.) rather than in saved config. The `~/.ensemble/config.json` file is created with `0600` permissions, but avoid writing keys there if possible.

### MCP Server (`ensemble mcp`)

The CLI MCP server exposes project-creation, schema-write, and audit tools over stdio. It is intended for local dev use only. Do not expose the MCP server's stdin/stdout over a network socket. Only connect trusted AI agents to it.

### Generated Artisan Commands

`ensemble watch --auto-build` runs `php artisan ensemble:build --dry-run` in the project directory. Ensure the project directory is trusted before enabling `--auto-build` in automated scripts.

## License

Laravel Ensemble CLI is open-sourced software licensed under the [MIT license](LICENSE.md).
