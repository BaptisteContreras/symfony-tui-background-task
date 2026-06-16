<?php

use Symfony\Component\Console\Attribute\AsCommand;
use TuiBackground\Worker\AbstractWorkerCommand;

#[AsCommand('worker')]
final class WorkerCommand extends AbstractWorkerCommand
{
    protected function handle(array $payload): void
    {
        usleep(400000);
        $this->progress('initialized');

        $steps = 5;
        for ($i = 1; $i <= $steps; ++$i) {
            usleep(500000);
            $this->progress('processing', data: ['step' => $i, 'total' => $steps]);
        }

        usleep(400000);
        $this->progress('finalized');
    }
}
