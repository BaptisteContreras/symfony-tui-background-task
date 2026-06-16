<?php

namespace TuiBackground\Worker;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

abstract class AbstractWorkerCommand extends Command
{
    private ?string $failureMessage = null;
    private ?WorkerSocket $socket = null;

    public function __construct(private readonly WorkerSocketFactoryInterface $socketFactory)
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->setHidden(true);
    }

    final protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $socket = $this->socketFactory->create();
        $this->socket = $socket;

        $payload = $socket->readPayload();

        try {
            $this->handle($payload);
        } catch (\Throwable $e) {
            $this->failureMessage ??= $e->getMessage();
        }

        $socket->terminate($this->failureMessage);
        $this->socket = null;

        return Command::SUCCESS;
    }

    /**
     * @param array<string, mixed> $payload
     */
    abstract protected function handle(array $payload): void;

    /**
     * @param array<string, mixed> $event
     */
    protected function emit(array $event): void
    {
        $this->socket?->emit($event);
    }

    protected function fail(string $message): void
    {
        $this->failureMessage = $message;
    }
}
