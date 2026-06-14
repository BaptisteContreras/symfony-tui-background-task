<?php

namespace TuiBackground\Event;

use TuiBackground\TaskId;

final class BackgroundProcessStoppedEvent
{
    public function __construct(
        public readonly TaskId $id,
        public readonly string $label,
    ) {
    }
}
