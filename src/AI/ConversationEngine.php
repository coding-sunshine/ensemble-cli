<?php

namespace CodingSunshine\Ensemble\AI;

use CodingSunshine\Ensemble\AI\Providers\ProviderContract;
use CodingSunshine\Ensemble\Recipes\KnownRecipes;
use RuntimeException;
use Symfony\Component\Console\Output\OutputInterface;

use function Laravel\Prompts\info;
use function Laravel\Prompts\multiselect;
use function Laravel\Prompts\note;
use function Laravel\Prompts\select;
use function Laravel\Prompts\spin;
use function Laravel\Prompts\text;
use function Laravel\Prompts\textarea;
use function Laravel\Prompts\warning;

enum SchemaAction: string
{
    case Proceed = 'proceed';
    case Regenerate = 'regenerate';
    case Abort = 'abort';
}

class ConversationEngine
{

    public const SCHEMA_VERSION = 1;

    protected const LIVEWIRE_UI_OPTIONS = [
        'mary' => 'MaryUI (recommended, MIT, 30+ components)',
        'tallstack' => 'Tallstack',
        'wireui' => 'WireUI',
        'flux' => 'Flux (5 free components, Pro is paid)',
        'none' => 'None',
    ];

    public function __construct(
        protected readonly ProviderContract $provider,
        protected readonly ?OutputInterface $output = null,
    ) {}

    /**
     * Run the multi-round AI interview and return the schema array.
     *
     * @param  array<string, mixed>|null  $existingSchema  An existing schema to extend.
     * @return array<string, mixed>|null Returns null if the user aborts.
     */
    public function run(?array $existingSchema = null): ?array
    {
        $description = textarea(
            label: $existingSchema
                ? 'What would you like to add or change in the existing schema?'
                : 'Describe your application',
            placeholder: $existingSchema
                ? 'E.g. Add a reporting dashboard, notification system, and invoice PDF generation...'
                : 'E.g. A project management tool with teams, tasks, time tracking, and client invoicing...',
            required: $existingSchema
                ? 'Please describe what you want to add or change.'
                : 'Please describe the application you want to build.',
        );

        $stack = select(
            label: 'Which frontend stack would you like to use?',
            options: [
                'livewire' => 'Livewire',
                'react' => 'React (Inertia)',
                'vue' => 'Vue (Inertia)',
                'svelte' => 'Svelte (Inertia)',
            ],
            default: 'livewire',
        );

        $uiLibrary = $this->resolveUiLibrary($stack);

        $features = multiselect(
            label: 'Which features does your application need?',
            options: [
                'auth' => 'Authentication (included with starter kit)',
                'roles' => 'Roles & Permissions (spatie/permission)',
                'billing' => 'SaaS Billing (Laravel Cashier)',
                'media' => 'Media Uploads (spatie/medialibrary)',
                'search' => 'Full-Text Search (Laravel Scout)',
                'notifications' => 'Notifications',
                'api' => 'API Authentication (Sanctum)',
                'admin' => 'Admin Panel (Filament)',
                'activity' => 'Activity Log (spatie/activitylog)',
                'tenancy' => 'Multi-Tenancy (stancl/tenancy)',
            ],
            default: ['auth'],
            hint: 'Use space to select, enter to confirm.',
        );

        $additionalContext = text(
            label: 'Anything else the AI should know?',
            placeholder: 'E.g. specific business rules, integrations, user types...',
        );

        $recipes = $this->buildRecipes($features);
        $userPrompt = $this->buildUserPrompt($description, $stack, $uiLibrary, $features, $additionalContext, $existingSchema);

        while (true) {
            $schema = spin(
                message: "Generating application schema with {$this->provider->name()}...",
                callback: fn () => $this->generateSchema($userPrompt),
            );

            $schema['app']['stack'] = $stack;
            $schema['app']['ui'] = $uiLibrary;
            $schema['recipes'] = $recipes;
            $schema['version'] = self::SCHEMA_VERSION;

            $this->displaySummary($schema);

            $action = SchemaAction::from(select(
                label: 'How would you like to proceed?',
                options: [
                    'proceed' => 'Proceed with this schema',
                    'regenerate' => 'Regenerate (ask AI again)',
                    'abort' => 'Abort (no schema)',
                ],
                default: 'proceed',
            ));

            if ($action === SchemaAction::Proceed) {
                return $schema;
            }

            if ($action === SchemaAction::Abort) {
                warning('Schema generation aborted.');

                return null;
            }

            info('Regenerating schema...');
        }
    }

    protected function resolveUiLibrary(string $stack): string
    {
        if ($stack !== 'livewire') {
            return 'shadcn';
        }

        return select(
            label: 'Which UI component library for Livewire?',
            options: self::LIVEWIRE_UI_OPTIONS,
            default: 'mary',
        );
    }

    /**
     * Build recipe objects from the selected features.
     *
     * @param  array<int, string>  $features
     * @return array<int, array{name: string, package: string|null}>
     */
    protected function buildRecipes(array $features): array
    {
        $recipes = [];
        $map = KnownRecipes::toFeatureRecipeMap();

        foreach ($features as $feature) {
            if ($feature === 'auth') {
                continue;
            }

            if (isset($map[$feature])) {
                $recipes[] = $map[$feature];
            }
        }

        return $recipes;
    }

    protected function buildUserPrompt(
        string $description,
        string $stack,
        string $uiLibrary,
        array $features,
        string $additionalContext,
        ?array $existingSchema = null,
    ): string {
        $featureList = implode(', ', $features);

        if ($existingSchema) {
            $existingJson = json_encode($existingSchema, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);

            $prompt = <<<PROMPT
            I have an existing application schema. Please EXTEND it with the requested additions. Keep all existing models, controllers, pages, and recipes — add new ones alongside them. Merge, don't replace.

            **Existing Schema:**
            ```json
            {$existingJson}
            ```

            **Requested Additions:** {$description}
            **Frontend Stack:** {$stack}
            **UI Library:** {$uiLibrary}
            **Additional Features:** {$featureList}
            PROMPT;
        } else {
            $prompt = <<<PROMPT
            Build a Laravel application with the following details:

            **Description:** {$description}
            **Frontend Stack:** {$stack}
            **UI Library:** {$uiLibrary}
            **Features:** {$featureList}
            PROMPT;
        }

        if ($additionalContext) {
            $prompt .= "\n**Additional Context:** {$additionalContext}";
        }

        return $prompt;
    }

    /**
     * Send the prompt to the AI provider and parse the JSON schema response.
     *
     * @return array<string, mixed>
     */
    protected function generateSchema(string $userPrompt): array
    {
        $systemPrompt = $this->buildSystemPrompt();

        $this->verbose('System prompt length: '.strlen($systemPrompt).' chars');
        $this->debug("--- SYSTEM PROMPT ---\n{$systemPrompt}\n--- END SYSTEM PROMPT ---");
        $this->debug("--- USER PROMPT ---\n{$userPrompt}\n--- END USER PROMPT ---");

        $tokenEstimate = $this->provider->estimateTokens($systemPrompt, $userPrompt);

        if ($tokenEstimate > 0) {
            $this->verbose("Estimated input tokens: ~{$tokenEstimate}");
        }

        $rawResponse = $this->provider->complete($systemPrompt, $userPrompt);

        $this->verbose('Response length: '.strlen($rawResponse).' chars');
        $this->debug("--- RAW RESPONSE ---\n{$rawResponse}\n--- END RAW RESPONSE ---");

        $json = $this->extractJson($rawResponse);
        $schema = json_decode($json, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            $this->verbose('Invalid JSON from AI: '.json_last_error_msg().'. Retrying...');
            warning('AI returned invalid JSON, retrying...');
            $rawResponse = $this->provider->complete(
                $systemPrompt,
                $userPrompt."\n\nIMPORTANT: Your previous response was not valid JSON. Return ONLY a valid JSON object, no markdown fences or explanation.",
            );

            $this->debug("--- RETRY RESPONSE ---\n{$rawResponse}\n--- END RETRY RESPONSE ---");

            $json = $this->extractJson($rawResponse);
            $schema = json_decode($json, true);

            if (json_last_error() !== JSON_ERROR_NONE) {
                throw new RuntimeException(
                    'AI failed to generate valid JSON after 2 attempts. Raw response: '.substr($rawResponse, 0, 500)
                );
            }
        }

        if (! isset($schema['app'])) {
            $schema['app'] = [];
        }

        return $schema;
    }

    /**
     * Write a message at VERY_VERBOSE level (-vv).
     */
    protected function verbose(string $message): void
    {
        if ($this->output && $this->output->isVeryVerbose()) {
            $this->output->writeln("  <fg=gray>[ai]</> {$message}");
        }
    }

    /**
     * Write a message at DEBUG level (-vvv).
     */
    protected function debug(string $message): void
    {
        if ($this->output && $this->output->isDebug()) {
            $this->output->writeln("  <fg=gray>[ai:debug]</> {$message}");
        }
    }

    protected function buildSystemPrompt(): string
    {
        $promptPath = dirname(__DIR__, 2).'/stubs/system-prompt.md';

        if (file_exists($promptPath)) {
            return file_get_contents($promptPath);
        }

        return 'You are an expert Laravel application architect. Given a description, return ONLY valid JSON with models, controllers, pages, recipes, notifications, and workflows sections. Follow Laravel Blueprint field syntax conventions.';
    }

    /**
     * Strip markdown code fences and extract raw JSON from AI response.
     */
    protected function extractJson(string $response): string
    {
        $response = trim($response);

        if (preg_match('/```(?:json)?\s*\n?(.*?)\n?```/s', $response, $matches)) {
            return trim($matches[1]);
        }

        return $response;
    }

    protected function displaySummary(array $schema): void
    {
        $counts = [
            'Models' => isset($schema['models']) ? count($schema['models']) : 0,
            'Controllers' => isset($schema['controllers']) ? count($schema['controllers']) : 0,
            'Pages' => isset($schema['pages']) ? count($schema['pages']) : 0,
            'Recipes' => isset($schema['recipes']) ? count($schema['recipes']) : 0,
            'Notifications' => isset($schema['notifications']) ? count($schema['notifications']) : 0,
            'Workflows' => isset($schema['workflows']) ? count($schema['workflows']) : 0,
        ];

        $lines = [];
        $lines[] = "Stack: {$schema['app']['stack']}";
        $lines[] = "UI: {$schema['app']['ui']}";

        foreach ($counts as $label => $count) {
            if ($count > 0) {
                $lines[] = "{$label}: {$count}";
            }
        }

        if ($counts['Models'] > 0) {
            $lines[] = '';
            $lines[] = 'Models: '.implode(', ', array_keys($schema['models']));
        }

        note(implode("\n", $lines));
        info('Schema generated successfully.');
    }
}
