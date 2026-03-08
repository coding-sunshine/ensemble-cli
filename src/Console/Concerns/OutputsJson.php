<?php

namespace CodingSunshine\Ensemble\Console\Concerns;

use Symfony\Component\Console\Output\OutputInterface;

trait OutputsJson
{
    /**
     * Output a structured JSON payload.
     *
     * @param  array<string, mixed>  $data
     */
    protected function outputJson(OutputInterface $output, array $data): void
    {
        $output->writeln(json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
    }

    /**
     * Output a single structured progress event.
     *
     * Format: {"event":"step","step":2,"total":7,"message":"..."}
     *
     * @param  array<string, mixed>  $data
     */
    protected function outputJsonEvent(OutputInterface $output, string $event, array $data): void
    {
        $payload = array_merge(['event' => $event], $data);
        $output->writeln(json_encode($payload, JSON_UNESCAPED_SLASHES));
    }
}
