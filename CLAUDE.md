# CLAUDE.md — Laravel Ensemble CLI

## What is this?

A standalone CLI tool (`bin/ensemble`) that extends the Laravel installer with AI-powered schema generation and full-stack code scaffolding. Forked from `laravel/installer`.

**This is Package 1 of 2.** Package 2 is `coding-sunshine/ensemble` — a Laravel package (fork of `laravel-shift/blueprint`) that reads `ensemble.json` and generates all the code. This CLI creates projects and schemas; the companion package does the actual code generation.

## Project structure

```
bin/ensemble                          ← CLI entry point (Symfony Console app, $_composer_autoload_path aware)
src/
├── Console/
│   ├── NewCommand.php                ← `ensemble new` — creates a Laravel project + AI scaffolding
│   ├── DraftCommand.php              ← `ensemble draft` — generates ensemble.json without a project
│   ├── InitCommand.php               ← `ensemble init` — add Ensemble to an existing Laravel project
│   ├── ShowCommand.php               ← `ensemble show` — pretty-print schema (models, notifications, workflows)
│   ├── ValidateCommand.php           ← `ensemble validate` — standalone schema validation with errors/warnings
│   ├── DiffCommand.php               ← `ensemble diff` — compare two schema files
│   ├── ExportCommand.php             ← `ensemble export` — export schema as markdown documentation
│   ├── ConfigCommand.php             ← `ensemble config` — view/set/clear saved configuration
│   ├── DoctorCommand.php             ← `ensemble doctor` — environment health check
│   ├── Concerns/
│   │   ├── ConfiguresPrompts.php     ← Prompt fallbacks for non-interactive/Windows
│   │   ├── DisplaysDryRun.php        ← --dry-run display logic shared between new and init
│   │   ├── InteractsWithHerdOrValet.php ← Herd/Valet park detection
│   │   ├── ResolvesAIProvider.php    ← Resolves AI provider from saved config, --options, env, or prompts
│   │   └── TracksProgress.php        ← [X/Y] step progress indicator for long operations
│   └── Enums/
│       └── NodePackageManager.php    ← NPM/YARN/PNPM/BUN helpers
├── AI/
│   ├── ConversationEngine.php        ← Multi-step AI interview → JSON schema (extend mode, verbose logging)
│   └── Providers/
│       ├── ProviderContract.php      ← Interface: complete(), ping(), estimateTokens(), name()
│       ├── AnthropicProvider.php     ← Claude API via Guzzle (ping uses max_tokens:1)
│       ├── OpenAIProvider.php        ← GPT API via Guzzle (ping uses GET /v1/models)
│       ├── OpenRouterProvider.php    ← OpenRouter via Guzzle (ping uses GET /api/v1/models)
│       └── OllamaProvider.php        ← Local LLM (configurable via OLLAMA_HOST env var)
├── Config/
│   └── ConfigStore.php               ← Persistent config at ~/.ensemble/config.json (provider, API keys)
├── Schema/
│   ├── SchemaWriter.php              ← Read/write ensemble.json (JSON only, with version + structure validation)
│   ├── SchemaValidator.php           ← Validates schema structure, field types, relationship syntax, etc.
│   └── TemplateRegistry.php          ← Bundled starter templates (saas, blog, ecommerce, crm, api)
└── Scaffold/
    └── StarterKitResolver.php        ← Maps stack name → Laravel starter kit package
stubs/
├── system-prompt.md                  ← Externalized AI system prompt (full schema spec with all sections)
└── templates/                        ← Pre-built schema files for offline/no-AI use
    ├── saas.json
    ├── blog.json
    ├── ecommerce.json
    ├── crm.json
    └── api.json
tests/
├── NewCommandTest.php
├── InteractsWithHerdOrValetTest.php
├── ConfigStoreTest.php
├── TemplateRegistryTest.php
├── SchemaWriterTest.php
├── StarterKitResolverTest.php
└── SchemaValidatorTest.php
```

## Namespace

`CodingSunshine\Ensemble\` mapped to `src/` via PSR-4.

## Commands

- `php bin/ensemble new <name>` — Full project creation with optional AI
- `php bin/ensemble draft` — Standalone schema generation
- `php bin/ensemble init` — Add Ensemble to an existing Laravel project
- `php bin/ensemble show [path]` — Pretty-print an ensemble.json schema
- `php bin/ensemble validate [path]` — Validate schema structure with errors/warnings
- `php bin/ensemble diff <old> <new>` — Compare two schema files (added/removed/changed)
- `php bin/ensemble export [path]` — Export schema as markdown documentation
- `php bin/ensemble config [action] [key] [value]` — View/set/clear saved configuration
- `php bin/ensemble doctor` — Check environment for compatibility
- `php bin/ensemble new <name> --from=ensemble.json` — Create project from existing schema
- `php bin/ensemble new <name> --template=saas` — Create project from bundled template
- `php bin/ensemble new <name> --dry-run --from=schema.json` — Preview what would happen
- `php bin/ensemble draft --extend=ensemble.json` — AI extends an existing schema
- `php bin/ensemble draft --template=blog` — Generate schema from bundled template
- `php bin/ensemble init --template=crm` — Add template schema to existing project
- `php bin/ensemble new <name> --from=schema.json -n` — Fully headless, non-interactive mode
- `php bin/ensemble new <name> --no-ai` — Plain Laravel project (same as `laravel new`)

## Key CLI options

| Option | Commands | Purpose |
|--------|----------|---------|
| `--from` | new, init | Path to existing ensemble.json |
| `--template` / `-t` | new, draft, init | Use bundled template (saas, blog, ecommerce, crm, api) |
| `--extend` / `-e` | draft | Extend an existing schema with AI additions |
| `--dry-run` | new, init | Preview operations without executing |
| `--no-ai` | new | Skip AI, plain Laravel install |
| `--provider` | new, draft, init | anthropic, openai, openrouter, ollama |
| `--model` | new, draft, init | Override default AI model |
| `--api-key` | new, draft, init | API key for cloud providers |
| `--ai-budget` | new, init | Budget level for ensemble:build |
| `--output` / `-o` | draft, export | Output path for schema/markdown file |
| `--path` | init | Path to existing Laravel project (default: `.`) |

## DX features

- **Persistent config**: API keys and default provider saved to `~/.ensemble/config.json` (file permissions 600). Manage with `ensemble config`.
- **Provider health check**: Lightweight `ping()` (model-list endpoints, not completions) verifies connectivity before the interview.
- **Confirmation gate**: After AI generates a schema, users see a summary and choose: Proceed / Regenerate / Abort.
- **Schema versioning**: `"version": 1` field in every `ensemble.json`. SchemaWriter validates compatibility.
- **Schema validation**: `SchemaValidator` checks structure, field types, relationship syntax, recipe format, notifications, and workflows on read.
- **Externalized system prompt**: `stubs/system-prompt.md` contains the full AI prompt spec. Edit without touching PHP.
- **Bundled templates**: Five pre-built schemas (saas, blog, ecommerce, crm, api) for offline/no-AI use.
- **Fully headless mode**: `ensemble new my-app --from=schema.json -n` auto-derives all defaults.
- **Step progress indicator**: `[1/7] Creating Laravel project...` style progress during `ensemble new`.
- **Environment health**: `ensemble doctor` checks PHP, extensions, Composer, Git, Node, AI keys, Ollama.
- **Token estimation**: `estimateTokens()` on all providers for cost awareness (returns 0 for Ollama).
- **`ensemble validate`**: Standalone schema validation — reports errors and warnings without creating anything.
- **`ensemble diff`**: Compare two schema files — shows added/removed/changed models, fields, recipes, etc.
- **`ensemble export`**: Export schema as clean markdown documentation with tables and sections.
- **`--dry-run` mode**: `ensemble new --dry-run` and `ensemble init --dry-run` preview all operations without executing.
- **Verbose AI debugging**: `-vv` shows prompt lengths and token estimates; `-vvv` dumps full prompts and raw AI responses.
- **Schema extend**: `ensemble draft --extend=existing.json` has AI add to an existing schema rather than starting from scratch.
- **Always install `ensemble`**: The companion package is installed as a dev dependency in all CLI-created projects, even with `--no-ai`.

## Environment variables

| Variable | Purpose |
|----------|---------|
| `ENSEMBLE_API_KEY` | Universal API key (any provider) |
| `ANTHROPIC_API_KEY` | Anthropic-specific key |
| `OPENAI_API_KEY` | OpenAI-specific key |
| `OPENROUTER_API_KEY` | OpenRouter-specific key |
| `OLLAMA_HOST` | Ollama base URL (default: `http://localhost:11434`) |

Priority: `--api-key` flag > `ENSEMBLE_API_KEY` > provider-specific env var > saved config (~/.ensemble/config.json) > interactive prompt.

## Dependencies

- **Runtime**: PHP 8.2+, guzzlehttp/guzzle ^7.9, symfony/console ^6.2|^7.0, symfony/process ^6.2|^7.0, laravel/prompts ^0.1.18|^0.2|^0.3, illuminate/filesystem, illuminate/support
- **Dev**: phpstan/phpstan ^2.1, phpunit/phpunit ^10.4

## Schema format (ensemble.json)

```json
{
  "version": 1,
  "app": { "name": "my-app", "stack": "livewire", "ui": "mary" },
  "models": {
    "Project": {
      "fields": { "name": "string:200", "status": "enum:draft,active default:draft" },
      "relationships": { "tasks": "hasMany:Task", "owner": "belongsTo:User" },
      "softDeletes": true,
      "policies": { "update": "owner or admin" }
    }
  },
  "controllers": {
    "Project": {
      "resource": "web",
      "archive": { "find": "project", "update": "status", "redirect": "projects.index" }
    }
  },
  "pages": {
    "dashboard": { "layout": "sidebar", "sections": [{ "widget": "stats" }] },
    "projects.index": { "layout": "sidebar", "columns": ["name", "status"] }
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
      "states": ["todo", "in_progress", "done"],
      "transitions": { "start": { "from": "todo", "to": "in_progress" } }
    }
  },
  "recipes": [
    { "name": "roles-permissions", "package": "spatie/laravel-permission" },
    { "name": "notifications", "package": null }
  ]
}
```

### Schema sections

| Section | Purpose |
|---------|---------|
| `app` | Application name, stack, UI library |
| `models` | Model definitions with fields, relationships, softDeletes, policies |
| `controllers` | Resource controllers + custom actions |
| `pages` | Route/page definitions with layouts, sections, columns, actions |
| `notifications` | Notification classes with channels, recipients, subjects |
| `workflows` | State machines with states and transitions |
| `recipes` | Feature packages to install |

Field syntax follows Laravel Blueprint conventions. Relationship format is flat: `"relName": "type:Model"`. AI is told NOT to include id/timestamps.

## Coding standards

- PHP 8.2+: readonly properties, enums, named arguments, match expressions, arrow functions
- Type declarations on all method parameters and return types
- No abbreviations in variable/method names
- Import laravel/prompts functions individually: `use function Laravel\Prompts\{select, text, confirm}`
- Error messages should be helpful and suggest fixes
- Follow Taylor Otwell's style: expressive, readable code

## The two packages

| | Package 1: `ensemble-cli` (this repo) | Package 2: `ensemble` (separate repo) |
|--|--|--|
| **Base** | Fork of `laravel/installer` | Fork of `laravel-shift/blueprint` |
| **Install** | `composer global require` | `composer require --dev` (project-level) |
| **Purpose** | Create projects + AI conversation + schema generation | Read `ensemble.json` → generate all code |
| **Commands** | `ensemble new`, `draft`, `init`, `show`, `validate`, `diff`, `export`, `config`, `doctor` | `artisan ensemble:build`, `:trace`, `:analyze`, `:validate`, `:diff`, `:append`, `:reduce`, `:from-database`, `:apply`, `:erase`, etc. |

This CLI handles schema creation. The companion package handles code generation from that schema. The package also supports **AI-friendly iteration**: `ensemble:append`, `ensemble:reduce`, `ensemble:from-database`, `ensemble:apply`, and `ensemble:validate --json` (machine-readable errors + suggestions) so schemas can be updated incrementally or by AI without replacing the whole file.

## What needs building next

1. The `coding-sunshine/ensemble` Laravel package (fork blueprint, add analyzers, generators, lexers)
2. CI/CD pipeline for this CLI repo
3. Publish to Packagist as `coding-sunshine/ensemble-cli`
4. Integration tests with mocked AI providers
