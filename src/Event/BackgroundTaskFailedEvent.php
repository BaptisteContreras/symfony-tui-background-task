<?php

namespace TuiBackground\Event;

final class BackgroundTaskFailedEvent
{
    public function __construct(public readonly string $message)
    {
    }
}
