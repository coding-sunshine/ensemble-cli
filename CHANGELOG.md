# Changelog

All notable changes to **ensemble-cli** are documented here.

Format: [Keep a Changelog](https://keepachangelog.com/en/1.0.0/).

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

## [Unreleased]

_(No unreleased changes.)_
