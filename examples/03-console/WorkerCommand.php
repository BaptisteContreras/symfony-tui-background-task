<?php

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand('worker')]
final class WorkerCommand extends Command
{
    protected function configure(): void
    {
        $this->setHidden(true);
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        stream_get_contents(STDIN); // consume JSON payload

        usleep(400000);
        fwrite(STDOUT, json_encode(['type' => 'initialized'])."\n");
        fflush(STDOUT);

        $steps = 5;
        for ($i = 1; $i <= $steps; ++$i) {
            usleep(500000);
            fwrite(STDOUT, json_encode(['type' => 'processing', 'step' => $i, 'total' => $steps])."\n");
            fflush(STDOUT);
        }

        usleep(400000);
        fwrite(STDOUT, json_encode(['type' => 'finalized'])."\n");
        fflush(STDOUT);

        fwrite(STDOUT, json_encode(['type' => 'done'])."\n");
        fflush(STDOUT);

        return Command::SUCCESS;
    }
}
