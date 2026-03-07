<?php

namespace CodingSunshine\Ensemble\Console\Concerns;

use Symfony\Component\Console\Output\OutputInterface;

trait TracksProgress
{
    protected int $totalSteps = 0;

    protected int $currentStep = 0;

    protected function initializeProgress(int $total): void
    {
        $this->totalSteps = $total;
        $this->currentStep = 0;
    }

    protected function step(OutputInterface $output, string $message): void
    {
        $this->currentStep++;

        $output->writeln('');
        $output->writeln("  <fg=cyan>[{$this->currentStep}/{$this->totalSteps}]</> <options=bold>{$message}</>");
    }
}
