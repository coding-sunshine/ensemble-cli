<?php

namespace CodingSunshine\Ensemble\Console\Concerns;

use Symfony\Component\Console\Output\OutputInterface;

trait ConfiguresProjectEnv
{
    /**
     * If a local AI provider (claude-cli, gemini-cli, lmstudio) is detected, set ENSEMBLE_AI_PROVIDER
     * in the project's .env and .env.example so Studio chat and field suggestions work without manual setup.
     *
     * @return string|null The provider name that was set, or null if none was configured
     */
    protected function configureEnsembleAiProviderInProject(string $directory, OutputInterface $output): ?string
    {
        $config = new \CodingSunshine\Ensemble\Config\ConfigStore();
        $provider = $config->detectLocalProvider();
        if ($provider === null) {
            return null;
        }

        $envPath = $directory.'/.env';
        $examplePath = $directory.'/.env.example';
        if (! is_file($envPath)) {
            return null;
        }

        $line = 'ENSEMBLE_AI_PROVIDER='.$provider;
        $block = "\n# Ensemble Studio AI (local)\n".$line."\n";

        foreach ([$envPath, $examplePath] as $path) {
            if (! is_file($path)) {
                continue;
            }
            $contents = file_get_contents($path);
            if (preg_match('/^\s*ENSEMBLE_AI_PROVIDER\s*=.*/m', $contents)) {
                $contents = preg_replace('/^\s*ENSEMBLE_AI_PROVIDER\s*=.*/m', $line, $contents);
            } else {
                $contents = rtrim($contents).$block;
            }
            file_put_contents($path, $contents);
        }

        return $provider;
    }
}
