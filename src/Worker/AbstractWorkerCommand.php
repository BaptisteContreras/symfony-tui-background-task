<?php

namespace TuiBackground\Worker;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

abstract class AbstractWorkerCommand extends Command
{
    private ?string $failureMessage = null;

    protected function configure(): void
    {
        $this->setHidden(true);
    }

    final protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $payload = $this->readPayload();

        try {
            $this->handle($payload);
        } catch (\Throwable $e) {
            $this->failureMessage ??= $e->getMessage();
        }

        if (null !== $this->failureMessage) {
            $this->writeEvent(['type' => 'error', 'message' => $this->failureMessage]);
        }

        $this->writeEvent(['type' => 'done']);

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
        $this->writeEvent($event);
    }

    protected function fail(string $message): void
    {
        $this->failureMessage = $message;
    }

    /**
     * @return array<string, mixed>
     */
    private function readPayload(): array
    {
        $raw = (string) stream_get_contents(STDIN);

        if ('' === $raw) {
            return [];
        }

        try {
            $decoded = json_decode($raw, true, flags: \JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            return [];
        }

        return is_array($decoded) ? $decoded : [];
    }

    /**
     * @param array<string, mixed> $event
     */
    private function writeEvent(array $event): void
    {
        fwrite(STDOUT, json_encode($event)."\n");
        fflush(STDOUT);
    }
}
