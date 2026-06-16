<?php

namespace TuiBackground\Exception;

final class BackgroundTaskAlreadyStartedException extends LogicException
{
    public function __construct()
    {
        parent::__construct('BackgroundTask already started. Create a new instance to run another task.');
    }
}
