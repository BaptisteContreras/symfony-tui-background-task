<?php

namespace TuiBackground\Event;

use TuiBackground\TaskId;

final class BackgroundTaskStartedEvent
{
    public function __construct(
        public readonly TaskId $id,
        public readonly string $label,
    ) {
    }
}
