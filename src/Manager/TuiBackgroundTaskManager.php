<?php

namespace TuiBackground\Manager;

use Symfony\Component\Tui\Tui;
use TuiBackground\TaskId;
use TuiBackground\Tui\RendererInterface;
use TuiBackground\Tui\TuiRenderer;

final class TuiBackgroundTaskManager implements BackgroundTaskManagerInterface
{
    private readonly TuiRenderer $renderer;

    public function __construct(
        private readonly BackgroundTaskManagerInterface $manager,
        private readonly Tui $tui,
    ) {
        $this->renderer = new TuiRenderer($tui);
    }

    public function start(
        string $label,
        array $command,
        array $payload,
        int $timeoutSeconds = 120,
    ): TaskId {
        return $this->manager->start($label, $command, $payload, $timeoutSeconds);
    }

    public function stop(TaskId $id): void
    {
        $this->manager->stop($id);
    }

    public function onTaskProgress(TaskId $id, callable $listener): void
    {
        $this->manager->onTaskProgress($id, $listener);
    }

    public function onTaskCompleted(TaskId $id, callable $listener): void
    {
        $this->manager->onTaskCompleted($id, $listener);
    }

    public function onTaskFailed(TaskId $id, callable $listener): void
    {
        $this->manager->onTaskFailed($id, $listener);
    }

    public function getRenderer(): RendererInterface
    {
        return $this->renderer;
    }
}
