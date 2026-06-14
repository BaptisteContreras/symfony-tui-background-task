<?php

namespace TuiBackground\Event;

final class BackgroundTaskProgressEvent
{
    /**
     * @param array<string, mixed> $data
     */
    public function __construct(public readonly array $data)
    {
    }
}
