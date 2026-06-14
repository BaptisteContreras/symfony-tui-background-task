<?php

namespace TuiBackground\Event;

use TuiBackground\TaskId;

final class BackgroundProcessStartedEvent
{
    public function __construct(
        public readonly TaskId $id,
        public readonly string $label,
    ) {
    }
}
