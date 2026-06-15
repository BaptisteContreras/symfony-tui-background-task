<?php

namespace TuiBackground\Manager;

use TuiBackground\TaskId;

interface BackgroundTaskManagerInterface
{
    /**
     * @param non-empty-list<string> $command
     * @param array<string, mixed>   $payload
     */
    public function start(
        string $label,
        array $command,
        array $payload,
        int $timeoutSeconds = 120,
    ): TaskId;

    public function stop(TaskId $id): void;

    public function onTaskProgress(TaskId $id, callable $listener): void;

    public function onTaskCompleted(TaskId $id, callable $listener): void;

    public function onTaskFailed(TaskId $id, callable $listener): void;
}
