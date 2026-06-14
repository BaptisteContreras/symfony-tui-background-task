<?php

namespace TuiBackground;

use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;
use TuiBackground\Event\BackgroundProcessStartedEvent;
use TuiBackground\Event\BackgroundProcessStoppedEvent;
use TuiBackground\Event\BackgroundTaskCompletedEvent;
use TuiBackground\Event\BackgroundTaskFailedEvent;
use TuiBackground\Event\BackgroundTaskProgressEvent;

final class BackgroundTaskManager
{
    /** @var array<string, array{id: TaskId, label: string, process: BackgroundTask, dispatcher: EventDispatcher}> */
    private array $entries = [];

    /** @var array<string, true> */
    private array $stopped = [];

    public function __construct(
        private readonly EventDispatcherInterface $dispatcher,
    ) {
    }

    /**
     * @param non-empty-list<string> $command
     * @param array<string, mixed>   $payload
     */
    public function start(
        string $label,
        array $command,
        array $payload,
        int $timeoutSeconds = 120,
    ): TaskId {
        $id = TaskId::generate();
        $internalDispatcher = new EventDispatcher();
        $process = new BackgroundTask($command, $internalDispatcher, $timeoutSeconds);

        $this->entries[$id->value] = [
            'id' => $id,
            'label' => $label,
            'process' => $process,
            'dispatcher' => $internalDispatcher,
        ];

        foreach ([BackgroundTaskCompletedEvent::class, BackgroundTaskFailedEvent::class] as $eventClass) {
            $internalDispatcher->addListener($eventClass, function () use ($id, $label): void {
                if (isset($this->stopped[$id->value])) {
                    return;
                }
                $this->stopped[$id->value] = true;
                $this->dispatcher->dispatch(new BackgroundProcessStoppedEvent($id, $label));
            });
        }

        $this->dispatcher->dispatch(new BackgroundProcessStartedEvent($id, $label));
        $process->start($payload);

        return $id;
    }

    public function stop(TaskId $id): void
    {
        $entry = $this->entries[$id->value] ?? null;
        if (null === $entry || isset($this->stopped[$id->value])) {
            return;
        }

        $this->stopped[$id->value] = true;
        $entry['process']->kill();
        $this->dispatcher->dispatch(new BackgroundProcessStoppedEvent($id, $entry['label']));
    }

    public function onProcessProgress(TaskId $id, callable $listener): void
    {
        $this->addListener($id, BackgroundTaskProgressEvent::class, $listener);
    }

    public function onProcessCompleted(TaskId $id, callable $listener): void
    {
        $this->addListener($id, BackgroundTaskCompletedEvent::class, $listener);
    }

    public function onProcessFailed(TaskId $id, callable $listener): void
    {
        $this->addListener($id, BackgroundTaskFailedEvent::class, $listener);
    }

    private function addListener(TaskId $id, string $eventClass, callable $listener): void
    {
        $entry = $this->entries[$id->value] ?? null;
        if (null === $entry) {
            return;
        }

        $entry['dispatcher']->addListener($eventClass, $listener);
    }
}
