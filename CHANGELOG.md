# Changelog

All notable changes to **ensemble-cli** are documented here.

Format: [Keep a Changelog](https://keepachangelog.com/en/1.0.0/).

---

## [1.5.2] - 2026-03-10

### Added
- New `Concerns\ConfiguresProjectEnv` trait with `configureEnsembleAiProviderInProject(string $directory, OutputInterface $output): ?string`. Detects the first available local AI provider (`claude-cli`, `gemini-cli`, `ollama`, LM Studio) via `ConfigStore::detectLocalProvider()` and writes/updates `ENSEMBLE_AI_PROVIDER=<provider>` in the project's `.env` and `.env.example`. Returns the provider name set (or `null` if none detected).

### Changed
- **`InitCommand`** — now uses `ConfiguresProjectEnv` trait. After installing the Ensemble package it calls `configureEnsembleAiProviderInProject()` and prints a green success line (`✓ AI set to claude-cli in .env for Studio.`) when a local provider is detected. This means `ensemble init` users also get Studio AI without a manual `.env` edit, consistent with `ensemble new`.
- **`NewCommand`** — the `configureEnsembleAiProviderInProject()` method has been removed from `NewCommand` and replaced with the shared trait; behaviour is unchanged.

---

## [1.0.2] - 2026-03-10

### Added
- Self-update check: `ensemble new` now warns when a newer version of `ensemble-cli` is available on Packagist, with a one-line update command. Result is cached locally for 24 hours (no network hit on repeated runs).

### Fixed
- Version string in `bin/ensemble` corrected from `0.1.0` → `1.0.2`.

---

## [1.0.0] - 2026-03-10

### Added

#### MCP Server (`ensemble mcp`)
- Full MCP server over stdio with tools: `get_schema`, `update_schema`, `validate_schema`, `create_project`, `build_project`, `append_model`, `list_recipes`, `snapshot_schema`, `audit_project`, `search_packages`.
- `CreateProjectTool`: accepts `template` (bundled template name), `schema_path` (path to existing ensemble.json), and `stack` (frontend stack) arguments — matching all CLI `ensemble new` options.

#### Watch Command (`ensemble watch`)
- Config-aware defaults: `watch.schema_path`, `watch.interval_ms`, `watch.debounce_ms`, `watch.auto_build`, and `watch.project_path` can be persisted with `ensemble config set watch.*`.
- Shows a tip on schema-not-found pointing to the config command.

#### Doctor Command (`ensemble doctor`)
- **Actionable next-step lines** when no AI provider is detected: exact install commands for `claude-cli` and `gemini-cli`, plus `ensemble config set default_provider`.
- Prints a **Quick start** section after the summary with the most common first commands.
- When a local provider is detected but none is configured: shows one-line `ensemble config set` suggestion.

#### Local AI Providers
- `ClaudeCliProvider`, `GeminiCliProvider`, `LmStudioProvider` — key-free providers usable with `--provider=claude-cli` etc.
- `ConfigStore::detectLocalProvider()` — auto-detects first available local AI tool.

#### Templates
- Added `project-management` template (workspaces, boards, tasks, labels, comments, time entries) — uses Inertia + React + shadcn/ui.
- 12 bundled templates total: `saas`, `blog`, `ecommerce`, `crm`, `api`, `project-management`, `marketplace`, `booking`, `inventory`, `helpdesk`, `lms`, `social`.

#### KnownRecipes (synced with ensemble package)
- Added `dedoc/scramble` (`api-docs`), `spatie/laravel-openapi-cli` (`openapi-cli`), `laravel/pennant` (`feature-flags`), `spatie/laravel-event-sourcing` (`event-sourcing`), `spatie/laravel-data` (`data-transfer`), `spatie/laravel-query-builder` (`query-builder`), `rate-limiting`, and `api-versioning` to the known recipe catalog.
- Both `ensemble` and `ensemble-cli` recipe lists are now in sync.

---

## [1.0.3] - 2026-03-10

### Added

- **LaraPlugins.io integration:** Package health data from [LaraPlugins.io](https://laraplugins.io) is used in several places:
  - `ensemble doctor` — when run in a project with `ensemble.json`, shows dependency health for each recipe package (healthy/medium/unhealthy).
  - `ensemble recipe add <name>` — after adding a recipe, optionally fetches package details and shows a health warning for unhealthy packages.
  - MCP tool `search_packages` — optional `healthy_only` input to filter out unhealthy packages from search results.
  - MCP tool `get_package_details` — new tool to fetch full package details (health score, downloads, stars, etc.) for a given `vendor/package` name.

### Changed

- **Dynamic feature list in `ensemble new`:** The multiselect list of features during the AI interview is now built from the known recipe catalog (`KnownRecipes::toPromptOptions()`), so new recipes appear automatically and labels stay in sync. Previously the list was hardcoded and could drift from the catalog.

---

## [Unreleased]

_(No unreleased changes.)_
