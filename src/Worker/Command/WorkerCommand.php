<?php

namespace TuiBackground\Worker\Command;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use TuiBackground\Event\EventType;
use TuiBackground\Exception\LogicException;
use TuiBackground\Worker\Factory\WorkerTaskFactoryInterface;
use TuiBackground\Worker\WorkerTask;

abstract class WorkerCommand extends Command
{
    private bool $terminated = false;
    private ?WorkerTask $task = null;

    public function __construct(private readonly WorkerTaskFactoryInterface $taskFactory)
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->setHidden();
    }

    final protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $task = $this->taskFactory->create();
        $this->task = $task;

        $payload = $task->readPayload();

        try {
            $this->handle($payload);
        } catch (\Throwable $e) {
            $this->fail($e->getMessage());
        }

        if (!$this->terminated) {
            $this->done();
        }

        $this->task = null;

        return Command::SUCCESS;
    }

    /**
     * @param array<string, mixed> $payload
     */
    abstract protected function handle(array $payload): void;

    /**
     * @param array<string, mixed> $data
     */
    protected function progress(string $subType, ?string $message = null, array $data = []): void
    {
        if ($this->terminated) {
            throw new LogicException('Cannot emit progress after the task has been terminated.');
        }

        $event = ['type' => EventType::Progress->value, 'sub_type' => $subType];

        if (null !== $message) {
            $event['message'] = $message;
        }

        if ([] !== $data) {
            $event['data'] = $data;
        }

        $this->task?->emit($event);
    }

    /**
     * @param array<string, mixed> $data
     */
    protected function done(?string $message = null, array $data = []): bool
    {
        if ($this->terminated) {
            return false;
        }

        $this->task?->complete($message, $data);
        $this->terminated = true;

        return true;
    }

    /**
     * @param array<string, mixed> $data
     */
    protected function fail(?string $message = null, array $data = []): bool
    {
        if ($this->terminated) {
            return false;
        }

        $this->task?->fail($message, $data);
        $this->terminated = true;

        return true;
    }
}
